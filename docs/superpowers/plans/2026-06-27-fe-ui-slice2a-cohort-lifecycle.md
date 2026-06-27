# FE UI Slice 2a — Cohort Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the operator's "stand up a cohort and open it for intake" path — re-skin the three existing program/cohort screens onto shadcn/Tailwind and add the cohort setup wizard + enrollment-window editor, all UI-first against MSW.

**Architecture:** Reuse the existing `cohorts` Zod schema (flat snake_case fields), the existing `getCohort`/`createCohort`/`updateCohort` api clients, and the module-mutable MSW `cohorts` array. Add one new api function (`openCohort`), the four missing MSW cohort CRUD handlers, two new pages (setup wizard, enrollment-window editor), re-skin three pages, and wire routes + e2e + a11y.

**Tech Stack:** React 19, Vite, TypeScript, shadcn/ui + Tailwind, @tanstack/react-query, Zod, MSW, react-router-dom, Vitest + Testing Library, Playwright, Storybook.

## Global Constraints

- **Design system:** shadcn/Tailwind only. Use theme tokens (`border-border`, `bg-secondary`, `text-secondary-foreground`, `text-muted-foreground`, `bg-card`, `text-primary`) and the status-badge pattern `<span data-status={...} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">`. Remove all `ds-badge` / `ds-muted` usage from pages touched here. (`FormLayout` still wraps `ds-form` internally — leave it; it is out of scope.)
- **Reuse existing cohort schema/api:** `Cohort` fields are flat snake_case (`enrollment_opens_at`, `enrollment_closes_at`, `capacity`, `starts_at`, `ends_at`, `status: 'draft'|'open'|'closed'|'completed'`). **Do NOT** introduce the spec's `enrollmentWindow`/`setupStatus` objects — those were design sketches; the flat fields + existing `status` enum already cover them. `boundFormVersionId`/`stagePipelineVersionId` are deferred to Slices 2b/2c.
- **UI-first:** pages call real `src/api/` client functions; MSW intercepts `fetch`. Unit tests mock `fetch` directly via `vi.spyOn(globalThis, 'fetch')` + `jsonResponse`. No screen changes at Slice 9.
- **AppShell no-idle-fetch invariant:** no AppShell-rendered component may fetch at mount beyond the roles query AppShell already makes. Any test rendering a page that contains `AppShell` MUST include `vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))`.
- **Test render helper:** `<DirectionProvider><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>…</QueryClientProvider></DirectionProvider>`. **No MemoryRouter** — pages use the custom `Link`, not react-router's. Pre-seed XSRF cookie in `beforeEach` when a mutation runs: `Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })`. `afterEach(() => vi.restoreAllMocks())`.
- **`aria-label` caution:** never add an `aria-label` where visible text should be the accessible name.
- **Commits:** end each commit body with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

## File Structure

| File | Responsibility | Task |
|------|----------------|------|
| `src/schemas/cohorts.ts` | + `OpenCohortError` / `OpenCohortErrorCode` | 1 |
| `src/api/cohorts.ts` | + `openCohort(id)` | 1 |
| `src/api/cohorts.test.ts` | + `openCohort` unit tests | 1 |
| `src/mocks/handlers.ts` | + GET `/cohorts/:id`, POST `/programs/:programId/cohorts`, PATCH `/cohorts/:id`, POST `/cohorts/:id/open` | 1 |
| `src/mocks/handlers.cohorts.test.ts` | handler-registration guard | 1 |
| `src/pages/EnrollmentWindowEditor.tsx` | NEW — open/close window + capacity editor | 2 |
| `src/pages/EnrollmentWindowEditor.test.tsx` | NEW — unit tests | 2 |
| `src/pages/ProgramCohortsSection.tsx` | re-skin to Tailwind | 3 |
| `src/pages/ProgramCohortsSection.test.tsx` | update assertions | 3 |
| `src/pages/CohortDetailPage.tsx` | re-skin + window/form/stage status rows | 4 |
| `src/pages/CohortDetailPage.test.tsx` | update assertions | 4 |
| `src/pages/ProgramDetailPage.tsx` | re-skin + config-hub entry point | 5 |
| `src/pages/ProgramDetailPage.test.tsx` | update assertions | 5 |
| `src/pages/CohortSetupWizard.tsx` | NEW — stepper wizard | 6 |
| `src/pages/CohortSetupWizard.test.tsx` | NEW — unit tests | 6 |
| `src/pages/CohortSetupWizard.stories.tsx` | NEW — Storybook | 6 |
| `src/app/App.tsx` | + wizard / enrollment / config-hub routes | 7 |
| `src/tests/a11y.test.tsx` | + cases for the two new pages | 7 |
| `tests/e2e/fe-ui-slice2a.spec.ts` | NEW — wizard happy path | 7 |
| `playwright.config.ts` | add spec to `msw-dev` `testMatch` | 7 |

---

### Task 1: Cohort data layer — `openCohort` + MSW CRUD handlers

**Files:**
- Modify: `src/schemas/cohorts.ts`
- Modify: `src/api/cohorts.ts`
- Test: `src/api/cohorts.test.ts`
- Modify: `src/mocks/handlers.ts`
- Test: `src/mocks/handlers.cohorts.test.ts` (Create)

**Interfaces:**
- Consumes: `csrfFetch` from `./csrf`; `cohortResponseSchema`, `Cohort` from `../schemas/cohorts`; `ApiError` from `./errors`.
- Produces: `openCohort(id: string): Promise<Cohort>`; `OpenCohortError`/`OpenCohortErrorCode`. MSW endpoints: `GET *​/api/v1/cohorts/:id`, `POST *​/api/v1/programs/:programId/cohorts`, `PATCH *​/api/v1/cohorts/:id`, `POST *​/api/v1/cohorts/:id/open`.

- [ ] **Step 1: Write the failing api test**

Append to `src/api/cohorts.test.ts` (mirrors the existing fetch-spy + `jsonResponse` pattern in that file):

```ts
import { openCohort, OpenCohortError } from './cohorts' // adjust the existing import line to include these

test('openCohort: 200 returns the opened cohort', async () => {
  const opened = { ...COHORT_FIXTURE, status: 'open' }
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: opened }))
  const result = await openCohort('coh_1')
  expect(result.status).toBe('open')
  const init = (globalThis.fetch as unknown as { mock: { calls: [string, RequestInit][] } }).mock.calls[0][1]
  expect(init.method).toBe('POST')
})

test('openCohort: 409 throws CONFLICT', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 409 }))
  await expect(openCohort('coh_1')).rejects.toMatchObject({ code: 'CONFLICT' })
})
```

