import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { NotificationsPage } from './NotificationsPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

afterEach(() => vi.restoreAllMocks())

const DATA = [
  { id: 'n1', type: 'action', title: 'Review applications', body: 'Overdue.', created_at: '2026-06-26T09:00:00Z', read_at: null, href: '/preview/applicants' },
  { id: 'n2', type: 'system', title: 'Cohort opened', body: 'Now open.', created_at: '2026-06-24T08:00:00Z', read_at: '2026-06-24T10:00:00Z', href: null },
]

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter><NotificationsPage /></MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

test('renders notifications and an unread count', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: DATA }))
  renderPage()
  expect(await screen.findByText('Review applications')).toBeInTheDocument()
  // one unread of two
  expect(screen.getByText(/1 unread/i)).toBeInTheDocument()
})

test('mark-all-read calls the endpoint and refetches', async () => {
  const spy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DATA }))           // initial list
    .mockResolvedValueOnce(new Response(null, { status: 204 }))    // read-all
    .mockResolvedValue(jsonResponse({ data: DATA.map((n) => ({ ...n, read_at: '2026-06-27T00:00:00Z' })) })) // refetch
  renderPage()
  await screen.findByText('Review applications')
  fireEvent.click(screen.getByRole('button', { name: /mark all read/i }))
  await waitFor(() => expect(spy).toHaveBeenCalledWith(expect.stringContaining('/notifications/read-all'), expect.objectContaining({ method: 'POST' })))
  await waitFor(() => expect(screen.getByText(/0 unread/i)).toBeInTheDocument())
})

test('shows an empty state when there are none', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: [] }))
  renderPage()
  expect(await screen.findByText(/no notifications/i)).toBeInTheDocument()
})
