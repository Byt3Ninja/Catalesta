import type { Meta, StoryObj } from '@storybook/react-vite'
import { Spinner, Skeleton } from './Loading'

const meta = { title: 'Primitives/Loading', component: Spinner } satisfies Meta<typeof Spinner>
export default meta
type Story = StoryObj<typeof meta>

export const SpinnerDefault: Story = {}
export const SkeletonLines: Story = { render: () => <Skeleton lines={3} /> }
