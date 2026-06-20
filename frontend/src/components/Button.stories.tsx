import type { Meta, StoryObj } from '@storybook/react-vite'
import { Button } from './Button'

const meta = {
  title: 'Primitives/Button',
  component: Button,
  args: { children: 'Save' },
} satisfies Meta<typeof Button>
export default meta
type Story = StoryObj<typeof meta>

export const Primary: Story = {}
export const Secondary: Story = { args: { variant: 'secondary', children: 'Cancel' } }
export const Disabled: Story = { args: { disabled: true, children: 'Disabled' } }
export const Loading: Story = { args: { loading: true, children: 'Saving' } }
