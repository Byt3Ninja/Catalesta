# FE UI Slice 1b — Personal Surfaces Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the personal surfaces that complete Slice 1 — a consent-aware Profile view, a Consent management screen, a Notifications center + preferences, and a header Global Search — all on shadcn/ui and backed by MSW fixtures.

**Architecture:** Three independent feature areas built on the Slice-0/1a foundation. Profile reuses the existing `ConsentProvider` seam (the single profile-read site) and a new MSW consent-state holder so the consent gate is demonstrable (profile 403 until the `profile` category is granted). Notifications and Search are new invented contracts typed in `src/schemas/`, consumed through typed `api/` clients, served by MSW. New navigation affordances (a notifications bell + global search) live in the shared `AppShell` header so every console surface gets them; neither fetches at idle, so the AppShell test suite is not re-coupled to the network.

**Tech Stack:** React 19, Vite 8, TypeScript 6, Tailwind 4 + shadcn/ui, react-query, react-router 7, MSW, Vitest + Testing Library, Playwright, Zod.

## Global Constraints

- **Mock data only via MSW.** Screens call real typed `api/` clients; MSW returns fixtures. No backend/auth/tenancy changes this slice.
- **Invented contracts** (`notifications`, `search`, `consent`) are typed in `src/schemas/`; MSW fixtures are typed against those schemas so drift fails typecheck. The slice-9 wiring pass reconciles them with the real backend.
- **Consent is a first-class state, not an error.** The profile read keeps routing through the existing `ConsentProvider`/`useConsent()` seam (`src/app/ConsentProvider.tsx`, `src/app/consent-context.ts`). A `CONSENT_REQUIRED` (403) renders a neutral affordance, never a crash or leaked data. Do not add a second profile-read call site.
- **No new header fetch at idle.** The notifications bell is a plain `Link` (no badge fetch); `GlobalSearch` queries only when the query string is non-empty (`enabled: q.trim().length > 0`). This preserves the 1a fix that AppShell must not fetch on mount.
- **Notification types (verbatim):** `action`, `message`, `system`. **Search categories (verbatim, in order):** `people`, `programs`, `cohorts`, `documents`. **Consent categories (verbatim):** `profile`, `contact`, `documents`.
- **shadcn presentation only.** Match the Slice-0 `LoginPage`/`RegisterPage` card pattern and the 1a `ActionCenterPage` shell pattern (`<AppShell rail={<RoleSidebar />}>`). No `ds-*` classes in any file this plan touches.
- **MSW mock state is module-level mutable** (consent + notification read-state). It persists across requests within a session by design (so toggling consent then re-reading the profile works in the e2e). Unit tests mock `fetch` directly and never touch it.
- Run all npm commands from `frontend/`. Commit trailer: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Work on a `feat/fe-ui-slice1b-personal-surfaces` branch — never commit to `main`; check `git branch --show-current` before every commit.

## File structure

| Path | Responsibility | Task |
|------|----------------|------|
| `src/schemas/notifications.ts` | `Notification`, `NotificationType`, list response schema | 1 |
| `src/api/notifications.ts` | `listNotifications`, `markNotificationRead`, `markAllNotificationsRead` | 1 |
| `src/mocks/handlers.ts` | notifications + search + consent + profile handlers (+ mock state) | 1,4,7 |
| `src/pages/NotificationsPage.tsx` | notifications center (list, filter, mark read) | 2 |
| `src/components/AppShell.tsx` | header bell (T2) + global search (T5) | 2,5 |
| `src/app/App.tsx` | new gated routes | 2,3,6,7 |
| `src/pages/NotificationPreferencesPage.tsx` | channel/frequency toggles (presentational) | 3 |
| `src/schemas/search.ts` | `SearchItem`, `SearchGroup`, response schema | 4 |
| `src/api/search.ts` | `search(q)` | 4 |
| `src/components/GlobalSearch.tsx` | header type-ahead + categorized results dropdown | 5 |
| `src/schemas/consent.ts` | `ConsentCategory`, `ConsentEntry`, list response schema | 6 |
| `src/api/consent.ts` | `getConsents`, `setConsent` | 6 |
| `src/pages/ProfilePage.tsx` | consent-aware profile view | 6 |
| `src/pages/ConsentManagementPage.tsx` | grant/revoke per category | 7 |
| Stories + `e2e/personal-surfaces.spec.ts` | stories for new components + 1b e2e | 8 |

**Scope note (carry to handoff):** the Slice-1 spec named a separate `SearchPage.tsx`. This plan delivers the search results surface as the `GlobalSearch` header dropdown (one fully-testable surface) and does **not** build a separate `/search` route — add one when a full-page "see all results" view is actually needed. This is a deliberate plan-time YAGNI call; flag it to the user at the final review.

---

### Task 1: Notifications contract + client + MSW

**Files:**
- Create: `src/schemas/notifications.ts`, `src/api/notifications.ts`, `src/api/notifications.test.ts`
- Modify: `src/mocks/handlers.ts`

**Interfaces:**
- Produces: `NotificationType` (`'action' | 'message' | 'system'`), `Notification = { id, type, title, body, created_at, read_at: string|null, href: string|null }`, `notificationListResponseSchema`; `listNotifications(): Promise<Notification[]>`, `markNotificationRead(id): Promise<void>`, `markAllNotificationsRead(): Promise<void>`.

- [ ] **Step 1: Write `src/schemas/notifications.ts`**

```ts
import { z } from 'zod'

export const NOTIFICATION_TYPES = ['action', 'message', 'system'] as const
export const notificationTypeSchema = z.enum(NOTIFICATION_TYPES)
export type NotificationType = z.infer<typeof notificationTypeSchema>

export const NOTIFICATION_TYPE_LABEL: Record<NotificationType, string> = {
  action: 'Action',
  message: 'Message',
  system: 'System',
}

export const notificationSchema = z.object({
  id: z.string(),
  type: notificationTypeSchema,
  title: z.string(),
  body: z.string(),
  created_at: z.string(),
  read_at: z.string().nullable(),
  href: z.string().nullable(),
})
export type Notification = z.infer<typeof notificationSchema>

export const notificationListResponseSchema = z.object({ data: z.array(notificationSchema) })
```

- [ ] **Step 2: Write `src/api/notifications.ts`**