If `COHORT_FIXTURE` does not already exist in the test file, add it near the top:

```ts
const COHORT_FIXTURE = {
  id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026',
  slug: 'spring-2026', status: 'draft' as const, capacity: null,
  enrollment_opens_at: null, enrollment_closes_at: null, starts_at: null, ends_at: null,
  timeline: null, created_at: '2026-06-20T10:00:00+00:00', updated_at: '2026-06-20T10:00:00+00:00',
}
```

- [ ] **Step 2: Run test — verify it fails**

Run: `cd frontend && npx vitest run src/api/cohorts.test.ts -t openCohort`
Expected: FAIL — `openCohort is not exported` / undefined.

- [ ] **Step 3: Add the error type to the schema**

In `src/schemas/cohorts.ts`, after the `UpdateCohortError` block, add:

```ts
export type OpenCohortErrorCode = 'FORBIDDEN' | 'NOT_FOUND' | 'CONFLICT' | 'UNAUTHENTICATED' | 'UNKNOWN'
export class OpenCohortError extends ApiError<OpenCohortErrorCode> {}
```

- [ ] **Step 4: Implement `openCohort`**

In `src/api/cohorts.ts`, add `OpenCohortError` to the schema import and append:

```ts
export async function openCohort(id: string): Promise<Cohort> {
  const response = await csrfFetch(`/cohorts/${id}/open`, { method: 'POST' })
  if (response.status === 200) {
    return cohortResponseSchema.parse(await response.json()).data
  }
  if (response.status === 401) throw new OpenCohortError('UNAUTHENTICATED')
  if (response.status === 403) throw new OpenCohortError('FORBIDDEN')
  if (response.status === 404) throw new OpenCohortError('NOT_FOUND')
  if (response.status === 409) throw new OpenCohortError('CONFLICT', 'Cohort cannot be opened in its current state.')
  throw new OpenCohortError('UNKNOWN', `Unexpected status ${response.status}`)
}
```

- [ ] **Step 5: Run test — verify it passes**

Run: `cd frontend && npx vitest run src/api/cohorts.test.ts`
Expected: PASS (all cohort api tests).

- [ ] **Step 6: Write the failing handler-registration test**

Create `src/mocks/handlers.cohorts.test.ts`:

```ts
import { expect, test } from 'vitest'
import { handlers } from './handlers'

function hasRoute(method: string, pathFragment: string): boolean {
  return handlers.some(
    (h) =>
      // @ts-expect-error msw HttpHandler exposes runtime info
      h.info?.method === method && String(h.info?.path ?? '').includes(pathFragment),
  )
}

test('cohort CRUD handlers are registered', () => {
  expect(hasRoute('GET', '/cohorts/:id')).toBe(true)
  expect(hasRoute('POST', '/programs/:programId/cohorts')).toBe(true)
  expect(hasRoute('PATCH', '/cohorts/:id')).toBe(true)
  expect(hasRoute('POST', '/cohorts/:id/open')).toBe(true)
})
```

- [ ] **Step 7: Run test — verify it fails**

Run: `cd frontend && npx vitest run src/mocks/handlers.cohorts.test.ts`
Expected: FAIL — the new routes are not registered.

- [ ] **Step 8: Add the MSW handlers**

In `src/mocks/handlers.ts`, locate the existing `http.get('*/api/v1/cohorts', …)` entry and add these handlers in the same array (the `cohorts` module array and `http`/`HttpResponse` imports already exist):

```ts
http.get('*/api/v1/cohorts/:id', ({ params }) => {
  const found = cohorts.find((c) => c.id === params.id)
  if (!found) return new HttpResponse(null, { status: 404 })
  return HttpResponse.json({ data: found })
}),
http.post('*/api/v1/programs/:programId/cohorts', async ({ params, request }) => {
  const body = (await request.json()) as { name?: string }
  const name = (body.name ?? '').trim()
  if (!name) {
    return HttpResponse.json(
      { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
      { status: 422 },
    )
  }
  const now = new Date().toISOString()
  const created = {
    id: `coh_${cohorts.length + 1}`,
    organization_id: 'org_demo',
    program_id: String(params.programId),
    name,
    slug: name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
    status: 'draft' as const,
    capacity: null,
    enrollment_opens_at: null,
    enrollment_closes_at: null,
    starts_at: null,
    ends_at: null,
    timeline: null,
    submissions_count: 0,
    created_at: now,
    updated_at: now,
  }
  cohorts.push(created)
  return HttpResponse.json({ data: created }, { status: 201 })
}),
http.patch('*/api/v1/cohorts/:id', async ({ params, request }) => {
  const found = cohorts.find((c) => c.id === params.id)
  if (!found) return new HttpResponse(null, { status: 404 })
  const body = (await request.json()) as Record<string, unknown>
  for (const key of ['name', 'capacity', 'enrollment_opens_at', 'enrollment_closes_at', 'starts_at', 'ends_at'] as const) {
    if (key in body) (found as Record<string, unknown>)[key] = body[key]
  }
  found.updated_at = new Date().toISOString()
  return HttpResponse.json({ data: found })
}),
http.post('*/api/v1/cohorts/:id/open', ({ params }) => {
  const found = cohorts.find((c) => c.id === params.id)
  if (!found) return new HttpResponse(null, { status: 404 })
  found.status = 'open'
  found.updated_at = new Date().toISOString()
  return HttpResponse.json({ data: found })
}),
```

- [ ] **Step 9: Run tests — verify they pass**

Run: `cd frontend && npx vitest run src/mocks/handlers.cohorts.test.ts src/api/cohorts.test.ts && npx tsc -b`
Expected: PASS + clean typecheck.

- [ ] **Step 10: Commit**

