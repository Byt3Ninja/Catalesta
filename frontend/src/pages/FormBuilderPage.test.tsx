import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormBuilderPage } from './FormBuilderPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const FORM = { id: 'frm_draft', name: 'New form', description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: 'fv_draft_1' }
const DRAFT = { id: 'fv_draft_1', form_id: 'frm_draft', version: 1, status: 'draft', fields: [], created_at: 'x', published_at: null }

function mockApi() {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/forms/frm_draft/draft')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/form-versions/fv_draft_1')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/forms/frm_draft')) return Promise.resolve(jsonResponse({ data: FORM }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}
function renderBuilder(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><FormBuilderPage formId="frm_draft" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

test('adds a field from the palette and shows it on the canvas', async () => {
  mockApi(); renderBuilder()
  await screen.findByRole('heading', { name: /form builder|new form/i })
  fireEvent.click(screen.getByRole('button', { name: /add short text/i }))
  await waitFor(() => expect(screen.getByText(/short text/i)).toBeInTheDocument())
})

test('reorders fields with move up', async () => {
  mockApi(); renderBuilder()
  await screen.findByRole('heading', { name: /form builder|new form/i })
  fireEvent.click(screen.getByRole('button', { name: /add short text/i }))
  fireEvent.click(screen.getByRole('button', { name: /add date/i }))
  const ups = screen.getAllByRole('button', { name: /move up/i })
  fireEvent.click(ups[ups.length - 1]) // move the date field above the text field
  const items = screen.getAllByRole('listitem')
  expect(items[0]).toHaveTextContent(/date/i)
})
