# FE UI Slice 1a — Shell & Identity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the mocked multi-role shell — an active-role store, role-scoped nav, a real context selector, and a data-driven Action Center home for all 10 roles — and re-skin the 7 remaining auth/onboarding pages onto shadcn.

**Architecture:** A module-level `active-role` store exposes a `useActiveRole()` hook via `useSyncExternalStore` so a role switch re-renders live (a step up from Slice 0's render-time active-org read). `ROLE_NAV` maps each role to task-oriented sidebar items; built destinations link to real routes, unbuilt ones to a single `/preview/:section` → `ComingSoonPage`. The Action Center is one `ActionCard` + one `ActionCenterPage` rendering 8 prioritized sections from a role-scoped MSW fixture. All data is MSW; no backend changes.

**Tech Stack:** React 19, Vite 8, TypeScript 6, Tailwind 4 + shadcn/ui, react-query, react-router 7, MSW, Vitest + Testing Library, Playwright, Zod.

## Global Constraints

- **Mock data only via MSW.** Screens call real typed `api/` clients; MSW returns fixtures. No backend/auth/tenancy changes.
- **Invented contracts** (`roles`, `action-center`) are typed in `src/schemas/`; fixtures typed against them so drift fails typecheck.
- **10 role keys (verbatim):** `founder`, `co_founder`, `mentor`, `trainer`, `evaluator`, `judge`, `service_provider`, `program_manager`, `program_coordinator`, `org_admin`. Default active role = `program_manager`.
- **8 Action Center sections, in priority order (verbatim):** `required_actions`, `deadlines`, `current_stage`, `upcoming_sessions`, `blocked_items`, `recent_decisions`, `progress`, `opportunities`.
- **Action card fields (per `dashboard.md`):** what / why / deadline / responsible person / action link / blocking dependency.
- **Preserve import paths + props** of re-skinned auth pages; behavior unchanged, only markup → shadcn cards (match the Slice-0 `LoginPage`). No `ds-*` in any file this plan touches.
- **Role store is module-level mutable** — reset with `setActiveRole('program_manager')` in `afterEach` of touched suites.
- Run all npm commands from `frontend/`. Commit trailer: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Work on a `feat/fe-ui-slice1a-*` branch — never commit to `main`; check `git branch --show-current` before every commit.

## File structure

| Path | Responsibility | Task |
|------|----------------|------|
| `src/schemas/roles.ts` | `Role`, `RoleKey`, role-list response schema | 1 |
| `src/api/roles.ts` | `listMyRoles()` | 1 |
| `src/app/active-role.ts` | active-role holder + `useActiveRole()` | 1 |
| `src/mocks/handlers.ts` | add `/me/roles`, `/me/action-center` | 1,5 |
| `src/app/role-nav.ts` | `ROLE_NAV` map (10 roles) | 2 |
| `src/components/ContextSelector.tsx` | role/program/cohort switcher (rewrite) | 3 |
| `src/components/RoleSidebar.tsx` | role-scoped nav list | 3 |
| `src/pages/ComingSoonPage.tsx` | placeholder for unbuilt destinations | 4 |
| `src/app/App.tsx` | `/preview/:section` route; HomeRoute → ActionCenter | 4,6 |
| `src/schemas/actionCenter.ts` | `ActionItem`, section enum, response | 5 |
| `src/api/actionCenter.ts` | `getActionCenter(role)` | 5 |
| `src/components/ActionCard.tsx` | one action card | 5 |
| `src/pages/ActionCenterPage.tsx` | role-scoped home (replaces HomePage home) | 6 |
| `src/pages/{Register,ForgotPassword,ResetPassword,EmailVerified,VerifyEmailNotice,AuthCallback,Onboarding}*` | re-skin | 7 |

---

### Task 1: Role contract + active-role store

**Files:**
- Create: `src/schemas/roles.ts`, `src/api/roles.ts`, `src/app/active-role.ts`, `src/app/active-role.test.ts`
- Modify: `src/mocks/handlers.ts`

**Interfaces:**
- Produces: `RoleKey` (union of the 10 keys), `Role = { key: RoleKey; label: string }`, `roleListResponseSchema`; `listMyRoles(): Promise<Role[]>`; `getActiveRole(): RoleKey`, `setActiveRole(k: RoleKey): void`, `subscribe(cb): () => void`, `useActiveRole(): RoleKey`.

- [ ] **Step 1: Write `src/schemas/roles.ts`**

```ts
import { z } from 'zod'

export const ROLE_KEYS = [
  'founder', 'co_founder', 'mentor', 'trainer', 'evaluator', 'judge',
  'service_provider', 'program_manager', 'program_coordinator', 'org_admin',
] as const

export const roleKeySchema = z.enum(ROLE_KEYS)
export type RoleKey = z.infer<typeof roleKeySchema>

export const roleSchema = z.object({ key: roleKeySchema, label: z.string() })
export type Role = z.infer<typeof roleSchema>

export const roleListResponseSchema = z.object({ data: z.array(roleSchema) })
```

- [ ] **Step 2: Write `src/api/roles.ts`**

```ts
import { apiFetch } from './tenant'
import { roleListResponseSchema, type Role } from '../schemas/roles'

/** GET /me/roles — the current user's role memberships (prototype contract). */
export async function listMyRoles(): Promise<Role[]> {
  const response = await apiFetch('/me/roles')
  if (!response.ok) throw new Error(`roles list failed: ${response.status}`)
  const json: unknown = await response.json()
  return roleListResponseSchema.parse(json).data
}
```

- [ ] **Step 3: Write the failing test** `src/app/active-role.test.ts`

```ts
import { afterEach, expect, test } from 'vitest'
import { getActiveRole, setActiveRole, subscribe } from './active-role'

afterEach(() => setActiveRole('program_manager'))

test('defaults to program_manager', () => {
  expect(getActiveRole()).toBe('program_manager')
})

test('setActiveRole updates and notifies subscribers', () => {
  let notified = 0
  const unsub = subscribe(() => { notified += 1 })
  setActiveRole('founder')
  expect(getActiveRole()).toBe('founder')
  expect(notified).toBe(1)
  unsub()
  setActiveRole('mentor')
  expect(notified).toBe(1) // no longer notified after unsubscribe
})
```

- [ ] **Step 4: Run it — expect FAIL** (`Cannot find module './active-role'`)

Run: `cd frontend && npm run test -- src/app/active-role.test.ts`

- [ ] **Step 5: Write `src/app/active-role.ts`**

```ts
import { useSyncExternalStore } from 'react'
import type { RoleKey } from '../schemas/roles'

let activeRole: RoleKey = 'program_manager'
const listeners = new Set<() => void>()

export function getActiveRole(): RoleKey {
  return activeRole
}

export function setActiveRole(key: RoleKey): void {
  if (key === activeRole) return
  activeRole = key
  listeners.forEach((l) => l())
}

export function subscribe(cb: () => void): () => void {
  listeners.add(cb)
  return () => listeners.delete(cb)
}

/** Re-renders the caller whenever the active role changes. */
export function useActiveRole(): RoleKey {
  return useSyncExternalStore(subscribe, getActiveRole, getActiveRole)
}
```

- [ ] **Step 6: Run the test — expect PASS**

Run: `cd frontend && npm run test -- src/app/active-role.test.ts`

- [ ] **Step 7: Add MSW handler** — in `src/mocks/handlers.ts`, import `Role` and add to the `handlers` array a `/me/roles` handler giving the demo user several roles:

```ts
// add to imports:
import type { Role } from '@/schemas/roles'
// add near the other fixtures:
const roles: Role[] = [
  { key: 'program_manager', label: 'Program Manager' },
  { key: 'founder', label: 'Founder' },
  { key: 'mentor', label: 'Mentor' },
  { key: 'evaluator', label: 'Evaluator' },
]
// add to the handlers array:
http.get('*/api/v1/me/roles', () => HttpResponse.json({ data: roles })),
```

- [ ] **Step 8: Typecheck + commit**

Run: `cd frontend && npm run typecheck && npm run test -- src/app/active-role.test.ts`
```bash
git add src/schemas/roles.ts src/api/roles.ts src/app/active-role.ts src/app/active-role.test.ts src/mocks/handlers.ts
git commit -m "feat(fe): FE-UI-1a — role contract + active-role store + /me/roles mock

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Role-scoped nav map

**Files:** Create `src/app/role-nav.ts`, `src/app/role-nav.test.ts`

**Interfaces:**
- Consumes: `RoleKey` from `@/schemas/roles`.
- Produces: `NavItem = { label: string; href: string }`; `ROLE_NAV: Record<RoleKey, NavItem[]>`. Built destinations use real paths (`/` Home, `/programs`); everything else uses `/preview/<slug>`.

- [ ] **Step 1: Write `src/app/role-nav.ts`** (all 10 roles; task-oriented labels from `strategy.md`/`navigation.md`)

```ts
import type { RoleKey } from '../schemas/roles'

export interface NavItem {
  label: string
  href: string
}

// Built routes today: Home ('/') and Programs ('/programs'). Everything else
// routes to the ComingSoonPage via /preview/<slug> until its slice lands.
export const ROLE_NAV: Record<RoleKey, NavItem[]> = {
  program_manager: [
    { label: 'Overview', href: '/' },
    { label: 'Programs', href: '/programs' },
    { label: 'Applicants', href: '/preview/applicants' },
    { label: 'Selection', href: '/preview/selection' },
    { label: 'Participants', href: '/preview/participants' },
    { label: 'Program Delivery', href: '/preview/delivery' },
    { label: 'Mentors & Trainers', href: '/preview/mentors-trainers' },
    { label: 'Final Evaluation', href: '/preview/final-evaluation' },
    { label: 'Reports', href: '/preview/reports' },
    { label: 'Configuration', href: '/preview/configuration' },
  ],
  founder: [
    { label: 'Overview', href: '/' },
    { label: 'My Application', href: '/preview/my-application' },
    { label: 'Required Actions', href: '/preview/required-actions' },
    { label: 'Program Journey', href: '/preview/program-journey' },
    { label: 'Sessions', href: '/preview/sessions' },
    { label: 'Training', href: '/preview/training' },
    { label: 'Documents', href: '/preview/documents' },
    { label: 'Messages', href: '/preview/messages' },
    { label: 'My Startup', href: '/preview/my-startup' },
  ],
  co_founder: [
    { label: 'Overview', href: '/' },
    { label: 'My Application', href: '/preview/my-application' },
    { label: 'Program Journey', href: '/preview/program-journey' },
    { label: 'Sessions', href: '/preview/sessions' },
    { label: 'Documents', href: '/preview/documents' },
    { label: 'My Startup', href: '/preview/my-startup' },
  ],
  mentor: [
    { label: 'Overview', href: '/' },
    { label: 'My Mentees', href: '/preview/mentees' },
    { label: 'Sessions', href: '/preview/sessions' },
    { label: 'Availability', href: '/preview/availability' },
    { label: 'Messages', href: '/preview/messages' },
  ],
  trainer: [
    { label: 'Overview', href: '/' },
    { label: 'My Sessions', href: '/preview/training-sessions' },
    { label: 'Attendance', href: '/preview/attendance' },
    { label: 'Materials', href: '/preview/materials' },
  ],
  evaluator: [
    { label: 'Overview', href: '/' },
    { label: 'My Queue', href: '/preview/evaluation-queue' },
    { label: 'Conflicts', href: '/preview/conflicts' },
  ],
  judge: [
    { label: 'Overview', href: '/' },
    { label: 'Panel', href: '/preview/panel' },
    { label: 'Scoring', href: '/preview/final-scoring' },
  ],
  service_provider: [
    { label: 'Overview', href: '/' },
    { label: 'My Offerings', href: '/preview/offerings' },
    { label: 'Requests', href: '/preview/service-requests' },
  ],
  program_coordinator: [
    { label: 'Overview', href: '/' },
    { label: 'Logistics', href: '/preview/logistics' },
    { label: 'Tasks', href: '/preview/coordinator-tasks' },
  ],
  org_admin: [
    { label: 'Overview', href: '/' },
    { label: 'Members', href: '/preview/members' },
    { label: 'Roles', href: '/preview/roles' },
    { label: 'Settings', href: '/preview/org-settings' },
  ],
}
```

- [ ] **Step 2: Write `src/app/role-nav.test.ts`**

```ts
import { expect, test } from 'vitest'
import { ROLE_NAV } from './role-nav'
import { ROLE_KEYS } from '../schemas/roles'

test('every role has at least an Overview item and Programs only for program_manager', () => {
  for (const key of ROLE_KEYS) {
    expect(ROLE_NAV[key].length).toBeGreaterThan(0)
    expect(ROLE_NAV[key][0]).toEqual({ label: 'Overview', href: '/' })
  }
  expect(ROLE_NAV.program_manager.some((i) => i.href === '/programs')).toBe(true)
  expect(ROLE_NAV.founder.some((i) => i.href === '/programs')).toBe(false)
})
```

- [ ] **Step 3: Run + commit**

Run: `cd frontend && npm run test -- src/app/role-nav.test.ts && npm run typecheck`
```bash
git add src/app/role-nav.ts src/app/role-nav.test.ts
git commit -m "feat(fe): FE-UI-1a — role-scoped nav map (10 roles)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: ContextSelector rewrite + RoleSidebar

**Files:**
- Modify: `src/components/ContextSelector.tsx`
- Create: `src/components/RoleSidebar.tsx`, `src/components/ContextSelector.test.tsx`, `src/components/RoleSidebar.test.tsx`

**Interfaces:**
- Consumes: `useActiveRole`/`setActiveRole` from `@/app/active-role`, `listMyRoles` from `@/api/roles`, `ROLE_NAV` from `@/app/role-nav`, shadcn `DropdownMenu*`, `Link`.
- Produces: `<ContextSelector />` (role switcher; program/cohort dropdowns), `<RoleSidebar />` (renders `ROLE_NAV[activeRole]`).

- [ ] **Step 1: Rewrite `src/components/ContextSelector.tsx`**

```tsx
import { useQuery } from '@tanstack/react-query'
import { ChevronDown } from 'lucide-react'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from './ui/dropdown-menu'
import { Button } from './ui/button'
import { useActiveRole, setActiveRole } from '../app/active-role'
import { listMyRoles } from '../api/roles'
import type { RoleKey } from '../schemas/roles'

/** Role / org context. Role switches the active-role store (re-renders shell). */
export function ContextSelector() {
  const activeRole = useActiveRole()
  const rolesQuery = useQuery({ queryKey: ['me-roles'], queryFn: listMyRoles, retry: false })
  const roles = rolesQuery.data ?? []
  const activeLabel = roles.find((r) => r.key === activeRole)?.label ?? 'Program Manager'

  return (
    <div className="flex items-center gap-2 text-sm" aria-label="Active context">
      <span className="text-muted-foreground">Acme Incubator</span>
      {roles.length > 1 ? (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" size="sm" className="gap-1">
              {activeLabel} <ChevronDown className="size-3" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {roles.map((r) => (
              <DropdownMenuItem key={r.key} onClick={() => setActiveRole(r.key as RoleKey)}>
                {r.label}
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      ) : (
        <span className="font-medium text-foreground">{activeLabel}</span>
      )}
    </div>
  )
}
```

- [ ] **Step 2: Write `src/components/RoleSidebar.tsx`**

```tsx
import { Link } from './Link'
import { useActiveRole } from '../app/active-role'
import { ROLE_NAV } from '../app/role-nav'

/** Role-scoped sidebar nav. Re-renders when the active role changes. */
export function RoleSidebar() {
  const role = useActiveRole()
  return (
    <nav aria-label="Sections" className="grid gap-1 text-sm">
      {ROLE_NAV[role].map((item) => (
        <Link key={item.href + item.label} href={item.href} className="px-2 py-1">
          {item.label}
        </Link>
      ))}
    </nav>
  )
}
```

- [ ] **Step 3: Write `src/components/RoleSidebar.test.tsx`** (role switch changes nav)

```tsx
import { afterEach, expect, test } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { RoleSidebar } from './RoleSidebar'
import { setActiveRole } from '../app/active-role'

afterEach(() => setActiveRole('program_manager'))

test('renders the active role nav and updates on switch', () => {
  const { rerender } = render(<MemoryRouter><RoleSidebar /></MemoryRouter>)
  expect(screen.getByRole('link', { name: 'Programs' })).toBeInTheDocument() // program_manager
  setActiveRole('founder')
  rerender(<MemoryRouter><RoleSidebar /></MemoryRouter>)
  expect(screen.queryByRole('link', { name: 'Programs' })).not.toBeInTheDocument()
  expect(screen.getByRole('link', { name: 'My Startup' })).toBeInTheDocument()
})
```

- [ ] **Step 4: Write `src/components/ContextSelector.test.tsx`** — render with a react-query provider + `MemoryRouter`, seed roles via a `fetch` mock returning `{data:[...]}`, assert the active label renders and a menu item switches role. (Mirror the existing page-test fetch-mock pattern in `src/pages/*.test.tsx`.)

- [ ] **Step 5: Run + commit**

Run: `cd frontend && npm run typecheck && npm run test -- src/components/RoleSidebar.test.tsx src/components/ContextSelector.test.tsx`
```bash
git add src/components/ContextSelector.tsx src/components/RoleSidebar.tsx src/components/RoleSidebar.test.tsx src/components/ContextSelector.test.tsx
git commit -m "feat(fe): FE-UI-1a — context selector role switch + role-scoped sidebar

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: ComingSoonPage + /preview route

**Files:**
- Create: `src/pages/ComingSoonPage.tsx`, `src/pages/ComingSoonPage.test.tsx`
- Modify: `src/app/App.tsx`

**Interfaces:**
- Consumes: `AppShell`, `RoleSidebar`, `useParams`.
- Produces: route `/preview/:section` → `ComingSoonPage` inside `ConsoleGate`.

- [ ] **Step 1: Write `src/pages/ComingSoonPage.tsx`**

```tsx
import { useParams } from 'react-router-dom'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'

/** Placeholder for nav destinations whose screens arrive in a later slice. */
export function ComingSoonPage() {
  const { section } = useParams()
  const title = (section ?? 'This screen').replace(/-/g, ' ')
  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="coming-soon-heading" className="grid gap-2">
        <h1 id="coming-soon-heading" className="text-2xl font-semibold capitalize">{title}</h1>
        <p className="text-muted-foreground">
          This screen arrives in a later slice of the UI rebuild. It is a navigable
          placeholder in the current prototype.
        </p>
      </section>
    </AppShell>
  )
}
```

- [ ] **Step 2: Write `src/pages/ComingSoonPage.test.tsx`**

```tsx
import { expect, test } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { ComingSoonPage } from './ComingSoonPage'

