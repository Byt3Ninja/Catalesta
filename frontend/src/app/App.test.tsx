import { render, screen, fireEvent } from '@testing-library/react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { App } from './App'
import { queryClient } from './queryClient'
import { jsonResponse } from '../tests/test-utils'

const USER = {
  id: 'user-1',
  startup_gate_subject_id: 'sub-1',
  email: 'op@example.com',
  display_name: 'Operator',
}

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

function setPath(path: string) {
  window.history.pushState({}, '', path)
}

beforeEach(() => {
  // The app uses a module-level QueryClient; staleTime on the gate queries would
  // otherwise leak cached results across tests. Clear it for per-test isolation.
  queryClient.clear()
  setPath('/')
})

afterEach(() => {
  vi.restoreAllMocks()
  setPath('/')
})

test('gate: unauthenticated (401 session) → login page', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 401 }))

  render(<App />)

  expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
  expect(
    screen.getByRole('button', { name: /sign in with startup gate/i }),
  ).toBeInTheDocument()
})

test('gate: authenticated with no org → forced onboarding (non-skippable)', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // organizations

  render(<App />)

  expect(
    await screen.findByRole('heading', { name: /create your organization/i }),
  ).toBeInTheDocument()

  // Not skippable: no skip/dismiss/back-out control exists.
  expect(screen.queryByRole('button', { name: /skip/i })).not.toBeInTheDocument()
  expect(screen.queryByRole('button', { name: /dismiss/i })).not.toBeInTheDocument()
  expect(screen.queryByRole('link', { name: /skip/i })).not.toBeInTheDocument()
})

test('gate: authenticated with an org → operator Home (AppShell)', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations

  render(<App />)

  expect(await screen.findByRole('heading', { name: 'Acme Incubator' })).toBeInTheDocument()
  expect(screen.getByText(/operator console/i)).toBeInTheDocument()
})

test('create success → lands on Home', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // organizations: none
    .mockResolvedValueOnce(jsonResponse({ data: ORG }, 201)) // create
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations refetch

  render(<App />)

  const input = (await screen.findByLabelText('Organization name')) as HTMLInputElement
  fireEvent.change(input, { target: { value: 'Acme Incubator' } })
  fireEvent.click(screen.getByRole('button', { name: /create organization/i }))

  expect(await screen.findByRole('heading', { name: 'Acme Incubator' })).toBeInTheDocument()
})

test('duplicate-name 422 preserves the entered name and shows the message', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // organizations
    .mockResolvedValueOnce(
      jsonResponse(
        {
          error: {
            code: 'VALIDATION_ERROR',
            message: 'The given data was invalid.',
            details: { name: ['An organization with a similar name already exists.'] },
          },
        },
        422,
      ),
    )

  render(<App />)

  const input = (await screen.findByLabelText('Organization name')) as HTMLInputElement
  fireEvent.change(input, { target: { value: 'Acme Incubator' } })
  fireEvent.click(screen.getByRole('button', { name: /create organization/i }))

  expect(
    await screen.findByText(/an organization with a similar name already exists/i),
  ).toBeInTheDocument()
  // Entered name preserved.
  expect((screen.getByLabelText('Organization name') as HTMLInputElement).value).toBe(
    'Acme Incubator',
  )
  // Still on onboarding — the gate did not pass.
  expect(
    screen.getByRole('heading', { name: /create your organization/i }),
  ).toBeInTheDocument()
})

test('gate persists: no-org user cannot reach a console surface (no Home heading)', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // organizations

  render(<App />)

  await screen.findByRole('heading', { name: /create your organization/i })
  // Home/console surface never rendered.
  expect(screen.queryByText(/operator console/i)).not.toBeInTheDocument()
})
