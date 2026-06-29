import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { StagePipelineVersionsPage } from './StagePipelineVersionsPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const PIPELINE = { pipeline_id: 'pl_pub', program_id: 'prog_1', name: 'Acceleration pipeline', latest_version: 2, published_version_ids: ['plv_pub_1', 'plv_pub_2'], current_draft_version_id: null, created_at: 'x' }
const stage = (over: Record<string, unknown>) => ({ entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, ...over })
const V1 = { version_id: 'plv_pub_1', pipeline_id: 'pl_pub', version: 1, status: 'published', created_at: 'x', published_at: '2026-06-01T00:00:00Z', stages: [stage({ stage_id: 's_screen', name: 'Screening', type: 'review', order: 0 }), stage({ stage_id: 's_interview', name: 'Interview', type: 'interview', order: 1 })] }
const V2 = { version_id: 'plv_pub_2', pipeline_id: 'pl_pub', version: 2, status: 'published', created_at: 'x', published_at: '2026-06-02T00:00:00Z', stages: [stage({ stage_id: 's_screen', name: 'Screening', type: 'review', order: 0 }), stage({ stage_id: 's_decide', name: 'Decision', type: 'decision', order: 1 })] }
const FORKED = { version_id: 'plv_fork_1', pipeline_id: 'pl_pub', version: 3, status: 'draft', created_at: 'x', published_at: null, stages: [] }

function mockApi() {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/stage-pipeline-versions/plv_pub_1')) return Promise.resolve(jsonResponse({ data: V1 }))
    if (url.includes('/stage-pipeline-versions/plv_pub_2')) return Promise.resolve(jsonResponse({ data: V2 }))
    if (url.includes('/stage-pipelines/pl_pub/versions')) return Promise.resolve(jsonResponse({ data: [V2, V1] }))
    if (url.includes('/stage-pipelines/pl_pub/fork')) return Promise.resolve(jsonResponse({ data: FORKED }))
    if (url.includes('/stage-pipelines/pl_pub')) return Promise.resolve(jsonResponse({ data: PIPELINE }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}
function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><StagePipelineVersionsPage pipelineId="pl_pub" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals() })

test('selecting two versions renders a stage-level diff', async () => {
  mockApi(); renderPage()
  fireEvent.click(await screen.findByRole('checkbox', { name: /version 1/i }))
  fireEvent.click(screen.getByRole('checkbox', { name: /version 2/i }))
  // Interview exists only in v1 (removed); Decision only in v2 (added)
  const removed = await screen.findByText('2. Interview — interview')
  expect(removed.closest('li')).toHaveAttribute('data-diff', 'removed')
  expect(screen.getByText('2. Decision — decision').closest('li')).toHaveAttribute('data-diff', 'added')
})

test('Edit forks a new draft and routes to the builder', async () => {
  const assign = vi.fn()
  vi.stubGlobal('location', { ...window.location, assign })
  const spy = mockApi(); renderPage()
  fireEvent.click(await screen.findByRole('button', { name: /edit.*new draft/i }))
  await waitFor(() => expect(assign).toHaveBeenCalledWith('/programs/prog_1/stages/pl_pub/edit'))
  const forkCall = spy.mock.calls.find((c) => String(c[0]).includes('/stage-pipelines/pl_pub/fork') && c[1]?.method === 'POST')
  expect(forkCall).toBeDefined()
})

test('shows an error state when versions cannot be loaded', async () => {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/stage-pipelines/pl_pub/versions')) return Promise.resolve(new Response(null, { status: 500 }))
    if (url.includes('/stage-pipelines/pl_pub')) return Promise.resolve(jsonResponse({ data: PIPELINE }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
  renderPage()
  expect(await screen.findByText(/could not load versions/i)).toBeInTheDocument()
})
