# FE-1: Programs Lifecycle UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add program detail, edit, and clone surfaces on top of the existing list/create/publish, wired to the real backend lifecycle endpoints, using the FE-0 router.

**Architecture:** Master–detail. `/programs` stays the list+create screen (rows now link to detail; inline Publish removed). A new gated `/programs/:programId` route renders `ProgramDetailPage`, which shows the program and hosts Edit (inline name/description), Clone (→ navigate to the new draft), and Publish (draft only). Three new typed API client calls back it.

**Tech Stack:** React 19, react-router-dom v7 (already installed by FE-0), @tanstack/react-query, Zod schemas, Vitest + Testing Library.

## Global Constraints

- Phase-1a: only build flows with a real backend endpoint. All three new calls map to existing routes (`GET/PATCH /programs/{id}`, `POST /programs/{id}/clone`).
- Editable fields are **name + description only**. `settings` is an open map — out of scope.
- Versioning copy must be correct: **publishing** records an immutable version; **editing** changes the live program (audited) and does NOT fork a version. Editing a published program is allowed.
- Pages keep their own `AppShell` — no nav shell / `ConsoleLayout` (deferred FE-0.5).
- Frontend visibility is never authorization: render actions and degrade on `403`; there is no client-side abilities feed.
- Use the existing design-system components only (`AppShell`, `Field`, `Button`, `Banner`, `FormLayout`, `StateBlock`, `Link`, `Spinner`). Do not introduce a second design system.
- **Publish is removed from the list rows** and lives only on the detail screen.
- Commit trailer on every commit: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Run all npm commands from `frontend/`.

---

### Task 1: API client + schemas — `getProgram`, `updateProgram`, `cloneProgram`

**Files:**
- Modify: `frontend/src/schemas/programs.ts` (add three error classes)
- Modify: `frontend/src/api/programs.ts` (add three functions + imports)
- Test: `frontend/src/api/programs.test.ts` (append tests)

**Interfaces:**
- Consumes: existing `programResponseSchema`, `ApiError`, `firstValidationMessage`, `readValidationDetails`, `csrfFetch`, `API_BASE_URL`.
- Produces (later tasks rely on these exact signatures):
  - `getProgram(id: string): Promise<Program>`
  - `updateProgram(id: string, input: { name?: string; description?: string | null }): Promise<Program>`
  - `cloneProgram(id: string, name: string): Promise<Program>`
  - Error classes `GetProgramError` (`NOT_FOUND|UNAUTHENTICATED|UNKNOWN`), `UpdateProgramError` (`VALIDATION|FORBIDDEN|NOT_FOUND|UNAUTHENTICATED|UNKNOWN`), `CloneProgramError` (same codes as Update).

- [ ] **Step 1: Write failing tests** — append to `frontend/src/api/programs.test.ts`:

```ts
// added imports at top of file — extend the existing import line:
import {
  cloneProgram,
  createProgram,
  getProgram,
  listPrograms,
  publishProgram,
  updateProgram,
} from './programs'

test('getProgram returns the program on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: PROGRAM }))
  await expect(getProgram('01J0PROG')).resolves.toMatchObject({ slug: 'spring-accelerator' })
})

test('getProgram maps 404 → NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(getProgram('missing')).rejects.toMatchObject({
    name: 'GetProgramError',
    code: 'NOT_FOUND',
  })
})

test('getProgram maps 401 → UNAUTHENTICATED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(getProgram('01J0PROG')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})

test('updateProgram PATCHes name/description and returns the program on 200', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: { ...PROGRAM, name: 'Renamed' } }))
  await expect(
    updateProgram('01J0PROG', { name: 'Renamed', description: null }),
  ).resolves.toMatchObject({ name: 'Renamed' })
  const init = fetchSpy.mock.calls[0][1]
  expect(init?.method).toBe('PATCH')
  expect(JSON.parse((init?.body as string) ?? '{}')).toEqual({ name: 'Renamed', description: null })
})

test('updateProgram maps 422 → VALIDATION with the first field message', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
      422,
    ),
  )
  await expect(updateProgram('01J0PROG', { name: '' })).rejects.toMatchObject({
    name: 'UpdateProgramError',
    code: 'VALIDATION',
    message: 'The name field is required.',
  })
})

test('updateProgram maps 403 → FORBIDDEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 403 }))
  await expect(updateProgram('01J0PROG', { name: 'x' })).rejects.toMatchObject({ code: 'FORBIDDEN' })
})

test('cloneProgram POSTs the name and returns the new draft on 201', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: { ...PROGRAM, id: '01J0NEW', name: 'Copy' } }, 201))
  await expect(cloneProgram('01J0PROG', 'Copy')).resolves.toMatchObject({ id: '01J0NEW' })
  const init = fetchSpy.mock.calls[0][1]
  expect(init?.method).toBe('POST')
  expect(JSON.parse((init?.body as string) ?? '{}')).toEqual({ name: 'Copy' })
})

test('cloneProgram maps 403 → FORBIDDEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 403 }))
  await expect(cloneProgram('01J0PROG', 'Copy')).rejects.toMatchObject({
    name: 'CloneProgramError',
    code: 'FORBIDDEN',
  })
})
```