test('shows the section name and a placeholder message', () => {
  render(
    <MemoryRouter initialEntries={['/preview/applicants']}>
      <Routes><Route path="/preview/:section" element={<ComingSoonPage />} /></Routes>
    </MemoryRouter>,
  )
  expect(screen.getByRole('heading', { name: /applicants/i, level: 1 })).toBeInTheDocument()
  expect(screen.getByText(/arrives in a later slice/i)).toBeInTheDocument()
})
```

- [ ] **Step 3: Add the route in `src/app/App.tsx`** — add a route element and register it inside the console group:

```tsx
function PreviewRoute() {
  return <ConsoleGate>{() => <ComingSoonPage />}</ConsoleGate>
}
```
and in `AppRoutes`, alongside the other console routes:
```tsx
      <Route path="/preview/:section" element={<PreviewRoute />} />
```
Add the import: `import { ComingSoonPage } from '../pages/ComingSoonPage'`.

- [ ] **Step 4: Run + commit**

Run: `cd frontend && npm run typecheck && npm run test -- src/pages/ComingSoonPage.test.tsx`
```bash
git add src/pages/ComingSoonPage.tsx src/pages/ComingSoonPage.test.tsx src/app/App.tsx
git commit -m "feat(fe): FE-UI-1a — ComingSoonPage + /preview/:section route

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: ActionCard + action-center contract + 10 role fixtures