```bash
cd frontend && git add src/schemas/cohorts.ts src/api/cohorts.ts src/api/cohorts.test.ts src/mocks/handlers.ts src/mocks/handlers.cohorts.test.ts
git commit -m "feat(fe): Slice 2a — openCohort api + MSW cohort CRUD handlers

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Enrollment-window editor (new page)

**Files:**
- Create: `src/pages/EnrollmentWindowEditor.tsx`
- Test: `src/pages/EnrollmentWindowEditor.test.tsx`

**Interfaces:**
- Consumes: `getCohort`, `updateCohort` from `../api/cohorts`; `Cohort` from `../schemas/cohorts`; `AppShell`, `Banner`, `Button`, `Field`, `FormLayout`, `Spinner`, `StateBlock`, `Link`.
- Produces: `export function EnrollmentWindowEditor({ cohortId }: { cohortId: string }): JSX.Element`.

- [ ] **Step 1: Write the failing test**

Create `src/pages/EnrollmentWindowEditor.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { EnrollmentWindowEditor } from './EnrollmentWindowEditor'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const COHORT = {
  id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026',
  slug: 'spring-2026', status: 'draft' as const, capacity: 20,
  enrollment_opens_at: '2026-07-01T00:00:00+00:00', enrollment_closes_at: '2026-07-31T00:00:00+00:00',
  starts_at: null, ends_at: null, timeline: null,
  created_at: '2026-06-20T10:00:00+00:00', updated_at: '2026-06-20T10:00:00+00:00',
}

function renderEditor(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <EnrollmentWindowEditor cohortId="coh_1" />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('loads the cohort window and saves a PATCH with the edited dates + capacity', async () => {
  const updated = { ...COHORT, enrollment_closes_at: '2026-08-15T00:00:00+00:00', capacity: 30 }
  const spy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT }))      // GET
    .mockResolvedValueOnce(jsonResponse({ data: updated }))     // PATCH
    .mockResolvedValueOnce(jsonResponse({ data: updated }))     // refetch

  renderEditor()

  const capacity = await screen.findByLabelText(/capacity/i)
  fireEvent.change(capacity, { target: { value: '30' } })
  fireEvent.click(screen.getByRole('button', { name: /save window/i }))

  await screen.findByText(/window saved/i)
  const patch = spy.mock.calls.find((c) => c[1]?.method === 'PATCH')?.[1]
  const body = JSON.parse((patch?.body as string) ?? '{}')
  expect(body.capacity).toBe(30)
})

test('rejects a close date that is not after the open date', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  renderEditor()
  const closes = await screen.findByLabelText(/closes/i)
  fireEvent.change(closes, { target: { value: '2026-06-01' } })
  fireEvent.click(screen.getByRole('button', { name: /save window/i }))
  expect(await screen.findByText(/close.*after.*open/i)).toBeInTheDocument()
})
```

- [ ] **Step 2: Run test — verify it fails**

Run: `cd frontend && npx vitest run src/pages/EnrollmentWindowEditor.test.tsx`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement the page**

Create `src/pages/EnrollmentWindowEditor.tsx`:

```tsx
import { useEffect, useState } from 'react'
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

/** Strips a stored ISO timestamp down to the YYYY-MM-DD an <input type="date"> expects. */
function toDateInput(iso: string | null): string {
  return iso ? iso.slice(0, 10) : ''
}
/** Expands a date input back to a midnight-UTC ISO string, or null when cleared. */
function toIso(date: string): string | null {
  return date ? `${date}T00:00:00+00:00` : null
}

export function EnrollmentWindowEditor({ cohortId }: { cohortId: string }) {
  const queryClient = useQueryClient()
  const cohortQuery = useQuery({ queryKey: ['cohort', cohortId], queryFn: () => getCohort(cohortId), retry: false })

  const [opens, setOpens] = useState('')
  const [closes, setCloses] = useState('')
  const [capacity, setCapacity] = useState('')
  const [validationError, setValidationError] = useState<string | null>(null)

  useEffect(() => {
    if (cohortQuery.data) {
      setOpens(toDateInput(cohortQuery.data.enrollment_opens_at))
      setCloses(toDateInput(cohortQuery.data.enrollment_closes_at))
      setCapacity(cohortQuery.data.capacity == null ? '' : String(cohortQuery.data.capacity))
    }
  }, [cohortQuery.data])

  const saveMutation = useMutation({
    mutationFn: () =>
      updateCohort(cohortId, {
        enrollment_opens_at: toIso(opens),
        enrollment_closes_at: toIso(closes),
        capacity: capacity.trim() === '' ? null : Number(capacity),
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['cohort', cohortId] })
      await queryClient.invalidateQueries({ queryKey: ['cohorts'] })
    },
  })

  function onSave(event: React.FormEvent) {
    event.preventDefault()
    setValidationError(null)
    if (opens && closes && toIso(closes)! <= toIso(opens)!) {
      setValidationError('The close date must be after the open date.')
      return
    }
    saveMutation.mutate()
  }

  const rail = (
    <nav aria-label="Sections" className="grid gap-1 text-sm">
      <Link href="/programs">Programs</Link>
      <Link href={`/cohorts/${cohortId}`}>Cohort</Link>
    </nav>
  )

  return (
    <AppShell
      rail={rail}
      pageHeader={<h1 id="window-heading" className="text-2xl font-semibold">Enrollment window</h1>}
    >
      <section aria-labelledby="window-heading" className="grid max-w-xl gap-6">
        {cohortQuery.isLoading ? (
          <Spinner label="Loading cohort…" />
        ) : cohortQuery.isError ? (
          <StateBlock variant="error" message="Could not load this cohort." />
        ) : (
          <form onSubmit={onSave} noValidate className="grid gap-4 rounded-lg border border-border p-4">
            {validationError && <Banner variant="error">{validationError}</Banner>}
            {saveMutation.isError && <Banner variant="error">Could not save the window. Try again.</Banner>}
            {saveMutation.isSuccess && <Banner variant="success">Window saved.</Banner>}
            <FormLayout>
              <Field label="Opens" name="opens" type="date" value={opens} onChange={(e) => setOpens(e.target.value)} />
              <Field label="Closes" name="closes" type="date" value={closes} onChange={(e) => setCloses(e.target.value)} />
              <Field
                label="Capacity"
                name="capacity"
                type="number"
                min={0}
                value={capacity}
                help="Leave blank for unlimited."
                onChange={(e) => setCapacity(e.target.value)}
              />
            </FormLayout>
            <div>
              <Button type="submit" loading={saveMutation.isPending}>Save window</Button>
            </div>
          </form>
        )}
      </section>
    </AppShell>
  )
}
```

- [ ] **Step 4: Run test — verify it passes**

Run: `cd frontend && npx vitest run src/pages/EnrollmentWindowEditor.test.tsx`
Expected: PASS (both tests).

- [ ] **Step 5: Commit**

```bash
cd frontend && git add src/pages/EnrollmentWindowEditor.tsx src/pages/EnrollmentWindowEditor.test.tsx
git commit -m "feat(fe): Slice 2a — enrollment window editor

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Re-skin `ProgramCohortsSection`

