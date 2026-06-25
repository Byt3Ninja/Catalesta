# FE-2.5: Tenant Header Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the frontend send `X-Organization-Id` on every tenant-scoped API call so Home/Programs/Cohorts work against the live backend (and FE-3 is unblocked).

**Architecture:** A module-level active-organization holder set by the console gate; the header is injected by `csrfFetch` (mutations) and by a new `apiFetch` helper that the tenant-scoped reads (programs, cohorts, submissions) route through. Non-tenant calls are untouched.

**Tech Stack:** React 19, react-query, Vitest + Testing Library, the existing `csrfFetch`/`API_BASE_URL` API layer.

## Global Constraints

- Header name is exactly `X-Organization-Id` (matches `config('tenancy.header')`).
- Backend is NOT changed — the middleware is correct; the frontend simply wasn't sending the header.
- Only tenant-scoped calls are migrated: programs (index/show), cohorts (index/show), submissions (index/show/funnel), and all mutations via `csrfFetch`. Do NOT migrate session/`me`/organizations-index/auth/apply/health.
- The holder uses `orgs[0]` (matching today's gate) — no multi-org switcher.
- Module-level holder state leaks across Vitest tests in a file: reset it with `setActiveOrganizationId(null)` in `afterEach` of every touched suite.
- Commit trailer on every commit: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Run all npm commands from `frontend/`.

---

### Task 1: `api/tenant.ts` — active-org holder + `apiFetch`

**Files:**
- Create: `frontend/src/api/tenant.ts`
- Test: `frontend/src/api/tenant.test.ts`

**Interfaces:**
- Consumes: `API_BASE_URL` from `frontend/src/api/client.ts`.
- Produces:
  - `setActiveOrganizationId(id: string | null): void`
  - `getActiveOrganizationId(): string | null`
  - `tenantHeaders(): Record<string, string>` — `{ 'X-Organization-Id': id }` when set, else `{}`
  - `apiFetch(path: string, init?: RequestInit): Promise<Response>` — `fetch(API_BASE_URL+path, { ...init, credentials:'include', headers: { ...init.headers, ...tenantHeaders() } })`

- [ ] **Step 1: Write the failing tests** — create `frontend/src/api/tenant.test.ts`:

```ts
import { afterEach, expect, test, vi } from 'vitest'
import {
  apiFetch,
  getActiveOrganizationId,
  setActiveOrganizationId,
  tenantHeaders,
} from './tenant'

afterEach(() => {
  setActiveOrganizationId(null) // module state leaks across tests
  vi.restoreAllMocks()
})

test('set/get/clear the active organization id', () => {
  expect(getActiveOrganizationId()).toBeNull()
  setActiveOrganizationId('org-1')
  expect(getActiveOrganizationId()).toBe('org-1')
  setActiveOrganizationId(null)
  expect(getActiveOrganizationId()).toBeNull()
})

test('tenantHeaders carries X-Organization-Id only when an org is active', () => {
  expect(tenantHeaders()).toEqual({})
  setActiveOrganizationId('org-1')
  expect(tenantHeaders()).toEqual({ 'X-Organization-Id': 'org-1' })
})

test('apiFetch includes credentials and the tenant header when an org is active', async () => {
  setActiveOrganizationId('org-7')
  const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response('{}', { status: 200 }))

  await apiFetch('/programs')

  const [url, init] = fetchSpy.mock.calls[0]
  expect(String(url)).toContain('/programs')
  expect(init?.credentials).toBe('include')
  expect(new Headers(init?.headers).get('X-Organization-Id')).toBe('org-7')
})

test('apiFetch omits the tenant header when no org is active', async () => {
  const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response('{}', { status: 200 }))

  await apiFetch('/programs')

  const [, init] = fetchSpy.mock.calls[0]
  expect(new Headers(init?.headers).has('X-Organization-Id')).toBe(false)
})
```

- [ ] **Step 2: Run, verify failure**

Run: `cd frontend && npm run test -- src/api/tenant.test.ts`
Expected: FAIL — module `./tenant` does not exist.

- [ ] **Step 3: Create `frontend/src/api/tenant.ts`:**

```ts
import { API_BASE_URL } from './client'

/**
 * The active tenant organization for this page session. The console gate sets it
 * when it resolves the user's org; tenant-scoped API calls send it as the
 * X-Organization-Id header that ResolveTenant requires. Null before login /
 * during onboarding — those calls are not tenant-scoped.
 */
let activeOrganizationId: string | null = null

export function setActiveOrganizationId(id: string | null): void {
  activeOrganizationId = id
}

export function getActiveOrganizationId(): string | null {
  return activeOrganizationId
}

/** The tenant header, or an empty object when no org is active. */
export function tenantHeaders(): Record<string, string> {
  return activeOrganizationId !== null ? { 'X-Organization-Id': activeOrganizationId } : {}
}

/**
 * fetch for tenant-scoped reads: prepends the API base, sends cookies, and adds
 * X-Organization-Id when an org is active. Mutations use csrfFetch (which injects
 * the same header); this is the read-side equivalent.
 */
export function apiFetch(path: string, init: RequestInit = {}): Promise<Response> {
  return fetch(`${API_BASE_URL}${path}`, {
    ...init,
    credentials: 'include',
    headers: { ...init.headers, ...tenantHeaders() },
  })
}
```

- [ ] **Step 4: Run, verify pass**

Run: `cd frontend && npm run test -- src/api/tenant.test.ts`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta/.claude/worktrees/fe25-tenant-header
git add frontend/src/api/tenant.ts frontend/src/api/tenant.test.ts
git commit -m "feat(fe): FE-2.5 — active-org holder + apiFetch (X-Organization-Id)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Inject the header into mutations + tenant-scoped reads

**Files:**
- Modify: `frontend/src/api/csrf.ts` (add the header in the request builder)
- Modify: `frontend/src/api/programs.ts` (reads → `apiFetch`; drop now-unused `API_BASE_URL` import)
- Modify: `frontend/src/api/cohorts.ts` (reads → `apiFetch`; drop unused `API_BASE_URL`)
- Modify: `frontend/src/api/submissions.ts` (3 reads → `apiFetch`; drop unused `API_BASE_URL`)
- Test: `frontend/src/api/csrf.test.ts` (extend), `frontend/src/api/programs.test.ts` (extend)

**Interfaces:**
- Consumes: `getActiveOrganizationId`, `tenantHeaders`, `apiFetch` from Task 1.
- Produces: no new exports; existing functions keep their signatures.

- [ ] **Step 1: Write the failing tests.**

(a) Extend `frontend/src/api/csrf.test.ts` — add the import and an `afterEach` holder reset, then a header test:

```ts
import { setActiveOrganizationId } from './tenant'

// add to the existing afterEach body:
//   setActiveOrganizationId(null)

test('includes X-Organization-Id when an org is active, omits it otherwise', async () => {
  setCookie('XSRF-TOKEN=already')
  const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ ok: true }))

  await csrfFetch('/programs', { method: 'POST', body: '{}' })
  expect(new Headers(fetchMock.mock.calls[0][1]?.headers).has('X-Organization-Id')).toBe(false)

  setActiveOrganizationId('org-9')
  await csrfFetch('/programs', { method: 'POST', body: '{}' })
  expect(new Headers(fetchMock.mock.calls[1][1]?.headers).get('X-Organization-Id')).toBe('org-9')
})
```

> If the existing `afterEach` in `csrf.test.ts` does not already reset the holder, add `setActiveOrganizationId(null)` to it so the active org never leaks into the other csrf tests.

(b) Extend `frontend/src/api/programs.test.ts` — prove a read carries the header. Add the import + an `afterEach` reset + the test:

```ts
import { setActiveOrganizationId } from './tenant'

// add to the existing afterEach body:
//   setActiveOrganizationId(null)

test('listPrograms sends X-Organization-Id when an org is active', async () => {
  setActiveOrganizationId('org-3')
  const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [] }))

  await listPrograms()

  expect(new Headers(fetchSpy.mock.calls[0][1]?.headers).get('X-Organization-Id')).toBe('org-3')
})
```

- [ ] **Step 2: Run, verify failure**

Run: `cd frontend && npm run test -- src/api/csrf.test.ts src/api/programs.test.ts`
Expected: FAIL — `csrfFetch` doesn't add the header yet; `listPrograms` still uses bare `fetch` (no header).

- [ ] **Step 3: Inject the header in `csrf.ts`.** Add the import and set the header in the existing header block (right after the `X-XSRF-TOKEN` block, before the final `return fetch(...)`):

```ts
// at the top, alongside the other imports:
import { getActiveOrganizationId } from './tenant'

// inside csrfFetch, after `if (token !== undefined) { headers.set('X-XSRF-TOKEN', ...) }`:
  const orgId = getActiveOrganizationId()
  if (orgId !== null) {
    headers.set('X-Organization-Id', orgId)
  }
```

- [ ] **Step 4: Migrate the tenant-scoped reads to `apiFetch`.**

In `frontend/src/api/programs.ts`: change the import line `import { API_BASE_URL } from './client'` to `import { apiFetch } from './tenant'`, then:
- `listPrograms`: replace `await fetch(\`${API_BASE_URL}/programs\`, { credentials: 'include' })` with `await apiFetch('/programs')`.
- `getProgram`: replace `await fetch(\`${API_BASE_URL}/programs/${id}\`, { credentials: 'include' })` with `await apiFetch(\`/programs/${id}\`)`.

In `frontend/src/api/cohorts.ts`: change `import { API_BASE_URL } from './client'` to `import { apiFetch } from './tenant'`, then:
- `listCohorts`: `await apiFetch('/cohorts')`.
- `getCohort`: `await apiFetch(\`/cohorts/${id}\`)`.

In `frontend/src/api/submissions.ts`: change `import { API_BASE_URL } from './client'` to `import { apiFetch } from './tenant'`, then:
- `listSubmissions`: `await apiFetch(\`/cohorts/${encodeURIComponent(cohortId)}/submissions\`)`.
- `getSubmission`: `await apiFetch(\`/cohorts/${encodeURIComponent(cohortId)}/submissions/${encodeURIComponent(submissionId)}\`)`.
- `getFunnel`: `await apiFetch(\`/cohorts/${encodeURIComponent(cohortId)}/funnel\`)`.

> `apiFetch` already sets `credentials: 'include'`, so drop the `{ credentials: 'include' }` arg from these calls. After editing, confirm no `API_BASE_URL` references remain in these three files: `cd frontend && grep -n "API_BASE_URL" src/api/programs.ts src/api/cohorts.ts src/api/submissions.ts` → no matches (otherwise the now-unused import will fail lint).

- [ ] **Step 5: Run the targeted tests, then the full suite + typecheck + lint**

Run: `cd frontend && npm run test -- src/api/csrf.test.ts src/api/programs.test.ts && npm run typecheck && npm run lint && npm run test`
Expected: the two suites pass; typecheck/lint clean (no unused `API_BASE_URL`); whole suite green (existing program/cohort/submission tests don't assert header absence, so they stay green).

- [ ] **Step 6: Commit**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta/.claude/worktrees/fe25-tenant-header
git add frontend/src/api/csrf.ts frontend/src/api/programs.ts frontend/src/api/cohorts.ts frontend/src/api/submissions.ts frontend/src/api/csrf.test.ts frontend/src/api/programs.test.ts
git commit -m "feat(fe): FE-2.5 — send X-Organization-Id on tenant-scoped reads + mutations

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Console gate publishes the active organization

**Files:**
- Modify: `frontend/src/app/App.tsx` (`ConsoleGate` — set the holder via `useEffect`)
- Test: `frontend/src/app/App.test.tsx` (extend)

**Interfaces:**
- Consumes: `setActiveOrganizationId`, `getActiveOrganizationId` from Task 1.
- Produces: no new exports.

- [ ] **Step 1: Write the failing test** — append to `frontend/src/app/App.test.tsx` (the `renderRoute`/`render(<App/>)` helpers, `USER`, `ORG`, `jsonResponse` already exist; add the import and an `afterEach` reset):

```ts
import { getActiveOrganizationId, setActiveOrganizationId } from '../api/tenant'

// add to the existing afterEach body:
//   setActiveOrganizationId(null)

test('the gate publishes the resolved org id to the tenant holder', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations

  render(<App />)

  expect(await screen.findByRole('heading', { name: 'Acme Incubator' })).toBeInTheDocument()
  expect(getActiveOrganizationId()).toBe(ORG.id)
})

test('an unauthenticated gate leaves the tenant holder null', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 401 }))

  render(<App />)

  expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
  expect(getActiveOrganizationId()).toBeNull()
})
```

- [ ] **Step 2: Run, verify failure**

Run: `cd frontend && npm run test -- src/app/App.test.tsx`
Expected: FAIL — the gate doesn't set the holder yet (`getActiveOrganizationId()` stays null).

- [ ] **Step 3: Publish the org from `ConsoleGate`.** In `frontend/src/app/App.tsx`:

(a) Add to the React import: `import { useEffect, type ReactNode } from 'react'` (keep `ReactNode` if already imported — just add `useEffect`).

(b) Add the holder import alongside the other api imports:

```tsx
import { setActiveOrganizationId } from '../api/tenant'
```

(c) Inside `ConsoleGate`, immediately after the two `useQuery` calls (the `sessionQuery` and `orgsQuery` declarations) and BEFORE the first `if (sessionQuery.isLoading)` return, add:

```tsx
  // Publish the resolved tenant org so tenant-scoped API calls can send
  // X-Organization-Id (ResolveTenant requires it). Null until an org resolves.
  const resolvedOrgId =
    orgsQuery.isSuccess && (orgsQuery.data?.length ?? 0) > 0 ? orgsQuery.data![0].id : null
  useEffect(() => {
    setActiveOrganizationId(resolvedOrgId)
  }, [resolvedOrgId])
```

This keeps hook order stable (it runs before any conditional `return`).

- [ ] **Step 4: Run, verify pass + full gates**

Run: `cd frontend && npm run test -- src/app/App.test.tsx && npm run typecheck && npm run lint && npm run test && npm run build`
Expected: the App tests pass; typecheck/lint clean; whole suite green; build succeeds.

- [ ] **Step 5: Commit**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta/.claude/worktrees/fe25-tenant-header
git add frontend/src/app/App.tsx frontend/src/app/App.test.tsx
git commit -m "feat(fe): FE-2.5 — console gate publishes active org to the tenant holder

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Post-implementation: live verification (controller, after merge)

Not a unit test — the reason this slice exists. With the stack running and the
seeded login `alice@catalesta.test` / `Password123!` at http://localhost:3000:
1. Sign in → operator Home renders for "Acme Incubator" with **no** "could not
   load cohorts" error (Home's `GET /cohorts` now carries the header).
2. Open `/programs` → the programs list loads (not a 400 error state).
3. Create a program, open it, add a cohort → the mutations succeed.
Capture the browser network tab showing `X-Organization-Id` on a `/cohorts`
request and a 200 response. Report as evidence.

## Self-Review

**1. Spec coverage:**
- Active-org holder (`set`/`get`/`tenantHeaders`) + `apiFetch` → Task 1. ✓
- `csrfFetch` injects the header → Task 2 Step 3. ✓
- Tenant-scoped reads (programs, cohorts, submissions incl. funnel) → Task 2 Step 4. ✓
- Non-tenant calls untouched → only the named reads/mutations are changed. ✓
- Gate publishes `orgs[0].id` via `useEffect` → Task 3. ✓
- Holder reset in `afterEach` of touched suites → Tasks 1/2/3 tests. ✓
- Live verification → Post-implementation section. ✓
- No backend change → nothing under `backend/` is touched. ✓

**2. Placeholder scan:** No TBD/"handle errors"/"similar to Task N"; every code step is complete. ✓

**3. Type consistency:** `setActiveOrganizationId(string|null)`, `getActiveOrganizationId(): string|null`, `tenantHeaders()`, `apiFetch(path, init?)` are used identically across Task 1 (definition), Task 2 (csrf + reads), and Task 3 (gate) and the tests. The header string `'X-Organization-Id'` is identical everywhere. ✓
