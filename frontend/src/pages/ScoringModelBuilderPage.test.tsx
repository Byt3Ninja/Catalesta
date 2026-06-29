import { render, screen, fireEvent, waitFor, within } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ScoringModelBuilderPage } from './ScoringModelBuilderPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const MODEL = { model_id: 'sm_draft', program_id: 'prog_1', name: 'New scoring model', latest_version: 1, published_version_ids: [], current_draft_version_id: 'smv_draft_1', created_at: 'x' }
const DRAFT = { version_id: 'smv_draft_1', model_id: 'sm_draft', version: 1, status: 'draft', criteria: [], created_at: 'x', published_at: null }

function mockApi() {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/scoring-models/sm_draft/draft')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/scoring-model-versions/smv_draft_1')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/scoring-models/sm_draft')) return Promise.resolve(jsonResponse({ data: MODEL }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}
function renderBuilder(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><ScoringModelBuilderPage modelId="sm_draft" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

// The palette is disabled until the draft has seeded (readOnly while draftId loads),
// so every interaction test must wait for the canvas's data-version-id before clicking.
async function awaitSeeded() {
  await waitFor(() => expect(document.querySelector('[data-version-id="smv_draft_1"]')).toBeTruthy())
}
function lastPatchCriteria(spy: ReturnType<typeof mockApi>): Array<Record<string, unknown>> | null {
  const calls = spy.mock.calls.filter(([i, init]) => String(i).includes('/scoring-models/sm_draft/draft') && (init as RequestInit | undefined)?.method === 'PATCH')
  if (calls.length === 0) return null
  return JSON.parse(String((calls.at(-1)![1] as RequestInit).body)).criteria
}

test('adds a criterion from the palette and shows it on the canvas', async () => {
  mockApi(); renderBuilder()
  await awaitSeeded()
  fireEvent.click(screen.getByRole('button', { name: /add criterion/i }))
  expect(await screen.findByRole('listitem')).toHaveTextContent(/new criterion/i)
})

test('reorders criteria with move up', async () => {
  mockApi(); renderBuilder()
  await awaitSeeded()
  fireEvent.click(screen.getByRole('button', { name: /add criterion/i }))
  fireEvent.click(screen.getByRole('button', { name: /add criterion/i }))
  expect(screen.getAllByRole('listitem')).toHaveLength(2)
  const ups = screen.getAllByRole('button', { name: /move up new criterion/i })
  expect(ups).toHaveLength(2)
  expect(ups[0]).toBeDisabled()
  expect(ups[1]).not.toBeDisabled()
  fireEvent.click(ups[1]) // move 2nd item up
  expect(screen.getAllByRole('listitem')).toHaveLength(2)
  // first item's move-up is still disabled (index 0 invariant)
  expect(screen.getAllByRole('button', { name: /move up new criterion/i })[0]).toBeDisabled()
})

test('autosave does NOT fire on initial load', async () => {
  const fetchSpy = mockApi()
  renderBuilder()
  await waitFor(() => expect(document.querySelector('[data-version-id="smv_draft_1"]')).toBeTruthy())
  vi.useFakeTimers()
  try {
    fetchSpy.mockClear()
    await vi.advanceTimersByTimeAsync(600)
    const patchCalls = fetchSpy.mock.calls.filter(([input, init]) => String(input).includes('/scoring-models/sm_draft/draft') && (init as RequestInit | undefined)?.method === 'PATCH')
    expect(patchCalls).toHaveLength(0)
  } finally { vi.useRealTimers() }
})

test('autosave fires after a user adds a criterion', async () => {
  const fetchSpy = mockApi()
  renderBuilder()
  await waitFor(() => expect(document.querySelector('[data-version-id="smv_draft_1"]')).toBeTruthy())
  fetchSpy.mockClear()
  vi.useFakeTimers()
  try {
    fireEvent.click(screen.getByRole('button', { name: /add criterion/i }))
    await vi.runAllTimersAsync()
    await vi.advanceTimersByTimeAsync(500)
  } finally { vi.useRealTimers() }
  const patchCalls = fetchSpy.mock.calls.filter(([input, init]) => String(input).includes('/scoring-models/sm_draft/draft') && (init as RequestInit | undefined)?.method === 'PATCH')
  expect(patchCalls.length).toBeGreaterThan(0)
  const criteria = lastPatchCriteria(fetchSpy)
  expect(criteria).not.toBeNull()
  expect(criteria).toHaveLength(1)
}, 4000)

test('publish snapshots the draft and flips to read-only', async () => {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/scoring-models/sm_draft/publish')) return Promise.resolve(jsonResponse({ data: { ...DRAFT, status: 'published', published_at: 'y' } }))
    if (url.includes('/scoring-models/sm_draft/draft')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/scoring-model-versions/smv_draft_1')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/scoring-models/sm_draft')) return Promise.resolve(jsonResponse({ data: MODEL }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
  renderBuilder()
  await screen.findByRole('heading', { name: /scoring model builder|new scoring model/i })
  await waitFor(() => expect(screen.getByRole('button', { name: /add criterion/i })).not.toBeDisabled())
  fireEvent.click(screen.getByRole('button', { name: /add criterion/i }))
  await waitFor(() => expect(screen.getByRole('button', { name: /^publish$/i })).not.toBeDisabled())
  fireEvent.click(screen.getByRole('button', { name: /^publish$/i }))
  await waitFor(() => expect(document.querySelector('[data-status="published"]')).toBeTruthy())
})

test('published version is read-only and Edit forks a new editable draft', async () => {
  const PUBLISHED_MODEL = { model_id: 'sm_draft', program_id: 'prog_1', name: 'New scoring model', latest_version: 2, published_version_ids: ['smv_pub_1'], current_draft_version_id: null, created_at: 'x' }
  const FORKED_DRAFT = { version_id: 'smv_fork_1', model_id: 'sm_draft', version: 2, status: 'draft', criteria: [], created_at: 'x', published_at: null }
  const MODEL_AFTER_FORK = { ...PUBLISHED_MODEL, current_draft_version_id: 'smv_fork_1' }
  let fetches = 0
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/scoring-models/sm_draft/fork')) return Promise.resolve(jsonResponse({ data: FORKED_DRAFT }))
    if (url.includes('/scoring-model-versions/smv_fork_1')) return Promise.resolve(jsonResponse({ data: FORKED_DRAFT }))
    if (url.includes('/scoring-models/sm_draft')) { fetches += 1; return Promise.resolve(jsonResponse({ data: fetches > 1 ? MODEL_AFTER_FORK : PUBLISHED_MODEL })) }
    return Promise.resolve(new Response(null, { status: 404 }))
  })
  renderBuilder()
  await screen.findByRole('heading', { name: /scoring model builder|new scoring model/i })
  const editBtn = await screen.findByRole('button', { name: /edit.*new draft/i })
  expect(screen.getByRole('button', { name: /add criterion/i })).toBeDisabled()
  fireEvent.click(editBtn)
  await waitFor(() => expect(screen.getByRole('button', { name: /add criterion/i })).not.toBeDisabled())
})

