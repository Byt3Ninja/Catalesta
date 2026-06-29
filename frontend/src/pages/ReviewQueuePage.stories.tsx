import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ReviewQueuePage } from './ReviewQueuePage'

const REVIEWER_ID = 'rev_alice'

const SEEDED_ASSIGNMENTS = [
  {
    assignment_id: 'asgn_1',
    cohort_id: 'coh_1',
    stage_id: 'stg_screen',
    application_id: 'app_1',
    reviewer_id: REVIEWER_ID,
    status: 'pending',
  },
  {
    assignment_id: 'asgn_2',
    cohort_id: 'coh_1',
    stage_id: 'stg_screen',
    application_id: 'app_2',
    reviewer_id: REVIEWER_ID,
    status: 'submitted',
  },
  {
    assignment_id: 'asgn_3',
    cohort_id: 'coh_1',
    stage_id: 'stg_screen',
    application_id: 'app_3',
    reviewer_id: REVIEWER_ID,
    status: 'pending',
  },
]

function withProviders(assignments: unknown[]) {
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (async () =>
      new Response(JSON.stringify({ data: assignments }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })) as typeof fetch
    const client = new QueryClient()
    return (
      <DirectionProvider>
        <QueryClientProvider client={client}>
          <Story />
        </QueryClientProvider>
      </DirectionProvider>
    )
  }
}

const meta = {
  title: 'Pages/ReviewQueuePage',
  component: ReviewQueuePage,
  args: {
    cohortId: 'coh_1',
    stageId: 'stg_screen',
    reviewerId: REVIEWER_ID,
  },
} satisfies Meta<typeof ReviewQueuePage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {
  decorators: [withProviders(SEEDED_ASSIGNMENTS)],
}

export const Empty: Story = {
  decorators: [withProviders([])],
}
