import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { SubmissionsPage } from './SubmissionsPage'
import type { StageOption } from './SubmissionsPage'
import { jsonResponse } from '../tests/test-utils'
import { getStageLeaderboard } from '../api/assessments'

// Module-level mocks — hoisted by vitest before imports execute.
// assessments: the leaderboard query is gated on stage selection so the mock
//   is never invoked by tests that do not select a stage.
// roles: prevents any tree-level role fetch from reaching the real module.
vi.mock('../api/assessments')
vi.mock('../api/roles', () => ({ listMyRoles: vi.fn().mockResolvedValue([]) }))

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

const ROW = {
  reference_number: '01J0SUB',
  cohort_id: '01J0COH',
  submitted_at: '2026-06-21T10:00:00+00:00',
}

const STAGES: StageOption[] = [
  { id: 'stg_screen', name: 'Screening' },
  { id: 'stg_interview', name: 'Interview' },
]

function mockApi(opts: {
  funnel?: { viewed: number; started: number; submitted: number }
  submissions?: unknown[]
  submissionsStatus?: number
}) {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : String(input)
    if (url.includes('/funnel')) {
      return Promise.resolve(
        jsonResponse({ data: opts.funnel ?? { viewed: 0, started: 0, submitted: 0 } }),
      )
    }
    if (url.includes('/submissions')) {
      if (opts.submissionsStatus && opts.submissionsStatus >= 400) {
        return Promise.resolve(new Response(null, { status: opts.submissionsStatus }))
      }
      return Promise.resolve(jsonResponse({ data: opts.submissions ?? [], meta: { total: 0 } }))
    }
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}

function renderPage(
  dir: 'ltr' | 'rtl' = 'ltr',
  theme: 'light' | 'dark' = 'light',
  stages?: StageOption[],
) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider initialDir={dir} initialTheme={theme}>
      <QueryClientProvider client={client}>
        <SubmissionsPage cohortId="01J0COH" organization={ORG} stages={stages} />
      </QueryClientProvider>
    </DirectionProvider>
  )
  return render(ui)
}

afterEach(() => {
  vi.restoreAllMocks() // restores vi.spyOn fetch spy to original
  vi.clearAllMocks()   // resets call counts for vi.mock auto-mocks (restoreAllMocks only covers spies)
})

// ── Existing funnel / list tests ──────────────────────────────────────────────

test('renders the funnel with the approximate-views caveat', async () => {
  mockApi({ funnel: { viewed: 9, started: 4, submitted: 2 }, submissions: [ROW] })
  renderPage()

  const funnel = await screen.findByRole('group', { name: /application funnel/i })
  expect(funnel).toHaveTextContent('9')
  expect(funnel).toHaveTextContent('4')
  expect(funnel).toHaveTextContent('2')
  expect(screen.getByText(/approximate/i)).toBeInTheDocument()
})

test('zero-day: no submissions shows the empty state + copyable share link', async () => {
  mockApi({ funnel: { viewed: 0, started: 0, submitted: 0 }, submissions: [] })
  renderPage()

  expect(await screen.findByText(/no applications yet/i)).toBeInTheDocument()
  // The public apply URL is shown, and a copy control flips to "Copied" only once
  // the clipboard write resolves (jsdom has no Clipboard API, so we provide one).
  expect(screen.getByText(/\/apply\/01J0COH/)).toBeInTheDocument()
  const writeText = vi.fn().mockResolvedValue(undefined)
  Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
  fireEvent.click(screen.getByRole('button', { name: /copy link/i }))
  expect(await screen.findByRole('button', { name: /copied/i })).toBeInTheDocument()
  expect(writeText).toHaveBeenCalledWith(expect.stringContaining('/apply/01J0COH'))
})

test('lists submissions with a focusable open-detail link', async () => {
  mockApi({ funnel: { viewed: 3, started: 2, submitted: 1 }, submissions: [ROW] })
  renderPage()

  const link = await screen.findByRole('link', { name: /open detail/i })
  expect(link).toHaveAttribute('href', '/cohorts/01J0COH/submissions/01J0SUB')
})

test('submissions load failure shows an error with retry', async () => {
  mockApi({ funnel: { viewed: 0, started: 0, submitted: 0 }, submissionsStatus: 500 })
  renderPage()

  expect(await screen.findByText(/could not load submissions/i)).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument()
})