```ts
import { apiFetch } from './tenant'
import { notificationListResponseSchema, type Notification } from '../schemas/notifications'

/** GET /notifications — the current user's notifications (prototype contract). */
export async function listNotifications(): Promise<Notification[]> {
  const response = await apiFetch('/notifications')
  if (!response.ok) throw new Error(`notifications list failed: ${response.status}`)
  const json: unknown = await response.json()
  return notificationListResponseSchema.parse(json).data
}

/** POST /notifications/{id}/read — mark one notification read. */
export async function markNotificationRead(id: string): Promise<void> {
  const response = await apiFetch(`/notifications/${id}/read`, { method: 'POST' })
  if (!response.ok) throw new Error(`mark read failed: ${response.status}`)
}

/** POST /notifications/read-all — mark every notification read. */
export async function markAllNotificationsRead(): Promise<void> {
  const response = await apiFetch('/notifications/read-all', { method: 'POST' })
  if (!response.ok) throw new Error(`mark all read failed: ${response.status}`)
}
```

- [ ] **Step 3: Write the failing test** `src/api/notifications.test.ts`

```ts
import { afterEach, expect, test, vi } from 'vitest'
import { listNotifications, markNotificationRead } from './notifications'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

const ITEM = {
  id: 'n1', type: 'action', title: 'Review applications', body: '4 are overdue.',
  created_at: '2026-06-26T00:00:00Z', read_at: null, href: '/preview/applicants',
}

test('listNotifications parses the data array', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [ITEM] }))
  const result = await listNotifications()
  expect(result).toHaveLength(1)
  expect(result[0].id).toBe('n1')
})

test('listNotifications rejects a malformed payload', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [{ id: 'x' }] }))
  await expect(listNotifications()).rejects.toThrow()
})

test('markNotificationRead POSTs and resolves on ok', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 }))
  await markNotificationRead('n1')
  expect(spy).toHaveBeenCalledWith(expect.stringContaining('/notifications/n1/read'), expect.objectContaining({ method: 'POST' }))
})
```

- [ ] **Step 4: Run it — expect FAIL** (`Cannot find module './notifications'`)

Run: `npm run test -- src/api/notifications.test.ts`
Expected: FAIL (module/exports missing).

- [ ] **Step 5: Implement Steps 1–2 so the test passes**, then run:

Run: `npm run test -- src/api/notifications.test.ts`
Expected: PASS (3/3).

- [ ] **Step 6: Add MSW handlers + mock read-state to `src/mocks/handlers.ts`**

Add the fixture + mutable state near the other fixtures (after `ACTION_CENTER`):

```ts
import type { Notification } from '@/schemas/notifications'

// Module-level mock state: notification read-status mutates within a session.
const NOTIFICATIONS: Notification[] = [
  { id: 'n1', type: 'action', title: 'Review delayed applications', body: '4 applications are past the screening SLA.', created_at: '2026-06-26T09:00:00Z', read_at: null, href: '/preview/applicants' },
  { id: 'n2', type: 'message', title: 'New message from Layla', body: 'Confirming Thursday 3pm mentor session.', created_at: '2026-06-25T14:30:00Z', read_at: null, href: '/preview/sessions' },
  { id: 'n3', type: 'system', title: 'Cohort Spring 2026 opened', body: 'Enrollment is now open.', created_at: '2026-06-24T08:00:00Z', read_at: '2026-06-24T10:00:00Z', href: null },
]
```

Add to the `handlers` array (the `apiFetch` base already includes `/api/v1`):

```ts
  http.get('*/api/v1/notifications', () => HttpResponse.json({ data: NOTIFICATIONS })),
  http.post('*/api/v1/notifications/read-all', () => {
    for (const n of NOTIFICATIONS) if (n.read_at === null) n.read_at = NOW
    return new HttpResponse(null, { status: 204 })
  }),
  http.post('*/api/v1/notifications/:id/read', ({ params }) => {
    const found = NOTIFICATIONS.find((n) => n.id === params.id)
    if (found && found.read_at === null) found.read_at = NOW
    return new HttpResponse(null, { status: 204 })
  }),
```

> Order the `:id/read` route after `read-all` so the literal path is not shadowed by the param route.

- [ ] **Step 7: Verify typecheck + the new test stay green**

Run: `npm run typecheck && npm run test -- src/api/notifications.test.ts`
Expected: typecheck clean; 3/3 pass.

- [ ] **Step 8: Commit**

```bash
git add src/schemas/notifications.ts src/api/notifications.ts src/api/notifications.test.ts src/mocks/handlers.ts
git commit -m "feat(fe): notifications contract, client, and MSW handlers"
```

---

### Task 2: Notifications center page + header bell

**Files:**
- Create: `src/pages/NotificationsPage.tsx`, `src/pages/NotificationsPage.test.tsx`
- Modify: `src/components/AppShell.tsx`, `src/app/App.tsx`

**Interfaces:**
- Consumes: `listNotifications`, `markNotificationRead`, `markAllNotificationsRead` (Task 1); `AppShell`, `RoleSidebar`, `StateBlock`, `Button`, `Link`, `Spinner`; `NOTIFICATION_TYPES`, `NOTIFICATION_TYPE_LABEL`.
- Produces: `NotificationsPage` (default home for `/notifications`).

- [ ] **Step 1: Write the failing test** `src/pages/NotificationsPage.test.tsx`

```tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { NotificationsPage } from './NotificationsPage'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

const DATA = [
  { id: 'n1', type: 'action', title: 'Review applications', body: 'Overdue.', created_at: '2026-06-26T09:00:00Z', read_at: null, href: '/preview/applicants' },
  { id: 'n2', type: 'system', title: 'Cohort opened', body: 'Now open.', created_at: '2026-06-24T08:00:00Z', read_at: '2026-06-24T10:00:00Z', href: null },
]

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter><NotificationsPage /></MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

test('renders notifications and an unread count', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: DATA }))
  renderPage()
  expect(await screen.findByText('Review applications')).toBeInTheDocument()
  // one unread of two
  expect(screen.getByText(/1 unread/i)).toBeInTheDocument()
})

test('mark-all-read calls the endpoint and refetches', async () => {
  const spy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DATA }))           // initial list
    .mockResolvedValueOnce(new Response(null, { status: 204 }))    // read-all
    .mockResolvedValue(jsonResponse({ data: DATA.map((n) => ({ ...n, read_at: '2026-06-27T00:00:00Z' })) })) // refetch
  renderPage()
  await screen.findByText('Review applications')
  fireEvent.click(screen.getByRole('button', { name: /mark all read/i }))
  await waitFor(() => expect(spy).toHaveBeenCalledWith(expect.stringContaining('/notifications/read-all'), expect.objectContaining({ method: 'POST' })))
  await waitFor(() => expect(screen.getByText(/0 unread/i)).toBeInTheDocument())
})

test('shows an empty state when there are none', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: [] }))
  renderPage()
  expect(await screen.findByText(/no notifications/i)).toBeInTheDocument()
})
```

- [ ] **Step 2: Run it — expect FAIL** (`Cannot find module './NotificationsPage'`)

Run: `npm run test -- src/pages/NotificationsPage.test.tsx`
Expected: FAIL.

