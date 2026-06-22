import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ResetPasswordPage } from './ResetPasswordPage'
import { jsonResponse } from '../tests/test-utils'

function renderPage(search: string): void {
  vi.stubGlobal('location', { ...window.location, search })
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ResetPasswordPage />
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

test('missing token/email shows an error with a forgot-password link', () => {
  renderPage('')
  expect(screen.getByText(/link is invalid or incomplete/i)).toBeInTheDocument()
})

test('a valid reset shows a success confirmation', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ message: 'ok' }))
  renderPage('?token=abc&email=a%40b.com')
  fireEvent.change(screen.getByLabelText('New password'), { target: { value: 'super-secret' } })
  fireEvent.click(screen.getByRole('button', { name: /reset password/i }))

  expect(await screen.findByText(/your password has been reset/i)).toBeInTheDocument()
})

test('an invalid token shows an error banner', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['This password reset token is invalid or has expired.'] } } }, 422),
  )
  renderPage('?token=bad&email=a%40b.com')
  fireEvent.change(screen.getByLabelText('New password'), { target: { value: 'super-secret' } })
  fireEvent.click(screen.getByRole('button', { name: /reset password/i }))

  expect(await screen.findByText(/invalid or has expired/i)).toBeInTheDocument()
})