**Files:**
- Modify: `src/pages/ProgramCohortsSection.tsx`
- Test: `src/pages/ProgramCohortsSection.test.tsx`

**Interfaces:**
- Consumes: `listCohorts`, `createCohort` from `../api/cohorts`. Props unchanged: `{ programId: string }`.
- Produces: same component contract; status badge now uses the Tailwind pattern (no `ds-badge`).

- [ ] **Step 1: Add a failing assertion for the Tailwind badge**

In `src/pages/ProgramCohortsSection.test.tsx`, add (and ensure `vi.mock('../api/roles', …)` is present if the test renders through any AppShell-bearing parent — this section is standalone, so it is not required, but harmless):

```tsx
test('status badge uses the shadcn token classes, not ds-badge', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: [{
      id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026',
      slug: 'spring-2026', status: 'open', capacity: null, enrollment_opens_at: null,
      enrollment_closes_at: null, starts_at: null, ends_at: null, timeline: null,
      submissions_count: 0, created_at: 'x', updated_at: 'x',
    }] }),
  )
  renderSection('prog_1') // use the file's existing render helper
  const badge = await screen.findByText('Open')
  expect(badge).toHaveClass('bg-secondary')
  expect(badge.className).not.toContain('ds-badge')
})
```

If the file lacks a `STATUS_LABEL` mapping check, this assumes a human-readable label "Open" — see Step 3 for the label map.

- [ ] **Step 2: Run test — verify it fails**

Run: `cd frontend && npx vitest run src/pages/ProgramCohortsSection.test.tsx -t "shadcn token"`
Expected: FAIL — badge has `ds-badge`, not `bg-secondary`; label may be raw status.

- [ ] **Step 3: Re-skin the component**

Replace the full contents of `src/pages/ProgramCohortsSection.tsx` with:

```tsx
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { createCohort, listCohorts, CreateCohortError } from '../api/cohorts'

const STATUS_LABEL: Record<string, string> = {
  draft: 'Draft',
  open: 'Open',
  closed: 'Closed',
  completed: 'Completed',
}

function renderCreateError(error: unknown) {
  if (!error) return null
  const message = error instanceof CreateCohortError ? error.message : 'Could not create the cohort.'
  return <Banner variant="error">{message}</Banner>
}

export function ProgramCohortsSection({ programId }: { programId: string }) {
  const queryClient = useQueryClient()
  const cohortsQuery = useQuery({ queryKey: ['cohorts'], queryFn: listCohorts, retry: false })
  const [name, setName] = useState('')

  const createMutation = useMutation({
    mutationFn: () => createCohort(programId, { name: name.trim() }),
    onSuccess: () => {
      setName('')
      return queryClient.invalidateQueries({ queryKey: ['cohorts'] })
    },
  })

  const cohorts = (cohortsQuery.data ?? []).filter((c) => c.program_id === programId)

  function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (name.trim()) createMutation.mutate()
  }

  return (
    <section aria-labelledby="cohorts-heading" className="grid gap-4">
      <h2 id="cohorts-heading" className="text-lg font-medium">Cohorts</h2>
      {renderCreateError(createMutation.error)}
      <form onSubmit={onSubmit} noValidate className="grid gap-3 rounded-lg border border-border p-4">
        <FormLayout>
          <Field label="Cohort name" name="cohort-name" required value={name} onChange={(e) => setName(e.target.value)} />
        </FormLayout>
        <div className="flex gap-2">
          <Button type="submit" loading={createMutation.isPending} disabled={!name.trim()}>Create cohort</Button>
          <Link href={`/programs/${programId}/cohorts/new`}>Set up with wizard</Link>
        </div>
      </form>

      {cohortsQuery.isLoading ? (
        <Spinner label="Loading cohorts…" />
      ) : cohortsQuery.isError ? (
        <StateBlock variant="error" message="Could not load cohorts." />
      ) : cohorts.length === 0 ? (
        <StateBlock variant="empty" message="No cohorts yet. Create one to begin intake." />
      ) : (
        <ul aria-labelledby="cohorts-heading" className="grid gap-2">
          {cohorts.map((cohort) => (
            <li key={cohort.id} className="flex items-center justify-between rounded-md border border-border px-4 py-3">
              <Link href={`/cohorts/${cohort.id}`}><bdi>{cohort.name}</bdi></Link>
              <span
                data-status={cohort.status}
                className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
              >
                {STATUS_LABEL[cohort.status] ?? cohort.status}
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
```

- [ ] **Step 4: Run tests — verify they pass**

Run: `cd frontend && npx vitest run src/pages/ProgramCohortsSection.test.tsx`
Expected: PASS. Fix any pre-existing assertion that referenced `ds-badge` or raw status text by updating it to the new label/classes.

- [ ] **Step 5: Commit**