- [ ] **Step 3: Write `src/pages/NotificationsPage.tsx`**

```tsx
import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { StateBlock } from '../components/StateBlock'
import { Spinner } from '../components/Loading'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { listNotifications, markNotificationRead, markAllNotificationsRead } from '../api/notifications'
import { NOTIFICATION_TYPES, NOTIFICATION_TYPE_LABEL, type NotificationType } from '../schemas/notifications'

type Filter = 'all' | NotificationType

/** Notifications center: list, type filter, mark single/all read, unread count. */
export function NotificationsPage() {
  const queryClient = useQueryClient()
  const [filter, setFilter] = useState<Filter>('all')
  const query = useQuery({ queryKey: ['notifications'], queryFn: listNotifications, retry: false })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['notifications'] })
  const markOne = useMutation({ mutationFn: markNotificationRead, onSuccess: invalidate })
  const markAll = useMutation({ mutationFn: markAllNotificationsRead, onSuccess: invalidate })

  const items = query.data ?? []
  const unread = items.filter((n) => n.read_at === null).length
  const shown = filter === 'all' ? items : items.filter((n) => n.type === filter)

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="notif-heading" className="grid gap-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 id="notif-heading" className="text-2xl font-semibold">Notifications</h1>
            <p className="text-muted-foreground">{unread} unread</p>
          </div>
          <div className="flex items-center gap-2">
            <Link href="/notifications/preferences" className="text-sm">Preferences</Link>
            <Button onClick={() => markAll.mutate()} disabled={unread === 0 || markAll.isPending}>Mark all read</Button>
          </div>
        </div>

        <div className="flex flex-wrap gap-2" role="group" aria-label="Filter by type">
          {(['all', ...NOTIFICATION_TYPES] as Filter[]).map((f) => (
            <Button key={f} variant={filter === f ? 'primary' : 'ghost'} onClick={() => setFilter(f)} aria-pressed={filter === f}>
              {f === 'all' ? 'All' : NOTIFICATION_TYPE_LABEL[f]}
            </Button>
          ))}
        </div>

        {query.isLoading ? (
          <Spinner label="Loading notifications…" />
        ) : query.isError ? (
          <StateBlock variant="error" message="We could not load your notifications." action={<Button onClick={() => query.refetch()}>Try again</Button>} />
        ) : shown.length === 0 ? (
          <StateBlock variant="empty" message={items.length === 0 ? 'No notifications yet.' : 'No notifications of this type.'} />
        ) : (
          <ul className="grid gap-2">
            {shown.map((n) => (
              <li key={n.id} className="rounded-md border border-border p-3" data-read={n.read_at !== null}>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-medium">
                      {n.read_at === null ? <span aria-label="Unread" className="me-2 inline-block size-2 rounded-full bg-primary align-middle" /> : null}
                      {n.title}
                    </p>
                    <p className="text-sm text-muted-foreground">{n.body}</p>
                    <p className="mt-1 text-xs text-muted-foreground">{NOTIFICATION_TYPE_LABEL[n.type]} · {n.created_at.slice(0, 10)}</p>
                    {n.href ? <Link href={n.href} className="text-sm">Open</Link> : null}
                  </div>
                  {n.read_at === null ? (
                    <Button variant="ghost" onClick={() => markOne.mutate(n.id)} disabled={markOne.isPending}>Mark read</Button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    </AppShell>
  )
}
```

> If `Button`'s `variant` union does not include `'primary'`/`'ghost'`, read `src/components/Button.tsx` and use its actual variant names — do not invent values.

- [ ] **Step 4: Run the test — expect PASS**

Run: `npm run test -- src/pages/NotificationsPage.test.tsx`
Expected: PASS (3/3).

- [ ] **Step 5: Add the header bell to `src/components/AppShell.tsx`**

Add the import:

```tsx
import { Menu, Bell } from 'lucide-react'
import { Link } from './Link'
```

In the header, between `<ContextSelector />`'s wrapper and `<ThemeToggle />`, add a plain navigational link (no fetch, no badge — see Global Constraints):

```tsx
        <div className="ms-2 flex-1"><ContextSelector /></div>
        <Link href="/notifications" aria-label="Notifications" className="inline-flex items-center p-2">
          <Bell className="size-4" />
        </Link>
        <ThemeToggle />
```

- [ ] **Step 6: Add the route to `src/app/App.tsx`**

Import and add a gated route (no org needed — the gate just admits the console surface):

```tsx
import { NotificationsPage } from '../pages/NotificationsPage'
```

```tsx
      <Route path="/notifications" element={<ConsoleGate>{() => <NotificationsPage />}</ConsoleGate>} />
```

- [ ] **Step 7: Verify the touched suites + AppShell stay green**

Run: `npm run test -- src/pages/NotificationsPage.test.tsx src/components/AppShell.test.tsx`
Expected: PASS. (AppShell must not regress — the bell is a `Link`, not a fetch.)

- [ ] **Step 8: Commit**

```bash
git add src/pages/NotificationsPage.tsx src/pages/NotificationsPage.test.tsx src/components/AppShell.tsx src/app/App.tsx
git commit -m "feat(fe): notifications center page + header bell + route"
```

---

### Task 3: Notification preferences page (presentational)

**Files:**
- Create: `src/pages/NotificationPreferencesPage.tsx`, `src/pages/NotificationPreferencesPage.test.tsx`
- Modify: `src/app/App.tsx`

**Interfaces:**
- Consumes: `AppShell`, `RoleSidebar`, `Button`, `Link`, `NOTIFICATION_TYPES`, `NOTIFICATION_TYPE_LABEL`.
- Produces: `NotificationPreferencesPage` (route `/notifications/preferences`).

**ponytail:** local state only, no backend persistence. Saving shows an in-page confirmation. Add real persistence in slice 9 when a preferences endpoint exists.

- [ ] **Step 1: Write the failing test** `src/pages/NotificationPreferencesPage.test.tsx`

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { expect, test } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { NotificationPreferencesPage } from './NotificationPreferencesPage'

function renderPage(): void {
  const ui: ReactElement = (
    <DirectionProvider>
      <MemoryRouter><NotificationPreferencesPage /></MemoryRouter>
    </DirectionProvider>
  )
  render(ui)
}

test('toggling a channel and saving shows a confirmation', () => {
  renderPage()
  const emailToggle = screen.getByRole('checkbox', { name: /email/i })
  fireEvent.click(emailToggle)
  fireEvent.click(screen.getByRole('button', { name: /save preferences/i }))
  expect(screen.getByRole('status')).toHaveTextContent(/saved/i)
})
```

- [ ] **Step 2: Run it — expect FAIL**

Run: `npm run test -- src/pages/NotificationPreferencesPage.test.tsx`
Expected: FAIL (module missing).

- [ ] **Step 3: Write `src/pages/NotificationPreferencesPage.tsx`**

```tsx
import { useState } from 'react'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { Button } from '../components/Button'
import { Link } from '../components/Link'

