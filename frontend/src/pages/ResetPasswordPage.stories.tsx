import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '../app/queryClient'
import { ResetPasswordPage } from './ResetPasswordPage'

const meta = {
  title: 'Pages/ResetPasswordPage',
  component: ResetPasswordPage,
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <Story />
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof ResetPasswordPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
