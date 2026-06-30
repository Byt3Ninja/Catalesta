import { render, screen, fireEvent, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, it, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ProgramsPage } from './ProgramsPage'
import { jsonResponse } from '../tests/test-utils'

// ContextSelector (rendered by AppShell) fetches /me/roles; stub it so these
// content tests aren't coupled to the role switcher's query (≤1 role → plain label).
vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

// Existing fixture — type:null added (schema requires the field)
const DRAFT = {
  id: '01J0PROG',
  name: 'Spring Accelerator',
  slug: 'spring-accelerator',
  status: 'draft',
  type: null,
  description: null,
  settings: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

// Fixtures for new tests
const PROGRAM_PUBLISHED = {
  id: 'p1',
  name: 'Spring',
  slug: 'spring',
  status: 'published',
  type: 'accelerator',
  description: null,
  settings: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

const PROGRAM_DRAFT = {
  id: 'p2',
  name: 'Winter Boot',
  slug: 'winter-boot',
  status: 'draft',
  type: null,
  description: null,
  settings: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

const COHORT_P1 = {
  id: 'c1',
  organization_id: '01J0ORG',
  program_id: 'p1',
  name: 'Cohort 1',
  slug: 'cohort-1',
  status: 'open',
  capacity: 30,
  enrollment_opens_at: null,
  enrollment_closes_at: null,
  starts_at: '2025-01-01T00:00:00Z',
  ends_at: '2025-06-01T00:00:00Z',
  timeline: null,
  submissions_count: 12,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

function renderProgramsPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ProgramsPage organization={ORG} />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

// createProgram routes through csrfFetch (PR #26 follow-up). Pre-seed the XSRF
// cookie so the preflight is skipped and sequential fetch mocks stay aligned.
beforeEach(() => {
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})

afterEach(() => {
  vi.restoreAllMocks()
})

// ── Existing tests (updated: +cohorts mock per render) ─────────────────────

test('empty → create → the new program appears in the list', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [] }))             // GET /programs (initial)
    .mockResolvedValueOnce(jsonResponse({ data: [] }))             // GET /cohorts
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT }, 201))     // POST /programs (create)
    .mockResolvedValueOnce(jsonResponse({ data: [DRAFT] }))        // GET /programs (refetch)

  renderProgramsPage()

  expect(await screen.findByText(/no programs yet/i)).toBeInTheDocument()

  fireEvent.change(screen.getByLabelText('Program name'), {
    target: { value: 'Spring Accelerator' },
  })
  fireEvent.click(screen.getByRole('button', { name: /create program/i }))

  expect(await screen.findByText('Spring Accelerator')).toBeInTheDocument()
})

test('a program row links to its detail page', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [DRAFT] }))        // GET /programs
    .mockResolvedValueOnce(jsonResponse({ data: [] }))             // GET /cohorts
  renderProgramsPage()
  const link = await screen.findByRole('link', { name: /spring accelerator/i })
  expect(link).toHaveAttribute('href', '/programs/01J0PROG')
})

test('list rows no longer carry an inline Publish button', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [DRAFT] }))        // GET /programs
    .mockResolvedValueOnce(jsonResponse({ data: [] }))             // GET /cohorts
  renderProgramsPage()
  await screen.findByRole('link', { name: /spring accelerator/i })
  expect(screen.queryByRole('button', { name: /publish/i })).not.toBeInTheDocument()
})

test('the versioning banner does not claim editing creates a new version', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [] }))             // GET /programs
    .mockResolvedValueOnce(jsonResponse({ data: [] }))             // GET /cohorts
  renderProgramsPage()
  await screen.findByText(/no programs yet/i)
  expect(screen.queryByText(/creates a new version/i)).not.toBeInTheDocument()
})

test('create error preserves the entered name and shows a banner', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [] }))             // GET /programs
    .mockResolvedValueOnce(jsonResponse({ data: [] }))             // GET /cohorts
    .mockResolvedValueOnce(
      jsonResponse(
        { error: { code: 'VALIDATION_ERROR', details: { name: ['The name has already been taken.'] } } },
        422,
      ),
    )                                                               // POST /programs (422)

  renderProgramsPage()

  await screen.findByText(/no programs yet/i)
  fireEvent.change(screen.getByLabelText('Program name'), {
    target: { value: 'Spring Accelerator' },
  })
  fireEvent.click(screen.getByRole('button', { name: /create program/i }))

  expect(await screen.findByText(/the name has already been taken/i)).toBeInTheDocument()
  expect((screen.getByLabelText('Program name') as HTMLInputElement).value).toBe(
    'Spring Accelerator',
  )
})

// ── New: table + type badge + derived columns ──────────────────────────────