```bash
cd frontend && git add src/pages/ProgramCohortsSection.tsx src/pages/ProgramCohortsSection.test.tsx
git commit -m "feat(fe): Slice 2a — re-skin ProgramCohortsSection to shadcn

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Re-skin `CohortDetailPage` + window/form/stage status rows

**Files:**
- Modify: `src/pages/CohortDetailPage.tsx`
- Test: `src/pages/CohortDetailPage.test.tsx`

**Interfaces:**
- Consumes: `getCohort`, `updateCohort`; `Cohort`. Props unchanged: `{ cohortId: string }`.
- Produces: same contract; adds a "Configuration" summary block with deep links to the enrollment editor (`/cohorts/:id/enrollment`), and read-only "Form: not bound" / "Stages: not configured" rows (placeholders for 2b/2c). Status badge uses the Tailwind pattern.

- [ ] **Step 1: Add failing assertions**

In `src/pages/CohortDetailPage.test.tsx`, add (the file already stubs roles and seeds the XSRF cookie):

```tsx
test('renders the shadcn status badge and a link to the enrollment window editor', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  renderDetail('coh_1') // file's existing helper; COHORT fixture status 'open'
  const badge = await screen.findByText('Open')
  expect(badge).toHaveClass('bg-secondary')
  expect(screen.getByRole('link', { name: /enrollment window/i })).toHaveAttribute('href', '/cohorts/coh_1/enrollment')
})
```

Ensure the `COHORT` fixture in the file has `status: 'open'` (adjust if needed).

- [ ] **Step 2: Run test — verify it fails**

Run: `cd frontend && npx vitest run src/pages/CohortDetailPage.test.tsx -t "enrollment window editor"`
Expected: FAIL — old `ds-badge`, no enrollment link.

- [ ] **Step 3: Re-skin the page**

Replace the full contents of `src/pages/CohortDetailPage.tsx` with:

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
import { getCohort, updateCohort, UpdateCohortError } from '../api/cohorts'

const STATUS_LABEL: Record<string, string> = { draft: 'Draft', open: 'Open', closed: 'Closed', completed: 'Completed' }

function formatWindow(opens: string | null, closes: string | null): string {
  if (!opens && !closes) return 'Not scheduled'
  const fmt = (iso: string | null) => (iso ? iso.slice(0, 10) : '—')
  return `${fmt(opens)} → ${fmt(closes)}`
}

export function CohortDetailPage({ cohortId }: { cohortId: string }) {
  const queryClient = useQueryClient()
  const cohortQuery = useQuery({ queryKey: ['cohort', cohortId], queryFn: () => getCohort(cohortId), retry: false })

  const [editing, setEditing] = useState(false)
  const [name, setName] = useState('')

  const updateMutation = useMutation({
    mutationFn: () => updateCohort(cohortId, { name: name.trim() }),
    onSuccess: async () => {
      setEditing(false)
      await queryClient.invalidateQueries({ queryKey: ['cohort', cohortId] })
      await queryClient.invalidateQueries({ queryKey: ['cohorts'] })
    },
  })

  const cohort = cohortQuery.data
  const rail = (
    <nav aria-label="Sections" className="grid gap-1 text-sm">
      <Link href="/programs">Programs</Link>
    </nav>
  )

  return (
    <AppShell
      rail={rail}
      pageHeader={
        <h1 id="cohort-heading" className="text-2xl font-semibold">
          <bdi>{cohort?.name ?? 'Cohort'}</bdi>
        </h1>
      }
    >
      <section aria-labelledby="cohort-heading" className="grid max-w-2xl gap-6">
        {cohortQuery.isLoading ? (
          <Spinner label="Loading cohort…" />
        ) : cohortQuery.isError ? (
          <StateBlock variant="error" message="Could not load this cohort." />
        ) : cohort ? (
          <>
            <div className="flex items-center gap-3">
              <span
                data-status={cohort.status}
                className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
              >
                {STATUS_LABEL[cohort.status] ?? cohort.status}
              </span>
              {!editing && (
                <Button variant="secondary" onClick={() => { setName(cohort.name); setEditing(true) }}>Edit</Button>
              )}
            </div>

            {editing ? (
              <form
                onSubmit={(e) => { e.preventDefault(); if (name.trim()) updateMutation.mutate() }}
                noValidate
                className="grid gap-4 rounded-lg border border-border p-4"
              >
                {updateMutation.isError && (
                  <Banner variant="error">
                    {updateMutation.error instanceof UpdateCohortError ? updateMutation.error.message : 'Could not save.'}
                  </Banner>
                )}
                <FormLayout>
                  <Field label="Cohort name" name="cohort-name" required value={name} onChange={(e) => setName(e.target.value)} />
                </FormLayout>
                <div className="flex gap-2">
                  <Button type="submit" loading={updateMutation.isPending} disabled={!name.trim()}>Save</Button>
                  <Button variant="secondary" type="button" onClick={() => setEditing(false)}>Cancel</Button>
                </div>
              </form>
            ) : (
              <dl className="grid gap-4 rounded-lg border border-border p-4 text-sm">
                <div className="flex items-center justify-between">
                  <dt className="text-muted-foreground">Enrollment window</dt>
                  <dd className="flex items-center gap-3">
                    <span>{formatWindow(cohort.enrollment_opens_at, cohort.enrollment_closes_at)}</span>
                    <Link href={`/cohorts/${cohortId}/enrollment`}>Edit enrollment window</Link>
                  </dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-muted-foreground">Application form</dt>
                  <dd className="text-muted-foreground">Not bound yet</dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-muted-foreground">Stage pipeline</dt>
                  <dd className="text-muted-foreground">Not configured yet</dd>
                </div>
              </dl>
            )}
          </>
        ) : null}
      </section>
    </AppShell>
  )
}
```

- [ ] **Step 4: Run tests — verify they pass**

Run: `cd frontend && npx vitest run src/pages/CohortDetailPage.test.tsx`
Expected: PASS. Update any pre-existing assertion that referenced `ds-badge`/`ds-muted` or the old edit-form field set (the edit form is now name-only; the dates/capacity moved to the enrollment editor — adjust or delete stale date-field assertions, which are now covered by `EnrollmentWindowEditor.test.tsx`).

- [ ] **Step 5: Commit**

```bash
cd frontend && git add src/pages/CohortDetailPage.tsx src/pages/CohortDetailPage.test.tsx
git commit -m "feat(fe): Slice 2a — re-skin CohortDetailPage + config summary rows

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Re-skin `ProgramDetailPage` + config-hub entry point

**Files:**
- Modify: `src/pages/ProgramDetailPage.tsx`
- Test: `src/pages/ProgramDetailPage.test.tsx`

**Interfaces:**
- Consumes: existing `getProgram`, `updateProgram`, `publishProgram`, `cloneProgram` (unchanged signatures); `ProgramCohortsSection`. Props unchanged: `{ programId: string }`.
- Produces: same contract; status badge uses the Tailwind pattern; adds a "Configure program" link to `/programs/:id/config` (route added as a ComingSoon placeholder in Task 7, replaced in Slice 2c).

- [ ] **Step 1: Add a failing assertion**

In `src/pages/ProgramDetailPage.test.tsx`, add:

```tsx
test('renders shadcn badge and a Configure program link', async () => {
  // reuse the file's existing program fetch mock + render helper; PROGRAM fixture status 'published'
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: PROGRAM }))
  renderDetail('prog_1')
  const badge = await screen.findByText(/published/i)
  expect(badge).toHaveClass('bg-secondary')
  expect(screen.getByRole('link', { name: /configure program/i })).toHaveAttribute('href', '/programs/prog_1/config')
})
```

- [ ] **Step 2: Run test — verify it fails**

Run: `cd frontend && npx vitest run src/pages/ProgramDetailPage.test.tsx -t "Configure program"`
Expected: FAIL — old `ds-badge`, no config link.

- [ ] **Step 3: Re-skin the page**

Open `src/pages/ProgramDetailPage.tsx`. Apply these changes (the program-specific query/mutation logic — `getProgram`/`updateMutation`/`publishMutation`/`cloneMutation` — stays exactly as it is; only the presentation changes):

1. Replace the AppShell `rail`/`pageHeader` and the view-mode block. The status badge:

```tsx
// BEFORE: <span className="ds-badge" data-status={program.status}>{program.status}</span>
// AFTER:
<span
  data-status={program.status}
  className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