test('renders in RTL + dark', async () => {
  mockApi({ funnel: { viewed: 1, started: 1, submitted: 0 }, submissions: [] })
  const { container } = renderPage('rtl', 'dark')

  expect(await screen.findByText(/no applications yet/i)).toBeInTheDocument()
  expect(container.querySelector('bdi')).not.toBeNull()
})

// ── Leaderboard tests ─────────────────────────────────────────────────────────

test('leaderboard: not fetched at mount (no-idle-fetch invariant)', async () => {
  mockApi({ funnel: { viewed: 0, started: 0, submitted: 0 }, submissions: [] })
  renderPage('ltr', 'light', STAGES)

  // Wait for page to settle on the submissions list view
  await screen.findByText(/no applications yet/i)

  // The leaderboard query must not have fired — no stage was selected
  expect(vi.mocked(getStageLeaderboard)).not.toHaveBeenCalled()
})

test('leaderboard: renders ranked rows with count, spread, and disqualified flag', async () => {
  const leaderboardData = [
    { application_id: 'app_1', mean: 8.5, model_max: 10, count: 2, min: 8, max: 9, disqualified: false },
    { application_id: 'app_2', mean: 7.0, model_max: 10, count: 1, min: 7, max: 7, disqualified: true },
    { application_id: 'app_3', mean: 5.0, model_max: 10, count: 2, min: 4, max: 6, disqualified: false },
  ]
  vi.mocked(getStageLeaderboard).mockResolvedValue(leaderboardData)
  mockApi({ funnel: { viewed: 3, started: 2, submitted: 3 }, submissions: [] })

  renderPage('ltr', 'light', STAGES)

  // Switch to leaderboard view
  fireEvent.click(screen.getByRole('button', { name: /leaderboard/i }))

  // Select Screening stage
  const select = screen.getByRole('combobox', { name: /stage/i })
  fireEvent.change(select, { target: { value: 'stg_screen' } })

  // Table appears after query resolves
  const table = await screen.findByRole('table', { name: /stage leaderboard/i })
  expect(table).toBeInTheDocument()

  const rows = screen.getAllByRole('row')
  // Row 0 is the header; data rows start at index 1.
  // Rank 1 — app_1: mean 8.50 / max 10.00, count 2, spread 8.00–9.00
  expect(rows[1]).toHaveTextContent('8.50')
  expect(rows[1]).toHaveTextContent('10.00')
  expect(rows[1]).toHaveTextContent('2')
  expect(rows[1]).toHaveTextContent('8.00')
  expect(rows[1]).toHaveTextContent('9.00')
  expect(rows[1]).not.toHaveTextContent('Disqualified')

  // Rank 2 — app_2: disqualified flag visible
  expect(rows[2]).toHaveTextContent('7.00')
  expect(rows[2]).toHaveTextContent('Disqualified')

  // Rank 3 — app_3: not disqualified
  expect(rows[3]).toHaveTextContent('5.00')
  expect(rows[3]).not.toHaveTextContent('Disqualified')

  // Application identifiers must not be visible (applicant privacy)
  expect(screen.queryByText('app_1')).not.toBeInTheDocument()
  expect(screen.queryByText('app_2')).not.toBeInTheDocument()
  expect(screen.queryByText('app_3')).not.toBeInTheDocument()

  // API called with correct args
  expect(vi.mocked(getStageLeaderboard)).toHaveBeenCalledWith('01J0COH', 'stg_screen')
})

test('leaderboard: empty state when no submitted scorecards for stage', async () => {
  vi.mocked(getStageLeaderboard).mockResolvedValue([])
  mockApi({ funnel: { viewed: 0, started: 0, submitted: 0 }, submissions: [] })

  renderPage('ltr', 'light', STAGES)
  fireEvent.click(screen.getByRole('button', { name: /leaderboard/i }))
  fireEvent.change(screen.getByRole('combobox', { name: /stage/i }), {
    target: { value: 'stg_screen' },
  })

  expect(await screen.findByText(/no submitted scorecards yet/i)).toBeInTheDocument()
  expect(screen.queryByRole('table')).not.toBeInTheDocument()
})

test('leaderboard: prompt shown when no stage is selected', async () => {
  mockApi({ funnel: { viewed: 0, started: 0, submitted: 0 }, submissions: [] })
  renderPage('ltr', 'light', STAGES)

  fireEvent.click(screen.getByRole('button', { name: /leaderboard/i }))

  expect(screen.getByText(/select a stage to view the leaderboard/i)).toBeInTheDocument()
  expect(screen.queryByRole('table')).not.toBeInTheDocument()
  expect(vi.mocked(getStageLeaderboard)).not.toHaveBeenCalled()
})
