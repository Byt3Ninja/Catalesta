# FE-2: Cohorts Management UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make cohorts manageable within their program — list (per-program), create (draft), view, and edit metadata — using only the cleanly-backed endpoints; no open/close.

**Architecture:** `ProgramDetailPage` (FE-1) gains a `ProgramCohortsSection` (filters the tenant `['cohorts']` list by `program_id`, plus a name-only create form). A new gated `/cohorts/:cohortId` route renders `CohortDetailPage` (metadata view + inline edit with native `date`/`number` inputs; link to its existing submissions). Three new typed API client calls back it.

**Tech Stack:** React 19, react-router-dom v7, @tanstack/react-query, Zod, Vitest + Testing Library.

## Global Constraints

- Phase-1a: only flows with a real backend endpoint. Maps to `GET /cohorts/{id}`, `POST /programs/{programId}/cohorts`, `PATCH /cohorts/{id}`.
- **No open/close, no status transition, no form-version binding** (backend `OpenCohort`/`CloseCohort` not wired). A short muted note says opening isn't available yet.
- Editable cohort metadata only: `name`, `capacity` (number|null), `enrollment_opens_at`, `enrollment_closes_at`, `starts_at`, `ends_at` (date strings|null). Not `status`, not `timeline`.
- Pages keep their own `AppShell` — no nav shell / `ConsoleLayout`.
- Frontend visibility is never authorization: actions render and degrade on `403`; no client-side abilities feed.
- Use existing design-system components only (`AppShell`, `Field`, `Button`, `Banner`, `FormLayout`, `StateBlock`, `Link`, `Spinner`). Native `<input type="date">`/`type="number"` via `Field` (no picker lib).
- Cohorts are reached via their program (and Home); no standalone `/cohorts` list in FE-2.
- Commit trailer on every commit: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Run npm from `frontend/`.

---

### Task 1: API client + schemas — `getCohort`, `createCohort`, `updateCohort`

**Files:**
- Modify: `frontend/src/schemas/cohorts.ts` (add `cohortResponseSchema` + three error classes; import `ApiError`)
- Modify: `frontend/src/api/cohorts.ts` (add three functions + imports)
- Test: `frontend/src/api/cohorts.test.ts` (append tests + a cookie `beforeEach`)

**Interfaces:**
- Consumes: existing `cohortSchema`, `cohortListResponseSchema`, `API_BASE_URL`; `ApiError`, `firstValidationMessage`, `readValidationDetails`, `csrfFetch`.
- Produces:
  - `getCohort(id: string): Promise<Cohort>`
  - `createCohort(programId: string, input: { name: string }): Promise<Cohort>`
  - `updateCohort(id: string, input: { name?: string; capacity?: number | null; enrollment_opens_at?: string | null; enrollment_closes_at?: string | null; starts_at?: string | null; ends_at?: string | null }): Promise<Cohort>`
  - Errors `GetCohortError` (`NOT_FOUND|UNAUTHENTICATED|UNKNOWN`), `CreateCohortError` & `UpdateCohortError` (`VALIDATION|FORBIDDEN|NOT_FOUND|UNAUTHENTICATED|UNKNOWN`).
  - `cohortResponseSchema` (`{ data: cohortSchema }`).

- [ ] **Step 1: Write failing tests** — edit `frontend/src/api/cohorts.test.ts`. Widen the import, add a cookie `beforeEach`, and append the tests:

```ts
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { createCohort, getCohort, listCohorts, updateCohort } from './cohorts'
import { jsonResponse } from '../tests/test-utils'

// (keep the existing COHORT constant and the existing listCohorts tests)

// create/update route through csrfFetch — pre-seed the XSRF cookie so the
// preflight is skipped and a single fetch mock stays aligned.
beforeEach(() => {
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})

test('getCohort returns the cohort on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  await expect(getCohort('01J0COH')).resolves.toMatchObject({ slug: 'spring-2026' })
})

test('getCohort maps 404 → NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(getCohort('missing')).rejects.toMatchObject({
    name: 'GetCohortError',
    code: 'NOT_FOUND',
  })
})

test('getCohort maps 401 → UNAUTHENTICATED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(getCohort('01J0COH')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})

test('createCohort POSTs under the program and returns the draft on 201', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, status: 'draft' } }, 201))
  await expect(createCohort('01J0PROG', { name: 'Spring 2026' })).resolves.toMatchObject({
    status: 'draft',
  })
  const [url, init] = fetchSpy.mock.calls[0]
  expect(String(url)).toContain('/programs/01J0PROG/cohorts')
  expect(init?.method).toBe('POST')
  expect(JSON.parse((init?.body as string) ?? '{}')).toEqual({ name: 'Spring 2026' })
})

test('createCohort maps 422 → VALIDATION with the first field message', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
      422,
    ),
  )
  await expect(createCohort('01J0PROG', { name: '' })).rejects.toMatchObject({
    name: 'CreateCohortError',
    code: 'VALIDATION',
    message: 'The name field is required.',
  })
})

test('createCohort maps 403 → FORBIDDEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 403 }))
  await expect(createCohort('01J0PROG', { name: 'x' })).rejects.toMatchObject({ code: 'FORBIDDEN' })
})

test('updateCohort PATCHes the metadata and returns the cohort on 200', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, capacity: 50 } }))
  await expect(
    updateCohort('01J0COH', { name: 'Spring 2026', capacity: 50, enrollment_opens_at: '2026-07-01' }),
  ).resolves.toMatchObject({ capacity: 50 })
  const init = fetchSpy.mock.calls[0][1]
  expect(init?.method).toBe('PATCH')
  expect(JSON.parse((init?.body as string) ?? '{}')).toEqual({
    name: 'Spring 2026',
    capacity: 50,
    enrollment_opens_at: '2026-07-01',
  })
})

test('updateCohort maps 422 → VALIDATION', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { ends_at: ['The ends at must be a date after starts at.'] } } },
      422,
    ),
  )
  await expect(updateCohort('01J0COH', { ends_at: '2020-01-01' })).rejects.toMatchObject({
    name: 'UpdateCohortError',
    code: 'VALIDATION',
  })
})
```

