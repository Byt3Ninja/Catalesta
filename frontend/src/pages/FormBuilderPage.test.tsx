import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormBuilderPage } from './FormBuilderPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const FORM = { id: 'frm_draft', name: 'New form', description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: 'fv_draft_1' }
const DRAFT = { id: 'fv_draft_1', form_id: 'frm_draft', version: 1, status: 'draft', fields: [], created_at: 'x', published_at: null }

function mockApi() {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/forms/frm_draft/draft')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/form-versions/fv_draft_1')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/forms/frm_draft')) return Promise.resolve(jsonResponse({ data: FORM }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}
function renderBuilder(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><FormBuilderPage formId="frm_draft" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

test('adds a field from the palette and shows it on the canvas', async () => {
  mockApi(); renderBuilder()
  await screen.findByRole('heading', { name: /form builder|new form/i })
  fireEvent.click(screen.getByRole('button', { name: /add short text/i }))
  await waitFor(() => expect(screen.getByText(/short text/i)).toBeInTheDocument())
})

test('reorders fields with move up', async () => {
  mockApi(); renderBuilder()
  await screen.findByRole('heading', { name: /form builder|new form/i })
  fireEvent.click(screen.getByRole('button', { name: /add short text/i }))
  fireEvent.click(screen.getByRole('button', { name: /add date/i }))
  const ups = screen.getAllByRole('button', { name: /move up/i })
  fireEvent.click(ups[ups.length - 1]) // move the date field above the text field
  const items = screen.getAllByRole('listitem')
  expect(items[0]).toHaveTextContent(/date/i)
})

test('autosave does NOT fire on initial load (spurious-write guard)', async () => {
  // Reuse the spy returned by mockApi — a second spyOn would overwrite the mock implementation
  const fetchSpy = mockApi()
  renderBuilder()
  // Wait for the draft to be fully seeded — canvas carries data-version-id once seededId is set
  await waitFor(() => expect(document.querySelector('[data-version-id="fv_draft_1"]')).toBeTruthy())
  // Switch to fake timers so we can advance past the debounce without real delays
  vi.useFakeTimers()
  try {
    fetchSpy.mockClear()
    // Advance well past the 400 ms autosave debounce with no user action
    await vi.advanceTimersByTimeAsync(600)
    const patchCalls = fetchSpy.mock.calls.filter(
      ([input, init]) => String(input).includes('/forms/frm_draft/draft') && (init as RequestInit | undefined)?.method === 'PATCH'
    )
    expect(patchCalls).toHaveLength(0)
  } finally {
    vi.useRealTimers()
  }
})

test('autosave fires after a user adds a field', async () => {
  // Reuse the spy returned by mockApi — a second spyOn would overwrite the mock implementation
  const fetchSpy = mockApi()
  renderBuilder()
  // Wait for the draft to be fully seeded — canvas data-version-id is set when seededId state
  // lands after the render-time seed block fires. Only THEN is it safe to click without racing.
  await waitFor(() => expect(document.querySelector('[data-version-id="fv_draft_1"]')).toBeTruthy())
  fetchSpy.mockClear()
  // Switch to fake timers AFTER the real-fetch loading is done. This freezes the event loop's
  // timer queue so the autosave's 400 ms debounce only fires when we advance time explicitly —
  // avoiding the race where waitFor polling delays the effect past our wait window.
  vi.useFakeTimers()
  try {
    // User action: add a field — sets dirtyRef.current = true and queues the effect.
    // With fake timers, fireEvent is still synchronous and React flushes synchronously.
    fireEvent.click(screen.getByRole('button', { name: /add short text/i }))
    // Let React flush the render and the useEffect (effects flush as microtasks after commit).
    // A zero-delay fake-timer advance + flush of pending microtasks does the job.
    await vi.runAllTimersAsync()
    // The effect has now registered the 400 ms autosave setTimeout (fake timer).
    // Advance past the debounce.
    await vi.advanceTimersByTimeAsync(500)
  } finally {
    vi.useRealTimers()
  }
  const patchCalls = fetchSpy.mock.calls.filter(
    ([input, init]) => String(input).includes('/forms/frm_draft/draft') && (init as RequestInit | undefined)?.method === 'PATCH'
  )
  expect(patchCalls.length).toBeGreaterThan(0)
}, 4000)

test('inspector label edit updates the canvas label and flows through updateFields', async () => {
  mockApi(); renderBuilder()
  await screen.findByRole('heading', { name: /form builder|new form/i })
  // Add a short text field
  fireEvent.click(screen.getByRole('button', { name: /add short text/i }))
  // Click the field row to select it (the field row button shows the label)
  const fieldBtn = await screen.findByRole('button', { name: /short text/i })
  fireEvent.click(fieldBtn)
  // Inspector should now be visible — change the label
  const labelInput = screen.getByLabelText(/field label/i)
  fireEvent.change(labelInput, { target: { value: 'Your email' } })
  // The canvas should now reflect the updated label in the list item
  expect(screen.getByText('Your email')).toBeInTheDocument()
})
