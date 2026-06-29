import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ScoringModelPreviewPage } from './ScoringModelPreviewPage'

const VERSION = {
  version_id: 'smv_pub_1', model_id: 'sm_1', version: 2, status: 'published',
  created_at: '2026-06-20T10:00:00+00:00', published_at: '2026-06-20T10:00:00+00:00',
  criteria: [
    { criterion_id: 'c_team', label: 'Team', max_points: 20, descriptors: ['Strong founding team', 'Relevant domain experience'] },
    { criterion_id: 'c_market', label: 'Market size', max_points: 15, descriptors: null },
    { criterion_id: 'c_traction', label: 'Traction', max_points: 25, descriptors: ['Monthly active users', 'Revenue growth rate'] },
  ],
}

function withProviders(Story: () => ReactElement) {
  globalThis.fetch = (async () =>
    new Response(JSON.stringify({ data: VERSION }), { status: 200, headers: { 'Content-Type': 'application/json' } })) as typeof fetch
  const client = new QueryClient()
  return (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <Story />
      </QueryClientProvider>
    </DirectionProvider>
  )
}

const meta = {
  title: 'Pages/ScoringModelPreviewPage',
  component: ScoringModelPreviewPage,
  args: { versionId: 'smv_pub_1' },
  decorators: [withProviders],
} satisfies Meta<typeof ScoringModelPreviewPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
