import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ProgramsPage } from './ProgramsPage'
import { jsonResponse } from '../tests/test-utils'

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

const DRAFT = {
  id: '01J0PROG',
  name: 'Spring Accelerator',
  slug: 'spring-accelerator',
  status: 'draft',
  description: null,
  settings: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ProgramsPage organization={ORG} />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

// createProgram + publishProgram now route through csrfFetch (PR #26 follow-up).
// Pre-seed the XSRF cookie so the preflight is skipped and sequential fetch mocks stay aligned.
beforeEach(() => {
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})

afterEach(() => {
  vi.restoreAllMocks()
})

test('empty → create → the new program appears in the list', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // initial list: empty
    .mockResolvedValueOnce(jsonResponse({ data: DRAFT }, 201)) // create
    .mockResolvedValueOnce(jsonResponse({ data: [DRAFT] })) // list refetch

  renderPage()

  expect(await screen.findByText(/no programs yet/i)).toBeInTheDocument()

  fireEvent.change(screen.getByLabelText('Program name'), {
    target: { value: 'Spring Accelerator' },
  })
  fireEvent.click(screen.getByRole('button', { name: /create program/i }))

  expect(await screen.findByText('Spring Accelerator')).toBeInTheDocument()
})

test('publishing a draft flips its status to Published', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [DRAFT] })) // initial list
    .mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, status: 'published' } })) // publish
    .mockResolvedValueOnce(jsonResponse({ data: [{ ...DRAFT, status: 'published' }] })) // refetch

  renderPage()

  fireEvent.click(await screen.findByRole('button', { name: /publish/i }))

  expect(await screen.findByText('Published')).toBeInTheDocument()
  // A published program shows no publish button.
  expect(screen.queryByRole('button', { name: /publish/i })).not.toBeInTheDocument()
})

test('create error preserves the entered name and shows a banner', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // initial list
    .mockResolvedValueOnce(
      jsonResponse(
        { error: { code: 'VALIDATION_ERROR', details: { name: ['The name has already been taken.'] } } },
        422,
      ),
    )

  renderPage()

  await screen.findByText(/no programs yet/i)
  fireEvent.change(screen.getByLabelText('Program name'), {
    target: { value: 'Spring Accelerator' },
  })
  fireEvent.click(screen.getByRole('button', { name: /create program/i }))

  expect(await screen.findByText(/the name has already been taken/i)).toBeInTheDocument()
  expect((screen.getByLabelText('Program name') as HTMLInputElement).value).toBe(
    'Spring Accelerator',
  )
})
