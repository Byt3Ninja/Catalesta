import { render, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ScoringModelPreviewPage } from './ScoringModelPreviewPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const VERSION = {
  version_id: 'smv_pub_1', model_id: 'sm_1', version: 2, status: 'published',
  created_at: 'x', published_at: 'x',
  criteria: [
    { criterion_id: 'c_team', label: 'Team', max_points: 20, descriptors: ['Strong founding team', 'Relevant experience'] },
    { criterion_id: 'c_market', label: 'Market size', max_points: 15, descriptors: null },
    { criterion_id: 'c_traction', label: 'Traction', max_points: 25, descriptors: ['Revenue', 'User growth'] },
  ],
}

function renderPreview(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><ScoringModelPreviewPage versionId="smv_pub_1" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
afterEach(() => vi.restoreAllMocks())

test('renders ordered criterion labels and the total possible line', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: VERSION }))
  renderPreview()
  expect(await screen.findByText('1. Team')).toBeInTheDocument()
  expect(screen.getByText('2. Market size')).toBeInTheDocument()
  expect(screen.getByText('3. Traction')).toBeInTheDocument()
  expect(screen.getByText('Total possible: 60 pts')).toBeInTheDocument()
})

test('shows an error state when the version cannot be loaded', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 404 }))
  renderPreview()
  expect(await screen.findByText('Could not load this scoring model version.')).toBeInTheDocument()
})