>
  {PROGRAM_STATUS_LABEL[program.status] ?? program.status}
</span>
```

Add near the top of the file (if not already present):

```tsx
const PROGRAM_STATUS_LABEL: Record<string, string> = {
  draft: 'Draft', published: 'Published', archived: 'Archived',
}
```
(Match the actual `program.status` enum values from `src/schemas/programs.ts`; add any missing keys.)

2. Replace every `className="ds-muted"` with `className="text-sm text-muted-foreground"`, and wrap the page body section in `className="grid max-w-2xl gap-6"`.

3. Add the actions row with the config-hub entry point, beside the existing Edit/Publish/Clone buttons:

```tsx
<div className="flex flex-wrap items-center gap-2">
  {/* existing Edit / Publish / Clone buttons stay here */}
  <Link href={`/programs/${programId}/config`}>Configure program</Link>
</div>
```

4. Keep `<ProgramCohortsSection programId={programId} />` at the bottom of the section.

- [ ] **Step 4: Run tests — verify they pass**

Run: `cd frontend && npx vitest run src/pages/ProgramDetailPage.test.tsx`
Expected: PASS. Update any stale `ds-badge`/`ds-muted` assertions.

- [ ] **Step 5: Commit**

```bash
cd frontend && git add src/pages/ProgramDetailPage.tsx src/pages/ProgramDetailPage.test.tsx
git commit -m "feat(fe): Slice 2a — re-skin ProgramDetailPage + config entry point

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Cohort setup wizard (new page)

**Files:**
- Create: `src/pages/CohortSetupWizard.tsx`
- Test: `src/pages/CohortSetupWizard.test.tsx`
- Create: `src/pages/CohortSetupWizard.stories.tsx`

**Interfaces:**
- Consumes: `createCohort`, `updateCohort`, `openCohort` from `../api/cohorts`; `Cohort`. Shared components as elsewhere.
- Produces: `export function CohortSetupWizard({ programId }: { programId: string }): JSX.Element`. A self-contained stepper (no shadcn stepper primitive exists; build a lightweight ordered step indicator + local `step` state — no new dependency).

The wizard steps: **1 Create → 2 Attach form → 3 Attach stages → 4 Dates → 5 Review → 6 Open.** Steps 2–3 are placeholder panels (per the spec build-order note): they explain the form/stage will be configured from the config hub once Slices 2b/2c land, and are skippable. Step 1 creates the cohort (gets an id); step 4 PATCHes dates; step 6 calls `openCohort`.

- [ ] **Step 1: Write the failing test**

Create `src/pages/CohortSetupWizard.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { CohortSetupWizard } from './CohortSetupWizard'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const CREATED = {
  id: 'coh_9', organization_id: 'org_demo', program_id: 'prog_1', name: 'Autumn 2026',
  slug: 'autumn-2026', status: 'draft' as const, capacity: null,
  enrollment_opens_at: null, enrollment_closes_at: null, starts_at: null, ends_at: null,
  timeline: null, created_at: 'x', updated_at: 'x',
}

function renderWizard(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <CohortSetupWizard programId="prog_1" />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('create step posts the cohort and advances to Attach form', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: CREATED }, 201))
  renderWizard()
  fireEvent.change(screen.getByLabelText(/cohort name/i), { target: { value: 'Autumn 2026' } })
  fireEvent.click(screen.getByRole('button', { name: /create & continue/i }))
  expect(await screen.findByRole('heading', { name: /attach form/i })).toBeInTheDocument()
})

test('full happy path reaches Open and calls openCohort', async () => {
  const opened = { ...CREATED, status: 'open' as const }
  const spy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: CREATED }, 201))   // create
    .mockResolvedValueOnce(jsonResponse({ data: CREATED }))        // dates PATCH
    .mockResolvedValueOnce(jsonResponse({ data: opened }))         // open

  renderWizard()
  fireEvent.change(screen.getByLabelText(/cohort name/i), { target: { value: 'Autumn 2026' } })
  fireEvent.click(screen.getByRole('button', { name: /create & continue/i }))
  await screen.findByRole('heading', { name: /attach form/i })
  fireEvent.click(screen.getByRole('button', { name: /skip for now/i }))     // step 2 -> 3
  await screen.findByRole('heading', { name: /attach stages/i })
  fireEvent.click(screen.getByRole('button', { name: /skip for now/i }))     // step 3 -> 4
  await screen.findByRole('heading', { name: /dates/i })
  fireEvent.click(screen.getByRole('button', { name: /continue/i }))         // step 4 -> 5
  await screen.findByRole('heading', { name: /review/i })
  fireEvent.click(screen.getByRole('button', { name: /open cohort/i }))      // step 5 -> open

  expect(await screen.findByText(/cohort is open/i)).toBeInTheDocument()
  const opens = spy.mock.calls.some((c) => String(c[0]).endsWith('/cohorts/coh_9/open') && c[1]?.method === 'POST')
  expect(opens).toBe(true)
})
```

- [ ] **Step 2: Run test — verify it fails**

Run: `cd frontend && npx vitest run src/pages/CohortSetupWizard.test.tsx`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement the wizard**

Create `src/pages/CohortSetupWizard.tsx`:

