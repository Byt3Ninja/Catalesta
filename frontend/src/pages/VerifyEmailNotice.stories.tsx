import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '../app/queryClient'
import { VerifyEmailNotice } from './VerifyEmailNotice'

const meta = {
  title: 'Pages/VerifyEmailNotice',
  component: VerifyEmailNotice,
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <Story />
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof VerifyEmailNotice>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
