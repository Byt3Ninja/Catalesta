import type { Meta, StoryObj } from '@storybook/react-vite'
import { FormLayout } from './FormLayout'
import { Field } from './Field'
import { Button } from './Button'

const meta = {
  title: 'Primitives/FormLayout',
  component: FormLayout,
  args: { children: null },
} satisfies Meta<typeof FormLayout>
export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {
  render: () => (
    <FormLayout>
      <Field label="Name" />
      <Field label="Email" type="email" help="Required." />
      <Button>Submit</Button>
    </FormLayout>
  ),
}