const CHANNELS = [
  { key: 'email', label: 'Email' },
  { key: 'in_app', label: 'In-app' },
] as const
const FREQUENCIES = ['immediate', 'daily', 'weekly'] as const

/** Notification preferences — presentational only (mock persistence, slice 1b). */
export function NotificationPreferencesPage() {
  const [channels, setChannels] = useState<Record<string, boolean>>({ email: true, in_app: true })
  const [frequency, setFrequency] = useState<string>('immediate')
  const [saved, setSaved] = useState(false)

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="prefs-heading" className="grid max-w-md gap-6">
        <div>
          <h1 id="prefs-heading" className="text-2xl font-semibold">Notification preferences</h1>
          <p className="text-muted-foreground"><Link href="/notifications">Back to notifications</Link></p>
        </div>

        <fieldset className="grid gap-2">
          <legend className="text-sm font-medium">Channels</legend>
          {CHANNELS.map((c) => (
            <label key={c.key} className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={channels[c.key]}
                onChange={(e) => { setChannels((prev) => ({ ...prev, [c.key]: e.target.checked })); setSaved(false) }}
              />
              {c.label}
            </label>
          ))}
        </fieldset>

        <label className="grid gap-1">
          <span className="text-sm font-medium">Frequency</span>
          <select
            className="rounded-md border border-input bg-background px-2 py-1"
            value={frequency}
            onChange={(e) => { setFrequency(e.target.value); setSaved(false) }}
          >
            {FREQUENCIES.map((f) => <option key={f} value={f}>{f[0].toUpperCase() + f.slice(1)}</option>)}
          </select>
        </label>

        <div className="flex items-center gap-3">
          <Button onClick={() => setSaved(true)}>Save preferences</Button>
          {saved ? <p role="status" className="text-sm text-muted-foreground">Preferences saved.</p> : null}
        </div>
      </section>
    </AppShell>
  )
}
```

- [ ] **Step 4: Run the test — expect PASS**

Run: `npm run test -- src/pages/NotificationPreferencesPage.test.tsx`
Expected: PASS.

- [ ] **Step 5: Add the route to `src/app/App.tsx`**

```tsx
import { NotificationPreferencesPage } from '../pages/NotificationPreferencesPage'
```

```tsx
      <Route path="/notifications/preferences" element={<ConsoleGate>{() => <NotificationPreferencesPage />}</ConsoleGate>} />
```

> Place this route **before** `/notifications` is not required (distinct paths), but keep both together for readability.

- [ ] **Step 6: Commit**

```bash
git add src/pages/NotificationPreferencesPage.tsx src/pages/NotificationPreferencesPage.test.tsx src/app/App.tsx
git commit -m "feat(fe): notification preferences page + route"
```

---

### Task 4: Search contract + client + MSW

**Files:**
- Create: `src/schemas/search.ts`, `src/api/search.ts`, `src/api/search.test.ts`
- Modify: `src/mocks/handlers.ts`

**Interfaces:**
- Produces: `SearchCategory` (`'people' | 'programs' | 'cohorts' | 'documents'`), `SearchItem = { id, label, sublabel: string|null, href: string }`, `SearchGroup = { category: SearchCategory, items: SearchItem[] }`, `searchResponseSchema`; `search(q: string): Promise<SearchGroup[]>`.

- [ ] **Step 1: Write `src/schemas/search.ts`**

```ts
import { z } from 'zod'

export const SEARCH_CATEGORIES = ['people', 'programs', 'cohorts', 'documents'] as const
export const searchCategorySchema = z.enum(SEARCH_CATEGORIES)
export type SearchCategory = z.infer<typeof searchCategorySchema>

export const SEARCH_CATEGORY_LABEL: Record<SearchCategory, string> = {
  people: 'People',
  programs: 'Programs',
  cohorts: 'Cohorts',
  documents: 'Documents',
}

export const searchItemSchema = z.object({
  id: z.string(),
  label: z.string(),
  sublabel: z.string().nullable(),
  href: z.string(),
})
export type SearchItem = z.infer<typeof searchItemSchema>

export const searchGroupSchema = z.object({ category: searchCategorySchema, items: z.array(searchItemSchema) })
export type SearchGroup = z.infer<typeof searchGroupSchema>

export const searchResponseSchema = z.object({ data: z.array(searchGroupSchema) })
```

- [ ] **Step 2: Write `src/api/search.ts`**

```ts
import { apiFetch } from './tenant'
import { searchResponseSchema, type SearchGroup } from '../schemas/search'

/** GET /search?q= — categorized global search results (prototype contract). */
export async function search(q: string): Promise<SearchGroup[]> {
  const response = await apiFetch(`/search?q=${encodeURIComponent(q)}`)
  if (!response.ok) throw new Error(`search failed: ${response.status}`)
  const json: unknown = await response.json()
  return searchResponseSchema.parse(json).data
}
```

- [ ] **Step 3: Write the failing test** `src/api/search.test.ts`

```ts
import { afterEach, expect, test, vi } from 'vitest'
import { search } from './search'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

test('search encodes the query and parses groups', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: [{ category: 'people', items: [{ id: 'p1', label: 'Alice', sublabel: 'Founder', href: '/preview/people/p1' }] }] }),
  )
  const result = await search('al ice')
  expect(spy).toHaveBeenCalledWith(expect.stringContaining('q=al%20ice'), expect.anything())
  expect(result[0].category).toBe('people')
  expect(result[0].items[0].label).toBe('Alice')
})

test('rejects a malformed payload', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [{ category: 'nope', items: [] }] }))
  await expect(search('x')).rejects.toThrow()
})
```

> `apiFetch` may encode the space as `+` or `%20` depending on its URL handling. If the assertion fails on that detail, assert `expect.stringContaining('q=al')` instead — the point is the query is forwarded, not the exact space encoding.

- [ ] **Step 4: Run it — expect FAIL**, then implement Steps 1–2 and re-run to PASS.

Run: `npm run test -- src/api/search.test.ts`
Expected: FAIL → (after impl) PASS (2/2).

- [ ] **Step 5: Add the MSW handler + fixture to `src/mocks/handlers.ts`**

Add the fixture (typed) near the others:

```ts
import type { SearchGroup } from '@/schemas/search'

