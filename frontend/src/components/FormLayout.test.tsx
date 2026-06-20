import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { FormLayout } from './FormLayout'

describe('FormLayout', () => {
  it('renders its children in the form container', () => {
    render(
      <FormLayout>
        <span>child</span>
      </FormLayout>,
    )
    expect(screen.getByText('child')).toBeInTheDocument()
  })
})