- [ ] **Step 2: Run, verify failure**

Run: `cd frontend && npm run test -- src/api/cohorts.test.ts`
Expected: FAIL — `getCohort`/`createCohort`/`updateCohort` not exported.

- [ ] **Step 3: Add schema response + errors** to `frontend/src/schemas/cohorts.ts`. Add the import at the top and append after the existing exports:

```ts
import { ApiError } from '../api/errors'

export const cohortResponseSchema = z.object({
  data: cohortSchema,
})

/** Typed get-cohort error the CohortDetailPage renders. */
export type GetCohortErrorCode = 'NOT_FOUND' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class GetCohortError extends ApiError<GetCohortErrorCode> {
  constructor(code: GetCohortErrorCode, message?: string) {
    super(code, message)
    this.name = 'GetCohortError'
  }
}

/** Typed create-cohort error. */
export type CreateCohortErrorCode =
  | 'VALIDATION'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'UNAUTHENTICATED'
  | 'UNKNOWN'

export class CreateCohortError extends ApiError<CreateCohortErrorCode> {
  constructor(code: CreateCohortErrorCode, message?: string) {
    super(code, message)
    this.name = 'CreateCohortError'
  }
}

/** Typed update-cohort error. */
export type UpdateCohortErrorCode =
  | 'VALIDATION'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'UNAUTHENTICATED'
  | 'UNKNOWN'

export class UpdateCohortError extends ApiError<UpdateCohortErrorCode> {
  constructor(code: UpdateCohortErrorCode, message?: string) {
    super(code, message)
    this.name = 'UpdateCohortError'
  }
}
```

- [ ] **Step 4: Add the client functions** to `frontend/src/api/cohorts.ts`. Replace the import block and append the functions (keep the existing `listCohorts`):

