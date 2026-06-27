import { expect, test } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ActionCard } from './ActionCard'
import type { ActionItem } from '../schemas/actionCenter'

const item: ActionItem = {
  id: 'a1', section: 'required_actions', what: 'Assign evaluators',
  why: '3 cohorts await scoring', deadline: 'Jun 30', who: 'You',
  href: '/preview/selection', blocker: null,
}

test('renders what/why/deadline/owner and an open link; omits null blocker', () => {
  render(<ActionCard item={item} />)
  expect(screen.getByText('Assign evaluators')).toBeInTheDocument()
  expect(screen.getByText('3 cohorts await scoring')).toBeInTheDocument()
  expect(screen.getByText(/Jun 30/)).toBeInTheDocument()
  expect(screen.getByRole('link', { name: 'Open' })).toBeInTheDocument()
  expect(screen.queryByText(/Blocked by/)).not.toBeInTheDocument()
})
