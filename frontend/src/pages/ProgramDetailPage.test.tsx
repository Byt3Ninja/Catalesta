import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { ProgramDetailPage } from './ProgramDetailPage'
import { jsonResponse } from '../tests/test-utils'

const { navigateSpy } = vi.hoisted(() => ({ navigateSpy: vi.fn() }))
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>()
  return { ...actual, useNavigate: () => navigateSpy }
})

const DRAFT = {
  id: '01J0PROG',
  name: 'Spring Accelerator',
  slug: 'spring-accelerator',
  status: 'draft',
  description: 'Seed cohort',
  settings: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

function renderDetail(programId = '01J0PROG'): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <ProgramDetailPage programId={programId} />
        </MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  navigateSpy.mockReset()
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})
afterEach(() => vi.restoreAllMocks())

test('renders the program name, status and description', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: DRAFT }))
  renderDetail()
  expect(await screen.findByRole('heading', { name: 'Spring Accelerator' })).toBeInTheDocument()
  expect(screen.getByText('Draft')).toBeInTheDocument()
  expect(screen.getByText('Seed cohort')).toBeInTheDocument()
})

test('a 404 shows the "no longer exists" state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  renderDetail('missing')
  expect(await screen.findByText(/that program no longer exists/i)).toBeInTheDocument()
})

test('edit → save updates the displayed name', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT })) // initial load
    .mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, name: 'Renamed' } })) // PATCH
    .mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, name: 'Renamed' } })) // refetch
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Program name'), { target: { value: 'Renamed' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByRole('heading', { name: 'Renamed' })).toBeInTheDocument()
})

test('edit → 422 shows the validation message and stays in edit mode', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT })) // initial load
    .mockResolvedValueOnce(
      jsonResponse(
        { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
        422,
      ),
    ) // PATCH 422
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Program name'), { target: { value: 'x' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByText(/the name field is required/i)).toBeInTheDocument()
  expect(screen.getByLabelText('Program name')).toBeInTheDocument() // still editing
})

test('clone → navigates to the new draft on success', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT })) // initial load
    .mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, id: '01J0NEW' } }, 201)) // clone
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Clone' }))
  fireEvent.change(screen.getByLabelText('New program name'), { target: { value: 'Copy' } })
  fireEvent.click(screen.getByRole('button', { name: /create copy/i }))

  await vi.waitFor(() => expect(navigateSpy).toHaveBeenCalledWith('/programs/01J0NEW'))
})

test('clone → 403 shows a permission banner', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT })) // initial load
    .mockResolvedValueOnce(new Response(null, { status: 403 })) // clone forbidden
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Clone' }))
  fireEvent.change(screen.getByLabelText('New program name'), { target: { value: 'Copy' } })
  fireEvent.click(screen.getByRole('button', { name: /create copy/i }))

  expect(await screen.findByText(/do not have permission/i)).toBeInTheDocument()
})

test('Publish shows for a draft and is absent for a published program', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: DRAFT }))
  renderDetail()
  expect(await screen.findByRole('button', { name: 'Publish' })).toBeInTheDocument()
})

test('Publish is absent for a published program', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: { ...DRAFT, status: 'published' } }),
  )
  renderDetail()
  await screen.findByRole('heading', { name: 'Spring Accelerator' })
  expect(screen.queryByRole('button', { name: 'Publish' })).not.toBeInTheDocument()
})
