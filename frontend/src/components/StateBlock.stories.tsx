import type { Meta, StoryObj } from '@storybook/react-vite'
import { StateBlock } from './StateBlock'
import { Link } from './Link'

const meta = {
  title: 'Primitives/StateBlock',
  component: StateBlock,
  args: { variant: 'empty', message: 'No submissions yet.' },
} satisfies Meta<typeof StateBlock>
export default meta
type Story = StoryObj<typeof meta>

export const Empty: Story = { args: { action: <Link href="#">Share the link</Link> } }
export const ErrorState: Story = { args: { variant: 'error', message: 'Could not load submissions.' } }
export const Offline: Story = { args: { variant: 'offline', message: 'You appear to be offline.' } }
