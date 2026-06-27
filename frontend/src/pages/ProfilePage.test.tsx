import { render, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { ConsentProvider } from '../app/ConsentProvider'
import { ProfilePage } from './ProfilePage'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <ConsentProvider><ProfilePage /></ConsentProvider>
        </MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

/**
 * URL-routing fetch mock: ProfilePage fires multiple queries (profile via
 * ConsentProvider, roles via ContextSelector, etc.), in no guaranteed order —
 * route by path to return fresh Response objects per call, matching the
 * established pattern in ActionCenterPage.test.tsx.
 */
function mockFetch(profileStatus: number, profileBody?: unknown) {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : String(input)
    if (url.includes('/me/profile')) {
      if (profileStatus >= 400) {
        return Promise.resolve(new Response('forbidden', { status: profileStatus }))
      }
      return Promise.resolve(jsonResponse(profileBody ?? {}))
    }
    // All other endpoints (roles, search, etc.) return empty-but-valid responses.
    return Promise.resolve(jsonResponse({ data: [] }))
  })
}

test('renders profile fields when consent is granted', async () => {
  mockFetch(200, { display_name: 'Alice', email: 'alice@catalesta.test' })
  renderPage()
  expect(await screen.findByText('Alice')).toBeInTheDocument()
  expect(screen.getByText('alice@catalesta.test')).toBeInTheDocument()
})

test('shows the neutral consent affordance on 403, not an error', async () => {
  mockFetch(403)
  renderPage()
  expect(await screen.findByRole('link', { name: /manage consent/i })).toBeInTheDocument()
  expect(screen.queryByText(/something went wrong/i)).not.toBeInTheDocument()
})
