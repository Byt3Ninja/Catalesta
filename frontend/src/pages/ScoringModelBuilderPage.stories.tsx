import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ScoringModelBuilderPage } from './ScoringModelBuilderPage'

const MODEL = { model_id: 'sm_draft', program_id: 'prog_1', name: 'Acceleration scoring model', latest_version: 1, published_version_ids: [], current_draft_version_id: 'smv_draft_1', created_at: '2026-06-20T10:00:00+00:00' }
const criterion = (over: Record<string, unknown>) => ({ descriptors: null, ...over })
const DRAFT = {
  version_id: 'smv_draft_1', model_id: 'sm_draft', version: 1, status: 'draft', created_at: '2026-06-20T10:00:00+00:00', published_at: null,
  criteria: [
    criterion({ criterion_id: 'crit_1', label: 'Market opportunity', max_points: 25 }),
    criterion({ criterion_id: 'crit_2', label: 'Team strength', max_points: 25 }),
    criterion({ criterion_id: 'crit_3', label: 'Product traction', max_points: 30 }),
    criterion({ criterion_id: 'crit_4', label: 'Business model', max_points: 20 }),
  ],
}

const decorator = (Story: () => ReactElement) => {
  globalThis.fetch = (async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('/scoring-models/sm_draft/draft')) return new Response(JSON.stringify({ data: DRAFT }), { status: 200, headers: { 'Content-Type': 'application/json' } })
    if (url.includes('/scoring-model-versions/smv_draft_1')) return new Response(JSON.stringify({ data: DRAFT }), { status: 200, headers: { 'Content-Type': 'application/json' } })
    if (url.includes('/scoring-models/sm_draft')) return new Response(JSON.stringify({ data: MODEL }), { status: 200, headers: { 'Content-Type': 'application/json' } })
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
  title: 'Pages/ScoringModelBuilderPage',
  component: ScoringModelBuilderPage,
  args: { modelId: 'sm_draft' },
  decorators: [decorator],
} satisfies Meta<typeof ScoringModelBuilderPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
