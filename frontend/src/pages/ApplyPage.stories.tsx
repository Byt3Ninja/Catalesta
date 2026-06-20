import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ApplyPage } from './ApplyPage'
import type { ApplyForm } from '../schemas/apply'

const SAMPLE_FORM: ApplyForm = {
  open: true,
  cohort_id: 'demo-cohort',
  form_version_id: 'v1',
  form: [
    { type: 'short_text', label: 'Startup name', required: true, key: 'name' },
    { type: 'long_text', label: 'What problem do you solve?', key: 'problem' },
    {
      type: 'single_select',
      label: 'Stage',
      options: ['Idea', 'MVP', 'Revenue'],
      key: 'stage',
    },
    { type: 'consent', label: 'I agree to the terms.', required: true, key: 'consent' },
  ],
}

/** Each story serves its own canned response so the page's react-query fetch resolves. */
function withMockedFetch(form: ApplyForm) {
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (async () =>
      new Response(JSON.stringify(form), {
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
  title: 'Pages/ApplyPage',
  component: ApplyPage,
  args: { cohortId: 'demo-cohort' },
} satisfies Meta<typeof ApplyPage>

export default meta
type Story = StoryObj<typeof meta>

export const Open: Story = {
  decorators: [withMockedFetch(SAMPLE_FORM)],
}

export const Closed: Story = {
  decorators: [withMockedFetch({ ...SAMPLE_FORM, open: false, form: null })],
}
