import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { ConsentManagementPage } from './ConsentManagementPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

afterEach(() => vi.restoreAllMocks())

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter><ConsentManagementPage /></MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

const CONSENTS = [
  { category: 'profile', granted: false },
  { category: 'contact', granted: true },
  { category: 'documents', granted: false },
]

test('renders a toggle per category reflecting granted state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: CONSENTS }))
  renderPage()
  const profileToggle = await screen.findByRole('checkbox', { name: /profile details/i })
  expect(profileToggle).not.toBeChecked()
  expect(screen.getByRole('checkbox', { name: /contact information/i })).toBeChecked()
})

test('toggling a category POSTs the new state and refetches', async () => {
  const spy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: CONSENTS }))                 // initial list
    .mockResolvedValueOnce(new Response(null, { status: 204 }))              // POST
    .mockResolvedValue(jsonResponse({ data: CONSENTS.map((c) => c.category === 'profile' ? { ...c, granted: true } : c) })) // refetch
  renderPage()
  fireEvent.click(await screen.findByRole('checkbox', { name: /profile details/i }))
  await waitFor(() => {
    const posted = spy.mock.calls.find(([, init]) => init?.method === 'POST')
    expect(posted).toBeTruthy()
    expect(JSON.parse(String(posted![1]?.body))).toEqual({ category: 'profile', granted: true })
  })
  await waitFor(() => expect(screen.getByRole('checkbox', { name: /profile details/i })).toBeChecked())
})