const SEARCH_INDEX: SearchGroup[] = [
  { category: 'people', items: [
    { id: 'p1', label: 'Alice Founder', sublabel: 'Founder · Acme', href: '/preview/people/p1' },
    { id: 'p2', label: 'Layla Mentor', sublabel: 'Mentor', href: '/preview/people/p2' },
  ] },
  { category: 'programs', items: [
    { id: 'prog_1', label: 'FinTech Accelerator 2026', sublabel: 'Published', href: '/programs/prog_1' },
  ] },
  { category: 'cohorts', items: [
    { id: 'coh_1', label: 'Spring 2026', sublabel: 'Open', href: '/cohorts/coh_1' },
  ] },
  { category: 'documents', items: [
    { id: 'd1', label: 'Pitch deck v2', sublabel: 'PDF', href: '/preview/documents/d1' },
  ] },
]
```

Add the handler (case-insensitive substring match on label/sublabel; empty groups dropped):

```ts
  http.get('*/api/v1/search', ({ request }) => {
    const q = (new URL(request.url).searchParams.get('q') ?? '').trim().toLowerCase()
    if (q === '') return HttpResponse.json({ data: [] })
    const data = SEARCH_INDEX
      .map((g) => ({ category: g.category, items: g.items.filter((i) => `${i.label} ${i.sublabel ?? ''}`.toLowerCase().includes(q)) }))
      .filter((g) => g.items.length > 0)
    return HttpResponse.json({ data })
  }),
```

- [ ] **Step 6: Verify typecheck + the new test**

Run: `npm run typecheck && npm run test -- src/api/search.test.ts`
Expected: clean + PASS.

- [ ] **Step 7: Commit**

```bash
git add src/schemas/search.ts src/api/search.ts src/api/search.test.ts src/mocks/handlers.ts
git commit -m "feat(fe): global search contract, client, and MSW handler"
```

---

### Task 5: Global search (header type-ahead + results dropdown)

**Files:**
- Create: `src/components/GlobalSearch.tsx`, `src/components/GlobalSearch.test.tsx`
- Modify: `src/components/AppShell.tsx`

**Interfaces:**
- Consumes: `search` (Task 4), `SEARCH_CATEGORY_LABEL`, `Link`, `useQuery`.
- Produces: `GlobalSearch` (rendered in the `AppShell` header).

- [ ] **Step 1: Write the failing test** `src/components/GlobalSearch.test.tsx`

```tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { GlobalSearch } from './GlobalSearch'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

function renderSearch(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter><GlobalSearch /></MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

test('does not fetch for an empty query', () => {
  const spy = vi.spyOn(globalThis, 'fetch')
  renderSearch()
  expect(spy).not.toHaveBeenCalled()
})

test('typing a query shows categorized results', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(
    jsonResponse({ data: [{ category: 'programs', items: [{ id: 'prog_1', label: 'FinTech Accelerator 2026', sublabel: 'Published', href: '/programs/prog_1' }] }] }),
  )
  renderSearch()
  fireEvent.change(screen.getByRole('searchbox', { name: /search/i }), { target: { value: 'fintech' } })
  expect(await screen.findByText('FinTech Accelerator 2026')).toBeInTheDocument()
  expect(screen.getByText(/programs/i)).toBeInTheDocument()
})

test('shows a no-results state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: [] }))
  renderSearch()
  fireEvent.change(screen.getByRole('searchbox', { name: /search/i }), { target: { value: 'zzz' } })
  expect(await screen.findByText(/no matches/i)).toBeInTheDocument()
})
```

- [ ] **Step 2: Run it — expect FAIL**

Run: `npm run test -- src/components/GlobalSearch.test.tsx`
Expected: FAIL (module missing).

- [ ] **Step 3: Write `src/components/GlobalSearch.tsx`**

```tsx
import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from './Link'
import { search } from '../api/search'
import { SEARCH_CATEGORY_LABEL } from '../schemas/search'

/**
 * Header global search: a debounced type-ahead whose results surface is a
 * categorized dropdown. Fetches only for a non-empty query (no idle network),
 * so it is safe to mount in the shared AppShell header.
 */
export function GlobalSearch() {
  const [input, setInput] = useState('')
  const [debounced, setDebounced] = useState('')

  // ponytail: 250ms debounce via a single timer, no lib.
  useEffect(() => {
    const t = setTimeout(() => setDebounced(input.trim()), 250)
    return () => clearTimeout(t)
  }, [input])

  const query = useQuery({
    queryKey: ['search', debounced],
    queryFn: () => search(debounced),
    enabled: debounced.length > 0,
    retry: false,
  })

  const groups = query.data ?? []
  const open = debounced.length > 0

  return (
    <div className="relative hidden sm:block">
      <input
        type="search"
        role="searchbox"
        aria-label="Search"
        placeholder="Search…"
        value={input}
        onChange={(e) => setInput(e.target.value)}
        className="h-8 w-48 rounded-md border border-input bg-background px-2 text-sm"
      />
      {open ? (
        <div className="absolute end-0 z-50 mt-1 w-72 rounded-md border border-border bg-popover p-2 shadow-md" role="listbox" aria-label="Search results">
          {query.isLoading ? (
            <p className="px-2 py-1 text-sm text-muted-foreground">Searching…</p>
          ) : groups.length === 0 ? (
            <p className="px-2 py-1 text-sm text-muted-foreground">No matches.</p>
          ) : (
            groups.map((g) => (
              <div key={g.category} className="py-1">
                <p className="px-2 text-xs font-medium text-muted-foreground">{SEARCH_CATEGORY_LABEL[g.category]}</p>
                {g.items.map((item) => (
                  <Link key={item.id} href={item.href} className="block rounded px-2 py-1 text-sm hover:bg-accent">
                    {item.label}
                    {item.sublabel ? <span className="text-muted-foreground"> — {item.sublabel}</span> : null}
                  </Link>
                ))}
              </div>
            ))
          )}
        </div>
      ) : null}
    </div>
  )
}
```

> If `bg-popover`/`bg-accent` are not defined theme tokens in this project, substitute `bg-background`/`bg-muted` (check `src/index.css` / theme). Do not introduce raw hex colors.

- [ ] **Step 4: Run the test — expect PASS**

Run: `npm run test -- src/components/GlobalSearch.test.tsx`
Expected: PASS (3/3). The first test proves no idle fetch.

- [ ] **Step 5: Mount `GlobalSearch` in `src/components/AppShell.tsx`**

Import it and place it in the header before the bell:

```tsx
import { GlobalSearch } from './GlobalSearch'
```

```tsx
        <div className="ms-2 flex-1"><ContextSelector /></div>
        <GlobalSearch />
        <Link href="/notifications" aria-label="Notifications" className="inline-flex items-center p-2">
          <Bell className="size-4" />
        </Link>
        <ThemeToggle />
