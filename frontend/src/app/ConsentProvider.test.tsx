import { render, screen } from '@testing-library/react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConsentProvider } from './ConsentProvider'
import { useConsent } from './consent-context'
import { profileDisplayName } from '../schemas/profile'
import { jsonResponse } from '../tests/test-utils'

function Probe() {
  const consent = useConsent()
  const name = consent.profile ? profileDisplayName(consent.profile) : undefined
  return (
    <div>
      <span data-testid="status">{consent.status}</span>
      <span data-testid="name">{name ?? '—'}</span>
    </div>
  )
}

function renderProbe() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ConsentProvider>
        <Probe />
      </ConsentProvider>
    </QueryClientProvider>,
  )
}

afterEach(() => {
  vi.restoreAllMocks()
})

test('grants → ready state exposes the profile', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ display_name: 'Layla' }))
  renderProbe()
  expect(await screen.findByText('ready')).toBeInTheDocument()
  expect(screen.getByTestId('name')).toHaveTextContent('Layla')
})

test('denies (403) → consent-required state, no profile leaked', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 403 }))
  renderProbe()
  expect(await screen.findByText('consent-required')).toBeInTheDocument()
  expect(screen.getByTestId('name')).toHaveTextContent('—')
})

test('a non-consent failure → error state (distinct from consent-required)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 500 }))
  renderProbe()
  expect(await screen.findByText('error')).toBeInTheDocument()
})

test('useConsent outside a provider throws', () => {
  // Silence the expected React error boundary console output.
  const spy = vi.spyOn(console, 'error').mockImplementation(() => {})
  expect(() => render(<Probe />)).toThrow(/useConsent must be used within a ConsentProvider/)
  spy.mockRestore()
})
