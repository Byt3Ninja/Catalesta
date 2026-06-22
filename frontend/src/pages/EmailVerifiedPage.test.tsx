import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { EmailVerifiedPage } from './EmailVerifiedPage'

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <EmailVerifiedPage />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

test('shows confirmation and Continue navigates to /', () => {
  const assign = vi.fn()
  vi.stubGlobal('location', { ...window.location, assign })
  renderPage()
  expect(screen.getByText(/your email is verified/i)).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: /continue/i }))
  expect(assign).toHaveBeenCalledWith('/')
})
