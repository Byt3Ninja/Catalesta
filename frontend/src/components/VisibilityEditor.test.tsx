import { render, screen, fireEvent } from '@testing-library/react'
import { useState } from 'react'
import { expect, test } from 'vitest'
import { VisibilityEditor } from './VisibilityEditor'
import type { FormField, VisibilityRule } from '../schemas/forms'

const PRIOR: FormField[] = [{ id: 'a', type: 'single_select', label: 'A', options: ['Yes', 'No'] }]

function Harness() {
  const [vis, setVis] = useState<VisibilityRule | undefined>(undefined)
  return <VisibilityEditor field={{ id: 'b', type: 'short_text', label: 'B', visibility: vis }} priorFields={PRIOR} onChange={setVis} />
}

test('adding a condition offers only prior fields as triggers', () => {
  render(<Harness />)
  fireEvent.click(screen.getByRole('button', { name: /add condition/i }))
  const trigger = screen.getByLabelText(/when field/i) as HTMLSelectElement
  const optionValues = Array.from(trigger.options).map((o) => o.value).filter(Boolean)
  expect(optionValues).toEqual(['a'])
})

test('first field (no prior fields) shows a no-triggers notice', () => {
  render(<VisibilityEditor field={{ id: 'a', type: 'short_text', label: 'A' }} priorFields={[]} onChange={() => {}} />)
  expect(screen.getByText(/no earlier fields/i)).toBeInTheDocument()
})
