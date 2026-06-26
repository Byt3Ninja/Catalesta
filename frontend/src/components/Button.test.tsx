import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { Button } from './Button'

test('renders its label and is enabled by default', () => {
  render(<Button>Publish</Button>)
  expect(screen.getByRole('button', { name: 'Publish' })).toBeEnabled()
})

test('loading button is disabled and aria-busy', () => {
  render(<Button loading>Save</Button>)
  const btn = screen.getByRole('button')
  expect(btn).toBeDisabled()
  expect(btn).toHaveAttribute('aria-busy', 'true')
})

test('loading button shows spinner icon instead of label text', () => {
  render(<Button loading>Save</Button>)
  expect(screen.queryByText('Save')).not.toBeInTheDocument()
})

test('secondary variant renders a button element', () => {
  render(<Button variant="secondary">Cancel</Button>)
  expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument()
})
