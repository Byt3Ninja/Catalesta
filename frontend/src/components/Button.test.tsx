import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { Button } from './Button'

test('renders its label and is enabled by default', () => {
  render(<Button>Publish</Button>)
  expect(screen.getByRole('button', { name: 'Publish' })).toBeEnabled()
})

test('loading disables the button and marks it aria-busy', () => {
  render(<Button loading>Publish</Button>)
  const btn = screen.getByRole('button')
  expect(btn).toBeDisabled()
  expect(btn).toHaveAttribute('aria-busy', 'true')
})

test('primary variant applies the accent-button class', () => {
  render(<Button variant="primary">Go</Button>)
  expect(screen.getByRole('button')).toHaveClass('ds-btn--primary')
})