```ts
import { API_BASE_URL } from './client'
import { csrfFetch } from './csrf'
import { firstValidationMessage, readValidationDetails } from './errors'
import {
  CreateCohortError,
  GetCohortError,
  UpdateCohortError,
  cohortListResponseSchema,
  cohortResponseSchema,
  type Cohort,
} from '../schemas/cohorts'

// ... existing listCohorts stays unchanged ...

/**
 * GET /cohorts/{id} (auth:sanctum + tenant). 404 → the cohort is gone/foreign.
 * [Source: backend CohortController::show]
 */
export async function getCohort(id: string): Promise<Cohort> {
  const response = await fetch(`${API_BASE_URL}/cohorts/${id}`, {
    credentials: 'include',
  })
  if (response.status === 200) {
    const json: unknown = await response.json()
    return cohortResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new GetCohortError('UNAUTHENTICATED')
  }
  if (response.status === 404) {
    throw new GetCohortError('NOT_FOUND')
  }
  throw new GetCohortError('UNKNOWN', `get cohort failed: ${response.status}`)
}

/**
 * POST /programs/{programId}/cohorts (auth:sanctum + tenant). Creates a DRAFT
 * cohort under the program. A foreign/missing program → 403.
 * [Source: backend CohortController::store]
 */
export async function createCohort(programId: string, input: { name: string }): Promise<Cohort> {
  const response = await csrfFetch(`/programs/${programId}/cohorts`, {
    method: 'POST',
    body: JSON.stringify(input),
  })

  if (response.status === 201) {
    const json: unknown = await response.json()
    return cohortResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new CreateCohortError('UNAUTHENTICATED')
  }
  if (response.status === 403) {
    throw new CreateCohortError('FORBIDDEN')
  }
  if (response.status === 404) {
    throw new CreateCohortError('NOT_FOUND')
  }
  if (response.status === 422) {
    const message = firstValidationMessage(await readValidationDetails(response))
    throw new CreateCohortError('VALIDATION', message ?? 'Please check the name and try again.')
  }
  throw new CreateCohortError('UNKNOWN', `create cohort failed: ${response.status}`)
}

/**
 * PATCH /cohorts/{id} (auth:sanctum + tenant). Edits metadata only (name,
 * capacity, the enrollment/start/end dates). Backend enforces the date ordering
 * chain. [Source: backend CohortController::update]
 */
export async function updateCohort(
  id: string,
  input: {
    name?: string
    capacity?: number | null
    enrollment_opens_at?: string | null
    enrollment_closes_at?: string | null
    starts_at?: string | null
    ends_at?: string | null
  },
): Promise<Cohort> {
  const response = await csrfFetch(`/cohorts/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(input),
  })

  if (response.status === 200) {
    const json: unknown = await response.json()
    return cohortResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new UpdateCohortError('UNAUTHENTICATED')
  }
  if (response.status === 403) {
    throw new UpdateCohortError('FORBIDDEN')
  }
  if (response.status === 404) {
    throw new UpdateCohortError('NOT_FOUND')
  }
  if (response.status === 422) {
    const message = firstValidationMessage(await readValidationDetails(response))
    throw new UpdateCohortError('VALIDATION', message ?? 'Please check your entries and try again.')
  }
  throw new UpdateCohortError('UNKNOWN', `update cohort failed: ${response.status}`)
}
```

- [ ] **Step 5: Run tests + typecheck + lint**

Run: `cd frontend && npm run test -- src/api/cohorts.test.ts && npm run typecheck && npm run lint`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta/.claude/worktrees/fe2-cohorts-management
git add frontend/src/schemas/cohorts.ts frontend/src/api/cohorts.ts frontend/src/api/cohorts.test.ts
git commit -m "feat(fe): FE-2 — getCohort/createCohort/updateCohort API client + typed errors

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: CohortDetailPage + route

**Files:**
- Create: `frontend/src/pages/CohortDetailPage.tsx`
- Create: `frontend/src/pages/CohortDetailPage.test.tsx`
- Modify: `frontend/src/app/App.tsx` (import + `CohortDetailRoute` wrapper + one `<Route>`)
- Modify: `frontend/src/app/App.test.tsx` (one route-coverage test)

**Interfaces:**
- Consumes: `getCohort`, `updateCohort`, `GetCohortError`, `UpdateCohortError`, `type Cohort` (Task 1); design-system components; existing `ConsoleGate` + `useParams` in `App.tsx`.
- Produces: `export function CohortDetailPage({ cohortId }: { cohortId: string })` and route `/cohorts/:cohortId`.

- [ ] **Step 1: Write the failing page tests** — create `frontend/src/pages/CohortDetailPage.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { CohortDetailPage } from './CohortDetailPage'
import { jsonResponse } from '../tests/test-utils'

const COHORT = {
  id: '01J0COH',
  organization_id: '01J0ORG',
  program_id: '01J0PROG',
  name: 'Spring 2026',
  slug: 'spring-2026',
  status: 'draft',
  capacity: null,
  enrollment_opens_at: null,
  enrollment_closes_at: null,
  starts_at: null,
  ends_at: null,
  timeline: null,
  submissions_count: 0,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

function renderDetail(cohortId = '01J0COH'): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <CohortDetailPage cohortId={cohortId} />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})
afterEach(() => vi.restoreAllMocks())

test('renders the cohort name, status and a submissions link', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  renderDetail()
  expect(await screen.findByRole('heading', { name: 'Spring 2026' })).toBeInTheDocument()
  expect(screen.getByText('Draft')).toBeInTheDocument()
  expect(screen.getByRole('link', { name: /view submissions/i })).toHaveAttribute(
    'href',
    '/cohorts/01J0COH/submissions',
  )
})

test('a 404 shows the "no longer exists" state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  renderDetail('missing')
  expect(await screen.findByText(/that cohort no longer exists/i)).toBeInTheDocument()
})

test('edit → save sends the changed metadata and updates the view', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT })) // initial load
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, capacity: 50 } })) // PATCH
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, capacity: 50 } })) // refetch
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Capacity'), { target: { value: '50' } })
  fireEvent.change(screen.getByLabelText('Enrollment opens'), { target: { value: '2026-07-01' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  // On success the editor closes and the view (with its Edit button) returns.
  expect(await screen.findByRole('button', { name: 'Edit' })).toBeInTheDocument()
  // The PATCH carried the edited capacity (number) and date.
  const patchInit = fetchSpy.mock.calls.find((c) => c[1]?.method === 'PATCH')?.[1]
  expect(patchInit).toBeDefined()
  const body = JSON.parse((patchInit?.body as string) ?? '{}')
  expect(body.capacity).toBe(50)
  expect(body.enrollment_opens_at).toBe('2026-07-01')
})

test('edit → 422 shows the validation message and stays in edit mode', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT })) // initial load
    .mockResolvedValueOnce(
      jsonResponse(
        { error: { code: 'VALIDATION_ERROR', details: { ends_at: ['The ends at must be a date after starts at.'] } } },
        422,
      ),
    ) // PATCH 422
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByText(/must be a date after/i)).toBeInTheDocument()
  expect(screen.getByLabelText('Cohort name')).toBeInTheDocument() // still editing
})
```

- [ ] **Step 2: Run, verify failure**

Run: `cd frontend && npm run test -- src/pages/CohortDetailPage.test.tsx`
Expected: FAIL — module doesn't exist.

- [ ] **Step 3: Create the page** — `frontend/src/pages/CohortDetailPage.tsx`:

```tsx
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getCohort, updateCohort } from '../api/cohorts'
import { GetCohortError, UpdateCohortError, type Cohort } from '../schemas/cohorts'

