import { render, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { StagePipelinePreviewPage } from './StagePipelinePreviewPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const stage = (over: Record<string, unknown>) => ({
  entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, ...over,
})

const VERSION = {
  version_id: 'plv_pub_1', pipeline_id: 'pl_pub', version: 1, status: 'published',
  created_at: 'x', published_at: 'x',
  stages: [
    stage({ stage_id: 's_screen', name: 'Screening', type: 'review', order: 0, next_stage_ids: ['s_interview'] }),
    stage({ stage_id: 's_interview', name: 'Interview', type: 'interview', order: 1, next_stage_ids: ['s_decide'], entry_rule: { match: 'all', conditions: [{ field_id: 'score', operator: 'not_equals', value: '' }] } }),
    stage({ stage_id: 's_decide', name: 'Decision', type: 'decision', order: 2 }),
  ],
}

function renderPreview(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><StagePipelinePreviewPage versionId="plv_pub_1" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
afterEach(() => vi.restoreAllMocks())

test('renders ordered stage names and a routing summary line', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: VERSION }))
  renderPreview()
  expect(await screen.findByText('1. Screening')).toBeInTheDocument()
  expect(screen.getByText('2. Interview')).toBeInTheDocument()
  expect(screen.getByText('3. Decision')).toBeInTheDocument()
  // routing summary from Screening points to Interview, gated by Interview's entry rule
  expect(screen.getByText(/→\s*Interview when score ≠/)).toBeInTheDocument()
  // terminal stage has no onward routing
  expect(screen.getByText('→ End of pipeline')).toBeInTheDocument()
})

test('shows an error state when the version cannot be loaded', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 404 }))
  renderPreview()
  expect(await screen.findByText('Could not load this pipeline version.')).toBeInTheDocument()
})
