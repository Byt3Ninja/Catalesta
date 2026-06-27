import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ProgramCohortsSection } from './ProgramCohortsSection'
import { jsonResponse } from '../tests/test-utils'

const base = {
  organization_id: '01J0ORG',
  slug: 's',
  status: 'draft' as const,
  capacity: null,
  enrollment_opens_at: null,
  enrollment_closes_at: null,
  starts_at: null,
  ends_at: null,
  timeline: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}
const MINE = { ...base, id: '01J0COH', program_id: '01J0PROG', name: 'Spring 2026' }
const OTHER = { ...base, id: '01J0OTH', program_id: '01J0XXX', name: 'Other Program Cohort' }

function renderSection(programId = '01J0PROG'): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ProgramCohortsSection programId={programId} />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})
afterEach(() => vi.restoreAllMocks())

test("lists only this program's cohorts, each linking to its detail", async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [MINE, OTHER] }))
  renderSection()
  const link = await screen.findByRole('link', { name: /spring 2026/i })
  expect(link).toHaveAttribute('href', '/cohorts/01J0COH')
  expect(screen.queryByText(/other program cohort/i)).not.toBeInTheDocument()
})

test('empty program shows the create-first empty state', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [OTHER] }))
  renderSection()
  expect(await screen.findByText(/no cohorts yet/i)).toBeInTheDocument()
})

test('status badge uses the shadcn token classes, not ds-badge', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: [{
      id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026',
      slug: 'spring-2026', status: 'open', capacity: null, enrollment_opens_at: null,
      enrollment_closes_at: null, starts_at: null, ends_at: null, timeline: null,
      submissions_count: 0, created_at: 'x', updated_at: 'x',
    }] }),
  )
  renderSection('prog_1')
  const badge = await screen.findByText('Open')
  expect(badge).toHaveClass('bg-secondary')
  expect(badge.className).not.toContain('ds-badge')
})

test('create issues the per-program POST with the entered name', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // initial list
    .mockResolvedValueOnce(jsonResponse({ data: MINE }, 201)) // create
    .mockResolvedValueOnce(jsonResponse({ data: [MINE] })) // list refetch
  renderSection()

  fireEvent.change(await screen.findByLabelText('Cohort name'), { target: { value: 'Spring 2026' } })
  fireEvent.click(screen.getByRole('button', { name: /create cohort/i }))

  await vi.waitFor(() => {
    const post = fetchSpy.mock.calls.find((c) => c[1]?.method === 'POST')
    expect(post).toBeDefined()
    expect(String(post?.[0])).toContain('/programs/01J0PROG/cohorts')
    expect(JSON.parse((post?.[1]?.body as string) ?? '{}')).toEqual({ name: 'Spring 2026' })
  })
})
