import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormPreviewPage } from './FormPreviewPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const VERSION = { id: 'fv_pub_1', form_id: 'frm_pub', version: 1, status: 'published', published_at: 'x', created_at: 'x', fields: [
  { id: 'f_name', type: 'short_text', label: 'Startup name', required: false },
] }

function renderPreview(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><FormPreviewPage versionId="fv_pub_1" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
afterEach(() => vi.restoreAllMocks())

test('renders the version fields read-only and toggles RTL', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: VERSION }))
  renderPreview()
  expect(await screen.findByText('Startup name')).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: /right-to-left|rtl/i }))
  expect(document.querySelector('[dir="rtl"]')).not.toBeNull()
})