- [ ] **Step 2: Run, verify they fail**

Run: `cd frontend && npm run test -- src/api/programs.test.ts`
Expected: FAIL — `getProgram`/`updateProgram`/`cloneProgram` are not exported.

- [ ] **Step 3: Add the error classes** to `frontend/src/schemas/programs.ts` (append after the existing `PublishProgramError`):

```ts
/** Typed get-program error the ProgramDetailPage renders. */
export type GetProgramErrorCode = 'NOT_FOUND' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class GetProgramError extends ApiError<GetProgramErrorCode> {
  constructor(code: GetProgramErrorCode, message?: string) {
    super(code, message)
    this.name = 'GetProgramError'
  }
}

/** Typed update-program error. */
export type UpdateProgramErrorCode =
  | 'VALIDATION'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'UNAUTHENTICATED'
  | 'UNKNOWN'

export class UpdateProgramError extends ApiError<UpdateProgramErrorCode> {
  constructor(code: UpdateProgramErrorCode, message?: string) {
    super(code, message)
    this.name = 'UpdateProgramError'
  }
}

/** Typed clone-program error. */
export type CloneProgramErrorCode =
  | 'VALIDATION'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'UNAUTHENTICATED'
  | 'UNKNOWN'

export class CloneProgramError extends ApiError<CloneProgramErrorCode> {
  constructor(code: CloneProgramErrorCode, message?: string) {
    super(code, message)
    this.name = 'CloneProgramError'
  }
}
```

- [ ] **Step 4: Add the client functions** to `frontend/src/api/programs.ts`. Extend the schema import to include the three new error classes, then append the functions:

```ts
// extend the existing import from '../schemas/programs':
import {
  CloneProgramError,
  CreateProgramError,
  GetProgramError,
  PublishProgramError,
  UpdateProgramError,
  programListResponseSchema,
  programResponseSchema,
  type Program,
} from '../schemas/programs'

/**
 * GET /programs/{id} (auth:sanctum). 404 → the program is gone/never existed.
 * [Source: backend ProgramController::show]
 */
export async function getProgram(id: string): Promise<Program> {
  const response = await fetch(`${API_BASE_URL}/programs/${id}`, {
    credentials: 'include',
  })
  if (response.status === 200) {
    const json: unknown = await response.json()
    return programResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new GetProgramError('UNAUTHENTICATED')
  }
  if (response.status === 404) {
    throw new GetProgramError('NOT_FOUND')
  }
  throw new GetProgramError('UNKNOWN', `get program failed: ${response.status}`)
}

/**
 * PATCH /programs/{id} (auth:sanctum). Mutates the live program in place (audited);
 * works on published programs too — editing does NOT create a new version.
 * [Source: backend ProgramController::update]
 */
export async function updateProgram(
  id: string,
  input: { name?: string; description?: string | null },
): Promise<Program> {
  const response = await csrfFetch(`/programs/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(input),
  })

  if (response.status === 200) {
    const json: unknown = await response.json()
    return programResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new UpdateProgramError('UNAUTHENTICATED')
  }
  if (response.status === 403) {
    throw new UpdateProgramError('FORBIDDEN')
  }
  if (response.status === 404) {
    throw new UpdateProgramError('NOT_FOUND')
  }
  if (response.status === 422) {
    const message = firstValidationMessage(await readValidationDetails(response))
    throw new UpdateProgramError('VALIDATION', message ?? 'Please check your entries and try again.')
  }
  throw new UpdateProgramError('UNKNOWN', `update program failed: ${response.status}`)
}

