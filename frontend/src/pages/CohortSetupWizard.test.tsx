import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { CohortSetupWizard } from './CohortSetupWizard'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const CREATED = {
  id: 'coh_9', organization_id: 'org_demo', program_id: 'prog_1', name: 'Autumn 2026',
  slug: 'autumn-2026', status: 'draft' as const, capacity: null,
  enrollment_opens_at: null, enrollment_closes_at: null, starts_at: null, ends_at: null,
  timeline: null, created_at: 'x', updated_at: 'x',
}

function renderWizard(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <CohortSetupWizard programId="prog_1" />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('create step posts the cohort and advances to Attach form', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: CREATED }, 201))
  renderWizard()
  fireEvent.change(screen.getByLabelText(/cohort name/i), { target: { value: 'Autumn 2026' } })
  fireEvent.click(screen.getByRole('button', { name: /create & continue/i }))
  expect(await screen.findByRole('heading', { name: /attach form/i })).toBeInTheDocument()
})

test('full happy path reaches Open and calls openCohort', async () => {
  const opened = { ...CREATED, status: 'open' as const }
  const spy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: CREATED }, 201))   // create
    .mockResolvedValueOnce(jsonResponse({ data: CREATED }))        // dates PATCH
    .mockResolvedValueOnce(jsonResponse({ data: opened }))         // open

  renderWizard()
  fireEvent.change(screen.getByLabelText(/cohort name/i), { target: { value: 'Autumn 2026' } })
  fireEvent.click(screen.getByRole('button', { name: /create & continue/i }))
  await screen.findByRole('heading', { name: /attach form/i })
  fireEvent.click(screen.getByRole('button', { name: /skip for now/i }))     // step 2 -> 3
  await screen.findByRole('heading', { name: /attach stages/i })
  fireEvent.click(screen.getByRole('button', { name: /skip for now/i }))     // step 3 -> 4
  await screen.findByRole('heading', { name: /dates/i })
  fireEvent.click(screen.getByRole('button', { name: /continue/i }))         // step 4 -> 5
  await screen.findByRole('heading', { name: /review/i })
  fireEvent.click(screen.getByRole('button', { name: /open cohort/i }))      // step 5 -> open

  expect(await screen.findByText(/cohort is open/i)).toBeInTheDocument()
  const opens = spy.mock.calls.some((c) => String(c[0]).endsWith('/cohorts/coh_9/open') && c[1]?.method === 'POST')
  expect(opens).toBe(true)
})
