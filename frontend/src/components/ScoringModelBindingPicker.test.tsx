import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ScoringModelBindingPicker } from './ScoringModelBindingPicker'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const MODELS = [
  { model_id: 'sm_pub', program_id: 'prog_1', name: 'Technical Assessment', latest_version: 1, published_version_ids: ['smv_pub_1'], current_draft_version_id: null, created_at: 'x' },
]
// One published version + one draft (the draft must never be offered)
const VERSIONS = [
  { version_id: 'smv_draft_1', model_id: 'sm_pub', version: 2, status: 'draft', criteria: [], created_at: 'x', published_at: null },
  { version_id: 'smv_pub_1', model_id: 'sm_pub', version: 1, status: 'published', criteria: [], created_at: 'x', published_at: 'x' },
]
const UPDATED_COHORT = {
  id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026', slug: 'spring-2026',
  status: 'open', capacity: null, enrollment_opens_at: null, enrollment_closes_at: null, starts_at: null, ends_at: null,
  timeline: null, stage_scoring_model_version_ids: { s_screen: 'smv_pub_1' }, created_at: 'x', updated_at: 'x',
}

beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

function renderPicker(props: { boundVersionId?: string | null; onBound?: (c: unknown) => void } = {}): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ScoringModelBindingPicker
          cohortId="coh_1"
          programId="prog_1"
          stageId="s_screen"
          boundVersionId={props.boundVersionId ?? null}
          onBound={props.onBound ?? vi.fn()}
        />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

test('offers only published versions — drafts are excluded', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: MODELS }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS }))
  renderPicker()
  expect(await screen.findByRole('option', { name: /Technical Assessment v1/i })).toBeInTheDocument()
  const optionTexts = screen.getAllByRole('option').map((o) => o.textContent ?? '')
  expect(optionTexts.some((t) => /v2/i.test(t))).toBe(false) // the draft (v2) is not offered
})

test('binds the selected version and invokes onBound with correct POST body (includes stage_id)', async () => {
  const onBound = vi.fn()
  const fetchSpy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: MODELS }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS }))
    .mockResolvedValueOnce(jsonResponse({ data: UPDATED_COHORT })) // bind POST
  renderPicker({ onBound })
  const select = await screen.findByRole('combobox')
  fireEvent.change(select, { target: { value: 'smv_pub_1' } })
  fireEvent.click(screen.getByRole('button', { name: /^bind$/i }))
  await waitFor(() => expect(onBound).toHaveBeenCalledWith(UPDATED_COHORT))
  const postCall = fetchSpy.mock.calls.find((c) => c[1]?.method === 'POST')
  expect(String(postCall![0])).toContain('/cohorts/coh_1/bind-stage-scoring-model')
  expect(JSON.parse((postCall![1]?.body as string) ?? '{}')).toEqual({
    stage_id: 's_screen',
    scoring_model_version_id: 'smv_pub_1',
  })
})

test('shows the current binding label when boundVersionId is set', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: MODELS }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS }))
  renderPicker({ boundVersionId: 'smv_pub_1' })
  await screen.findByRole('option', { name: /Technical Assessment v1/i })
  expect(screen.getByTestId('bound-scoring-label')).toHaveTextContent(/Technical Assessment v1/i)
})