/**
 * POST /programs/{id}/clone (auth:sanctum). Deep-copies into a new DRAFT; the 201
 * body is the new program. Requires a name.
 * [Source: backend ProgramController::clone]
 */
export async function cloneProgram(id: string, name: string): Promise<Program> {
  const response = await csrfFetch(`/programs/${id}/clone`, {
    method: 'POST',
    body: JSON.stringify({ name }),
  })

  if (response.status === 201) {
    const json: unknown = await response.json()
    return programResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new CloneProgramError('UNAUTHENTICATED')
  }
  if (response.status === 403) {
    throw new CloneProgramError('FORBIDDEN')
  }
  if (response.status === 404) {
    throw new CloneProgramError('NOT_FOUND')
  }
  if (response.status === 422) {
    const message = firstValidationMessage(await readValidationDetails(response))
    throw new CloneProgramError('VALIDATION', message ?? 'Please check the name and try again.')
  }
  throw new CloneProgramError('UNKNOWN', `clone program failed: ${response.status}`)
}
```

> Note: `CreateProgramError` and `PublishProgramError` were already imported; keep them. Do not remove existing functions.

- [ ] **Step 5: Run tests + typecheck + lint**

Run: `cd frontend && npm run test -- src/api/programs.test.ts && npm run typecheck && npm run lint`
Expected: all new + existing programs API tests PASS; typecheck and lint clean.

- [ ] **Step 6: Commit**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta/.claude/worktrees/fe1-programs-lifecycle
git add frontend/src/schemas/programs.ts frontend/src/api/programs.ts frontend/src/api/programs.test.ts
git commit -m "feat(fe): FE-1 — getProgram/updateProgram/cloneProgram API client + typed errors

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: ProgramDetailPage + route wiring

**Files:**
- Create: `frontend/src/pages/ProgramDetailPage.tsx`
- Create: `frontend/src/pages/ProgramDetailPage.test.tsx`
- Modify: `frontend/src/app/App.tsx` (import + `ProgramDetailRoute` wrapper + one `<Route>`)
- Modify: `frontend/src/app/App.test.tsx` (add one route-coverage test)

**Interfaces:**
- Consumes: `getProgram`, `updateProgram`, `cloneProgram`, `publishProgram` (Task 1 + existing); `GetProgramError`, `UpdateProgramError`, `CloneProgramError`, `PublishProgramError`, `type Program`; design-system components; `useNavigate`/`useParams` from `react-router-dom`; the existing `ConsoleGate` (render-prop) in `App.tsx`.
- Produces: `export function ProgramDetailPage({ programId }: { programId: string })` and the route `/programs/:programId`.

- [ ] **Step 1: Write the failing page tests** — create `frontend/src/pages/ProgramDetailPage.test.tsx`:

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
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: DRAFT }))
  renderDetail()
  expect(await screen.findByRole('heading', { name: 'Spring Accelerator' })).toBeInTheDocument()
  expect(screen.getByText('Draft')).toBeInTheDocument()
  expect(screen.getByText('Seed cohort')).toBeInTheDocument()
})

test('a 404 shows the "no longer exists" state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  renderDetail('missing')
  expect(await screen.findByText(/that program no longer exists/i)).toBeInTheDocument()
})

test('edit → save updates the displayed name', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT })) // initial load
    .mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, name: 'Renamed' } })) // PATCH
    .mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, name: 'Renamed' } })) // refetch
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Program name'), { target: { value: 'Renamed' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByRole('heading', { name: 'Renamed' })).toBeInTheDocument()
})

test('edit → 422 shows the validation message and stays in edit mode', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT })) // initial load
    .mockResolvedValueOnce(
      jsonResponse(
        { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
        422,
      ),
    ) // PATCH 422
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Program name'), { target: { value: 'x' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByText(/the name field is required/i)).toBeInTheDocument()
  expect(screen.getByLabelText('Program name')).toBeInTheDocument() // still editing
})

test('clone → navigates to the new draft on success', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT })) // initial load
    .mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, id: '01J0NEW' } }, 201)) // clone
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Clone' }))
  fireEvent.change(screen.getByLabelText('New program name'), { target: { value: 'Copy' } })
  fireEvent.click(screen.getByRole('button', { name: /create copy/i }))

  await vi.waitFor(() => expect(navigateSpy).toHaveBeenCalledWith('/programs/01J0NEW'))
})

test('clone → 403 shows a permission banner', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT })) // initial load
    .mockResolvedValueOnce(new Response(null, { status: 403 })) // clone forbidden
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Clone' }))
  fireEvent.change(screen.getByLabelText('New program name'), { target: { value: 'Copy' } })
  fireEvent.click(screen.getByRole('button', { name: /create copy/i }))

  expect(await screen.findByText(/do not have permission/i)).toBeInTheDocument()
})

test('Publish shows for a draft and is absent for a published program', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: DRAFT }))
  renderDetail()
  expect(await screen.findByRole('button', { name: 'Publish' })).toBeInTheDocument()
})

test('Publish is absent for a published program', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: { ...DRAFT, status: 'published' } }),
  )
  renderDetail()
  await screen.findByRole('heading', { name: 'Spring Accelerator' })
  expect(screen.queryByRole('button', { name: 'Publish' })).not.toBeInTheDocument()
})
```

