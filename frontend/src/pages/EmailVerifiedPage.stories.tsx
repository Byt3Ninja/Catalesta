import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '../app/queryClient'
import { EmailVerifiedPage } from './EmailVerifiedPage'

const meta = {
  title: 'Pages/EmailVerifiedPage',
  component: EmailVerifiedPage,
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <Story />
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof EmailVerifiedPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
