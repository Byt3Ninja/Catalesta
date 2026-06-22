import { render, screen, fireEvent } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import type { ReactElement } from 'react'
import { DirectionProvider } from '../app/DirectionProvider'
import { ApplyPage } from './ApplyPage'
import type { ApplyForm, Receipt } from '../schemas/apply'

const COHORT = 'cohort-123'

const FORM: ApplyForm = {
  open: true,
  cohort_id: COHORT,
  form_version_id: 'v1',
  form: [
    { type: 'short_text', label: 'Startup name', required: true, key: 'name' },
    { type: 'consent', label: 'I agree', required: true, key: 'consent' },
  ],
}

const RECEIPT: Receipt = {
  reference_number: 'REF-2026-0001',
  status: 'submitted',
  cohort_id: COHORT,
  submitted_at: '2026-06-20T10:00:00Z',
}

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function renderPage(cohortId = COHORT): ReactElement {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ApplyPage cohortId={cohortId} />
      </QueryClientProvider>
    </DirectionProvider>
  )
}

// In-memory localStorage so the draft autosave is exercised deterministically,
// independent of the jsdom Storage implementation.
const memoryStore = new Map<string, string>()
const localStorageMock: Storage = {
  getItem: (k) => memoryStore.get(k) ?? null,
  setItem: (k, v) => void memoryStore.set(k, String(v)),
  removeItem: (k) => void memoryStore.delete(k),
  clear: () => memoryStore.clear(),
  key: (i) => [...memoryStore.keys()][i] ?? null,
  get length() {
    return memoryStore.size
  },
}

beforeEach(() => {
  memoryStore.clear()
  vi.stubGlobal('localStorage', localStorageMock)
  // submitApplication now routes through csrfFetch (PR #26 follow-up).
  // Pre-seed the XSRF cookie so the preflight is skipped and sequential fetch mocks stay aligned.
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
  memoryStore.clear()
})

test('renders fields from the form definition (first step)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(FORM))
  render(renderPage())

  expect(await screen.findByText('Startup name *')).toBeInTheDocument()
  expect(screen.getByText('Step 1 of 3')).toBeInTheDocument()
})

test('steps Next through to the confirm step with the no-edit warning', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(FORM))
  render(renderPage())

  await screen.findByText('Startup name *')
  fireEvent.click(screen.getByRole('button', { name: 'Next' })) // -> consent
  fireEvent.click(screen.getByRole('button', { name: 'Next' })) // -> confirm

  expect(screen.getByText('Review and submit')).toBeInTheDocument()
  expect(screen.getByText(/can't edit/i)).toBeInTheDocument()
})

test('autosave restores a draft from localStorage on mount', async () => {
  localStorage.setItem(
    `apply-draft:${COHORT}`,
    JSON.stringify({ answers: { name: 'Acme Inc' }, idempotencyKey: 'fixed-key' }),
  )
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(FORM))
  render(renderPage())

  const input = (await screen.findByLabelText('Startup name *')) as HTMLInputElement
  expect(input.value).toBe('Acme Inc')
})

test('open:false shows the closed state (distinct from an error)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(
    jsonResponse({ ...FORM, open: false, form: null }),
  )
  render(renderPage())

  expect(
    await screen.findByText(/no longer accepting applications/i),
  ).toBeInTheDocument()
})

test('happy submit: 201 shows the receipt with the reference number', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse(FORM))
    .mockResolvedValueOnce(jsonResponse(RECEIPT, 201))

  render(renderPage())
  await screen.findByText('Startup name *')
  fireEvent.click(screen.getByRole('button', { name: 'Next' }))
  fireEvent.click(screen.getByRole('button', { name: 'Next' }))
  fireEvent.click(screen.getByRole('button', { name: /submit application/i }))

  expect(await screen.findByText('Application received')).toBeInTheDocument()
  expect(screen.getByTestId('reference-number')).toHaveTextContent('REF-2026-0001')
  expect(fetchSpy).toHaveBeenCalledTimes(2)
})

