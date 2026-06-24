import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { CohortDetailPage } from './CohortDetailPage'
import { jsonResponse } from '../tests/test-utils'

const COHORT = {
  id: '01J0COH',
  organization_id: '01J0ORG',
  program_id: '01J0PROG',
  name: 'Spring 2026',
  slug: 'spring-2026',
  status: 'draft',
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

function renderDetail(cohortId = '01J0COH'): void {
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

test('renders the cohort name, status and a submissions link', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  renderDetail()
  expect(await screen.findByRole('heading', { name: 'Spring 2026' })).toBeInTheDocument()
  expect(screen.getByText('Draft')).toBeInTheDocument()
  expect(screen.getByRole('link', { name: /view submissions/i })).toHaveAttribute(
    'href',
    '/cohorts/01J0COH/submissions',
  )
})

test('a 404 shows the "no longer exists" state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  renderDetail('missing')
  expect(await screen.findByText(/that cohort no longer exists/i)).toBeInTheDocument()
})

test('edit → save sends the changed metadata and updates the view', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT })) // initial load
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, capacity: 50 } })) // PATCH
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, capacity: 50 } })) // refetch
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Capacity'), { target: { value: '50' } })
  fireEvent.change(screen.getByLabelText('Enrollment opens'), { target: { value: '2026-07-01' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  // On success the editor closes and the view (with its Edit button) returns.
  expect(await screen.findByRole('button', { name: 'Edit' })).toBeInTheDocument()
  // The PATCH carried the edited capacity (number) and date.
  const patchInit = fetchSpy.mock.calls.find((c) => c[1]?.method === 'PATCH')?.[1]
  expect(patchInit).toBeDefined()
  const body = JSON.parse((patchInit?.body as string) ?? '{}')
  expect(body.capacity).toBe(50)
  expect(body.enrollment_opens_at).toBe('2026-07-01')
})

test('edit → 422 shows the validation message and stays in edit mode', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT })) // initial load
    .mockResolvedValueOnce(
      jsonResponse(
        { error: { code: 'VALIDATION_ERROR', details: { ends_at: ['The ends at must be a date after starts at.'] } } },
        422,
      ),
    ) // PATCH 422
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByText(/must be a date after/i)).toBeInTheDocument()
  expect(screen.getByLabelText('Cohort name')).toBeInTheDocument() // still editing
})
