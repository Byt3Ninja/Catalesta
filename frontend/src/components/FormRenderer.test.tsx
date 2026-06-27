import { render, screen, fireEvent } from '@testing-library/react'
import { useState } from 'react'
import { expect, test } from 'vitest'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormRenderer } from './FormRenderer'
import type { FormField } from '../schemas/forms'

const FIELDS: FormField[] = [
  { id: 'has_team', type: 'single_select', label: 'Do you have a team?', options: ['Yes', 'No'], required: true },
  { id: 'team_size', type: 'short_text', label: 'Team size', visibility: { match: 'all', conditions: [{ field_id: 'has_team', operator: 'equals', value: 'Yes' }] } },
]

function Harness() {
  const [answers, setAnswers] = useState<Record<string, unknown>>({})
  return <DirectionProvider><FormRenderer fields={FIELDS} answers={answers} onChange={(id, v) => setAnswers((a) => ({ ...a, [id]: v }))} /></DirectionProvider>
}

test('conditional field shows only after the trigger answer is set', () => {
  render(<Harness />)
  expect(screen.queryByText('Team size')).not.toBeInTheDocument()
  fireEvent.click(screen.getByLabelText('Yes'))
  expect(screen.getByText('Team size')).toBeInTheDocument()
})
