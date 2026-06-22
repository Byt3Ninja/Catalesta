import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '../app/queryClient'
import { ForgotPasswordPage } from './ForgotPasswordPage'

const meta = {
  title: 'Pages/ForgotPasswordPage',
  component: ForgotPasswordPage,
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <Story />
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof ForgotPasswordPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
