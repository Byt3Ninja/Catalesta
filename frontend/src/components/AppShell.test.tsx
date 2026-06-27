import { render, screen } from '@testing-library/react'
import { expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AppShell } from './AppShell'
import { DirectionProvider } from '../app/DirectionProvider'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

function makeClient() {
  return new QueryClient({ defaultOptions: { queries: { retry: false } } })
}

test('renders brand, rail content, and children', () => {
  render(
    <DirectionProvider>
      <QueryClientProvider client={makeClient()}>
        <AppShell rail={<nav aria-label="Sections">Programs</nav>}>
          <p>Body</p>
        </AppShell>
      </QueryClientProvider>
    </DirectionProvider>,
  )
  expect(screen.getByText('Catalesta')).toBeInTheDocument()
  expect(screen.getByText('Body')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /switch to (light|dark) theme/i })).toBeInTheDocument()
})

test('renders without a rail', () => {
  render(
    <DirectionProvider>
      <QueryClientProvider client={makeClient()}>
        <AppShell>
          <p>Body</p>
        </AppShell>
      </QueryClientProvider>
    </DirectionProvider>,
  )
  expect(screen.getByText('Body')).toBeInTheDocument()
  expect(screen.queryByRole('button', { name: /open navigation/i })).toBeNull()
  expect(screen.queryByRole('complementary')).toBeNull()
})