```

- [ ] **Step 6: Verify AppShell does not regress** (no idle fetch)

Run: `npm run test -- src/components/AppShell.test.tsx src/components/GlobalSearch.test.tsx`
Expected: PASS. AppShell tests render without typing, so GlobalSearch issues no fetch.

- [ ] **Step 7: Commit**

```bash
git add src/components/GlobalSearch.tsx src/components/GlobalSearch.test.tsx src/components/AppShell.tsx
git commit -m "feat(fe): global search header type-ahead + results dropdown"
```

---

### Task 6: Consent contract + Profile view page

**Files:**
- Create: `src/schemas/consent.ts`, `src/api/consent.ts`, `src/api/consent.test.ts`, `src/pages/ProfilePage.tsx`, `src/pages/ProfilePage.test.tsx`
- Modify: `src/mocks/handlers.ts`, `src/app/App.tsx`

**Interfaces:**
- Produces: `ConsentCategory` (`'profile' | 'contact' | 'documents'`), `ConsentEntry = { category: ConsentCategory, granted: boolean }`, `consentListResponseSchema`; `getConsents(): Promise<ConsentEntry[]>`, `setConsent(category, granted): Promise<void>`; `ProfilePage` (route `/profile`, must render inside `ConsentProvider`).
- Consumes: existing `useConsent()` seam, `profileDisplayName`, `AppShell`, `RoleSidebar`, `StateBlock`, `Spinner`, `Link`.

- [ ] **Step 1: Write `src/schemas/consent.ts`**

```ts
import { z } from 'zod'

export const CONSENT_CATEGORIES = ['profile', 'contact', 'documents'] as const
export const consentCategorySchema = z.enum(CONSENT_CATEGORIES)
export type ConsentCategory = z.infer<typeof consentCategorySchema>

export const CONSENT_CATEGORY_LABEL: Record<ConsentCategory, string> = {
  profile: 'Profile details',
  contact: 'Contact information',
  documents: 'Documents',
}

export const consentEntrySchema = z.object({ category: consentCategorySchema, granted: z.boolean() })
export type ConsentEntry = z.infer<typeof consentEntrySchema>

export const consentListResponseSchema = z.object({ data: z.array(consentEntrySchema) })
```

- [ ] **Step 2: Write `src/api/consent.ts`**

```ts
import { apiFetch } from './tenant'
import { consentListResponseSchema, type ConsentCategory, type ConsentEntry } from '../schemas/consent'

/** GET /me/consent — the user's per-category consent grants (prototype contract). */
export async function getConsents(): Promise<ConsentEntry[]> {
  const response = await apiFetch('/me/consent')
  if (!response.ok) throw new Error(`consent list failed: ${response.status}`)
  const json: unknown = await response.json()
  return consentListResponseSchema.parse(json).data
}

/** POST /me/consent — grant or revoke one category. */
export async function setConsent(category: ConsentCategory, granted: boolean): Promise<void> {
  const response = await apiFetch('/me/consent', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ category, granted }),
  })
  if (!response.ok) throw new Error(`consent update failed: ${response.status}`)
}
```

- [ ] **Step 3: Write the failing test** `src/api/consent.test.ts`

```ts
import { afterEach, expect, test, vi } from 'vitest'
import { getConsents, setConsent } from './consent'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

test('getConsents parses the data array', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [{ category: 'profile', granted: false }] }))
  const result = await getConsents()
  expect(result[0]).toEqual({ category: 'profile', granted: false })
})

test('setConsent POSTs the category and flag', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 }))
  await setConsent('profile', true)
  const [, init] = spy.mock.calls[0]
  expect(init?.method).toBe('POST')
  expect(JSON.parse(String(init?.body))).toEqual({ category: 'profile', granted: true })
})
```

- [ ] **Step 4: Run it — expect FAIL**, then implement Steps 1–2 and re-run to PASS.

Run: `npm run test -- src/api/consent.test.ts`
Expected: FAIL → PASS (2/2).

- [ ] **Step 5: Write the failing test** `src/pages/ProfilePage.test.tsx`

```tsx
import { render, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { ConsentProvider } from '../app/ConsentProvider'
import { ProfilePage } from './ProfilePage'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <ConsentProvider><ProfilePage /></ConsentProvider>
        </MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

test('renders profile fields when consent is granted', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ display_name: 'Alice', email: 'alice@catalesta.test' }))
  renderPage()
  expect(await screen.findByText('Alice')).toBeInTheDocument()
  expect(screen.getByText('alice@catalesta.test')).toBeInTheDocument()
})

test('shows the neutral consent affordance on 403, not an error', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response('forbidden', { status: 403 }))
  renderPage()
  expect(await screen.findByRole('link', { name: /manage consent/i })).toBeInTheDocument()
  expect(screen.queryByText(/something went wrong/i)).not.toBeInTheDocument()
})
```

- [ ] **Step 6: Run it — expect FAIL**

Run: `npm run test -- src/pages/ProfilePage.test.tsx`
Expected: FAIL (module missing).

- [ ] **Step 7: Write `src/pages/ProfilePage.tsx`**

```tsx
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { StateBlock } from '../components/StateBlock'
import { Spinner } from '../components/Loading'
import { Link } from '../components/Link'
import { useConsent } from '../app/consent-context'
import { profileDisplayName } from '../schemas/profile'

// Known profile fields we render when present (tolerant: profile is a string→unknown map).
const FIELDS: { key: string; label: string }[] = [
  { key: 'email', label: 'Email' },
  { key: 'organization', label: 'Organization' },
  { key: 'title', label: 'Title' },
  { key: 'location', label: 'Location' },
]

/**
 * Consent-aware profile view (Story 1.5). Reads the profile through the existing
 * ConsentProvider seam — never a raw fetch — so a CONSENT_REQUIRED renders a
 * neutral affordance pointing at the consent screen, not an error.
 */
export function ProfilePage() {
  const consent = useConsent()

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="profile-heading" className="grid max-w-xl gap-6">
        <h1 id="profile-heading" className="text-2xl font-semibold">Profile</h1>

        {consent.status === 'loading' ? (
          <Spinner label="Loading your profile…" />
        ) : consent.status === 'consent-required' ? (
          <StateBlock
            variant="empty"
            message="Your profile is managed externally and access requires your consent."
            action={<Link href="/consent">Manage consent</Link>}
          />
        ) : consent.status === 'error' ? (
          <StateBlock variant="error" message="We could not load your profile." />
        ) : (
          <dl className="grid gap-3">
            <div>
              <dt className="text-sm text-muted-foreground">Name</dt>
              <dd className="font-medium"><bdi>{profileDisplayName(consent.profile!) ?? '—'}</bdi></dd>
            </div>
            {FIELDS.map((f) => {
              const value = consent.profile![f.key]
              if (typeof value !== 'string' || value.trim() === '') return null
              return (
                <div key={f.key}>
                  <dt className="text-sm text-muted-foreground">{f.label}</dt>
                  <dd><bdi>{value}</bdi></dd>
                </div>
              )
            })}
          </dl>
        )}
      </section>
    </AppShell>
  )
}
```

- [ ] **Step 8: Run the ProfilePage test — expect PASS**

Run: `npm run test -- src/pages/ProfilePage.test.tsx`
Expected: PASS (2/2).

- [ ] **Step 9: Add MSW consent state + profile/consent handlers to `src/mocks/handlers.ts`**

Add the module-level consent state and a profile fixture near the others:

```ts
import type { ConsentCategory } from '@/schemas/consent'

