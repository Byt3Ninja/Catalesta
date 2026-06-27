import { expect, test } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ComingSoonPage } from './ComingSoonPage'

test('shows the section name and a placeholder message', () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={['/preview/applicants']}>
          <Routes><Route path="/preview/:section" element={<ComingSoonPage />} /></Routes>
        </MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>,
  )
  expect(screen.getByRole('heading', { name: /applicants/i, level: 1 })).toBeInTheDocument()
  expect(screen.getByText(/arrives in a later slice/i)).toBeInTheDocument()
})
