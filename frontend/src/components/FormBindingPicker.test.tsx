import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormBindingPicker } from './FormBindingPicker'
import { jsonResponse } from '../tests/test-utils'

// Stub out the api/roles fetch that AppShell triggers (no AppShell here, but guard it anyway)
vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const FORMS = [
  { id: 'frm_pub', name: 'Application form', description: 'Main intake', latest_version: 2, published_version_ids: ['fv_pub_1'], current_draft_version_id: 'fv_pub_2' },
  { id: 'frm_draft', name: 'New form', description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: 'fv_draft_1' },
]

// Versions for frm_pub: one published, one draft
const VERSIONS_FRM_PUB = [
  { id: 'fv_pub_2', form_id: 'frm_pub', version: 2, status: 'draft', fields: [], created_at: '2026-06-01T00:00:00Z', published_at: null },
  { id: 'fv_pub_1', form_id: 'frm_pub', version: 1, status: 'published', fields: [], created_at: '2026-06-01T00:00:00Z', published_at: '2026-06-01T00:00:00Z' },
]

// Versions for frm_draft: all draft — no published versions at all
const VERSIONS_FRM_DRAFT = [
  { id: 'fv_draft_1', form_id: 'frm_draft', version: 1, status: 'draft', fields: [], created_at: '2026-06-01T00:00:00Z', published_at: null },
]

beforeEach(() => {
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})
afterEach(() => vi.restoreAllMocks())

function renderPicker(props: { cohortId?: string; boundVersionId?: string | null; onBound?: (c: unknown) => void } = {}): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <FormBindingPicker
          cohortId={props.cohortId ?? 'coh_1'}
          boundVersionId={props.boundVersionId ?? null}
          onBound={props.onBound ?? vi.fn()}
        />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

test('shows only published versions — draft versions are excluded', async () => {
  // Fetch sequence: listForms, then listFormVersions for each form
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: FORMS }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS_FRM_PUB }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS_FRM_DRAFT }))

  renderPicker()

  // Wait for published option to appear
  const option = await screen.findByRole('option', { name: /Application form v1/i })
  expect(option).toBeInTheDocument()

  // Draft versions must NOT appear as options
  const allOptions = screen.getAllByRole('option')
  const optionTexts = allOptions.map((o) => o.textContent)
  // No version 2 (draft) should appear
  expect(optionTexts.some((t) => /v2/i.test(t ?? ''))).toBe(false)
  // No "New form" (has no published versions) should appear
  expect(optionTexts.some((t) => /New form/i.test(t ?? ''))).toBe(false)
})

test('calls bindCohortForm with the selected version id and invokes onBound', async () => {
  const UPDATED_COHORT = {
    id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026',
    slug: 'spring-2026', status: 'open', capacity: null, enrollment_opens_at: null,
    enrollment_closes_at: null, starts_at: null, ends_at: null, timeline: null,
    bound_form_version_id: 'fv_pub_1', created_at: '2026-06-01T00:00:00Z', updated_at: '2026-06-01T00:00:00Z',
  }
  const onBound = vi.fn()
  const fetchSpy = vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: FORMS }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS_FRM_PUB }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS_FRM_DRAFT }))
    .mockResolvedValueOnce(jsonResponse({ data: UPDATED_COHORT })) // bind POST

  renderPicker({ onBound })

  // Wait for select to populate then choose the published version
  const select = await screen.findByRole('combobox')
  fireEvent.change(select, { target: { value: 'fv_pub_1' } })

  // Find and click the Bind button
  const bindBtn = screen.getByRole('button', { name: /bind/i })
  fireEvent.click(bindBtn)

  await waitFor(() => expect(onBound).toHaveBeenCalledWith(UPDATED_COHORT))

  // Verify the POST body
  const postCall = fetchSpy.mock.calls.find((c) => c[1]?.method === 'POST')
  expect(postCall).toBeDefined()
  expect(String(postCall![0])).toContain('/cohorts/coh_1/bind-form')
  expect(JSON.parse((postCall![1]?.body as string) ?? '{}')).toEqual({ form_version_id: 'fv_pub_1' })
})

test('shows current binding label when boundVersionId is set', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: FORMS }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS_FRM_PUB }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS_FRM_DRAFT }))

  renderPicker({ boundVersionId: 'fv_pub_1' })

  // First wait for the select to load (versions resolved), then check bound label
  await screen.findByRole('option', { name: /Application form v1/i })
  const label = screen.getByTestId('bound-label')
  expect(label).toHaveTextContent(/Application form v1/i)
})

test('shows a replace-binding warning when rebinding over an existing binding', async () => {
  const UPDATED_COHORT = {
    id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026',
    slug: 'spring-2026', status: 'open', capacity: null, enrollment_opens_at: null,
    enrollment_closes_at: null, starts_at: null, ends_at: null, timeline: null,
    bound_form_version_id: 'fv_pub_1', created_at: '2026-06-01T00:00:00Z', updated_at: '2026-06-01T00:00:00Z',
  }

  // Add a second published version for testing replacement
  const VERSIONS_WITH_TWO = [
    { id: 'fv_pub_2', form_id: 'frm_pub', version: 2, status: 'published', fields: [], created_at: '2026-06-01T00:00:00Z', published_at: '2026-06-02T00:00:00Z' },
    { id: 'fv_pub_1', form_id: 'frm_pub', version: 1, status: 'published', fields: [], created_at: '2026-06-01T00:00:00Z', published_at: '2026-06-01T00:00:00Z' },
  ]

  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: FORMS }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS_WITH_TWO }))
    .mockResolvedValueOnce(jsonResponse({ data: VERSIONS_FRM_DRAFT }))
    .mockResolvedValueOnce(jsonResponse({ data: UPDATED_COHORT }))

  renderPicker({ boundVersionId: 'fv_pub_1' })

  // Wait for list to load, select a different version
  const select = await screen.findByRole('combobox')
  fireEvent.change(select, { target: { value: 'fv_pub_2' } })

  const bindBtn = screen.getByRole('button', { name: /bind/i })
  fireEvent.click(bindBtn)

  // Warning banner should appear before confirming
  expect(await screen.findByText(/replacing/i)).toBeInTheDocument()
})