// Mock consent state — `profile` starts NOT granted so the consent gate is
// demonstrable (profile read 403s until granted on the consent screen).
const CONSENT_STATE: Record<ConsentCategory, boolean> = { profile: false, contact: false, documents: false }

const PROFILE = { display_name: 'Alice', email: 'alice@catalesta.test', organization: 'Acme Incubator', title: 'Founder' }
```

Add the handlers:

```ts
  http.get('*/api/v1/me/profile', () =>
    CONSENT_STATE.profile ? HttpResponse.json(PROFILE) : new HttpResponse('forbidden', { status: 403 }),
  ),
  http.get('*/api/v1/me/consent', () =>
    HttpResponse.json({ data: (Object.keys(CONSENT_STATE) as ConsentCategory[]).map((category) => ({ category, granted: CONSENT_STATE[category] })) }),
  ),
  http.post('*/api/v1/me/consent', async ({ request }) => {
    const body = (await request.json()) as { category: ConsentCategory; granted: boolean }
    CONSENT_STATE[body.category] = body.granted
    return new HttpResponse(null, { status: 204 })
  }),
```

- [ ] **Step 10: Add the `/profile` route to `src/app/App.tsx`** (wrap in `ConsentProvider`, like `HomeRoute`)

```tsx
import { ProfilePage } from '../pages/ProfilePage'
```

```tsx
      <Route path="/profile" element={<ConsoleGate>{() => <ConsentProvider><ProfilePage /></ConsentProvider>}</ConsoleGate>} />
```

- [ ] **Step 11: Verify typecheck + touched suites**

Run: `npm run typecheck && npm run test -- src/api/consent.test.ts src/pages/ProfilePage.test.tsx`
Expected: clean + PASS.

- [ ] **Step 12: Commit**

```bash
git add src/schemas/consent.ts src/api/consent.ts src/api/consent.test.ts src/pages/ProfilePage.tsx src/pages/ProfilePage.test.tsx src/mocks/handlers.ts src/app/App.tsx
git commit -m "feat(fe): consent contract + consent-aware profile view + route"
```

---

### Task 7: Consent management page

**Files:**
- Create: `src/pages/ConsentManagementPage.tsx`, `src/pages/ConsentManagementPage.test.tsx`
- Modify: `src/app/App.tsx`

**Interfaces:**
- Consumes: `getConsents`, `setConsent` (Task 6), `CONSENT_CATEGORY_LABEL`, `AppShell`, `RoleSidebar`, `StateBlock`, `Spinner`, `Link`, react-query.
- Produces: `ConsentManagementPage` (route `/consent`). Granting/revoking invalidates both `['consents']` and `['profile']` so the profile view re-reads through the seam.

- [ ] **Step 1: Write the failing test** `src/pages/ConsentManagementPage.test.tsx`

```tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { ConsentManagementPage } from './ConsentManagementPage'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter><ConsentManagementPage /></MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

const CONSENTS = [
  { category: 'profile', granted: false },
  { category: 'contact', granted: true },
  { category: 'documents', granted: false },
]

test('renders a toggle per category reflecting granted state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: CONSENTS }))
  renderPage()
  const profileToggle = await screen.findByRole('checkbox', { name: /profile details/i })
  expect(profileToggle).not.toBeChecked()
  expect(screen.getByRole('checkbox', { name: /contact information/i })).toBeChecked()
})

test('toggling a category POSTs the new state and refetches', async () => {
  const spy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: CONSENTS }))                 // initial list
    .mockResolvedValueOnce(new Response(null, { status: 204 }))              // POST
    .mockResolvedValue(jsonResponse({ data: CONSENTS.map((c) => c.category === 'profile' ? { ...c, granted: true } : c) })) // refetch
  renderPage()
  fireEvent.click(await screen.findByRole('checkbox', { name: /profile details/i }))
  await waitFor(() => {
    const posted = spy.mock.calls.find(([, init]) => init?.method === 'POST')
    expect(posted).toBeTruthy()
    expect(JSON.parse(String(posted![1]?.body))).toEqual({ category: 'profile', granted: true })
  })
  await waitFor(() => expect(screen.getByRole('checkbox', { name: /profile details/i })).toBeChecked())
})
```

- [ ] **Step 2: Run it — expect FAIL**

Run: `npm run test -- src/pages/ConsentManagementPage.test.tsx`
Expected: FAIL (module missing).

- [ ] **Step 3: Write `src/pages/ConsentManagementPage.tsx`**

```tsx
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { StateBlock } from '../components/StateBlock'
import { Spinner } from '../components/Loading'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { getConsents, setConsent } from '../api/consent'
import { CONSENT_CATEGORY_LABEL, type ConsentCategory } from '../schemas/consent'

/**
 * Consent management: grant/revoke per data category. A change invalidates the
 * profile query too, so the consent-gated ProfilePage re-reads through the seam.
 */
export function ConsentManagementPage() {
  const queryClient = useQueryClient()
  const query = useQuery({ queryKey: ['consents'], queryFn: getConsents, retry: false })

  const mutation = useMutation({
    mutationFn: ({ category, granted }: { category: ConsentCategory; granted: boolean }) => setConsent(category, granted),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['consents'] }),
        queryClient.invalidateQueries({ queryKey: ['profile'] }),
      ])
    },
  })

  const entries = query.data ?? []

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="consent-heading" className="grid max-w-xl gap-6">
        <div>
          <h1 id="consent-heading" className="text-2xl font-semibold">Consent</h1>
          <p className="text-muted-foreground">Choose what this workspace may access. <Link href="/profile">View profile</Link></p>
        </div>

        {query.isLoading ? (
          <Spinner label="Loading consent settings…" />
        ) : query.isError ? (
          <StateBlock variant="error" message="We could not load your consent settings." action={<Button onClick={() => query.refetch()}>Try again</Button>} />
        ) : (
          <ul className="grid gap-3">
            {entries.map((e) => (
              <li key={e.category} className="flex items-center justify-between gap-3 rounded-md border border-border p-3">
                <label htmlFor={`consent-${e.category}`} className="font-medium">{CONSENT_CATEGORY_LABEL[e.category]}</label>
                <input
                  id={`consent-${e.category}`}
                  type="checkbox"
                  checked={e.granted}
                  disabled={mutation.isPending}
                  onChange={(ev) => mutation.mutate({ category: e.category, granted: ev.target.checked })}
                />
              </li>
            ))}
          </ul>
        )}
      </section>
    </AppShell>
  )
}
```

- [ ] **Step 4: Run the test — expect PASS**

Run: `npm run test -- src/pages/ConsentManagementPage.test.tsx`
Expected: PASS (2/2).

- [ ] **Step 5: Add the `/consent` route to `src/app/App.tsx`**

```tsx
import { ConsentManagementPage } from '../pages/ConsentManagementPage'
```

```tsx
      <Route path="/consent" element={<ConsoleGate>{() => <ConsentManagementPage />}</ConsoleGate>} />