- [ ] **Step 2: Run, verify they fail**

Run: `cd frontend && npm run test -- src/pages/ProgramDetailPage.test.tsx`
Expected: FAIL — `ProgramDetailPage` module does not exist.

- [ ] **Step 3: Create the page** — `frontend/src/pages/ProgramDetailPage.tsx`:

```tsx
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { cloneProgram, getProgram, publishProgram, updateProgram } from '../api/programs'
import {
  CloneProgramError,
  GetProgramError,
  PublishProgramError,
  UpdateProgramError,
  type Program,
} from '../schemas/programs'

/** Human-readable program status (text, never colour-alone). */
const STATUS_LABEL: Record<Program['status'], string> = {
  draft: 'Draft',
  published: 'Published',
  archived: 'Archived',
  closed: 'Closed',
}

/**
 * Program detail (Story 1.2 / FE-1). Shows one program and hosts its lifecycle
 * actions: inline Edit (name/description), Clone (→ new draft), and Publish (draft
 * only). Editing mutates the live program (audited) — it does NOT create a version;
 * publishing is what records an immutable version. A console surface → AppShell.
 */
export function ProgramDetailPage({ programId }: { programId: string }) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const programQuery = useQuery({
    queryKey: ['program', programId],
    queryFn: () => getProgram(programId),
    retry: false,
  })

  const [editing, setEditing] = useState(false)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [cloning, setCloning] = useState(false)
  const [cloneName, setCloneName] = useState('')

  const invalidate = () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: ['program', programId] }),
      queryClient.invalidateQueries({ queryKey: ['programs'] }),
    ])

  const updateMutation = useMutation({
    mutationFn: () =>
      updateProgram(programId, { name: name.trim(), description: description.trim() || null }),
    onSuccess: async () => {
      setEditing(false)
      await invalidate()
    },
  })

  const publishMutation = useMutation({
    mutationFn: () => publishProgram(programId),
    onSuccess: () => invalidate(),
  })

  const cloneMutation = useMutation({
    mutationFn: () => cloneProgram(programId, cloneName.trim()),
    onSuccess: (clone) => {
      void queryClient.invalidateQueries({ queryKey: ['programs'] })
      navigate(`/programs/${clone.id}`)
    },
  })

  const program = programQuery.data

  const beginEdit = (p: Program) => {
    setName(p.name)
    setDescription(p.description ?? '')
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
      <section aria-labelledby="program-heading">
        <p>
          <Link href="/programs">← Programs</Link>
        </p>

        {programQuery.isLoading ? (
          <Spinner label="Loading program…" />
        ) : programQuery.isError ? (
          renderLoadError(programQuery.error, () => programQuery.refetch())
        ) : program ? (
          <>
            <h1 id="program-heading">
              <bdi>{program.name}</bdi>
            </h1>
            <p>
              <span className="ds-badge" data-status={program.status}>
                {STATUS_LABEL[program.status]}
              </span>{' '}
              <span className="ds-muted">{program.slug}</span>
            </p>

            {renderMutationError(updateMutation.error)}
            {renderMutationError(publishMutation.error)}
            {renderMutationError(cloneMutation.error)}

            {editing ? (
              <form
                noValidate
                onSubmit={(event) => {
                  event.preventDefault()
                  if (name.trim().length > 0) updateMutation.mutate()
                }}
              >
                <FormLayout>
                  <Field
                    label="Program name"
                    name="program-name"
                    required
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                  />
                  <Field
                    label="Description"
                    name="program-description"
                    help="Optional."
                    value={description}
                    onChange={(event) => setDescription(event.target.value)}
                  />
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
                {program.description ? (
                  <p>
                    <bdi>{program.description}</bdi>
                  </p>
                ) : (
                  <p className="ds-muted">No description.</p>
                )}
                <p className="ds-muted">
                  Created {program.created_at} · Updated {program.updated_at}
                </p>
                <Button variant="secondary" onClick={() => beginEdit(program)}>
                  Edit
                </Button>{' '}
                <Button
                  variant="secondary"
                  onClick={() => {
                    setCloneName(`${program.name} (copy)`)
                    setCloning(true)
                  }}
                >
                  Clone
                </Button>
                {program.status === 'draft' ? (
                  <>
                    {' '}
                    <Button loading={publishMutation.isPending} onClick={() => publishMutation.mutate()}>
                      Publish
                    </Button>
                  </>
                ) : null}
              </>
            )}

            {program.status === 'draft' ? (
              <Banner variant="info">
                Publishing records an immutable version of this program. Editing afterward changes the
                live program (and is audited) — it does not create a new version.
              </Banner>
            ) : null}

            {cloning ? (
              <form
                noValidate
                aria-label="Clone program"
                onSubmit={(event) => {
                  event.preventDefault()
                  if (cloneName.trim().length > 0) cloneMutation.mutate()
                }}
              >
                <FormLayout>
                  <Field
                    label="New program name"
                    name="clone-name"
                    required
                    value={cloneName}
                    onChange={(event) => setCloneName(event.target.value)}
                  />
                </FormLayout>
                <Button type="submit" loading={cloneMutation.isPending} disabled={cloneName.trim().length === 0}>
                  Create copy
                </Button>{' '}
                <Button variant="secondary" onClick={() => setCloning(false)}>
                  Cancel
                </Button>
              </form>
            ) : null}
          </>
        ) : null}
      </section>
    </AppShell>
  )
}

function renderLoadError(error: unknown, retry: () => void) {
  if (error instanceof GetProgramError && error.code === 'NOT_FOUND') {
    return (
      <StateBlock
        variant="error"
        message="That program no longer exists."
        action={<Link href="/programs">Back to Programs</Link>}
      />
    )
  }
  return (
    <StateBlock
      variant="error"
      message="We could not load this program."
      action={<Button onClick={retry}>Try again</Button>}
    />
  )
}

function renderMutationError(error: unknown) {
  if (
    !(
      error instanceof UpdateProgramError ||
      error instanceof CloneProgramError ||
      error instanceof PublishProgramError
    )
  ) {
    return error ? <Banner variant="error">Something went wrong. Please try again.</Banner> : null
  }
  switch (error.code) {
    case 'FORBIDDEN':
      return <Banner variant="error">You do not have permission to perform that action.</Banner>
    case 'NOT_FOUND':
      return <Banner variant="error">That program no longer exists.</Banner>
    case 'UNAUTHENTICATED':
      return <Banner variant="error">Your session expired. Please sign in again.</Banner>
    case 'VALIDATION':
      return <Banner variant="error">{error.message}</Banner>
    default:
      return <Banner variant="error">Something went wrong. Please try again.</Banner>
  }
}
```

