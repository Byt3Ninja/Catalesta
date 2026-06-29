import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { CohortDetailPage } from './CohortDetailPage'
import { jsonResponse } from '../tests/test-utils'

// Stub forms fetches so the FormBindingPicker in the page doesn't make
// real fetch calls (it renders inside the detail view).
vi.mock('../api/forms', () => ({
  listForms: () => Promise.resolve([]),
  listFormVersions: () => Promise.resolve([]),
}))

// The StagePipelineBindingPicker in the page lists pipelines/versions; stub them
// so the detail-content tests aren't coupled to the binding picker's queries.
// getStagePipelineVersion is also stubbed — it returns a version with 2 stages
// so the per-stage scoring section renders when a pipeline is bound.
const PIPELINE_VERSION_STUB = {
  version_id: 'plv_pub_1',
  pipeline_id: 'pl_pub',
  version: 1,
  status: 'published' as const,
  stages: [
    { stage_id: 's_screen', name: 'Screening', type: 'review', entry_rule: null, exit_rule: null, next_stage_ids: ['s_interview'], depends_on_stage_ids: [], parallel_group: null, order: 0 },
    { stage_id: 's_interview', name: 'Interview', type: 'interview', entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, order: 1 },
  ],
  created_at: 'x',
  published_at: 'x',
}

vi.mock('../api/stages', () => ({
  listStagePipelines: () => Promise.resolve([]),
  listStagePipelineVersions: () => Promise.resolve([]),
  getStagePipelineVersion: () => Promise.resolve(PIPELINE_VERSION_STUB),
}))

// The ScoringModelBindingPicker fetches scoring models and versions; stub them
// so the per-stage scoring section renders without real fetch calls.
vi.mock('../api/assessments', () => ({
  listScoringModels: () => Promise.resolve([]),
  listScoringModelVersions: () => Promise.resolve([]),
}))

// ContextSelector (rendered by AppShell) fetches /me/roles; stub it so these
// content tests aren't coupled to the role switcher's query (≤1 role → plain label).
vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const COHORT = {
  id: 'coh_1',
  organization_id: '01J0ORG',
  program_id: '01J0PROG',
  name: 'Spring 2026',
  slug: 'spring-2026',
  status: 'open',
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

function renderDetail(cohortId = 'coh_1'): void {
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

test('renders the shadcn status badge and a link to the enrollment window editor', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  renderDetail('coh_1') // COHORT fixture status 'open'
  const badge = await screen.findByText('Open')
  expect(badge).toHaveClass('bg-secondary')
  expect(screen.getByRole('link', { name: /enrollment window/i })).toHaveAttribute('href', '/cohorts/coh_1/enrollment')
})

test('renders the cohort name and status badge', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  renderDetail()
  expect(await screen.findByRole('heading', { name: 'Spring 2026' })).toBeInTheDocument()
  expect(screen.getByText('Open')).toBeInTheDocument()
})

test('the stage-pipeline row reflects a bound version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, stage_pipeline_version_id: 'plv_pub_1' } }))
  renderDetail()
  expect(await screen.findByText(/Bound: plv_pub_1/)).toBeInTheDocument()
})

test('per-stage scoring row shows the bound scoring-model version id when map has an entry', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({
      data: {
        ...COHORT,
        stage_pipeline_version_id: 'plv_pub_1',
        stage_scoring_model_version_ids: { s_screen: 'smv_pub_1' },
      },
    }),
  )
  renderDetail()
  // The per-stage scoring section should render after the pipeline version query resolves
  expect(await screen.findByTestId('stage-scoring-bound-s_screen')).toHaveTextContent('Bound: smv_pub_1')
})

test('per-stage scoring row shows "not configured" when no model is bound for a stage', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({
      data: {
        ...COHORT,
        stage_pipeline_version_id: 'plv_pub_1',
        stage_scoring_model_version_ids: null,
      },
    }),
  )
  renderDetail()
  expect(await screen.findByTestId('stage-scoring-bound-s_screen')).toHaveTextContent('Bound: not configured')
})

test('a 404 shows the "could not load" error state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  renderDetail('missing')
  expect(await screen.findByText(/could not load this cohort/i)).toBeInTheDocument()
})

test('edit → save sends the changed name and returns to view mode', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT })) // initial load
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, name: 'Summer 2026' } })) // PATCH
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, name: 'Summer 2026' } })) // refetch
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Cohort name'), { target: { value: 'Summer 2026' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  // On success the editor closes and the view (with its Edit button) returns.
  expect(await screen.findByRole('button', { name: 'Edit' })).toBeInTheDocument()
  // The PATCH carried the edited name.
  const patchInit = fetchSpy.mock.calls.find((c) => c[1]?.method === 'PATCH')?.[1]
  expect(patchInit).toBeDefined()
  const body = JSON.parse((patchInit?.body as string) ?? '{}')
  expect(body.name).toBe('Summer 2026')
})

test('edit → 422 shows the validation message and stays in edit mode', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT })) // initial load
    .mockResolvedValueOnce(
      jsonResponse(
        { error: { code: 'VALIDATION_ERROR', details: { name: ['Please check your entries and try again.'] } } },
        422,
      ),
    ) // PATCH 422
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  // Name is already populated from cohort.name ('Spring 2026'); just submit as-is
  // so Save is enabled and the PATCH fires, returning the 422.
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByText(/please check your entries/i)).toBeInTheDocument()
  expect(screen.getByLabelText('Cohort name')).toBeInTheDocument() // still editing
})
