import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { LoginPage } from './LoginPage'
import { jsonResponse } from '../tests/test-utils'

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <LoginPage />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

test('wrong credentials show a single generic banner (no enumeration)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['These credentials do not match our records.'] } } }, 422),
  )
  renderPage()
  fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@b.com' } })
  fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'nope' } })
  fireEvent.click(screen.getByRole('button', { name: /^sign in$/i }))

  expect(await screen.findByText(/these details don't match our records/i)).toBeInTheDocument()
  // Enumeration sentinel: the failure must NOT surface as a field-level error on
  // either input (which could hint at which field was wrong / whether the user exists).
  expect(screen.getByLabelText(/email/i)).not.toHaveAttribute('aria-invalid')
  expect(screen.getByLabelText(/password/i)).not.toHaveAttribute('aria-invalid')
})

test('the Startup Gate SSO button is still present', () => {
  renderPage()
  expect(screen.getByRole('button', { name: /startup gate/i })).toBeInTheDocument()
})
