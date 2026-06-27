import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { CohortDetailPage } from './CohortDetailPage'
import { jsonResponse } from '../tests/test-utils'

// ContextSelector (rendered by AppShell) fetches /me/roles; stub it so these
// content tests aren't coupled to the role switcher's query (≤1 role → plain label).
vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const COHORT = {
  id: 'coh_1',
  organization_id: '01J0ORG',
  program_id: '01J0PROG',
  name: 'Spring 2026',
  slug: 'spring-2026',
  status: 'open',
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

function renderDetail(cohortId = 'coh_1'): void {
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

test('renders the shadcn status badge and a link to the enrollment window editor', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  renderDetail('coh_1') // COHORT fixture status 'open'
  const badge = await screen.findByText('Open')
  expect(badge).toHaveClass('bg-secondary')
  expect(screen.getByRole('link', { name: /enrollment window/i })).toHaveAttribute('href', '/cohorts/coh_1/enrollment')
})

test('renders the cohort name and status badge', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  renderDetail()
  expect(await screen.findByRole('heading', { name: 'Spring 2026' })).toBeInTheDocument()
  expect(screen.getByText('Open')).toBeInTheDocument()
})

test('a 404 shows the "could not load" error state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  renderDetail('missing')
  expect(await screen.findByText(/could not load this cohort/i)).toBeInTheDocument()
})

test('edit → save sends the changed name and returns to view mode', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT })) // initial load
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, name: 'Summer 2026' } })) // PATCH
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, name: 'Summer 2026' } })) // refetch
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Cohort name'), { target: { value: 'Summer 2026' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  // On success the editor closes and the view (with its Edit button) returns.
  expect(await screen.findByRole('button', { name: 'Edit' })).toBeInTheDocument()
  // The PATCH carried the edited name.
  const patchInit = fetchSpy.mock.calls.find((c) => c[1]?.method === 'PATCH')?.[1]
  expect(patchInit).toBeDefined()
  const body = JSON.parse((patchInit?.body as string) ?? '{}')
  expect(body.name).toBe('Summer 2026')
})

test('edit → 422 shows the validation message and stays in edit mode', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: COHORT })) // initial load
    .mockResolvedValueOnce(
      jsonResponse(
        { error: { code: 'VALIDATION_ERROR', details: { name: ['Please check your entries and try again.'] } } },
        422,
      ),
    ) // PATCH 422
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  // Name is already populated from cohort.name ('Spring 2026'); just submit as-is
  // so Save is enabled and the PATCH fires, returning the 422.
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByText(/please check your entries/i)).toBeInTheDocument()
  expect(screen.getByLabelText('Cohort name')).toBeInTheDocument() // still editing
})
