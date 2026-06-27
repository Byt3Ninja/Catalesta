import { afterEach, expect, test, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ContextSelector } from './ContextSelector'
import { setActiveRole, getActiveRole } from '../app/active-role'
import { jsonResponse } from '../tests/test-utils'

const ROLES = [
  { key: 'program_manager', label: 'Program Manager' },
  { key: 'founder', label: 'Founder' },
]

function renderSelector() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ContextSelector />
      </QueryClientProvider>
    </MemoryRouter>,
  )
}

afterEach(() => {
  setActiveRole('program_manager')
  vi.restoreAllMocks()
})

test('shows the active role label after roles load', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: ROLES }),
  )

  renderSelector()

  // Once data resolves the active label renders in the dropdown trigger
  expect(await screen.findByRole('button', { name: /program manager/i })).toBeInTheDocument()
})

test('switching role via setActiveRole updates the displayed label', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: ROLES }),
  )

  const { rerender } = renderSelector()

  // Wait for roles to resolve
  await screen.findByRole('button', { name: /program manager/i })

  // Switch role via the store (mirrors how a menu-item click would)
  setActiveRole('founder')
  expect(getActiveRole()).toBe('founder')

  // Re-render to pick up new store state
  const client2 = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: ROLES }),
  )
  rerender(
    <MemoryRouter>
      <QueryClientProvider client={client2}>
        <ContextSelector />
      </QueryClientProvider>
    </MemoryRouter>,
  )

  await waitFor(() => {
    expect(screen.getByRole('button', { name: /founder/i })).toBeInTheDocument()
  })
})
