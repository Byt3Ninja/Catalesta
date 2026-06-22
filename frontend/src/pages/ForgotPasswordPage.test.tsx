import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ForgotPasswordPage } from './ForgotPasswordPage'
import { jsonResponse } from '../tests/test-utils'

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ForgotPasswordPage />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('submitting shows a neutral confirmation (no enumeration)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ message: 'ok' }))
  renderPage()
  fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@b.com' } })
  fireEvent.click(screen.getByRole('button', { name: /send reset link/i }))

  expect(await screen.findByText(/if that email is registered/i)).toBeInTheDocument()
})