- [ ] **Step 4: Run the page tests, verify they pass**

Run: `cd frontend && npm run test -- src/pages/ProgramDetailPage.test.tsx`
Expected: PASS (all 8 tests).

- [ ] **Step 5: Wire the route** in `frontend/src/app/App.tsx`. Add the import alongside the other page imports:

```tsx
import { ProgramDetailPage } from '../pages/ProgramDetailPage'
```

Add the wrapper near the other route wrappers (e.g. after `ProgramsRoute`):

```tsx
function ProgramDetailRoute() {
  const { programId } = useParams()
  // Gate admits the console surface; the detail page needs only the id.
  return <ConsoleGate>{() => <ProgramDetailPage programId={programId!} />}</ConsoleGate>
}
```

Add the route inside `<Routes>`, right after the `/programs` route:

```tsx
      <Route path="/programs/:programId" element={<ProgramDetailRoute />} />
```

- [ ] **Step 6: Add route coverage** — append to `frontend/src/app/App.test.tsx` (the `renderRoute` helper and imports already exist from FE-0):

```tsx
test('route /programs/:programId renders the program detail for an org user', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations
    .mockResolvedValueOnce(
      jsonResponse({
        data: {
          id: '01J0PROG',
          name: 'Spring Accelerator',
          slug: 'spring-accelerator',
          status: 'draft',
          description: null,
          settings: null,
          created_at: '2026-06-20T10:00:00+00:00',
          updated_at: '2026-06-20T10:00:00+00:00',
        },
      }),
    ) // getProgram

  renderRoute('/programs/01J0PROG')

  expect(await screen.findByRole('heading', { name: 'Spring Accelerator' })).toBeInTheDocument()
})
```