```

- [ ] **Step 6: Commit**

```bash
git add src/pages/ConsentManagementPage.tsx src/pages/ConsentManagementPage.test.tsx src/app/App.tsx
git commit -m "feat(fe): consent management page + route"
```

---

### Task 8: Stories, full gates, and the 1b e2e

**Files:**
- Create: `src/pages/NotificationsPage.stories.tsx`, `src/pages/ProfilePage.stories.tsx`, `src/components/GlobalSearch.stories.tsx`, `e2e/personal-surfaces.spec.ts`
- Modify: none (verification + stories only)

**Interfaces:** none produced. This task proves the slice end-to-end and adds Storybook coverage for the new surfaces.

- [ ] **Step 1: Add Storybook stories for the three headline surfaces**

Follow the existing story pattern (read `src/pages/RegisterPage.stories.tsx` and `src/components/ActionCard.stories.tsx` for the exact CSF3 + decorator shape used here). Each story must provide the providers the component needs (`DirectionProvider`, `QueryClientProvider`, `MemoryRouter`) and, where the component fetches, a story-scoped fetch stub or MSW. Keep one representative state per story (e.g. NotificationsPage with a few items; ProfilePage granted; GlobalSearch with a typed query). Do **not** mutate any module-level singleton inside `render()` (the 1a `RoleSidebar.stories` leak finding — use a decorator if setup is needed).

- [ ] **Step 2: Run Storybook build to verify stories compile**

Run: `npm run build-storybook`
Expected: build succeeds.

- [ ] **Step 3: Write the 1b e2e** `e2e/personal-surfaces.spec.ts`

Follow the existing 1a e2e (`e2e/`*) for the MSW-backed harness setup (how the app is served with mocks enabled, the base URL, and any sign-in helper). The spec asserts the two spec-mandated flows:

```ts
import { test, expect } from '@playwright/test'

// Notifications: open the center, mark all read, unread count → 0.
test('notifications: mark all read clears the unread count', async ({ page }) => {
  await page.goto('/notifications')
  await expect(page.getByRole('heading', { name: 'Notifications' })).toBeVisible()
  await expect(page.getByText(/unread/i)).toBeVisible()
  await page.getByRole('button', { name: /mark all read/i }).click()
  await expect(page.getByText(/0 unread/i)).toBeVisible()
})

// Global search: typing a query surfaces a categorized result.
test('global search returns categorized results', async ({ page }) => {
  await page.goto('/')
  await page.getByRole('searchbox', { name: /search/i }).fill('fintech')
  await expect(page.getByText('FinTech Accelerator 2026')).toBeVisible()
})
```

> Match the real harness: if the 1a e2e signs in first or sets a mock flag, reuse that exact setup. If `/notifications` requires the console gate to pass, ensure the e2e reaches it the same way the 1a role-switch e2e reaches the Action Center.

- [ ] **Step 4: Run the e2e**

Run: `npm run test:e2e -- personal-surfaces` (or the project's e2e command — check `package.json` scripts)
Expected: 2/2 pass.

- [ ] **Step 5: Run the full gate suite**

Run from `frontend/`:

```bash
npm run typecheck && npm run lint && npm run test && npm run build && npm run build-storybook
```

Expected: typecheck clean, lint clean, full Vitest suite green (existing 212 + the new tests), production build succeeds, Storybook builds. The a11y + contrast suites are part of `npm run test` and must stay green.

- [ ] **Step 6: Commit**

```bash
git add src/pages/NotificationsPage.stories.tsx src/pages/ProfilePage.stories.tsx src/components/GlobalSearch.stories.tsx e2e/personal-surfaces.spec.ts
git commit -m "test(fe): stories + 1b personal-surfaces e2e; full gates green"
```

---

## Self-Review

**Spec coverage (`2026-06-27-fe-ui-slice1-identity-shell-design.md` §1b):**
- Profile (view) + Consent → Tasks 6 (ProfilePage, consent contract, MSW 403/200 flag) + 7 (ConsentManagementPage; grant re-fetches profile). ✅
- Notifications center + preferences → Tasks 1 (contract/client/MSW) + 2 (center: list, unread badge, mark single/all, filter by type, empty state) + 3 (preferences). ✅
- Global search → Tasks 4 (contract/client/MSW) + 5 (header type-ahead, debounced, categorized results, empty-query + no-results states). ✅
- Testing/quality gates (per-screen Vitest asserting roles/text; consent neutral-affordance test; one Playwright; typecheck/lint/vitest/playwright/build-storybook/a11y+contrast) → Tasks 2,3,5,6,7 tests + Task 8 e2e + gates. ✅

**Deliberate deviations (flag at final review):**
- Spec named a separate `SearchPage.tsx`; this plan delivers the results surface as the `GlobalSearch` header dropdown (one surface) and skips a `/search` route. YAGNI; add a full-page view when needed.
- Header notifications affordance is a plain bell `Link` with **no live unread badge** (the unread count lives on the NotificationsPage), to avoid re-coupling `AppShell` to an idle fetch (the 1a AppShell regression). The badge can move to the header in a later slice if desired.

**Placeholder scan:** none — every code step contains complete code; verification steps name exact commands and expected outcomes.

**Type consistency:** `Notification`/`NotificationType`, `SearchGroup`/`SearchCategory`/`SearchItem`, `ConsentEntry`/`ConsentCategory` are defined once in `src/schemas/*` and imported everywhere (clients, pages, MSW fixtures). MSW fixtures are typed against the schemas so any drift fails `npm run typecheck`. Profile reads exclusively through the existing `useConsent()` seam — no second profile-read call site is introduced.

**Carry-over follow-ups (not in this plan; raise at finish):** the 1a non-blockers — ContextSelector program/cohort switchers (spec-deferred scope delta), orphaned `HomePage` cleanup + migrate `a11y.test` → `ActionCenterPage`, `RoleSidebar.stories` singleton-in-render fix, and the minor test-hygiene batch.