it('renders programs in a table with type badge and derived columns', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [PROGRAM_PUBLISHED] }))  // GET /programs
    .mockResolvedValueOnce(jsonResponse({ data: [COHORT_P1] }))          // GET /cohorts

  renderProgramsPage()
  const table = await screen.findByRole('table')
  expect(table).toBeInTheDocument()
  expect(screen.getByText('Spring')).toBeInTheDocument()
  expect(within(table).getByText('Accelerator')).toBeInTheDocument()  // type badge (scoped away from select <option>)
  expect(within(table).getByText('12')).toBeInTheDocument()           // submissions
  expect(within(table).getByText(/\/\s*30/)).toBeInTheDocument()      // capacity "— / 30"
})

it('filters by status tab', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [PROGRAM_PUBLISHED, PROGRAM_DRAFT] }))  // programs
    .mockResolvedValueOnce(jsonResponse({ data: [] }))                                   // cohorts

  renderProgramsPage()
  await screen.findByText('Spring')
  await userEvent.click(screen.getByRole('tab', { name: 'Draft' }))
  expect(screen.queryByText('Spring')).not.toBeInTheDocument()    // published hidden under Draft tab
})

it('searches by name', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [PROGRAM_PUBLISHED] }))  // programs
    .mockResolvedValueOnce(jsonResponse({ data: [] }))                    // cohorts

  renderProgramsPage()
  await screen.findByText('Spring')
  await userEvent.type(screen.getByRole('searchbox', { name: /search programs/i }), 'zzz')
  expect(screen.queryByText('Spring')).not.toBeInTheDocument()
})

it('shows the empty state when there are no programs', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [] }))   // programs: empty
    .mockResolvedValueOnce(jsonResponse({ data: [] }))   // cohorts: empty

  renderProgramsPage()
  expect(await screen.findByText(/no programs yet/i)).toBeInTheDocument()
})

it('shows an error state with retry when the list fails', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ error: 'server error' }, 500))  // programs: 500
    .mockResolvedValueOnce(jsonResponse({ data: [] }))                     // cohorts

  renderProgramsPage()
  expect(await screen.findByText(/could not load your programs/i)).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument()
})

it('degrades derived columns to — when the cohorts query errors', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [PROGRAM_PUBLISHED] }))        // GET /programs
    .mockResolvedValueOnce(jsonResponse({ error: 'server error' }, 500))       // GET /cohorts: 500

  renderProgramsPage()

  // Program row renders — page did not crash
  expect(await screen.findByText('Spring')).toBeInTheDocument()

  const table = screen.getByRole('table')
  const rows = within(table).getAllByRole('row')
  // There should be a data row (header + at least one data row)
  expect(rows.length).toBeGreaterThan(1)

  // All derived cells in the data row(s) should show — not 0
  const cells = within(table).getAllByRole('cell')
  const cellTexts = cells.map((c) => c.textContent)

  // At least one — present (submissions column degrades)
  expect(cellTexts.some((t) => t === '—')).toBe(true)

  // No cell should show a bare "0" for submissions (the bug we fixed)
  // The submissions cell is the third <td>; assert it is not "0"
  // Find the data row (skip header row)
  const dataRow = rows[1]
  const dataCells = within(dataRow).getAllByRole('cell')
  // cohorts count is dataCells[1], submissions is dataCells[2]
  expect(dataCells[1].textContent).toBe('—')
  expect(dataCells[2].textContent).toBe('—')
})

it('creates a program with a type', async () => {
  let postedBody: Record<string, unknown> | null = null
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [] }))                        // GET /programs
    .mockResolvedValueOnce(jsonResponse({ data: [] }))                        // GET /cohorts
    .mockImplementationOnce(async (_url, init) => {
      postedBody = JSON.parse((init as RequestInit).body as string) as Record<string, unknown>
      return jsonResponse(
        {
          data: {
            id: 'p-new', name: 'New', slug: 'new', status: 'draft',
            type: 'incubator', description: null, settings: null,
            created_at: '2026-06-20T10:00:00+00:00',
            updated_at: '2026-06-20T10:00:00+00:00',
          },
        },
        201,
      )
    })
    .mockResolvedValueOnce(jsonResponse({ data: [] }))                        // GET /programs (refetch)

  renderProgramsPage()
  expect(await screen.findByText(/no programs yet/i)).toBeInTheDocument()

  await userEvent.type(screen.getByLabelText(/program name/i), 'New')
  await userEvent.selectOptions(screen.getByLabelText(/program type/i), 'incubator')
  await userEvent.click(screen.getByRole('button', { name: /create program/i }))

  await waitFor(() => expect(postedBody).not.toBeNull())
  expect(postedBody).toMatchObject({ name: 'New', type: 'incubator' })
})