/** Human-readable cohort status (text, never colour-alone). */
const STATUS_LABEL: Record<Cohort['status'], string> = {
  draft: 'Draft',
  open: 'Open',
  closed: 'Closed',
  completed: 'Completed',
}

/** Native <input type="date"> wants YYYY-MM-DD; the API returns ISO or a date. */
function toDateInput(value: string | null): string {
  return value ? value.slice(0, 10) : ''
}

/**
 * Cohort detail (FE-2). Shows one cohort's metadata and an inline editor for
 * name/capacity/dates. No open/close/status control — opening a cohort for
 * applications (form binding + entitlement + audit) is not wired in the backend
 * yet. A console surface → AppShell.
 */
export function CohortDetailPage({ cohortId }: { cohortId: string }) {
  const queryClient = useQueryClient()
  const cohortQuery = useQuery({
    queryKey: ['cohort', cohortId],
    queryFn: () => getCohort(cohortId),
    retry: false,
  })

  const [editing, setEditing] = useState(false)
  const [name, setName] = useState('')
  const [capacity, setCapacity] = useState('')
  const [opensAt, setOpensAt] = useState('')
  const [closesAt, setClosesAt] = useState('')
  const [startsAt, setStartsAt] = useState('')
  const [endsAt, setEndsAt] = useState('')

  const updateMutation = useMutation({
    mutationFn: () =>
      updateCohort(cohortId, {
        name: name.trim(),
        capacity: capacity.trim() === '' ? null : Number(capacity),
        enrollment_opens_at: opensAt || null,
        enrollment_closes_at: closesAt || null,
        starts_at: startsAt || null,
        ends_at: endsAt || null,
      }),
    onSuccess: async () => {
      setEditing(false)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['cohort', cohortId] }),
        queryClient.invalidateQueries({ queryKey: ['cohorts'] }),
      ])
    },
  })

  const cohort = cohortQuery.data

  const beginEdit = (c: Cohort) => {
    setName(c.name)
    setCapacity(c.capacity != null ? String(c.capacity) : '')
    setOpensAt(toDateInput(c.enrollment_opens_at))
    setClosesAt(toDateInput(c.enrollment_closes_at))
    setStartsAt(toDateInput(c.starts_at))
    setEndsAt(toDateInput(c.ends_at))
    setEditing(true)
  }

  return (
    <AppShell
      rail={
        <nav aria-label="Sections">
          <Link href="/programs">Programs</Link>
        </nav>
      }
    >
      <section aria-labelledby="cohort-heading">
        {cohortQuery.isLoading ? (
          <Spinner label="Loading cohort…" />
        ) : cohortQuery.isError ? (
          renderLoadError(cohortQuery.error, () => cohortQuery.refetch())
        ) : cohort ? (
          <>
            <p>
              <Link href={`/programs/${cohort.program_id}`}>← Program</Link>
            </p>
            <h1 id="cohort-heading">
              <bdi>{cohort.name}</bdi>
            </h1>
            <p>
              <span className="ds-badge" data-status={cohort.status}>
                {STATUS_LABEL[cohort.status]}
              </span>{' '}
              <span className="ds-muted">{cohort.slug}</span>
            </p>

            {renderUpdateError(updateMutation.error)}

            {editing ? (
              <form
                noValidate
                onSubmit={(event) => {
                  event.preventDefault()
                  if (name.trim().length > 0) updateMutation.mutate()
                }}
              >
                <FormLayout>
                  <Field label="Cohort name" name="cohort-name" required value={name} onChange={(e) => setName(e.target.value)} />
                  <Field label="Capacity" name="cohort-capacity" type="number" min={1} help="Optional." value={capacity} onChange={(e) => setCapacity(e.target.value)} />
                  <Field label="Enrollment opens" name="cohort-opens" type="date" value={opensAt} onChange={(e) => setOpensAt(e.target.value)} />
                  <Field label="Enrollment closes" name="cohort-closes" type="date" value={closesAt} onChange={(e) => setClosesAt(e.target.value)} />
                  <Field label="Starts" name="cohort-starts" type="date" value={startsAt} onChange={(e) => setStartsAt(e.target.value)} />
                  <Field label="Ends" name="cohort-ends" type="date" value={endsAt} onChange={(e) => setEndsAt(e.target.value)} />
                </FormLayout>
                <Button type="submit" loading={updateMutation.isPending} disabled={name.trim().length === 0}>
                  Save
                </Button>{' '}
                <Button variant="secondary" onClick={() => setEditing(false)}>
                  Cancel
                </Button>
              </form>
            ) : (
              <>
                <p className="ds-muted">
                  Capacity: {cohort.capacity != null ? cohort.capacity : 'No cap'} · Opens:{' '}
                  {cohort.enrollment_opens_at ?? '—'} · Closes: {cohort.enrollment_closes_at ?? '—'} ·
                  Starts: {cohort.starts_at ?? '—'} · Ends: {cohort.ends_at ?? '—'}
                </p>
                <p>
                  Submissions: {cohort.submissions_count ?? 0} —{' '}
                  <Link href={`/cohorts/${cohort.id}/submissions`}>View submissions</Link>
                </p>
                <Button variant="secondary" onClick={() => beginEdit(cohort)}>
                  Edit
                </Button>
                <p className="ds-muted">Opening a cohort for applications isn’t available yet.</p>
              </>
            )}
          </>
        ) : null}
      </section>
    </AppShell>
  )
}

