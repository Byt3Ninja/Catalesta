import { render, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ReviewQueuePage } from './ReviewQueuePage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const REVIEWER_ID = 'rev_alice'
const OTHER_REVIEWER_ID = 'rev_bob'

const ASSIGNMENTS = [
  {
    assignment_id: 'asgn_1',
    cohort_id: 'coh_1',
    stage_id: 'stg_1',
    application_id: 'app_1',
    reviewer_id: REVIEWER_ID,
    status: 'pending',
  },
  {
    assignment_id: 'asgn_2',
    cohort_id: 'coh_1',
    stage_id: 'stg_1',
    application_id: 'app_2',
    reviewer_id: REVIEWER_ID,
    status: 'submitted',
  },
  // Another reviewer's assignment — must not appear
  {
    assignment_id: 'asgn_3',
    cohort_id: 'coh_1',
    stage_id: 'stg_1',
    application_id: 'app_3',
    reviewer_id: OTHER_REVIEWER_ID,
    status: 'pending',
  },
]

function renderQueue(reviewerId = REVIEWER_ID): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ReviewQueuePage cohortId="coh_1" stageId="stg_1" reviewerId={reviewerId} />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

afterEach(() => vi.restoreAllMocks())

test('renders masked application labels with status badges and scorecard links', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: ASSIGNMENTS }))
  renderQueue()

  // Masked labels — Application #1 and #2 (only reviewer's 2 apps)
  expect(await screen.findByText('Application #1')).toBeInTheDocument()
  expect(screen.getByText('Application #2')).toBeInTheDocument()

  // Status badges
  expect(screen.getByText('Pending')).toBeInTheDocument()
  expect(screen.getByText('Submitted')).toBeInTheDocument()

  // Scorecard links
  const links = screen.getAllByRole('link', { name: 'Review' })
  expect(links).toHaveLength(2)
  expect(links[0]).toHaveAttribute('href', '/cohorts/coh_1/stages/stg_1/review/app_1')
  expect(links[1]).toHaveAttribute('href', '/cohorts/coh_1/stages/stg_1/review/app_2')
})

test('does not expose other reviewer assignments or applicant identity fields', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: ASSIGNMENTS }))
  renderQueue()

  await screen.findByText('Application #1')

  // The other reviewer's application must not render as a visible row
  // (app_3 is only for rev_bob — no link to it)
  const links = screen.queryAllByRole('link', { name: 'Review' })
  const hrefs = links.map((l) => l.getAttribute('href'))
  expect(hrefs).not.toContain('/cohorts/coh_1/stages/stg_1/review/app_3')

  // No applicant name / email / identity text should be present
  expect(screen.queryByText('rev_alice')).not.toBeInTheDocument()
  expect(screen.queryByText('rev_bob')).not.toBeInTheDocument()
  expect(screen.queryByText('OTHER_REVIEWER_ID')).not.toBeInTheDocument()

  // Only 2 rows for the target reviewer
  expect(screen.queryByText('Application #3')).not.toBeInTheDocument()
})

test('shows a loading spinner while fetching', () => {
  // Return a promise that never resolves so the loading state persists
  vi.spyOn(globalThis, 'fetch').mockReturnValue(new Promise(() => {}) as Promise<Response>)
  renderQueue()
  expect(screen.getByRole('status')).toBeInTheDocument()
})

test('shows an empty state when no assignments for the reviewer', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: [] }))
  renderQueue()
  expect(await screen.findByText('No applications assigned to you yet.')).toBeInTheDocument()
})

test('shows an error state when fetch fails', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 500 }))
  renderQueue()
  expect(await screen.findByText('Could not load your review assignments.')).toBeInTheDocument()
})
