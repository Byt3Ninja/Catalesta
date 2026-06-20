import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { AppShell } from './AppShell'

test('renders the work area as main and the rail as complementary', () => {
  render(
    <AppShell rail={<nav>Rail</nav>}>
      <p>Work</p>
    </AppShell>,
  )
  expect(screen.getByRole('main')).toHaveTextContent('Work')
  expect(screen.getByRole('complementary')).toHaveTextContent('Rail')
})

test('renders without a rail', () => {
  render(<AppShell>Just work</AppShell>)
  expect(screen.getByRole('main')).toHaveTextContent('Just work')
  expect(screen.queryByRole('complementary')).toBeNull()
})