function renderLoadError(error: unknown, retry: () => void) {
  if (error instanceof GetCohortError && error.code === 'NOT_FOUND') {
    return (
      <StateBlock
        variant="error"
        message="That cohort no longer exists."
        action={<Link href="/programs">Back to Programs</Link>}
      />
    )
  }
  return (
    <StateBlock
      variant="error"
      message="We could not load this cohort."
      action={<Button onClick={retry}>Try again</Button>}
    />
  )
}

function renderUpdateError(error: unknown) {
  if (!(error instanceof UpdateCohortError)) {
    return error ? <Banner variant="error">Something went wrong. Please try again.</Banner> : null
  }
  switch (error.code) {
    case 'FORBIDDEN':
      return <Banner variant="error">You do not have permission to perform that action.</Banner>
    case 'NOT_FOUND':
      return <Banner variant="error">That cohort no longer exists.</Banner>
    case 'UNAUTHENTICATED':
      return <Banner variant="error">Your session expired. Please sign in again.</Banner>
    case 'VALIDATION':
      return <Banner variant="error">{error.message}</Banner>
    default:
      return <Banner variant="error">Something went wrong. Please try again.</Banner>
  }
}
```

- [ ] **Step 4: Run page tests, verify pass**

Run: `cd frontend && npm run test -- src/pages/CohortDetailPage.test.tsx`
Expected: PASS (4 tests).

- [ ] **Step 5: Wire the route** in `frontend/src/app/App.tsx`. Add the import alongside the page imports:

```tsx
import { CohortDetailPage } from '../pages/CohortDetailPage'
```

Add the wrapper near the other route wrappers:

```tsx
function CohortDetailRoute() {
  const { cohortId } = useParams()
  return <ConsoleGate>{() => <CohortDetailPage cohortId={cohortId!} />}</ConsoleGate>
}
```

Add the route inside `<Routes>`, right before the existing `/cohorts/:cohortId/submissions` route:

```tsx
      <Route path="/cohorts/:cohortId" element={<CohortDetailRoute />} />
```

- [ ] **Step 6: Add route coverage** — append to `frontend/src/app/App.test.tsx`:

```tsx
test('route /cohorts/:cohortId renders the cohort detail for an org user', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations
    .mockResolvedValueOnce(
      jsonResponse({
        data: {
          id: '01J0COH',
          organization_id: '01J0ORG',
          program_id: '01J0PROG',
          name: 'Spring 2026',
          slug: 'spring-2026',
          status: 'draft',
          capacity: null,
          enrollment_opens_at: null,
          enrollment_closes_at: null,
          starts_at: null,
          ends_at: null,
          timeline: null,
          submissions_count: 0,
          created_at: '2026-06-20T10:00:00+00:00',
          updated_at: '2026-06-20T10:00:00+00:00',
        },
      }),
    ) // getCohort

  renderRoute('/cohorts/01J0COH')

  expect(await screen.findByRole('heading', { name: 'Spring 2026' })).toBeInTheDocument()
})
```

- [ ] **Step 7: Full gates**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta/.claude/worktrees/fe2-cohorts-management
git add frontend/src/pages/CohortDetailPage.tsx frontend/src/pages/CohortDetailPage.test.tsx frontend/src/app/App.tsx frontend/src/app/App.test.tsx
git commit -m "feat(fe): FE-2 — CohortDetailPage (metadata view + inline edit) + route

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: ProgramCohortsSection + integrate into ProgramDetailPage

**Files:**
- Create: `frontend/src/pages/ProgramCohortsSection.tsx`
- Create: `frontend/src/pages/ProgramCohortsSection.test.tsx`
- Modify: `frontend/src/pages/ProgramDetailPage.tsx` (import + render the section)
- Modify: `frontend/src/pages/ProgramDetailPage.test.tsx` (switch to a URL/method-aware fetch mock so the new `['cohorts']` mount-query doesn't desync the existing sequential mocks)

**Interfaces:**
- Consumes: `listCohorts` (existing), `createCohort`, `CreateCohortError`, `type Cohort` (Task 1); `/cohorts/:cohortId` route (Task 2); design-system components.
- Produces: `export function ProgramCohortsSection({ programId }: { programId: string })` (terminal task).

- [ ] **Step 1: Write the section's failing tests** — create `frontend/src/pages/ProgramCohortsSection.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ProgramCohortsSection } from './ProgramCohortsSection'
import { jsonResponse } from '../tests/test-utils'