**Files:**
- Create: `src/schemas/actionCenter.ts`, `src/api/actionCenter.ts`, `src/components/ActionCard.tsx`, `src/components/ActionCard.test.tsx`
- Modify: `src/mocks/handlers.ts`

**Interfaces:**
- Produces: `ACTION_SECTIONS` (the 8 keys, in order), `ActionItem = { id, section, what, why, deadline, who, href, blocker }` (`deadline`/`href`/`blocker` nullable), `actionCenterResponseSchema`; `getActionCenter(role: RoleKey): Promise<ActionItem[]>`; `<ActionCard item={ActionItem} />`.

- [ ] **Step 1: Write `src/schemas/actionCenter.ts`**

```ts
import { z } from 'zod'

export const ACTION_SECTIONS = [
  'required_actions', 'deadlines', 'current_stage', 'upcoming_sessions',
  'blocked_items', 'recent_decisions', 'progress', 'opportunities',
] as const

export const sectionSchema = z.enum(ACTION_SECTIONS)
export type ActionSection = z.infer<typeof sectionSchema>

export const SECTION_LABEL: Record<ActionSection, string> = {
  required_actions: 'Required actions',
  deadlines: 'Deadlines',
  current_stage: 'Current stage',
  upcoming_sessions: 'Upcoming sessions',
  blocked_items: 'Blocked items',
  recent_decisions: 'Recent decisions',
  progress: 'Progress',
  opportunities: 'Opportunities',
}

export const actionItemSchema = z.object({
  id: z.string(),
  section: sectionSchema,
  what: z.string(),
  why: z.string(),
  deadline: z.string().nullable(),
  who: z.string().nullable(),
  href: z.string().nullable(),
  blocker: z.string().nullable(),
})
export type ActionItem = z.infer<typeof actionItemSchema>

export const actionCenterResponseSchema = z.object({ data: z.array(actionItemSchema) })
```

