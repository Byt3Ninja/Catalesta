import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormVersionsPage } from './FormVersionsPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const VERSION_1 = {
  id: 'fv_1',
  form_id: 'frm_1',
  version: 1,
  status: 'published',
  fields: [
    { id: 'f_name', type: 'short_text', label: 'Name', required: false },
    { id: 'f_stage', type: 'single_select', label: 'Stage', required: false },
  ],
  created_at: '2026-01-01T00:00:00Z',
  published_at: '2026-01-01T00:00:00Z',
}

const VERSION_2 = {
  id: 'fv_2',
  form_id: 'frm_1',
  version: 2,
  status: 'draft',
  fields: [
    { id: 'f_name', type: 'short_text', label: 'Name', required: false },
    { id: 'f_stage', type: 'single_select', label: 'Stage', required: false },
    { id: 'f_website', type: 'short_text', label: 'Website', required: false },
  ],
  created_at: '2026-02-01T00:00:00Z',
  published_at: null,
}

function mockApi() {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : String(input)
    if (url.includes('/forms/frm_1/versions')) {
      return Promise.resolve(jsonResponse({ data: [VERSION_1, VERSION_2] }))
    }
    if (url.includes('/form-versions/fv_1')) {
      return Promise.resolve(jsonResponse({ data: VERSION_1 }))
    }
    if (url.includes('/form-versions/fv_2')) {
      return Promise.resolve(jsonResponse({ data: VERSION_2 }))
    }
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <FormVersionsPage formId="frm_1" />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

afterEach(() => vi.restoreAllMocks())

test('lists two versions in the history', async () => {
  mockApi()
  renderPage()
  expect(await screen.findByText('Version 1')).toBeInTheDocument()
  expect(screen.getByText('Version 2')).toBeInTheDocument()
})

test('selecting two versions shows the compare panel with an added field', async () => {
  mockApi()
  renderPage()

  // Wait for version list to render
  await screen.findByText('Version 1')

  const checkboxes = screen.getAllByRole('checkbox')
  fireEvent.click(checkboxes[0]) // select version 1
  fireEvent.click(checkboxes[1]) // select version 2

  // The compare panel should show "3. Website (short_text)" as added
  expect(await screen.findByText('3. Website (short_text)')).toBeInTheDocument()
  expect(screen.getByText('3. Website (short_text)')).toHaveAttribute('data-diff', 'added')
})
