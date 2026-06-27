import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { GlobalSearch } from './GlobalSearch'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

function renderSearch(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter><GlobalSearch /></MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

test('does not fetch for an empty query', () => {
  const spy = vi.spyOn(globalThis, 'fetch')
  renderSearch()
  expect(spy).not.toHaveBeenCalled()
})

test('typing a query shows categorized results', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(
    jsonResponse({ data: [{ category: 'programs', items: [{ id: 'prog_1', label: 'FinTech Accelerator 2026', sublabel: 'Published', href: '/programs/prog_1' }] }] }),
  )
  renderSearch()
  fireEvent.change(screen.getByRole('searchbox', { name: /search/i }), { target: { value: 'fintech' } })
  expect(await screen.findByText('FinTech Accelerator 2026')).toBeInTheDocument()
  expect(screen.getByText(/programs/i)).toBeInTheDocument()
})

test('shows a no-results state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: [] }))
  renderSearch()
  fireEvent.change(screen.getByRole('searchbox', { name: /search/i }), { target: { value: 'zzz' } })
  expect(await screen.findByText(/no matches/i)).toBeInTheDocument()
})
