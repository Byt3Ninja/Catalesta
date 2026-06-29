import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { StagePipelineBindingPicker } from './StagePipelineBindingPicker'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const PIPELINES = [
  { pipeline_id: 'pl_pub', program_id: 'prog_1', name: 'Acceleration pipeline', latest_version: 2, published_version_ids: ['plv_pub_1'], current_draft_version_id: 'plv_pub_2', created_at: 'x' },
]
// One published version + one draft (the draft must never be offered)
const VERSIONS = [
  { version_id: 'plv_pub_2', pipeline_id: 'pl_pub', version: 2, status: 'draft', stages: [], created_at: 'x', published_at: null },
  { version_id: 'plv_pub_1', pipeline_id: 'pl_pub', version: 1, status: 'published', stages: [], created_at: 'x', published_at: 'x' },
]
const UPDATED_COHORT = {
  id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026', slug: 'spring-2026',
  status: 'open', capacity: null, enrollment_opens_at: null, enrollment_closes_at: null, starts_at: null, ends_at: null,
  timeline: null, stage_pipeline_version_id: 'plv_pub_1', created_at: 'x', updated_at: 'x',
}

beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

function renderPicker(props: { boundVersionId?: string | null; onBound?: (c: unknown) => void } = {}): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <StagePipelineBindingPicker cohortId="coh_1" programId="prog_1" boundVersionId={props.boundVersionId ?? null} onBound={props.onBound ?? vi.fn()} />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

test('offers only published versions — drafts are excluded', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: PIPELINES }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS }))
  renderPicker()
  expect(await screen.findByRole('option', { name: /Acceleration pipeline v1/i })).toBeInTheDocument()
  const optionTexts = screen.getAllByRole('option').map((o) => o.textContent ?? '')
  expect(optionTexts.some((t) => /v2/i.test(t))).toBe(false) // the draft (v2) is not offered
})

test('binds the selected version and invokes onBound', async () => {
  const onBound = vi.fn()
  const fetchSpy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: PIPELINES }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS }))
    .mockResolvedValueOnce(jsonResponse({ data: UPDATED_COHORT })) // bind POST
  renderPicker({ onBound })
  const select = await screen.findByRole('combobox')
  fireEvent.change(select, { target: { value: 'plv_pub_1' } })
  fireEvent.click(screen.getByRole('button', { name: /^bind$/i }))
  await waitFor(() => expect(onBound).toHaveBeenCalledWith(UPDATED_COHORT))
  const postCall = fetchSpy.mock.calls.find((c) => c[1]?.method === 'POST')
  expect(String(postCall![0])).toContain('/cohorts/coh_1/bind-stage-pipeline')
  expect(JSON.parse((postCall![1]?.body as string) ?? '{}')).toEqual({ stage_pipeline_version_id: 'plv_pub_1' })
})

test('shows the current binding label when boundVersionId is set', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: PIPELINES }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS }))
  renderPicker({ boundVersionId: 'plv_pub_1' })
  await screen.findByRole('option', { name: /Acceleration pipeline v1/i })
  expect(screen.getByTestId('bound-stage-label')).toHaveTextContent(/Acceleration pipeline v1/i)
})
