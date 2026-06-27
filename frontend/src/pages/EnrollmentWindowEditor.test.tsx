import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { EnrollmentWindowEditor } from './EnrollmentWindowEditor'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const COHORT = {
  id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026',
  slug: 'spring-2026', status: 'draft' as const, capacity: 20,
  enrollment_opens_at: '2026-07-01T00:00:00+00:00', enrollment_closes_at: '2026-07-31T00:00:00+00:00',
  starts_at: null, ends_at: null, timeline: null,
  created_at: '2026-06-20T10:00:00+00:00', updated_at: '2026-06-20T10:00:00+00:00',
}

function renderEditor(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <EnrollmentWindowEditor cohortId="coh_1" />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('loads the cohort window and saves a PATCH with the edited dates + capacity', async () => {
  const updated = { ...COHORT, enrollment_closes_at: '2026-08-15T00:00:00+00:00', capacity: 30 }
  const spy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT }))      // GET
    .mockResolvedValueOnce(jsonResponse({ data: updated }))     // PATCH
    .mockResolvedValueOnce(jsonResponse({ data: updated }))     // refetch

  renderEditor()

  const capacity = await screen.findByLabelText(/capacity/i)
  fireEvent.change(capacity, { target: { value: '30' } })
  fireEvent.click(screen.getByRole('button', { name: /save window/i }))

  await screen.findByText(/window saved/i)
  const patch = spy.mock.calls.find((c) => c[1]?.method === 'PATCH')?.[1]
  const body = JSON.parse((patch?.body as string) ?? '{}')
  expect(body.capacity).toBe(30)
  expect(body.enrollment_opens_at).toBe('2026-07-01T00:00:00+00:00')
})

test('rejects a close date that is not after the open date', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  renderEditor()
  const closes = await screen.findByLabelText(/closes/i)
  fireEvent.change(closes, { target: { value: '2026-06-01' } })
  fireEvent.click(screen.getByRole('button', { name: /save window/i }))
  expect(await screen.findByText(/close.*after.*open/i)).toBeInTheDocument()
})
