import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { RegisterPage } from './RegisterPage'
import { jsonResponse } from '../tests/test-utils'

const USER = {
  id: 'u1', email: 'a@b.com', display_name: null, email_verified: false,
  startup_gate_subject_id: null, linked_providers: [], has_password: true,
}

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <RegisterPage />
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

test('successful registration redirects to the captured path', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ user: USER }, 201))
  const assign = vi.fn()
  vi.stubGlobal('location', { ...window.location, assign })
  vi.stubGlobal('sessionStorage', { getItem: () => '/', removeItem: vi.fn(), setItem: vi.fn() })

  renderPage()
  fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@b.com' } })
  fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'super-secret' } })
  fireEvent.click(screen.getByRole('button', { name: /create account/i }))

  await waitFor(() => expect(assign).toHaveBeenCalledWith('/'))
})

test('a taken email shows a field-level message', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['The email has already been taken.'] } } }, 422),
  )
  renderPage()
  fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@b.com' } })
  fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'super-secret' } })
  fireEvent.click(screen.getByRole('button', { name: /create account/i }))

  expect(await screen.findByText(/already been taken/i)).toBeInTheDocument()
})
