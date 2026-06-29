import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ScoringModelVersionsPage } from './ScoringModelVersionsPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const MODEL = { model_id: 'sm_pub', program_id: 'prog_1', name: 'Acceleration model', latest_version: 2, published_version_ids: ['smv_pub_1', 'smv_pub_2'], current_draft_version_id: null, created_at: 'x' }
const criterion = (over: Record<string, unknown>) => ({ descriptors: null, ...over })
const V1 = { version_id: 'smv_pub_1', model_id: 'sm_pub', version: 1, status: 'published', created_at: 'x', published_at: '2026-06-01T00:00:00Z', criteria: [criterion({ criterion_id: 'c_team', label: 'Team', max_points: 20 }), criterion({ criterion_id: 'c_product', label: 'Product', max_points: 30 })] }
const V2 = { version_id: 'smv_pub_2', model_id: 'sm_pub', version: 2, status: 'published', created_at: 'x', published_at: '2026-06-02T00:00:00Z', criteria: [criterion({ criterion_id: 'c_team', label: 'Team', max_points: 20 }), criterion({ criterion_id: 'c_traction', label: 'Traction', max_points: 25 })] }
const FORKED = { version_id: 'smv_fork_1', model_id: 'sm_pub', version: 3, status: 'draft', created_at: 'x', published_at: null, criteria: [] }

function mockApi() {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/scoring-model-versions/smv_pub_1')) return Promise.resolve(jsonResponse({ data: V1 }))
    if (url.includes('/scoring-model-versions/smv_pub_2')) return Promise.resolve(jsonResponse({ data: V2 }))
    if (url.includes('/scoring-models/sm_pub/versions')) return Promise.resolve(jsonResponse({ data: [V2, V1] }))
    if (url.includes('/scoring-models/sm_pub/fork')) return Promise.resolve(jsonResponse({ data: FORKED }))
    if (url.includes('/scoring-models/sm_pub')) return Promise.resolve(jsonResponse({ data: MODEL }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}
function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><ScoringModelVersionsPage modelId="sm_pub" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals() })

test('selecting two versions renders a criterion-level diff', async () => {
  mockApi(); renderPage()
  fireEvent.click(await screen.findByRole('checkbox', { name: /version 1/i }))
  fireEvent.click(screen.getByRole('checkbox', { name: /version 2/i }))
  // Product exists only in v1 (removed); Traction only in v2 (added)
  const removed = await screen.findByText('2. Product — max 30')
  expect(removed.closest('li')).toHaveAttribute('data-diff', 'removed')
  expect(screen.getByText('2. Traction — max 25').closest('li')).toHaveAttribute('data-diff', 'added')
})

test('Edit forks a new draft and routes to the builder', async () => {
  const assign = vi.fn()
  vi.stubGlobal('location', { ...window.location, assign })
  const spy = mockApi(); renderPage()
  fireEvent.click(await screen.findByRole('button', { name: /edit.*new draft/i }))
  await waitFor(() => expect(assign).toHaveBeenCalledWith('/programs/prog_1/scoring/sm_pub/edit'))
  const forkCall = spy.mock.calls.find((c) => String(c[0]).includes('/scoring-models/sm_pub/fork') && c[1]?.method === 'POST')
  expect(forkCall).toBeDefined()
})

test('shows an error state when versions cannot be loaded', async () => {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/scoring-models/sm_pub/versions')) return Promise.resolve(new Response(null, { status: 500 }))
    if (url.includes('/scoring-models/sm_pub')) return Promise.resolve(jsonResponse({ data: MODEL }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
  renderPage()
  expect(await screen.findByText(/could not load versions/i)).toBeInTheDocument()
})