- [ ] **Step 7: Full gates**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test`
Expected: all green (140 prior + Task 1 API tests + 8 page tests + 1 route test).

- [ ] **Step 8: Commit**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta/.claude/worktrees/fe1-programs-lifecycle
git add frontend/src/pages/ProgramDetailPage.tsx frontend/src/pages/ProgramDetailPage.test.tsx frontend/src/app/App.tsx frontend/src/app/App.test.tsx
git commit -m "feat(fe): FE-1 — ProgramDetailPage (view/edit/clone/publish) + route

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: List page — link rows to detail, remove inline publish, correct banner

**Files:**
- Modify: `frontend/src/pages/ProgramsPage.tsx`
- Modify: `frontend/src/pages/ProgramsPage.test.tsx`

**Interfaces:**
- Consumes: existing `listPrograms`, `createProgram`; `Link` component; `/programs/:programId` route (Task 2).
- Produces: nothing new for later tasks (terminal task).

- [ ] **Step 1: Update the tests first** — edit `frontend/src/pages/ProgramsPage.test.tsx`:

Replace the `'publishing a draft flips its status to Published'` test (it no longer belongs — publish moved to detail) with these two tests, and keep the create/error tests as-is:

```tsx
test('a program row links to its detail page', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [DRAFT] }))
  renderPage()
  const link = await screen.findByRole('link', { name: /spring accelerator/i })
  expect(link).toHaveAttribute('href', '/programs/01J0PROG')
})

test('list rows no longer carry an inline Publish button', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [DRAFT] }))
  renderPage()
  await screen.findByRole('link', { name: /spring accelerator/i })
  expect(screen.queryByRole('button', { name: /publish/i })).not.toBeInTheDocument()
})

