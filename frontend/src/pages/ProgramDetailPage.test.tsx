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

/**
 * Route GET …/cohorts (the embedded ProgramCohortsSection list) to a fixed
 * cohorts array; every other call (getProgram, PATCH, clone POST, createCohort
 * POST) is served from an ordered program queue. Deterministic regardless of
 * which mount-time query fires first.
 */
function mockApi(programQueue: Response[], cohorts: unknown[] = []) {
  const queue = [...programQueue]
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = String(input)
    const method = (init?.method ?? 'GET').toUpperCase()
    if (method === 'GET' && /\/cohorts$/.test(url)) {
      return Promise.resolve(jsonResponse({ data: cohorts }))
    }
    const next = queue.shift()
    return Promise.resolve(next ?? new Response(null, { status: 500 }))
  })
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
  mockApi([jsonResponse({ data: DRAFT })])
  renderDetail()
  expect(await screen.findByRole('heading', { name: 'Spring Accelerator' })).toBeInTheDocument()
  expect(screen.getByText('Draft')).toBeInTheDocument()
  expect(screen.getByText('Seed cohort')).toBeInTheDocument()
})

test('a 404 shows the "no longer exists" state', async () => {
  mockApi([new Response(null, { status: 404 })])
  renderDetail('missing')
  expect(await screen.findByText(/that program no longer exists/i)).toBeInTheDocument()
})

test('edit → save updates the displayed name', async () => {
  mockApi([
    jsonResponse({ data: DRAFT }), // initial load
    jsonResponse({ data: { ...DRAFT, name: 'Renamed' } }), // PATCH
    jsonResponse({ data: { ...DRAFT, name: 'Renamed' } }), // refetch
  ])
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Program name'), { target: { value: 'Renamed' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByRole('heading', { name: 'Renamed' })).toBeInTheDocument()
})

test('edit → 422 shows the validation message and stays in edit mode', async () => {
  mockApi([
    jsonResponse({ data: DRAFT }),
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
      422,
    ),
  ])
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Edit' }))
  fireEvent.change(screen.getByLabelText('Program name'), { target: { value: 'x' } })
  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  expect(await screen.findByText(/the name field is required/i)).toBeInTheDocument()
  expect(screen.getByLabelText('Program name')).toBeInTheDocument()
})

test('clone → navigates to the new draft on success', async () => {
  mockApi([jsonResponse({ data: DRAFT }), jsonResponse({ data: { ...DRAFT, id: '01J0NEW' } }, 201)])
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Clone' }))
  fireEvent.change(screen.getByLabelText('New program name'), { target: { value: 'Copy' } })
  fireEvent.click(screen.getByRole('button', { name: /create copy/i }))

  await vi.waitFor(() => expect(navigateSpy).toHaveBeenCalledWith('/programs/01J0NEW'))
})

test('clone → 403 shows a permission banner', async () => {
  mockApi([jsonResponse({ data: DRAFT }), new Response(null, { status: 403 })])
  renderDetail()

  fireEvent.click(await screen.findByRole('button', { name: 'Clone' }))
  fireEvent.change(screen.getByLabelText('New program name'), { target: { value: 'Copy' } })
  fireEvent.click(screen.getByRole('button', { name: /create copy/i }))

  expect(await screen.findByText(/do not have permission/i)).toBeInTheDocument()
})

test('Publish shows for a draft', async () => {
  mockApi([jsonResponse({ data: DRAFT })])
  renderDetail()
  expect(await screen.findByRole('button', { name: 'Publish' })).toBeInTheDocument()
})

test('Publish is absent for a published program', async () => {
  mockApi([jsonResponse({ data: { ...DRAFT, status: 'published' } })])
  renderDetail()
  await screen.findByRole('heading', { name: 'Spring Accelerator' })
  expect(screen.queryByRole('button', { name: 'Publish' })).not.toBeInTheDocument()
})

test('shows the program\'s cohorts section with a linked cohort', async () => {
  const cohort = {
    id: '01J0COH',
    organization_id: '01J0ORG',
    program_id: '01J0PROG',
    name: 'Spring 2026',
    slug: 'spring-2026',
    status: 'draft',
    capacity: null,
    enrollment_opens_at: null,
    enrollment_closes_at: null,
    starts_at: null,
    ends_at: null,
    timeline: null,
    submissions_count: 0,
    created_at: '2026-06-20T10:00:00+00:00',
    updated_at: '2026-06-20T10:00:00+00:00',
  }
  mockApi([jsonResponse({ data: DRAFT })], [cohort])
  renderDetail()
  expect(await screen.findByRole('link', { name: /spring 2026/i })).toHaveAttribute(
    'href',
    '/cohorts/01J0COH',
  )
})
