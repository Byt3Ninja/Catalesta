import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { StagePipelinePreviewPage } from './StagePipelinePreviewPage'

const stage = (over: Record<string, unknown>) => ({
  entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, ...over,
})

const VERSION = {
  version_id: 'plv_pub_1', pipeline_id: 'pl_pub', version: 1, status: 'published',
  created_at: '2026-06-20T10:00:00+00:00', published_at: '2026-06-20T10:00:00+00:00',
  stages: [
    stage({ stage_id: 's_screen', name: 'Screening', type: 'review', order: 0, next_stage_ids: ['s_ref'] }),
    stage({ stage_id: 's_ref', name: 'Reference check', type: 'task', order: 1, parallel_group: 'diligence', next_stage_ids: ['s_decide'] }),
    stage({ stage_id: 's_tech', name: 'Technical review', type: 'review', order: 2, parallel_group: 'diligence', next_stage_ids: ['s_decide'], entry_rule: { match: 'all', conditions: [{ field_id: 'track', operator: 'equals', value: 'deep' }] } }),
    stage({ stage_id: 's_decide', name: 'Decision', type: 'decision', order: 3, depends_on_stage_ids: ['s_ref'] }),
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
  title: 'Pages/StagePipelinePreviewPage',
  component: StagePipelinePreviewPage,
  args: { versionId: 'plv_pub_1' },
  decorators: [withProviders],
} satisfies Meta<typeof StagePipelinePreviewPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
