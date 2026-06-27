import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { expect, test } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { NotificationPreferencesPage } from './NotificationPreferencesPage'
import { queryClient } from '../app/queryClient'

function renderPage(): void {
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={queryClient}>
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
