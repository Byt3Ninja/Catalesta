import type { Meta, StoryObj } from '@storybook/react-vite'
import { Link } from './Link'

const meta = {
  title: 'Primitives/Link',
  component: Link,
  args: { href: '/apply', children: 'Apply now' },
} satisfies Meta<typeof Link>
export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
