import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { StagePipelineBuilderPage } from './StagePipelineBuilderPage'

const PIPELINE = { pipeline_id: 'pl_draft', program_id: 'prog_1', name: 'Acceleration pipeline', latest_version: 1, published_version_ids: [], current_draft_version_id: 'plv_draft_1', created_at: '2026-06-20T10:00:00+00:00' }
const stage = (over: Record<string, unknown>) => ({ entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, ...over })
const DRAFT = {
  version_id: 'plv_draft_1', pipeline_id: 'pl_draft', version: 1, status: 'draft', created_at: '2026-06-20T10:00:00+00:00', published_at: null,
  stages: [
    stage({ stage_id: 's_screen', name: 'Screening', type: 'review', order: 0, next_stage_ids: ['s_interview'] }),
    stage({ stage_id: 's_interview', name: 'Interview', type: 'interview', order: 1 }),
  ],
}

const decorator = (Story: () => ReactElement) => {
  globalThis.fetch = (async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('/stage-pipelines/pl_draft/draft')) return new Response(JSON.stringify({ data: DRAFT }), { status: 200, headers: { 'Content-Type': 'application/json' } })
    if (url.includes('/stage-pipeline-versions/plv_draft_1')) return new Response(JSON.stringify({ data: DRAFT }), { status: 200, headers: { 'Content-Type': 'application/json' } })
    if (url.includes('/stage-pipelines/pl_draft')) return new Response(JSON.stringify({ data: PIPELINE }), { status: 200, headers: { 'Content-Type': 'application/json' } })
    if (url.includes('/sanctum/csrf-cookie')) return new Response(null, { status: 204 })
    return new Response(null, { status: 404 })
  }) as typeof fetch
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <Story />
      </QueryClientProvider>
    </DirectionProvider>
  )
}

const meta = {
  title: 'Pages/StagePipelineBuilderPage',
  component: StagePipelineBuilderPage,
  args: { pipelineId: 'pl_draft' },
  decorators: [decorator],
} satisfies Meta<typeof StagePipelineBuilderPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
