import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { AppShell } from './AppShell'
import { DirectionProvider } from '../app/DirectionProvider'

test('renders brand, rail content, and children', () => {
  render(
    <DirectionProvider>
      <AppShell rail={<nav aria-label="Sections">Programs</nav>}>
        <p>Body</p>
      </AppShell>
    </DirectionProvider>,
  )
  expect(screen.getByText('Catalesta')).toBeInTheDocument()
  expect(screen.getByText('Body')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /switch to (light|dark) theme/i })).toBeInTheDocument()
})

test('renders without a rail', () => {
  render(
    <DirectionProvider>
      <AppShell>
        <p>Body</p>
      </AppShell>
    </DirectionProvider>,
  )
  expect(screen.getByText('Body')).toBeInTheDocument()
  expect(screen.queryByRole('button', { name: /open navigation/i })).toBeNull()
  expect(screen.queryByRole('complementary')).toBeNull()
})
