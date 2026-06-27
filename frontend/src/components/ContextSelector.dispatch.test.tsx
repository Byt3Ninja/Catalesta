import React from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ContextSelector } from './ContextSelector'
import { getActiveRole, setActiveRole } from '../app/active-role'
import { jsonResponse } from '../tests/test-utils'

// Mock the Radix portal-based dropdown so menu items render synchronously in jsdom
vi.mock('./ui/dropdown-menu', () => ({
  DropdownMenu: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  DropdownMenuTrigger: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  DropdownMenuContent: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  DropdownMenuItem: ({
    children,
    onClick,
  }: {
    children: React.ReactNode
    onClick?: () => void
  }) => (
    <button type="button" onClick={onClick}>
      {children}
    </button>
  ),
}))

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

test('clicking a role menu item calls setActiveRole with the correct key', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: ROLES }))

  renderSelector()

  // Wait for both roles to appear as mock buttons
  await screen.findByRole('button', { name: /founder/i })

  // Click the Founder item — this exercises the onClick → setActiveRole wiring
  await userEvent.click(screen.getByRole('button', { name: /founder/i }))

  expect(getActiveRole()).toBe('founder')
})
