import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { StateBlock } from './StateBlock'

describe('StateBlock', () => {
  it('shows the message and the next action', () => {
    render(<StateBlock variant="empty" message="No submissions yet." action={<a href="#">Share</a>} />)
    expect(screen.getByText('No submissions yet.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Share' })).toBeInTheDocument()
  })

  it('error variant is announced via role="alert"', () => {
    render(<StateBlock variant="error" message="Could not load." />)
    expect(screen.getByRole('alert')).toHaveTextContent('Could not load.')
  })

  it('non-error variants use role="status"', () => {
    render(<StateBlock variant="offline" message="You are offline." />)
    expect(screen.getByRole('status')).toHaveTextContent('You are offline.')
  })
})
