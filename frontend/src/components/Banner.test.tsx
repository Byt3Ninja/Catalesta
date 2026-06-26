import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { Banner } from './Banner'

test('error banner is announced via role=alert', () => {
  render(<Banner variant="error">Something failed</Banner>)
  expect(screen.getByRole('alert')).toHaveTextContent('Something failed')
})

test('non-error banner uses role=status', () => {
  render(<Banner variant="success">Saved</Banner>)
  expect(screen.getByRole('status')).toHaveTextContent('Saved')
})

test('info banner uses role=status', () => {
  render(<Banner variant="info">Notice</Banner>)
  expect(screen.getByRole('status')).toHaveTextContent('Notice')
})
