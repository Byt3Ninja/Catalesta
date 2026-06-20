import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { Field } from './Field'

test('associates label with input and defaults to valid', () => {
  render(<Field label="Full name" />)
  const input = screen.getByLabelText('Full name')
  expect(input).not.toHaveAttribute('aria-invalid')
  expect(input).toHaveAttribute('dir', 'auto')
})

test('error sets aria-invalid and links the message via aria-describedby', () => {
  render(<Field label="Email" error="Required" />)
  const input = screen.getByLabelText('Email')
  expect(input).toHaveAttribute('aria-invalid', 'true')
  const describedBy = input.getAttribute('aria-describedby')
  expect(describedBy).toBeTruthy()
  expect(screen.getByText('Required').id).toBe(describedBy)
})

test('help text is associated when there is no error', () => {
  render(<Field label="Idea" help="One sentence" />)
  const input = screen.getByLabelText('Idea')
  expect(screen.getByText('One sentence').id).toBe(input.getAttribute('aria-describedby'))
})
