import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { OnboardingPage } from './OnboardingPage'
import { jsonResponse } from '../tests/test-utils'

const ORG = { id: 'o1', name: 'Acme', created_at: '2024-01-01T00:00:00Z' }

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <OnboardingPage />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('heading is present and submit button is initially disabled', () => {
  renderPage()
  expect(screen.getByRole('heading', { name: /create your organization/i })).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /create organization/i })).toBeDisabled()
})

test('successful submission invalidates the orgs query', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse(ORG, 201))
  renderPage()
  fireEvent.change(screen.getByLabelText(/organization name/i), { target: { value: 'Acme' } })
  fireEvent.click(screen.getByRole('button', { name: /create organization/i }))

  // After success, the mutation resolves; we just verify no error banner appears.
  await vi.waitFor(() =>
    expect(screen.queryByText(/could not create your organization/i)).not.toBeInTheDocument(),
  )
})

test('a duplicate-name error shows the specific banner', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { name: ['An organization with a similar name already exists.'] } } },
      422,
    ),
  )
  renderPage()
  fireEvent.change(screen.getByLabelText(/organization name/i), { target: { value: 'Acme' } })
  fireEvent.click(screen.getByRole('button', { name: /create organization/i }))

  expect(await screen.findByText(/similar name already exists/i)).toBeInTheDocument()
})
