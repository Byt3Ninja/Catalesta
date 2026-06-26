import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Link } from './Link'

describe('Link', () => {
  it('renders an anchor with the correct href', () => {
    render(<Link href="/apply">Apply now</Link>)
    const link = screen.getByRole('link', { name: 'Apply now' })
    expect(link).toHaveAttribute('href', '/apply')
  })

  it('renders children correctly', () => {
    render(<Link href="/home">Home</Link>)
    expect(screen.getByRole('link', { name: 'Home' })).toBeInTheDocument()
  })

  it('passes through extra props', () => {
    render(<Link href="/about" target="_blank" rel="noopener noreferrer">About</Link>)
    const link = screen.getByRole('link', { name: 'About' })
    expect(link).toHaveAttribute('target', '_blank')
  })
})
