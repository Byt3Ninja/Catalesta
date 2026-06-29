import { render, screen, fireEvent, waitFor, within } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { StagePipelineBuilderPage } from './StagePipelineBuilderPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const PIPELINE = { pipeline_id: 'pl_draft', program_id: 'prog_1', name: 'New pipeline', latest_version: 1, published_version_ids: [], current_draft_version_id: 'plv_draft_1', created_at: 'x' }
const DRAFT = { version_id: 'plv_draft_1', pipeline_id: 'pl_draft', version: 1, status: 'draft', stages: [], created_at: 'x', published_at: null }

function mockApi() {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/stage-pipelines/pl_draft/draft')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/stage-pipeline-versions/plv_draft_1')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/stage-pipelines/pl_draft')) return Promise.resolve(jsonResponse({ data: PIPELINE }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}
function renderBuilder(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><StagePipelineBuilderPage pipelineId="pl_draft" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

// The palette is disabled until the draft has seeded (readOnly while draftId loads),
// so every interaction test must wait for the canvas's data-version-id before clicking.
async function awaitSeeded() {
  await waitFor(() => expect(document.querySelector('[data-version-id="plv_draft_1"]')).toBeTruthy())
}

test('adds a stage from the palette and shows it on the canvas', async () => {
  mockApi(); renderBuilder()
  await awaitSeeded()
  fireEvent.click(screen.getByRole('button', { name: /add review/i }))
  expect(await screen.findByRole('listitem')).toHaveTextContent(/review/i)
})

test('reorders stages with move up', async () => {
  mockApi(); renderBuilder()
  await awaitSeeded()
  fireEvent.click(screen.getByRole('button', { name: /add review/i }))
  fireEvent.click(screen.getByRole('button', { name: /add interview/i }))
  const ups = screen.getAllByRole('button', { name: /move up/i })
  fireEvent.click(ups[ups.length - 1]) // move Interview above Review
  const items = screen.getAllByRole('listitem')
  expect(items[0]).toHaveTextContent(/interview/i)
})

test('selecting a stage drives the inspector frame', async () => {
  mockApi(); renderBuilder()
  await awaitSeeded()
  fireEvent.click(screen.getByRole('button', { name: /add interview/i }))
  // select the stage row — the name button is the first button in the row
  const row = await screen.findByRole('listitem')
  fireEvent.click(within(row).getAllByRole('button')[0])
  // inspector frame renders the selected stage name as a heading (canvas uses a span, not a heading)
  expect(screen.getByRole('heading', { name: 'Interview' })).toBeInTheDocument()
})

test('autosave does NOT fire on initial load', async () => {
  const fetchSpy = mockApi()
  renderBuilder()
  await waitFor(() => expect(document.querySelector('[data-version-id="plv_draft_1"]')).toBeTruthy())
  vi.useFakeTimers()
  try {
    fetchSpy.mockClear()
    await vi.advanceTimersByTimeAsync(600)
    const patchCalls = fetchSpy.mock.calls.filter(([input, init]) => String(input).includes('/stage-pipelines/pl_draft/draft') && (init as RequestInit | undefined)?.method === 'PATCH')
    expect(patchCalls).toHaveLength(0)
  } finally { vi.useRealTimers() }
})

test('autosave fires after a user adds a stage', async () => {
  const fetchSpy = mockApi()
  renderBuilder()
  await waitFor(() => expect(document.querySelector('[data-version-id="plv_draft_1"]')).toBeTruthy())
  fetchSpy.mockClear()
  vi.useFakeTimers()
  try {
    fireEvent.click(screen.getByRole('button', { name: /add review/i }))
    await vi.runAllTimersAsync()
    await vi.advanceTimersByTimeAsync(500)
  } finally { vi.useRealTimers() }
  const patchCalls = fetchSpy.mock.calls.filter(([input, init]) => String(input).includes('/stage-pipelines/pl_draft/draft') && (init as RequestInit | undefined)?.method === 'PATCH')
  expect(patchCalls.length).toBeGreaterThan(0)
}, 4000)

test('publish snapshots the draft and flips to read-only', async () => {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/stage-pipelines/pl_draft/publish')) return Promise.resolve(jsonResponse({ data: { ...DRAFT, status: 'published', published_at: 'y' } }))
    if (url.includes('/stage-pipelines/pl_draft/draft')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/stage-pipeline-versions/plv_draft_1')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/stage-pipelines/pl_draft')) return Promise.resolve(jsonResponse({ data: PIPELINE }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
  renderBuilder()
  await screen.findByRole('heading', { name: /stage builder|new pipeline/i })
  await waitFor(() => expect(screen.getByRole('button', { name: /add review/i })).not.toBeDisabled())
  fireEvent.click(screen.getByRole('button', { name: /add review/i }))
  await waitFor(() => expect(screen.getByRole('button', { name: /^publish$/i })).not.toBeDisabled())
  fireEvent.click(screen.getByRole('button', { name: /^publish$/i }))
  await waitFor(() => expect(document.querySelector('[data-status="published"]')).toBeTruthy())
})

test('published version is read-only and Edit forks a new editable draft', async () => {
  const PUBLISHED_PIPELINE = { pipeline_id: 'pl_draft', program_id: 'prog_1', name: 'New pipeline', latest_version: 2, published_version_ids: ['plv_pub_1'], current_draft_version_id: null, created_at: 'x' }
  const FORKED_DRAFT = { version_id: 'plv_fork_1', pipeline_id: 'pl_draft', version: 2, status: 'draft', stages: [], created_at: 'x', published_at: null }
  const PIPELINE_AFTER_FORK = { ...PUBLISHED_PIPELINE, current_draft_version_id: 'plv_fork_1' }
  let fetches = 0
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/stage-pipelines/pl_draft/fork')) return Promise.resolve(jsonResponse({ data: FORKED_DRAFT }))
    if (url.includes('/stage-pipeline-versions/plv_fork_1')) return Promise.resolve(jsonResponse({ data: FORKED_DRAFT }))
    if (url.includes('/stage-pipelines/pl_draft')) { fetches += 1; return Promise.resolve(jsonResponse({ data: fetches > 1 ? PIPELINE_AFTER_FORK : PUBLISHED_PIPELINE })) }
    return Promise.resolve(new Response(null, { status: 404 }))
  })
  renderBuilder()
  await screen.findByRole('heading', { name: /stage builder|new pipeline/i })
  const editBtn = await screen.findByRole('button', { name: /edit.*new draft/i })
  expect(screen.getByRole('button', { name: /add review/i })).toBeDisabled()
  fireEvent.click(editBtn)
  await waitFor(() => expect(screen.getByRole('button', { name: /add review/i })).not.toBeDisabled())
})
