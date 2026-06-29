import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ScorecardPage } from './ScorecardPage'
import { jsonResponse } from '../tests/test-utils'

// AppShell uses listMyRoles — mock it so tests don't need a live roles endpoint.
vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

// ── fixtures ──────────────────────────────────────────────────────────────────

const MODEL_VERSION = {
  version_id: 'smv_test',
  model_id: 'sm_test',
  version: 1,
  status: 'published' as const,
  criteria: [
    { criterion_id: 'c1', label: 'Innovation', max_points: 10, descriptors: null },
    { criterion_id: 'c2', label: 'Market Opportunity', max_points: 10, descriptors: null },
  ],
  created_at: '2026-06-01T00:00:00Z',
  published_at: '2026-06-01T00:00:00Z',
}

const SCORECARD_DRAFT = {
  scorecard_id: 'sc_1',
  cohort_id: 'coh_1',
  stage_id: 'stg_1',
  application_id: 'app_1',
  reviewer_id: 'rev_alice',
  model_version_id: 'smv_test',
  values: { c1: 7 },        // c2 intentionally absent — partial fill
  disqualified: false,
  status: 'draft' as const,
  submitted_at: null,
}

// ── helpers ───────────────────────────────────────────────────────────────────

/**
 * Mock fetch dispatching by URL substring.
 * scorecardPayload = null → 404 (no existing scorecard)
 * scorecardPayload = object → 200 with that object
 */
function mockFetch(
  scorecardPayload: typeof SCORECARD_DRAFT | null,
  modelVersionPayload: typeof MODEL_VERSION,
) {
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
    const url = String(input)
    const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase()

    // CSRF preflight
    if (url.includes('/sanctum/csrf-cookie')) {
      return new Response(null, { status: 204 })
    }
    // Submit endpoint
    if (url.includes('/submit') && method === 'POST') {
      return jsonResponse({
        data: { ...SCORECARD_DRAFT, status: 'submitted', submitted_at: '2026-06-29T12:00:00Z' },
      })
    }
    // Scorecard PATCH (autosave draft)
    if (url.includes('/scorecards/') && method === 'PATCH') {
      const body = JSON.parse((init as RequestInit).body as string) as {
        values?: Record<string, number>
        disqualified?: boolean
        model_version_id?: string
      }
      return jsonResponse({ data: { ...SCORECARD_DRAFT, ...body } })
    }
    // Scorecard GET
    if (url.includes('/scorecards/') && method === 'GET') {
      return scorecardPayload === null
        ? new Response(null, { status: 404 })
        : jsonResponse({ data: scorecardPayload })
    }
    // Scoring model version
    if (url.includes('/scoring-model-versions/') && method === 'GET') {
      return jsonResponse({ data: modelVersionPayload })
    }
    return jsonResponse({})
  })
}

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ScorecardPage
          cohortId="coh_1"
          stageId="stg_1"
          applicationId="app_1"
          reviewerId="rev_alice"
          modelVersionId="smv_test"
        />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

afterEach(() => vi.restoreAllMocks())

// ── tests ─────────────────────────────────────────────────────────────────────

test('renders criterion inputs and live total updates via scoreCard', async () => {
  mockFetch(null, MODEL_VERSION)
  renderPage()

  // Criteria inputs appear
  const input1 = await screen.findByLabelText('Score for Innovation')
  const input2 = screen.getByLabelText('Score for Market Opportunity')
  expect(input1).toBeInTheDocument()
  expect(input2).toBeInTheDocument()

  // Initial total is 0
  expect(screen.getByTestId('score-earned')).toHaveTextContent('0')

  // Enter a score for c1 → total becomes 7
  fireEvent.change(input1, { target: { value: '7' } })
  expect(screen.getByTestId('score-earned')).toHaveTextContent('7')

  // Enter a score for c2 → total becomes 12 (decimal lib, not naive +)
  fireEvent.change(input2, { target: { value: '5' } })
  expect(screen.getByTestId('score-earned')).toHaveTextContent('12')
})

test('submit is disabled while any criterion is blank, enabled when all filled', async () => {
  mockFetch(null, MODEL_VERSION)
  renderPage()

  await screen.findByLabelText('Score for Innovation')
  const submitBtn = screen.getByRole('button', { name: 'Submit scorecard' })

  // Initially disabled — no criteria scored
  expect(submitBtn).toBeDisabled()

  // Fill only c1 — still disabled
  fireEvent.change(screen.getByLabelText('Score for Innovation'), { target: { value: '5' } })
  expect(submitBtn).toBeDisabled()

  // Fill c2 — now enabled
  fireEvent.change(screen.getByLabelText('Score for Market Opportunity'), { target: { value: '8' } })
  expect(submitBtn).not.toBeDisabled()

  // Clear c1 — disabled again
  fireEvent.change(screen.getByLabelText('Score for Innovation'), { target: { value: '' } })
  expect(submitBtn).toBeDisabled()
})

