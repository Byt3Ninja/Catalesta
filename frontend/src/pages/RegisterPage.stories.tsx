import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '../app/queryClient'
import { RegisterPage } from './RegisterPage'

const meta = {
  title: 'Pages/RegisterPage',
  component: RegisterPage,
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <Story />
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof RegisterPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
