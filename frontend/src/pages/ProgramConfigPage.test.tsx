import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ProgramConfigPage } from './ProgramConfigPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const FORMS = [
  { id: 'frm_pub', name: 'Application form', description: null, latest_version: 2, published_version_ids: ['fv_pub_1'], current_draft_version_id: 'fv_pub_2' },
]
const PIPELINES = [
  { pipeline_id: 'pl_pub', program_id: 'prog_1', name: 'Acceleration pipeline', latest_version: 2, published_version_ids: ['plv_pub_1'], current_draft_version_id: 'plv_pub_2', created_at: 'x' },
]
const CREATED = { pipeline_id: 'pl_new', program_id: 'prog_1', name: 'Mentorship pipeline', latest_version: 1, published_version_ids: [], current_draft_version_id: 'plv_new_1', created_at: 'x' }

function mockApi() {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = String(input)
    if (url.includes('/programs/prog_1/stage-pipelines') && (init as RequestInit | undefined)?.method === 'POST') return Promise.resolve(jsonResponse({ data: CREATED }, 201))
    if (url.includes('/programs/prog_1/stage-pipelines')) return Promise.resolve(jsonResponse({ data: PIPELINES }))
    if (url.includes('/forms')) return Promise.resolve(jsonResponse({ data: FORMS }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}
function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><ProgramConfigPage programId="prog_1" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals() })

test('lists the program forms and pipelines with open-builder links', async () => {
  mockApi(); renderPage()
  expect(await screen.findByText('Application form')).toBeInTheDocument()
  expect(await screen.findByText('Acceleration pipeline')).toBeInTheDocument()
  const builderLinks = screen.getAllByRole('link', { name: /open builder/i })
  expect(builderLinks.some((a) => a.getAttribute('href') === '/forms/frm_pub/edit')).toBe(true)
  expect(builderLinks.some((a) => a.getAttribute('href') === '/programs/prog_1/stages/pl_pub/edit')).toBe(true)
})

test('creating a new pipeline routes to its builder', async () => {
  const assign = vi.fn()
  vi.stubGlobal('location', { ...window.location, assign })
  mockApi(); renderPage()
  await screen.findByText('Acceleration pipeline')
  fireEvent.change(screen.getByLabelText(/new pipeline name/i), { target: { value: 'Mentorship pipeline' } })
  fireEvent.click(screen.getByRole('button', { name: /new pipeline/i }))
  await waitFor(() => expect(assign).toHaveBeenCalledWith('/programs/prog_1/stages/pl_new/edit'))
})
