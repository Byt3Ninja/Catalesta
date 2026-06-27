import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { expect, test, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { NotificationPreferencesPage } from './NotificationPreferencesPage'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter><NotificationPreferencesPage /></MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

test('toggling a channel and saving shows a confirmation', () => {
  renderPage()
  const emailToggle = screen.getByRole('checkbox', { name: /email/i })
  fireEvent.click(emailToggle)
  fireEvent.click(screen.getByRole('button', { name: /save preferences/i }))
  expect(screen.getByRole('status')).toHaveTextContent(/saved/i)
})