```tsx
import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { createCohort, updateCohort, openCohort } from '../api/cohorts'
import type { Cohort } from '../schemas/cohorts'

const STEPS = ['Create', 'Attach form', 'Attach stages', 'Dates', 'Review', 'Open'] as const

function toIso(date: string): string | null {
  return date ? `${date}T00:00:00+00:00` : null
}

export function CohortSetupWizard({ programId }: { programId: string }) {
  const [step, setStep] = useState(0)
  const [name, setName] = useState('')
  const [opens, setOpens] = useState('')
  const [closes, setCloses] = useState('')
  const [cohort, setCohort] = useState<Cohort | null>(null)
  const [opened, setOpened] = useState(false)

  const createMutation = useMutation({
    mutationFn: () => createCohort(programId, { name: name.trim() }),
    onSuccess: (c) => { setCohort(c); setStep(1) },
  })
  const datesMutation = useMutation({
    mutationFn: () => updateCohort(cohort!.id, { enrollment_opens_at: toIso(opens), enrollment_closes_at: toIso(closes) }),
    onSuccess: () => setStep(4),
  })
  const openMutation = useMutation({
    mutationFn: () => openCohort(cohort!.id),
    onSuccess: () => setOpened(true),
  })

  const rail = (
    <nav aria-label="Sections" className="grid gap-1 text-sm">
      <Link href="/programs">Programs</Link>
    </nav>
  )

  return (
    <AppShell
      rail={rail}
      pageHeader={<h1 id="wizard-heading" className="text-2xl font-semibold">Set up cohort</h1>}
    >
      <section aria-labelledby="wizard-heading" className="grid max-w-2xl gap-6">
        <ol className="flex flex-wrap gap-2 text-sm" aria-label="Setup steps">
          {STEPS.map((label, i) => (
            <li
              key={label}
              aria-current={i === step ? 'step' : undefined}
              className={
                i === step
                  ? 'rounded-full bg-primary px-3 py-1 font-medium text-primary-foreground'
                  : 'rounded-full bg-secondary px-3 py-1 text-secondary-foreground'
              }
            >
              {i + 1}. {label}
            </li>
          ))}
        </ol>

        {opened ? (
          <Banner variant="success">The cohort is open for intake. <Link href={`/cohorts/${cohort!.id}`}>View cohort</Link></Banner>
        ) : step === 0 ? (
          <form
            onSubmit={(e) => { e.preventDefault(); if (name.trim()) createMutation.mutate() }}
            noValidate
            className="grid gap-4 rounded-lg border border-border p-4"
          >
            <h2 className="text-lg font-medium">Create</h2>
            {createMutation.isError && <Banner variant="error">Could not create the cohort. Try again.</Banner>}
            <FormLayout>
              <Field label="Cohort name" name="cohort-name" required value={name} onChange={(e) => setName(e.target.value)} />
            </FormLayout>
            <div><Button type="submit" loading={createMutation.isPending} disabled={!name.trim()}>Create &amp; continue</Button></div>
          </form>
        ) : step === 1 ? (
          <div className="grid gap-4 rounded-lg border border-border p-4">
            <h2 className="text-lg font-medium">Attach form</h2>
            <p className="text-sm text-muted-foreground">
              You can bind a published application form from the program configuration hub once forms are available. Skip
              for now and attach it later.
            </p>
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => setStep(2)}>Skip for now</Button>
            </div>
          </div>
        ) : step === 2 ? (
          <div className="grid gap-4 rounded-lg border border-border p-4">
            <h2 className="text-lg font-medium">Attach stages</h2>
            <p className="text-sm text-muted-foreground">
              You can attach a stage pipeline from the configuration hub once stages are available. Skip for now and
              configure it later.
            </p>
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => setStep(3)}>Skip for now</Button>
            </div>
          </div>
        ) : step === 3 ? (
          <form
            onSubmit={(e) => { e.preventDefault(); datesMutation.mutate() }}
            noValidate
            className="grid gap-4 rounded-lg border border-border p-4"
          >
            <h2 className="text-lg font-medium">Dates</h2>
            {datesMutation.isError && <Banner variant="error">Could not save the dates. Try again.</Banner>}
            <FormLayout>
              <Field label="Opens" name="opens" type="date" value={opens} onChange={(e) => setOpens(e.target.value)} />
              <Field label="Closes" name="closes" type="date" value={closes} onChange={(e) => setCloses(e.target.value)} />
            </FormLayout>
            <div><Button type="submit" loading={datesMutation.isPending}>Continue</Button></div>
          </form>
        ) : step === 4 ? (
          <div className="grid gap-4 rounded-lg border border-border p-4">
            <h2 className="text-lg font-medium">Review</h2>
            <dl className="grid gap-2 text-sm">
              <div className="flex justify-between"><dt className="text-muted-foreground">Name</dt><dd><bdi>{name}</bdi></dd></div>
              <div className="flex justify-between"><dt className="text-muted-foreground">Window</dt><dd>{opens || '—'} → {closes || '—'}</dd></div>
            </dl>
            {openMutation.isError && <Banner variant="error">Could not open the cohort. Try again.</Banner>}
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => setStep(3)}>Back</Button>
              <Button loading={openMutation.isPending} onClick={() => openMutation.mutate()}>Open cohort</Button>
            </div>
          </div>
        ) : null}
      </section>
    </AppShell>
  )
}
```

- [ ] **Step 4: Run test — verify it passes**

Run: `cd frontend && npx vitest run src/pages/CohortSetupWizard.test.tsx`
Expected: PASS (both tests).

- [ ] **Step 5: Add a Storybook story**

Create `src/pages/CohortSetupWizard.stories.tsx`:

```tsx
import type { Meta, StoryObj } from '@storybook/react-vite'
import { CohortSetupWizard } from './CohortSetupWizard'

const meta = {
  title: 'Pages/CohortSetupWizard',
  component: CohortSetupWizard,
  args: { programId: 'prog_1' },
} satisfies Meta<typeof CohortSetupWizard>

export default meta
type Story = StoryObj<typeof meta>

/** Step 1 — create the cohort. Later steps advance on interaction. */
export const Default: Story = {}
```

- [ ] **Step 6: Verify the story builds**

Run: `cd frontend && npm run build-storybook`
Expected: build succeeds (no story errors).

- [ ] **Step 7: Commit**

```bash
cd frontend && git add src/pages/CohortSetupWizard.tsx src/pages/CohortSetupWizard.test.tsx src/pages/CohortSetupWizard.stories.tsx
git commit -m "feat(fe): Slice 2a — cohort setup wizard

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Routing + a11y + e2e wiring

**Files:**
- Modify: `src/app/App.tsx`
- Modify: `src/tests/a11y.test.tsx`
- Create: `tests/e2e/fe-ui-slice2a.spec.ts`
- Modify: `playwright.config.ts`

**Interfaces:**
- Consumes: `CohortSetupWizard`, `EnrollmentWindowEditor`, `ComingSoonPage`; `useParams`, `ConsoleGate`.
- Produces: routes `/programs/:programId/cohorts/new`, `/cohorts/:cohortId/enrollment`, `/programs/:programId/config` (ComingSoon placeholder, replaced in Slice 2c). New a11y cases. New e2e spec.

- [ ] **Step 1: Add the failing a11y cases**

In `src/tests/a11y.test.tsx`, add imports and cases (follow the existing `withProviders`/`expectNoViolations` shape):

```tsx
import { EnrollmentWindowEditor } from '../pages/EnrollmentWindowEditor'
import { CohortSetupWizard } from '../pages/CohortSetupWizard'