test('closed-on-submit (422 COHORT_CLOSED) surfaces a calm closed message', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse(FORM))
    .mockResolvedValueOnce(jsonResponse({ error: { code: 'COHORT_CLOSED' } }, 422))

  render(renderPage())
  await screen.findByText('Startup name *')
  fireEvent.click(screen.getByRole('button', { name: 'Next' }))
  fireEvent.click(screen.getByRole('button', { name: 'Next' }))
  fireEvent.click(screen.getByRole('button', { name: /submit application/i }))

  expect(
    await screen.findByText(/no longer accepting applications/i),
  ).toBeInTheDocument()
})

test('401 prompts the user to sign in', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse(FORM))
    .mockResolvedValueOnce(new Response(null, { status: 401 }))

  render(renderPage())
  await screen.findByText('Startup name *')
  fireEvent.click(screen.getByRole('button', { name: 'Next' }))
  fireEvent.click(screen.getByRole('button', { name: 'Next' }))
  fireEvent.click(screen.getByRole('button', { name: /submit application/i }))

  expect(await screen.findByText(/please sign in/i)).toBeInTheDocument()
  expect(screen.getByRole('link', { name: /sign in/i })).toBeInTheDocument()
})

test('double submit reuses the same Idempotency-Key (dedups to one effective submit)', async () => {
  // First fetch = form. Submit returns 409 IN_FLIGHT once, then 201 on retry.
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse(FORM))
    .mockResolvedValueOnce(jsonResponse({ error: { code: 'IDEMPOTENCY_IN_FLIGHT' } }, 409))
    .mockResolvedValueOnce(jsonResponse(RECEIPT, 201))

  render(renderPage())
  await screen.findByText('Startup name *')
  fireEvent.click(screen.getByRole('button', { name: 'Next' }))
  fireEvent.click(screen.getByRole('button', { name: 'Next' }))

  const submit = screen.getByRole('button', { name: /submit application/i })
  fireEvent.click(submit)
  await screen.findByText(/still processing/i)

  // Retry the same draft.
  fireEvent.click(screen.getByRole('button', { name: /submit application/i }))
  expect(await screen.findByText('Application received')).toBeInTheDocument()

  // Collect the Idempotency-Key from each submit call (calls 2 and 3).
  const submitCalls = fetchSpy.mock.calls.filter(([url]) =>
    String(url).endsWith('/submit'),
  )
  expect(submitCalls).toHaveLength(2)
  const keys = submitCalls.map(([, init]) => {
    // csrfFetch normalises headers via `new Headers(...)`, so read through Headers API.
    return new Headers((init as RequestInit).headers).get('Idempotency-Key')
  })
  expect(keys[0]).toBeTruthy()
  expect(keys[0]).toBe(keys[1]) // same key -> server dedups to same receipt
})

test('confirm-step Back does not lose answers (draft survives navigation)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(FORM))
  render(renderPage())

  const input = (await screen.findByLabelText('Startup name *')) as HTMLInputElement
  fireEvent.change(input, { target: { value: 'Beta Co' } })
  fireEvent.click(screen.getByRole('button', { name: 'Next' }))
  fireEvent.click(screen.getByRole('button', { name: 'Back' }))

  expect((screen.getByLabelText('Startup name *') as HTMLInputElement).value).toBe('Beta Co')
})

test('EN<->AR toggle flips dir and preserves the draft', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(FORM))
  render(renderPage())

  const input = (await screen.findByLabelText('Startup name *')) as HTMLInputElement
  fireEvent.change(input, { target: { value: 'Gamma' } })

  const toggle = screen.getByRole('button', { name: /switch language/i })
  fireEvent.click(toggle)

  expect(document.documentElement.dir).toBe('rtl')
  expect((screen.getByLabelText('Startup name *') as HTMLInputElement).value).toBe('Gamma')
})
