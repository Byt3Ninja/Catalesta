import type { Meta, StoryObj } from '@storybook/react-vite'
import { Banner } from './Banner'

const meta = {
  title: 'Primitives/Banner',
  component: Banner,
  args: { children: 'Heads up — this is an inline alert.' },
} satisfies Meta<typeof Banner>
export default meta
type Story = StoryObj<typeof meta>

export const Info: Story = { args: { variant: 'info' } }
export const Error: Story = { args: { variant: 'error', children: 'Something went wrong.' } }
export const Success: Story = { args: { variant: 'success', children: 'Saved successfully.' } }