// ── Task 4: criterion inspector ──────────────────────────────────────────────

test('selecting a criterion shows its label in the inspector', async () => {
  mockApi(); renderBuilder()
  await awaitSeeded()
  // addCriterion auto-selects the new criterion
  fireEvent.click(screen.getByRole('button', { name: /add criterion/i }))
  const inspector = document.querySelector('[aria-label="Criterion settings"]') as HTMLElement
  const labelInput = within(inspector).getByLabelText(/criterion label/i)
  expect(labelInput).toHaveValue('New criterion')
})

test('editing criterion label in inspector updates canvas row', async () => {
  mockApi(); renderBuilder()
  await awaitSeeded()
  fireEvent.click(screen.getByRole('button', { name: /add criterion/i }))
  const inspector = document.querySelector('[aria-label="Criterion settings"]') as HTMLElement
  const labelInput = within(inspector).getByLabelText(/criterion label/i)
  fireEvent.change(labelInput, { target: { value: 'My updated criterion' } })
  await waitFor(() => expect(screen.getByRole('listitem')).toHaveTextContent(/my updated criterion/i))
})

test('editing max_points in inspector updates the canvas badge', async () => {
  mockApi(); renderBuilder()
  await awaitSeeded()
  fireEvent.click(screen.getByRole('button', { name: /add criterion/i }))
  const inspector = document.querySelector('[aria-label="Criterion settings"]') as HTMLElement
  const maxPtsInput = within(inspector).getByLabelText(/max points/i)
  fireEvent.change(maxPtsInput, { target: { value: '25' } })
  await waitFor(() => expect(screen.getByRole('listitem')).toHaveTextContent(/max 25 pts/i))
})

test('autosave PATCH body carries updated max_points from inspector', async () => {
  const fetchSpy = mockApi()
  renderBuilder()
  await awaitSeeded()
  fetchSpy.mockClear()
  vi.useFakeTimers()
  try {
    fireEvent.click(screen.getByRole('button', { name: /add criterion/i }))
    // criterion is auto-selected; inspector renders synchronously with the click
    const inspector = document.querySelector('[aria-label="Criterion settings"]') as HTMLElement
    const maxPtsInput = within(inspector).getByLabelText(/max points/i)
    fireEvent.change(maxPtsInput, { target: { value: '42' } })
    await vi.runAllTimersAsync()
    await vi.advanceTimersByTimeAsync(500)
  } finally { vi.useRealTimers() }
  const crit = lastPatchCriteria(fetchSpy)
  expect(crit).not.toBeNull()
  expect(crit![0]).toMatchObject({ max_points: 42 })
}, 4000)
