import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { VerifyEmailNotice } from './VerifyEmailNotice'

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <VerifyEmailNotice />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('Resend posts and shows a confirmation', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 }))
  renderPage()
  fireEvent.click(screen.getByRole('button', { name: /resend/i }))
  expect(await screen.findByText(/we've sent another/i)).toBeInTheDocument()
})

test('a throttled resend (429) shows the rate-limit message', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 429 }))
  renderPage()
  fireEvent.click(screen.getByRole('button', { name: /resend/i }))
  expect(await screen.findByText(/too many attempts/i)).toBeInTheDocument()
})
