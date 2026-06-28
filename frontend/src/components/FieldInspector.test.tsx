import { render, screen, fireEvent } from '@testing-library/react'
import { useState } from 'react'
import { expect, test } from 'vitest'
import { DirectionProvider } from '../app/DirectionProvider'
import { FieldInspector } from './FieldInspector'
import type { FormField } from '../schemas/forms'

function Harness({ initial }: { initial: FormField }) {
  const [field, setField] = useState(initial)
  return <DirectionProvider><FieldInspector field={field} onChange={(p) => setField((f) => ({ ...f, ...p }))} /></DirectionProvider>
}

test('editing the label and toggling required patches the field', () => {
  render(<Harness initial={{ id: 'f1', type: 'short_text', label: 'X', required: false }} />)
  fireEvent.change(screen.getByLabelText(/field label/i), { target: { value: 'Email' } })
  expect(screen.getByLabelText(/field label/i)).toHaveValue('Email')
  fireEvent.click(screen.getByLabelText(/required/i))
  expect(screen.getByLabelText(/required/i)).toBeChecked()
})

test('select field shows an options editor', () => {
  render(<Harness initial={{ id: 'f2', type: 'single_select', label: 'Stage', options: ['Idea'] }} />)
  fireEvent.click(screen.getByRole('button', { name: /add option/i }))
  expect(screen.getAllByLabelText(/option \d+/i).length).toBe(2)
})
