import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { Skeleton, Spinner } from './Loading'

test('spinner announces politely with a text fallback', () => {
  render(<Spinner />)
  const status = screen.getByRole('status')
  expect(status).toHaveAttribute('aria-live', 'polite')
  expect(status).toHaveTextContent('Loading…')
})

test('spinner renders a custom label', () => {
  render(<Spinner label="Fetching data…" />)
  expect(screen.getByRole('status')).toHaveTextContent('Fetching data…')
})

test('skeleton is decorative (aria-busy, hidden) with the requested line count', () => {
  const { container } = render(<Skeleton lines={3} />)
  const root = container.firstElementChild
  expect(root).toHaveAttribute('aria-busy', 'true')
  expect(root).toHaveAttribute('aria-hidden', 'true')
  expect(root?.querySelectorAll('span')).toHaveLength(3)
})