it('CohortSetupWizard — step 1 (Slice 2a)', async () => {
  await expectNoViolations(withProviders(<CohortSetupWizard programId="prog_1" />))
})

it('EnrollmentWindowEditor — loading shell (Slice 2a)', async () => {
  // fetch is unmocked here → query stays pending → renders the Spinner shell, which must be a11y-clean
  await expectNoViolations(withProviders(<EnrollmentWindowEditor cohortId="coh_1" />))
})
```

- [ ] **Step 2: Run a11y — verify new cases fail to import / run**

Run: `cd frontend && npx vitest run src/tests/a11y.test.tsx`
Expected: FAIL on import (pages not yet routed/imported) OR pass once imports resolve; if axe flags a violation, fix the offending markup in the page before proceeding.

- [ ] **Step 3: Add the routes**

In `src/app/App.tsx`, import the new pages + `ComingSoonPage`, and add route entries alongside the existing program/cohort routes (mirror the existing `CohortDetailRoute` render-prop pattern):

```tsx
<Route path="/programs/:programId/cohorts/new" element={<CohortSetupRoute />} />
<Route path="/programs/:programId/config" element={<ProgramConfigRoute />} />
<Route path="/cohorts/:cohortId/enrollment" element={<EnrollmentWindowRoute />} />
```

Add the route components near the other `*Route` functions:

```tsx
function CohortSetupRoute() {
  const { programId } = useParams()
  return <ConsoleGate>{() => <CohortSetupWizard programId={programId!} />}</ConsoleGate>
}
function EnrollmentWindowRoute() {
  const { cohortId } = useParams()
  return <ConsoleGate>{() => <EnrollmentWindowEditor cohortId={cohortId!} />}</ConsoleGate>
}
function ProgramConfigRoute() {
  // Placeholder until Slice 2c builds the Program configuration hub.
  return <ConsoleGate>{() => <ComingSoonPage title="Program configuration" />}</ConsoleGate>
}
```

(If `ComingSoonPage`'s prop name differs, match its actual signature — check `src/pages/ComingSoonPage.tsx`.)

- [ ] **Step 4: Run typecheck + a11y — verify pass**

Run: `cd frontend && npx tsc -b && npx vitest run src/tests/a11y.test.tsx`
Expected: PASS.

- [ ] **Step 5: Add the e2e spec**

Create `tests/e2e/fe-ui-slice2a.spec.ts`:

```ts
import { test, expect } from '@playwright/test'

test('operator sets up a cohort end-to-end and opens it', async ({ page }) => {
  await page.goto('/programs/prog_1/cohorts/new')
  await expect(page.getByRole('heading', { name: 'Set up cohort' })).toBeVisible({ timeout: 15000 })

  await page.getByLabel(/cohort name/i).fill('E2E Cohort')
  await page.getByRole('button', { name: /create & continue/i }).click()

  await expect(page.getByRole('heading', { name: /attach form/i })).toBeVisible()
  await page.getByRole('button', { name: /skip for now/i }).click()
  await expect(page.getByRole('heading', { name: /attach stages/i })).toBeVisible()
  await page.getByRole('button', { name: /skip for now/i }).click()

  await expect(page.getByRole('heading', { name: /dates/i })).toBeVisible()
  await page.getByRole('button', { name: /continue/i }).click()

  await expect(page.getByRole('heading', { name: /review/i })).toBeVisible()
  await page.getByRole('button', { name: /open cohort/i }).click()

  await expect(page.getByText(/cohort is open/i)).toBeVisible()
})
```

- [ ] **Step 6: Register the spec with the MSW Playwright project**

In `playwright.config.ts`, add `'**/fe-ui-slice2a.spec.ts'` to BOTH the `testIgnore` list of the `chromium` project and the `testMatch` list of the `msw-dev` project (so it runs only against the Vite dev server with MSW, like the other slice specs).

- [ ] **Step 7: Run the e2e spec**

Run: `cd frontend && npm run test:e2e -- fe-ui-slice2a.spec.ts`
Expected: PASS (1 test). The MSW worker (dev) serves the cohort CRUD handlers added in Task 1.

- [ ] **Step 8: Full gate sweep**

Run: `cd frontend && npx vitest run && npm run build && npm run build-storybook`
Expected: all green.

- [ ] **Step 9: Commit**

```bash
cd frontend && git add src/app/App.tsx src/tests/a11y.test.tsx tests/e2e/fe-ui-slice2a.spec.ts playwright.config.ts
git commit -m "feat(fe): Slice 2a — routes, a11y cases, e2e wizard flow

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage (against §2a of the spec):**
- Program detail re-skin → Task 5. ✅
- Program cohorts section re-skin → Task 3. ✅
- Cohort detail re-skin + window/form/stage status rows → Task 4. ✅
- Cohort setup wizard (Create → Attach form → Attach stages → Dates → Review → Open; steps 2–3 placeholders per build-order note) → Task 6. ✅
- Enrollment window editor (opens/closes/capacity, close>open validation) → Task 2. ✅
- Cross-cutting: `StateBlock` states ✅ (Tasks 2–4), optimistic invalidation ✅, AppShell no-idle-fetch (no new mount fetch; tests stub roles) ✅, a11y cases ✅ (Task 7), e2e spine flow ✅ (Task 7), MSW persistence ✅ (Task 1).
- Deferred per spec: form binding (2b), stage config (2c), config hub real content (2c) — config route is a ComingSoon placeholder here. ✅

**Deliberate spec deviation (documented in Global Constraints):** the spec's `enrollmentWindow`/`setupStatus` objects are replaced by the existing flat `cohorts` fields + `status` enum; `boundFormVersionId`/`stagePipelineVersionId` deferred to 2b/2c. Cohort "open" = `openCohort` → `status: 'open'`.

**Type consistency:** `openCohort(id) → Promise<Cohort>` used identically in wizard + api test. `toIso`/`toDateInput` date helpers consistent across EnrollmentWindowEditor and the wizard. Status label maps use the same `draft|open|closed|completed` keys. MSW create returns the full `Cohort` shape the schema parses.

**Placeholder scan:** no TBD/TODO; every code step shows complete code. Two intentional "match the actual signature" notes (ProgramConfigRoute/ComingSoonPage prop, program status enum) point the implementer to verify an existing file's exact names — these are real existing files, not undefined references.