test('the versioning banner does not claim editing creates a new version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [] }))
  renderPage()
  await screen.findByText(/no programs yet/i)
  expect(screen.queryByText(/creates a new version/i)).not.toBeInTheDocument()
})
```

- [ ] **Step 2: Run, verify failure**

Run: `cd frontend && npm run test -- src/pages/ProgramsPage.test.tsx`
Expected: FAIL — rows are not links yet; the old banner still says "creates a new version"; (and the removed publish test no longer exists).

- [ ] **Step 3: Edit `ProgramsPage.tsx`.** Make these exact changes:

(a) Update the imports — drop `publishProgram`, drop `PublishProgramError`, add `Link`:

```tsx
import { Link } from '../components/Link'
import { createProgram, listPrograms } from '../api/programs'
import { CreateProgramError, type Program } from '../schemas/programs'
```

(b) Delete the `publishMutation` block and the `renderPublishError` function and its call site (`{renderPublishError(publishMutation.error)}`).

(c) Correct the versioning banner text to:

```tsx
        <Banner variant="info">
          Publishing a program records an immutable version. Editing a published
          program changes the live program (and is audited) — it does not create a
          new version.
        </Banner>
```

(d) Replace the list `<li>` body so the name is a link to detail and there is no inline Publish:

```tsx
            {programs.map((program) => (
              <li key={program.id}>
                <Link href={`/programs/${program.id}`}>
                  <bdi>{program.name}</bdi>
                </Link>{' '}
                <span className="ds-badge" data-status={program.status}>
                  {STATUS_LABEL[program.status]}
                </span>
              </li>
            ))}
```

> After this edit, `publishProgram`, `PublishProgramError`, and `useQueryClient`'s publish usage must have no remaining references. Keep `useQueryClient` (still used by the create mutation). Confirm: `cd frontend && grep -n "publish" src/pages/ProgramsPage.tsx` → no matches.

- [ ] **Step 4: Run the list tests + full gates**

Run: `cd frontend && npm run test -- src/pages/ProgramsPage.test.tsx && npm run typecheck && npm run lint`
Expected: PASS; no unused-import lint errors.

- [ ] **Step 5: Full suite + build**

Run: `cd frontend && npm run test && npm run build`
Expected: whole suite green; production build succeeds.

- [ ] **Step 6: Commit**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta/.claude/worktrees/fe1-programs-lifecycle
git add frontend/src/pages/ProgramsPage.tsx frontend/src/pages/ProgramsPage.test.tsx
git commit -m "feat(fe): FE-1 — list rows link to detail, publish moves to detail, fix versioning copy

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage:**
- `getProgram`/`updateProgram`/`cloneProgram` + typed errors → Task 1. ✓
- Detail route `/programs/:programId` + wrapper → Task 2 Step 5. ✓
- Detail view (name/slug/status/description/timestamps) + states (loading/error/404) → Task 2. ✓
- Inline edit (name+description), Save/Cancel, 422 handling → Task 2. ✓
- Clone (name form → navigate to new draft), 403 handling → Task 2. ✓
- Publish draft-only with explanatory banner → Task 2. ✓
- List rows link to detail; inline publish removed → Task 3. ✓
- Versioning copy corrected (both detail banner in Task 2 and list banner in Task 3) → ✓
- 403 degrades to permission banner; no abilities feed → `renderMutationError`. ✓
- Pages keep own AppShell; no nav shell → both pages use AppShell. ✓
- `settings` editing excluded → only name/description fields. ✓

**2. Placeholder scan:** No TBD/"handle errors"/"similar to Task N" — every code step is complete and self-contained. ✓

**3. Type consistency:** `getProgram(id)`, `updateProgram(id, {name?, description?})`, `cloneProgram(id, name)` and the error class names/codes are identical across Task 1 (definition), Task 2 (consumption in the page + tests), and the API tests. `['program', programId]` / `['programs']` query keys are used consistently. `ProgramDetailPage({ programId })` prop shape matches the route wrapper and both test files. ✓
