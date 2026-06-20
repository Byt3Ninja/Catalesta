import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Link } from './Link'

describe('Link', () => {
  it('renders an anchor with href and the focusable ring class', () => {
    render(<Link href="/apply">Apply now</Link>)
    const link = screen.getByRole('link', { name: 'Apply now' })
    expect(link).toHaveAttribute('href', '/apply')
    expect(link).toHaveClass('ds-focusable')
  })
})
