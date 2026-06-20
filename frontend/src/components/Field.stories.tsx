import type { Meta, StoryObj } from '@storybook/react-vite'
import { Field } from './Field'

const meta = {
  title: 'Primitives/Field',
  component: Field,
  args: { label: 'Email', placeholder: 'you@example.com' },
} satisfies Meta<typeof Field>
export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
export const WithHelp: Story = { args: { help: 'We never share it.' } }
export const WithError: Story = { args: { error: 'Enter a valid email.' } }
export const Arabic: Story = { args: { label: 'الاسم', defaultValue: 'محمد' } }