- [ ] **Step 2: Write `src/api/actionCenter.ts`**

```ts
import { apiFetch } from './tenant'
import { actionCenterResponseSchema, type ActionItem } from '../schemas/actionCenter'
import type { RoleKey } from '../schemas/roles'

/** GET /me/action-center?role= — role-scoped action items (prototype contract). */
export async function getActionCenter(role: RoleKey): Promise<ActionItem[]> {
  const response = await apiFetch(`/me/action-center?role=${role}`)
  if (!response.ok) throw new Error(`action-center failed: ${response.status}`)
  const json: unknown = await response.json()
  return actionCenterResponseSchema.parse(json).data
}
```

- [ ] **Step 3: Write `src/components/ActionCard.tsx`**

```tsx
import { Card, CardContent } from './ui/card'
import { Link } from './Link'
import type { ActionItem } from '../schemas/actionCenter'

/** One Action Center card: what / why / deadline / who / link / blocker. */
export function ActionCard({ item }: { item: ActionItem }) {
  return (
    <Card>
      <CardContent className="grid gap-1 py-4">
        <p className="font-medium"><bdi>{item.what}</bdi></p>
        <p className="text-sm text-muted-foreground"><bdi>{item.why}</bdi></p>
        <dl className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
          {item.deadline ? <div><dt className="inline font-medium">Due: </dt><dd className="inline">{item.deadline}</dd></div> : null}
          {item.who ? <div><dt className="inline font-medium">Owner: </dt><dd className="inline"><bdi>{item.who}</bdi></dd></div> : null}
          {item.blocker ? <div><dt className="inline font-medium">Blocked by: </dt><dd className="inline"><bdi>{item.blocker}</bdi></dd></div> : null}
        </dl>
        {item.href ? <Link href={item.href} className="text-sm">Open</Link> : null}
      </CardContent>
    </Card>
  )
}
```

