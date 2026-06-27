import { afterEach, expect, test } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { RoleSidebar } from './RoleSidebar'
import { setActiveRole } from '../app/active-role'

afterEach(() => setActiveRole('program_manager'))

test('renders the active role nav and updates on switch', () => {
  const { rerender } = render(<MemoryRouter><RoleSidebar /></MemoryRouter>)
  expect(screen.getByRole('link', { name: 'Programs' })).toBeInTheDocument() // program_manager
  setActiveRole('founder')
  rerender(<MemoryRouter><RoleSidebar /></MemoryRouter>)
  expect(screen.queryByRole('link', { name: 'Programs' })).not.toBeInTheDocument()
  expect(screen.getByRole('link', { name: 'My Startup' })).toBeInTheDocument()
})