const base = {
  organization_id: '01J0ORG',
  slug: 's',
  status: 'draft' as const,
  capacity: null,
  enrollment_opens_at: null,
  enrollment_closes_at: null,
  starts_at: null,
  ends_at: null,
  timeline: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}
const MINE = { ...base, id: '01J0COH', program_id: '01J0PROG', name: 'Spring 2026' }
const OTHER = { ...base, id: '01J0OTH', program_id: '01J0XXX', name: 'Other Program Cohort' }

function renderSection(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ProgramCohortsSection programId="01J0PROG" />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})
afterEach(() => vi.restoreAllMocks())

test('lists only this program’s cohorts, each linking to its detail', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [MINE, OTHER] }))
  renderSection()
  const link = await screen.findByRole('link', { name: /spring 2026/i })
  expect(link).toHaveAttribute('href', '/cohorts/01J0COH')
  expect(screen.queryByText(/other program cohort/i)).not.toBeInTheDocument()
})

test('empty program shows the create-first empty state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [OTHER] }))
  renderSection()
  expect(await screen.findByText(/no cohorts yet/i)).toBeInTheDocument()
})

test('create issues the per-program POST with the entered name', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // initial list
    .mockResolvedValueOnce(jsonResponse({ data: MINE }, 201)) // create
    .mockResolvedValueOnce(jsonResponse({ data: [MINE] })) // list refetch
  renderSection()

  fireEvent.change(await screen.findByLabelText('Cohort name'), { target: { value: 'Spring 2026' } })
  fireEvent.click(screen.getByRole('button', { name: /create cohort/i }))

  await vi.waitFor(() => {
    const post = fetchSpy.mock.calls.find((c) => c[1]?.method === 'POST')
    expect(post).toBeDefined()
    expect(String(post?.[0])).toContain('/programs/01J0PROG/cohorts')
    expect(JSON.parse((post?.[1]?.body as string) ?? '{}')).toEqual({ name: 'Spring 2026' })
  })
})
```

- [ ] **Step 2: Run, verify failure**

Run: `cd frontend && npm run test -- src/pages/ProgramCohortsSection.test.tsx`
Expected: FAIL — module doesn't exist.

- [ ] **Step 3: Create the section** — `frontend/src/pages/ProgramCohortsSection.tsx`:

```tsx
import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { createCohort, listCohorts } from '../api/cohorts'
import { CreateCohortError, type Cohort } from '../schemas/cohorts'

/** Human-readable cohort status (text, never colour-alone). */
const STATUS_LABEL: Record<Cohort['status'], string> = {
  draft: 'Draft',
  open: 'Open',
  closed: 'Closed',
  completed: 'Completed',
}

/**
 * Cohorts for one program (FE-2), rendered inside ProgramDetailPage. Reuses the
 * tenant ['cohorts'] list and filters by program_id (there is no per-program index
 * endpoint). Create is a name-only draft under this program; opening a cohort is
 * not available yet (backend not wired).
 */