- [ ] **Step 4: Write `src/components/ActionCard.test.tsx`**

```tsx
import { expect, test } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ActionCard } from './ActionCard'
import type { ActionItem } from '../schemas/actionCenter'

const item: ActionItem = {
  id: 'a1', section: 'required_actions', what: 'Assign evaluators',
  why: '3 cohorts await scoring', deadline: 'Jun 30', who: 'You',
  href: '/preview/selection', blocker: null,
}

test('renders what/why/deadline/owner and an open link; omits null blocker', () => {
  render(<ActionCard item={item} />)
  expect(screen.getByText('Assign evaluators')).toBeInTheDocument()
  expect(screen.getByText('3 cohorts await scoring')).toBeInTheDocument()
  expect(screen.getByText(/Jun 30/)).toBeInTheDocument()
  expect(screen.getByRole('link', { name: 'Open' })).toBeInTheDocument()
  expect(screen.queryByText(/Blocked by/)).not.toBeInTheDocument()
})
```

- [ ] **Step 5: Add the MSW handler + 10 role fixtures** — in `src/mocks/handlers.ts`, import the type and add a role→items map (each role 2–4 items across a few sections), plus a query-param-aware handler:

```ts
import type { ActionItem } from '@/schemas/actionCenter'
import type { RoleKey } from '@/schemas/roles'

const ACTION_CENTER: Record<RoleKey, ActionItem[]> = {
  program_manager: [
    { id: 'pm1', section: 'required_actions', what: 'Review 4 delayed applications', why: 'Past the screening SLA', deadline: 'Today', who: 'You', href: '/preview/applicants', blocker: null },
    { id: 'pm2', section: 'required_actions', what: 'Assign evaluators to Spring 2026', why: '12 submissions unassigned', deadline: 'Jun 30', who: 'You', href: '/preview/selection', blocker: null },
    { id: 'pm3', section: 'blocked_items', what: 'Approve stage transition', why: 'Cohort cannot advance', deadline: null, who: 'You', href: '/preview/configuration', blocker: 'Missing evaluator coverage' },
  ],
  founder: [
    { id: 'f1', section: 'required_actions', what: 'Complete the Team section', why: 'Application is 80% done', deadline: 'Jul 2', who: 'You', href: '/preview/my-application', blocker: null },
    { id: 'f2', section: 'required_actions', what: 'Upload the rejected pitch deck', why: 'Reviewer requested a new version', deadline: 'Jul 1', who: 'You', href: '/preview/documents', blocker: null },
    { id: 'f3', section: 'upcoming_sessions', what: 'Confirm mentor session', why: 'With Layla, Thu 3pm', deadline: 'Wed', who: 'You', href: '/preview/sessions', blocker: null },
  ],
  co_founder: [
    { id: 'cf1', section: 'required_actions', what: 'Review the application before submit', why: 'Your co-founder needs sign-off', deadline: 'Jul 2', who: 'You', href: '/preview/my-application', blocker: null },
    { id: 'cf2', section: 'progress', what: 'Startup profile 60% complete', why: 'Add traction metrics', deadline: null, who: 'You', href: '/preview/my-startup', blocker: null },
  ],
  mentor: [
    { id: 'm1', section: 'required_actions', what: 'Accept mentee assignment', why: '2 startups matched to you', deadline: 'Jun 29', who: 'You', href: '/preview/mentees', blocker: null },
    { id: 'm2', section: 'required_actions', what: 'Submit session notes', why: 'Session held yesterday', deadline: 'Today', who: 'You', href: '/preview/sessions', blocker: null },
    { id: 'm3', section: 'upcoming_sessions', what: 'Prepare for Fri session', why: 'Topic: go-to-market', deadline: 'Fri', who: 'You', href: '/preview/sessions', blocker: null },
  ],
  trainer: [
    { id: 't1', section: 'required_actions', what: 'Publish workshop materials', why: 'Session is tomorrow', deadline: 'Tomorrow', who: 'You', href: '/preview/materials', blocker: null },
    { id: 't2', section: 'upcoming_sessions', what: 'Take attendance for Cohort A', why: 'Live training at 2pm', deadline: 'Today', who: 'You', href: '/preview/attendance', blocker: null },
  ],
  evaluator: [
    { id: 'e1', section: 'required_actions', what: 'Score 5 assigned applications', why: 'Scoring window closes soon', deadline: 'Jun 30', who: 'You', href: '/preview/evaluation-queue', blocker: null },
    { id: 'e2', section: 'blocked_items', what: 'Resolve conflict declaration', why: 'Cannot score until cleared', deadline: null, who: 'You', href: '/preview/conflicts', blocker: 'Relationship disclosure pending' },
  ],
  judge: [
    { id: 'j1', section: 'required_actions', what: 'Finalize panel scores', why: 'Decision meeting Friday', deadline: 'Fri', who: 'You', href: '/preview/final-scoring', blocker: null },
    { id: 'j2', section: 'recent_decisions', what: 'Cohort A shortlist published', why: '8 of 24 advanced', deadline: null, who: null, href: '/preview/panel', blocker: null },
  ],
  service_provider: [
    { id: 'sp1', section: 'required_actions', what: 'Respond to 3 service requests', why: 'New requests this week', deadline: 'Jun 30', who: 'You', href: '/preview/service-requests', blocker: null },
    { id: 'sp2', section: 'progress', what: 'Listing views up 20%', why: 'Legal review offering', deadline: null, who: null, href: '/preview/offerings', blocker: null },
  ],
  program_coordinator: [
    { id: 'pc1', section: 'required_actions', what: 'Book venue for demo day', why: 'Date confirmed', deadline: 'Jul 5', who: 'You', href: '/preview/logistics', blocker: null },
    { id: 'pc2', section: 'deadlines', what: 'Send session reminders', why: '3 sessions next week', deadline: 'Mon', who: 'You', href: '/preview/coordinator-tasks', blocker: null },
  ],
  org_admin: [
    { id: 'oa1', section: 'required_actions', what: 'Invite 2 pending evaluators', why: 'Selection needs coverage', deadline: 'Jun 29', who: 'You', href: '/preview/members', blocker: null },
    { id: 'oa2', section: 'opportunities', what: 'Review role permissions', why: 'New coordinator added', deadline: null, who: 'You', href: '/preview/roles', blocker: null },
  ],
}

// add to handlers array:
http.get('*/api/v1/me/action-center', ({ request }) => {
  const role = (new URL(request.url).searchParams.get('role') ?? 'program_manager') as RoleKey
  return HttpResponse.json({ data: ACTION_CENTER[role] ?? [] })
}),
```

