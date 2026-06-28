import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { VersionCompare } from './VersionCompare'

test('marks added and removed lines between two versions', () => {
  render(<VersionCompare left={{ label: 'v1', lines: ['1. Name (short_text)', '2. Stage (single_select)'] }} right={{ label: 'v2', lines: ['1. Name (short_text)', '2. Stage (single_select)', '3. Website (short_text)'] }} />)
  expect(screen.getByText(/3\. Website/)).toHaveAttribute('data-diff', 'added')
})