test('clicking submit fires POST to the /submit endpoint', async () => {
  const fetchSpy = vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
    const url = String(input)
    const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase()

    if (url.includes('/sanctum/csrf-cookie')) return new Response(null, { status: 204 })
    if (url.includes('/submit') && method === 'POST') {
      return jsonResponse({
        data: { ...SCORECARD_DRAFT, values: { c1: 5, c2: 8 }, status: 'submitted', submitted_at: '2026-06-29T12:00:00Z' },
      })
    }
    if (url.includes('/scorecards/') && method === 'PATCH') return jsonResponse({ data: SCORECARD_DRAFT })
    if (url.includes('/scorecards/') && method === 'GET') return new Response(null, { status: 404 })
    if (url.includes('/scoring-model-versions/') && method === 'GET') return jsonResponse({ data: MODEL_VERSION })
    return jsonResponse({})
  })

  renderPage()
  await screen.findByLabelText('Score for Innovation')

  fireEvent.change(screen.getByLabelText('Score for Innovation'), { target: { value: '5' } })
  fireEvent.change(screen.getByLabelText('Score for Market Opportunity'), { target: { value: '8' } })

  const submitBtn = screen.getByRole('button', { name: 'Submit scorecard' })
  expect(submitBtn).not.toBeDisabled()
  fireEvent.click(submitBtn)

  // The /submit POST must fire
  await waitFor(() => {
    const postCalls = fetchSpy.mock.calls.filter(
      ([url, init]) =>
        String(url).includes('/submit') &&
        ((init as RequestInit | undefined)?.method ?? '').toUpperCase() === 'POST',
    )
    expect(postCalls.length).toBeGreaterThan(0)
  })

  // After success, the submitted state is shown
  expect(await screen.findByTestId('submitted-state')).toBeInTheDocument()
})

test('toggling disqualified flag is carried in the autosave draft PATCH body', async () => {
  const patchBodies: Array<Record<string, unknown>> = []

  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input, init) => {
    const url = String(input)
    const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase()

    if (url.includes('/sanctum/csrf-cookie')) return new Response(null, { status: 204 })
    if (url.includes('/scorecards/') && method === 'PATCH') {
      const body = JSON.parse((init as RequestInit).body as string) as Record<string, unknown>
      patchBodies.push(body)
      return jsonResponse({ data: { ...SCORECARD_DRAFT, ...body } })
    }
    if (url.includes('/scorecards/') && method === 'GET') return new Response(null, { status: 404 })
    if (url.includes('/scoring-model-versions/') && method === 'GET') return jsonResponse({ data: MODEL_VERSION })
    return jsonResponse({})
  })

  renderPage()
  await screen.findByLabelText('Score for Innovation')

  // Tick the disqualified checkbox (sets dirtyRef=true and triggers autosave after debounce)
  fireEvent.click(screen.getByLabelText('Disqualify this application'))

  // Wait for the debounced autosave (debounce=500ms, give generous buffer)
  await waitFor(
    () => {
      expect(patchBodies.length).toBeGreaterThan(0)
      expect(patchBodies[patchBodies.length - 1]).toMatchObject({ disqualified: true })
    },
    { timeout: 2000 },
  )
})

test('no applicant identity is rendered (blind evaluation)', async () => {
  mockFetch(SCORECARD_DRAFT, MODEL_VERSION)
  renderPage()

  await screen.findByLabelText('Score for Innovation')

  // Application identifiers must not appear in the DOM
  expect(screen.queryByText('app_1')).not.toBeInTheDocument()
  expect(screen.queryByText('rev_alice')).not.toBeInTheDocument()
  expect(screen.queryByText('coh_1')).not.toBeInTheDocument()
  expect(screen.queryByText('stg_1')).not.toBeInTheDocument()
  expect(screen.queryByText('smv_test')).not.toBeInTheDocument()

  // The blind banner is present
  expect(screen.getByTestId('blind-banner')).toBeInTheDocument()
})

test('shows a spinner while loading', () => {
  vi.spyOn(globalThis, 'fetch').mockReturnValue(new Promise(() => {}) as Promise<Response>)
  renderPage()
  expect(screen.getByRole('status')).toBeInTheDocument()
})

test('shows an error state when scorecard or model version fetch fails', async () => {
  vi.spyOn(globalThis, 'fetch').mockImplementation(async (input) => {
    const url = String(input)
    if (url.includes('/scoring-model-versions/')) return new Response(null, { status: 500 })
    if (url.includes('/scorecards/')) return new Response(null, { status: 404 })
    return jsonResponse({})
  })
  renderPage()
  expect(await screen.findByRole('alert')).toBeInTheDocument()
})

test('seeds existing draft values from a scorecard in the store', async () => {
  // SCORECARD_DRAFT has c1=7 pre-filled; c2 absent.
  mockFetch(SCORECARD_DRAFT, MODEL_VERSION)
  renderPage()

  await screen.findByLabelText('Score for Innovation')

  // c1 should be seeded with 7
  expect(screen.getByLabelText('Score for Innovation')).toHaveValue(7)
  // c2 should be blank
  expect(screen.getByLabelText('Score for Market Opportunity')).toHaveValue(null)
  // Live total reflects only c1
  expect(screen.getByTestId('score-earned')).toHaveTextContent('7')
})