- [ ] **Step 6: Run + commit**

Run: `cd frontend && npm run typecheck && npm run test -- src/components/ActionCard.test.tsx`
```bash
git add src/schemas/actionCenter.ts src/api/actionCenter.ts src/components/ActionCard.tsx src/components/ActionCard.test.tsx src/mocks/handlers.ts
git commit -m "feat(fe): FE-UI-1a — ActionCard + action-center contract + 10 role fixtures

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: ActionCenterPage (role-scoped home)

**Files:**
- Create: `src/pages/ActionCenterPage.tsx`, `src/pages/ActionCenterPage.test.tsx`
- Modify: `src/app/App.tsx` (HomeRoute → ActionCenterPage)

**Interfaces:**
- Consumes: `useActiveRole`, `getActionCenter`, `ACTION_SECTIONS`/`SECTION_LABEL`, `ActionCard`, `AppShell`, `RoleSidebar`, `useConsent`, `profileDisplayName`, `Organization`.
- Produces: `<ActionCenterPage organization={Organization} />`. Sections render in `ACTION_SECTIONS` order; empty sections are omitted; a day-one empty state when there are no items.

- [ ] **Step 1: Write `src/pages/ActionCenterPage.tsx`**

```tsx
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { ActionCard } from '../components/ActionCard'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { Button } from '../components/Button'
import { useActiveRole } from '../app/active-role'
import { getActionCenter } from '../api/actionCenter'
import { ACTION_SECTIONS, SECTION_LABEL } from '../schemas/actionCenter'
import { useConsent } from '../app/consent-context'
import { profileDisplayName } from '../schemas/profile'
import type { Organization } from '../schemas/organizations'

