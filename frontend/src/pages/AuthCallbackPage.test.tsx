import { render, screen, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { AuthCallbackPage } from './AuthCallbackPage'
import { jsonResponse } from '../tests/test-utils'

const SESSION_USER = {
  id: 'u1', email: 'a@b.com', display_name: null, email_verified: true,
  startup_gate_subject_id: 'sg1', linked_providers: [], has_password: false,
}

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <AuthCallbackPage />
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

test('missing state/code params shows an error banner', () => {
  vi.stubGlobal('location', { ...window.location, search: '' })
  renderPage()
  expect(screen.getByText(/we could not complete sign-in/i)).toBeInTheDocument()
})

test('valid params show the spinner and redirect on success', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ user: SESSION_USER }),
  )
  const assign = vi.fn()
  vi.stubGlobal('location', { ...window.location, search: '?state=s1&code=c1', assign })
  vi.stubGlobal('sessionStorage', { getItem: () => '/', removeItem: vi.fn(), setItem: vi.fn() })

  renderPage()
  expect(screen.getByText(/signing you in/i)).toBeInTheDocument()
  await waitFor(() => expect(assign).toHaveBeenCalledWith('/'))
})
