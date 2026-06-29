import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ProgramConfigPage } from './ProgramConfigPage'

const FORMS = [
  { id: 'frm_pub', name: 'Application form', description: null, latest_version: 2, published_version_ids: ['fv_pub_1'], current_draft_version_id: 'fv_pub_2' },
  { id: 'frm_draft', name: 'Mentor feedback', description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: 'fv_draft_1' },
]
const PIPELINES = [
  { pipeline_id: 'pl_pub', program_id: 'prog_1', name: 'Acceleration pipeline', latest_version: 2, published_version_ids: ['plv_pub_1'], current_draft_version_id: 'plv_pub_2', created_at: '2026-06-20T10:00:00+00:00' },
]

const decorator = (Story: () => ReactElement) => {
  globalThis.fetch = (async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('/programs/prog_1/stage-pipelines')) return new Response(JSON.stringify({ data: PIPELINES }), { status: 200, headers: { 'Content-Type': 'application/json' } })
    if (url.includes('/forms')) return new Response(JSON.stringify({ data: FORMS }), { status: 200, headers: { 'Content-Type': 'application/json' } })
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
  title: 'Pages/ProgramConfigPage',
  component: ProgramConfigPage,
  args: { programId: 'prog_1' },
  decorators: [decorator],
} satisfies Meta<typeof ProgramConfigPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