/** Role-scoped Action Center home. The single home surface for every role. */
export function ActionCenterPage({ organization }: { organization: Organization }) {
  const role = useActiveRole()
  const consent = useConsent()
  const query = useQuery({ queryKey: ['action-center', role], queryFn: () => getActionCenter(role), retry: false })
  const items = query.data ?? []
  const name = consent.status === 'ready' && consent.profile ? profileDisplayName(consent.profile) : undefined

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="home-heading" className="grid gap-6">
        <div>
          <h1 id="home-heading" className="text-2xl font-semibold"><bdi>{organization.name}</bdi></h1>
          <p className="text-muted-foreground">
            {name ? <>Welcome back, <bdi>{name}</bdi>.</> : 'Your action center.'}
          </p>
        </div>

        {query.isLoading ? (
          <Spinner label="Loading your action center…" />
        ) : query.isError ? (
          <StateBlock variant="error" message="We could not load your action center." action={<Button onClick={() => query.refetch()}>Try again</Button>} />
        ) : items.length === 0 ? (
          <StateBlock variant="empty" message="Nothing needs your attention right now." />
        ) : (
          ACTION_SECTIONS.map((section) => {
            const sectionItems = items.filter((i) => i.section === section)
            if (sectionItems.length === 0) return null
            return (
              <div key={section} className="grid gap-2">
                <h2 className="text-lg font-medium">{SECTION_LABEL[section]}</h2>
                <div className="grid gap-2">
                  {sectionItems.map((item) => <ActionCard key={item.id} item={item} />)}
                </div>
              </div>
            )
          })
        )}
      </section>
    </AppShell>
  )
}
```

- [ ] **Step 2: Wire `HomeRoute` to ActionCenterPage in `src/app/App.tsx`** — replace the `HomePage` import and the two `HomeRoute`/ConsoleGate-default render sites with `ActionCenterPage`:

```tsx
import { ActionCenterPage } from '../pages/ActionCenterPage'
// HomeRoute:
function HomeRoute() {
  return (
    <ConsoleGate>
      {(org) => (
        <ConsentProvider>
          <ActionCenterPage organization={org} />
        </ConsentProvider>
      )}
    </ConsoleGate>
  )
}
// and in ConsoleGate's own default branch, swap <HomePage organization={orgs[0]} /> → <ActionCenterPage organization={orgs[0]} />
```
Leave `HomePage.tsx` in place (no longer routed) to avoid touching its tests in this task; it is removed in a later cleanup. Remove the now-unused `HomePage` import if lint flags it — if so, also delete `HomePage.tsx` + `HomePage.test.tsx` + `HomePage.stories.tsx` in this step and note it in the report.

- [ ] **Step 3: Write `src/pages/ActionCenterPage.test.tsx`** — provide a react-query client + `MemoryRouter` + a `ConsentProvider` wrapper (or stub `useConsent`); mock `fetch` for `/me/action-center` to return the program_manager fixture; assert a "Required actions" heading + a card renders; then `setActiveRole('founder')`, invalidate/rerender, and assert a founder card ("Complete the Team section") appears. Reset role in `afterEach`. Mirror the existing `HomePage.test.tsx` setup for the consent/query wiring.

- [ ] **Step 4: Run + commit**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test -- src/pages/ActionCenterPage.test.tsx`
```bash
git add src/pages/ActionCenterPage.tsx src/pages/ActionCenterPage.test.tsx src/app/App.tsx
# include HomePage deletions here if step 2 required them
git commit -m "feat(fe): FE-UI-1a — role-scoped ActionCenterPage as the home surface

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Re-skin the 7 auth/onboarding pages

**Files (each + its `.test.tsx`):** `RegisterPage`, `ForgotPasswordPage`, `ResetPasswordPage`, `EmailVerifiedPage`, `VerifyEmailNotice`, `AuthCallbackPage`, `OnboardingPage`.

**Interfaces:** unchanged — these pages keep their exact props, forms, mutations, and routes. Only the outer markup changes to the shadcn card pattern proven in the Slice-0 `LoginPage`.

**The pattern (apply to each page):** wrap the page's existing inner content (heading + form/notice — keep all state, handlers, react-query, error rendering) in:

```tsx
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card'
// ...
<main className="grid min-h-dvh place-items-center bg-background px-4">
  <Card className="w-full max-w-sm">
    <CardHeader><CardTitle id="<existing-heading-id>">{/* existing heading text */}</CardTitle></CardHeader>
    <CardContent>{/* the existing form / notice content, unchanged */}</CardContent>
  </Card>