export function ProgramCohortsSection({ programId }: { programId: string }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')

  const cohortsQuery = useQuery({ queryKey: ['cohorts'], queryFn: listCohorts, retry: false })

  const createMutation = useMutation({
    mutationFn: () => createCohort(programId, { name: name.trim() }),
    onSuccess: () => {
      setName('')
      return queryClient.invalidateQueries({ queryKey: ['cohorts'] })
    },
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (name.trim().length > 0) createMutation.mutate()
  }

  const cohorts = (cohortsQuery.data ?? []).filter((c) => c.program_id === programId)

  return (
    <section aria-labelledby="cohorts-heading">
      <h2 id="cohorts-heading">Cohorts</h2>

      {renderCreateError(createMutation.error)}

      <form onSubmit={onSubmit} noValidate>
        <FormLayout>
          <Field
            label="Cohort name"
            name="cohort-name"
            required
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
        </FormLayout>
        <Button type="submit" loading={createMutation.isPending} disabled={name.trim().length === 0}>
          Create cohort
        </Button>
      </form>

      {cohortsQuery.isLoading ? (
        <Spinner label="Loading cohorts…" />
      ) : cohortsQuery.isError ? (
        <StateBlock
          variant="error"
          message="We could not load cohorts."
          action={<Button onClick={() => cohortsQuery.refetch()}>Try again</Button>}
        />
      ) : cohorts.length === 0 ? (
        <StateBlock variant="empty" message="No cohorts yet. Create the first one above." />
      ) : (
        <ul aria-labelledby="cohorts-heading">
          {cohorts.map((cohort) => (
            <li key={cohort.id}>
              <Link href={`/cohorts/${cohort.id}`}>
                <bdi>{cohort.name}</bdi>
              </Link>{' '}
              <span className="ds-badge" data-status={cohort.status}>
                {STATUS_LABEL[cohort.status]}
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

function renderCreateError(error: unknown) {
  if (!(error instanceof CreateCohortError)) {
    return error ? <Banner variant="error">Something went wrong. Please try again.</Banner> : null
  }
  switch (error.code) {
    case 'FORBIDDEN':
      return <Banner variant="error">You do not have permission to create a cohort here.</Banner>
    case 'VALIDATION':
      return <Banner variant="error">{error.message}</Banner>
    case 'UNAUTHENTICATED':
      return <Banner variant="error">Your session expired. Please sign in again.</Banner>
    default:
      return <Banner variant="error">We could not create the cohort. Please try again.</Banner>
  }
}
```

- [ ] **Step 4: Run section tests, verify pass**

Run: `cd frontend && npm run test -- src/pages/ProgramCohortsSection.test.tsx`
Expected: PASS (3 tests).

- [ ] **Step 5: Render the section in `ProgramDetailPage.tsx`.** Add the import:

```tsx
import { ProgramCohortsSection } from './ProgramCohortsSection'
```

Render it inside the `program ? (` branch, immediately after the `{cloning ? (…) : null}` block and before the closing `</>` of that branch:

```tsx
            <ProgramCohortsSection programId={programId} />
```

- [ ] **Step 6: Make `ProgramDetailPage.test.tsx` deterministic.** The page now fires a mount-time `GET /cohorts` (the section), so the existing sequential `mockResolvedValueOnce` chains would desync. Replace the whole test file with this version — it routes `GET …/cohorts` to a fixed cohorts list and sends every other call through an ordered program queue:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { ProgramDetailPage } from './ProgramDetailPage'
import { jsonResponse } from '../tests/test-utils'

const { navigateSpy } = vi.hoisted(() => ({ navigateSpy: vi.fn() }))
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>()
  return { ...actual, useNavigate: () => navigateSpy }
})

const DRAFT = {
  id: '01J0PROG',
  name: 'Spring Accelerator',
  slug: 'spring-accelerator',
  status: 'draft',
  description: 'Seed cohort',
  settings: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

/**
 * Route GET …/cohorts (the embedded ProgramCohortsSection list) to a fixed
 * cohorts array; every other call (getProgram, PATCH, clone POST, createCohort
 * POST) is served from an ordered program queue. Deterministic regardless of
 * which mount-time query fires first.
 */
function mockApi(programQueue: Response[], cohorts: unknown[] = []) {
  const queue = [...programQueue]
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = String(input)
    const method = (init?.method ?? 'GET').toUpperCase()
    if (method === 'GET' && /\/cohorts$/.test(url)) {
      return Promise.resolve(jsonResponse({ data: cohorts }))
    }
    const next = queue.shift()
    return Promise.resolve(next ?? new Response(null, { status: 500 }))
  })
}

function renderDetail(programId = '01J0PROG'): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <ProgramDetailPage programId={programId} />
        </MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  navigateSpy.mockReset()
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})
afterEach(() => vi.restoreAllMocks())

test('renders the program name, status and description', async () => {
  mockApi([jsonResponse({ data: DRAFT })])
  renderDetail()
  expect(await screen.findByRole('heading', { name: 'Spring Accelerator' })).toBeInTheDocument()
  expect(screen.getByText('Draft')).toBeInTheDocument()
  expect(screen.getByText('Seed cohort')).toBeInTheDocument()
})

test('a 404 shows the "no longer exists" state', async () => {
  mockApi([new Response(null, { status: 404 })])
  renderDetail('missing')
  expect(await screen.findByText(/that program no longer exists/i)).toBeInTheDocument()
})

test('edit → save updates the displayed name', async () => {
  mockApi([
    jsonResponse({ data: DRAFT }), // initial load
    jsonResponse({ data: { ...DRAFT, name: 'Renamed' } }), // PATCH
    jsonResponse({ data: { ...DRAFT, name: 'Renamed' } }), // refetch
  ])
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Program name'), { target: { value: 'Renamed' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByRole('heading', { name: 'Renamed' })).toBeInTheDocument()
})

test('edit → 422 shows the validation message and stays in edit mode', async () => {
  mockApi([
    jsonResponse({ data: DRAFT }),
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
      422,
    ),
  ])
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Program name'), { target: { value: 'x' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByText(/the name field is required/i)).toBeInTheDocument()
  expect(screen.getByLabelText('Program name')).toBeInTheDocument()
})

test('clone → navigates to the new draft on success', async () => {
  mockApi([jsonResponse({ data: DRAFT }), jsonResponse({ data: { ...DRAFT, id: '01J0NEW' } }, 201)])
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Clone' }))
  fireEvent.change(screen.getByLabelText('New program name'), { target: { value: 'Copy' } })
  fireEvent.click(screen.getByRole('button', { name: /create copy/i }))

  await vi.waitFor(() => expect(navigateSpy).toHaveBeenCalledWith('/programs/01J0NEW'))
})

test('clone → 403 shows a permission banner', async () => {
  mockApi([jsonResponse({ data: DRAFT }), new Response(null, { status: 403 })])
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Clone' }))
  fireEvent.change(screen.getByLabelText('New program name'), { target: { value: 'Copy' } })
  fireEvent.click(screen.getByRole('button', { name: /create copy/i }))

  expect(await screen.findByText(/do not have permission/i)).toBeInTheDocument()
})

test('Publish shows for a draft', async () => {
  mockApi([jsonResponse({ data: DRAFT })])
  renderDetail()
  expect(await screen.findByRole('button', { name: 'Publish' })).toBeInTheDocument()
})

test('Publish is absent for a published program', async () => {
  mockApi([jsonResponse({ data: { ...DRAFT, status: 'published' } })])
  renderDetail()
  await screen.findByRole('heading', { name: 'Spring Accelerator' })
  expect(screen.queryByRole('button', { name: 'Publish' })).not.toBeInTheDocument()
})

test('shows the program’s cohorts section with a linked cohort', async () => {
  const cohort = {
    id: '01J0COH',
    organization_id: '01J0ORG',
    program_id: '01J0PROG',
    name: 'Spring 2026',
    slug: 'spring-2026',
    status: 'draft',
    capacity: null,
    enrollment_opens_at: null,
    enrollment_closes_at: null,
    starts_at: null,
    ends_at: null,
    timeline: null,
    submissions_count: 0,
    created_at: '2026-06-20T10:00:00+00:00',
    updated_at: '2026-06-20T10:00:00+00:00',
  }
  mockApi([jsonResponse({ data: DRAFT })], [cohort])
  renderDetail()
  expect(await screen.findByRole('link', { name: /spring 2026/i })).toHaveAttribute(
    'href',
    '/cohorts/01J0COH',
  )
})
```

- [ ] **Step 7: Run the affected page tests + full gates + build**

Run: `cd frontend && npm run test -- src/pages/ProgramDetailPage.test.tsx src/pages/ProgramCohortsSection.test.tsx && npm run typecheck && npm run lint && npm run test && npm run build`
Expected: the two page suites green, then the whole suite green, then a successful build.

- [ ] **Step 8: Commit**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta/.claude/worktrees/fe2-cohorts-management
git add frontend/src/pages/ProgramCohortsSection.tsx frontend/src/pages/ProgramCohortsSection.test.tsx frontend/src/pages/ProgramDetailPage.tsx frontend/src/pages/ProgramDetailPage.test.tsx
git commit -m "feat(fe): FE-2 — cohorts section on program detail (list filtered by program + create)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage:**
- `getCohort`/`createCohort`/`updateCohort` + typed errors + `cohortResponseSchema` → Task 1. ✓
- `/cohorts/:cohortId` gated route + `CohortDetailPage` (view + inline metadata edit with native date/number inputs; submissions link; "opening not available" note) → Task 2. ✓
- Cohorts section on ProgramDetailPage (filtered by `program_id`, name-only create) → Task 3. ✓
- States: loading/error/404/forbidden/validation/disabled/empty → CohortDetailPage `renderLoadError`/`renderUpdateError`, section empty/error states. ✓
- No open/close/status/form-binding; "opening isn't available yet" note → Task 2 view branch + Global Constraints. ✓
- Editable metadata = name/capacity/4 dates only → `updateCohort` signature + edit form. ✓
- Pages keep AppShell; 403 degrades; design-system only → both new surfaces. ✓
- Reuse `['cohorts']`, filter client-side; invalidate on create/update → section + detail. ✓

**2. Placeholder scan:** No TBD/"handle errors"/"similar to". Every code step is complete. The only narrative line (`…existing listCohorts stays unchanged…`) marks an untouched region, not omitted code. ✓

**3. Type consistency:** `getCohort(id)`, `createCohort(programId, {name})`, `updateCohort(id, {…})`, error class names/codes, `cohortResponseSchema`, and props `CohortDetailPage({cohortId})` / `ProgramCohortsSection({programId})` are identical across Task 1 (def), Tasks 2–3 (use), and all tests. Query keys `['cohort', id]` / `['cohorts']` consistent. The `mockApi` helper's `GET …/cohorts` rule correctly excludes the `POST …/cohorts` create call (matched by method). ✓