</main>
```
Keep the existing level-1 heading id/text so heading-based tests/e2e still resolve (render the title via `CardTitle` with the same id, or keep an `<h1 className="sr-only">` if the page relied on one). Replace any `ds-*` classes on inner elements with Tailwind utilities (`text-muted-foreground`, `grid gap-*`, etc.). `OnboardingPage` keeps its single-field create-org form; `VerifyEmailNotice`/`EmailVerifiedPage`/`AuthCallbackPage` are notice screens — wrap their message/spinner in the same Card.

- [ ] **Step 1: Re-skin `RegisterPage.tsx`** using the pattern; keep the form/mutation/error states.
- [ ] **Step 2: Re-skin `ForgotPasswordPage.tsx`.**
- [ ] **Step 3: Re-skin `ResetPasswordPage.tsx`** (keep the exact field labels — a prior lesson: ambiguous "new password" label needs the exact string in tests).
- [ ] **Step 4: Re-skin `EmailVerifiedPage.tsx`.**
- [ ] **Step 5: Re-skin `VerifyEmailNotice.tsx`** (keep resend + rate-limit states).
- [ ] **Step 6: Re-skin `AuthCallbackPage.tsx`** (wrap the spinner/error in the Card).
- [ ] **Step 7: Re-skin `OnboardingPage.tsx`** (keep the create-org form + duplicate-name error).
- [ ] **Step 8: Update each page's `*.test.tsx`** — remove `ds-*` class assertions; keep behavioral assertions (form submit, error rendering, heading by role). Run the focused suite after each page; run the full auth/page suites once at the end:

Run: `cd frontend && npm run typecheck && npm run test -- src/pages`
Expected: all page suites green.

- [ ] **Step 9: Commit**

```bash
git add src/pages/RegisterPage.tsx src/pages/ForgotPasswordPage.tsx src/pages/ResetPasswordPage.tsx src/pages/EmailVerifiedPage.tsx src/pages/VerifyEmailNotice.tsx src/pages/AuthCallbackPage.tsx src/pages/OnboardingPage.tsx src/pages/*.test.tsx
git commit -m "feat(fe): FE-UI-1a — re-skin auth/onboarding pages onto shadcn cards

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: Stories, gates, and 1a e2e

**Files:** touched `*.stories.tsx` as needed; Create `frontend/tests/e2e/fe-ui-slice1a.spec.ts`

- [ ] **Step 1: Update stories** — add a `RoleSidebar`, `ActionCard`, and `ActionCenterPage` story if quick; fix any story that referenced removed `ds-*` markup on the re-skinned pages.

- [ ] **Step 2: Write the 1a e2e** `frontend/tests/e2e/fe-ui-slice1a.spec.ts` (MSW on, no backend)

```ts
import { test, expect } from '@playwright/test'

// Foundation proof: home renders the role-scoped Action Center; switching role
// changes the sections and the sidebar nav — all from MSW.
test('Action Center renders and reacts to role switch', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Acme Incubator', level: 1 })).toBeVisible({ timeout: 15000 })
  await expect(page.getByText('Review 4 delayed applications')).toBeVisible() // program_manager
  await expect(page.getByRole('link', { name: 'Programs' })).toBeVisible()

  await page.getByRole('button', { name: /Program Manager/ }).click()
  await page.getByRole('menuitem', { name: 'Founder' }).click()

  await expect(page.getByText('Complete the Team section')).toBeVisible() // founder
  await expect(page.getByRole('link', { name: 'My Startup' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Programs' })).toHaveCount(0)
})
```

- [ ] **Step 3: Run ALL gates**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test && npm run build && npm run build-storybook`
Expected: typecheck clean; lint clean; full vitest green; build + storybook build succeed.

- [ ] **Step 4: Run the 1a e2e** (dev server up, MSW on)

Run: `cd frontend && npx playwright test fe-ui-slice1a --reporter=list`
Expected: PASS — Action Center renders for program_manager, switches to founder's cards + nav.

- [ ] **Step 5: Commit**

```bash
git add frontend/tests/e2e/fe-ui-slice1a.spec.ts frontend/src/**/*.stories.tsx
git commit -m "test(fe): FE-UI-1a — role-switch e2e (MSW) + stories; slice 1a gates green

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage (vs `2026-06-27-fe-ui-slice1-identity-shell-design.md`, 1a scope):**
- Role contract + active-role store (`useSyncExternalStore`) → Task 1. ✓
- Role-scoped nav (10 roles) → Task 2. ✓
- ContextSelector role switch + role sidebar → Task 3. ✓
- ComingSoon for unbuilt destinations (never 404) → Task 4. ✓
- Action Center: ActionCard (6 card fields) + 8 sections + 10 role fixtures → Tasks 5–6. ✓
- Action Center replaces home; role switch re-renders sections + nav → Task 6 + e2e Task 8. ✓
- 7 auth/onboarding re-skins → Task 7. ✓
- Gates/stories/e2e → Task 8. ✓

**Placeholder scan:** no TBD/TODO. Task 3 Step 4, Task 6 Step 3, and Task 7 describe test setup by reference to the existing page-test fetch-mock pattern rather than transcribing full suites — the behavioral assertions to keep are named explicitly; this is direction, not a placeholder. Task 7 intentionally applies one shown pattern across 7 pages (the pages differ only in heading/content), which is complete instruction, not "similar to Task N".

**Type consistency:** `RoleKey` (10 keys) used in `active-role`, `role-nav`, `ContextSelector`, `getActionCenter`, MSW fixtures. `ActionItem`/`ActionSection`/`ACTION_SECTIONS`/`SECTION_LABEL` consistent across schema, ActionCard, ActionCenterPage, fixtures. `useActiveRole()`/`setActiveRole()`/`getActiveRole()`/`subscribe()` consistent. `getActionCenter(role)` and `/me/action-center?role=` match. `ROLE_NAV` keyed by every `RoleKey`. `/preview/:section` route matches the `ROLE_NAV` placeholder hrefs.
