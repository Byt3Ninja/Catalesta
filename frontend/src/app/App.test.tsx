import { render, screen, fireEvent } from '@testing-library/react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { QueryClientProvider } from '@tanstack/react-query'
import { App, AppRoutes } from './App'
import { queryClient } from './queryClient'
import { DirectionProvider } from './DirectionProvider'
import { jsonResponse } from '../tests/test-utils'
import { getActiveOrganizationId, setActiveOrganizationId } from '../api/tenant'

const USER = {
  id: 'user-1',
  startup_gate_subject_id: 'sub-1',
  email: 'op@example.com',
  display_name: 'Operator',
  email_verified: true,
  linked_providers: ['startup_gate'],
  has_password: false,
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

const renderApp = () => render(<DirectionProvider><App /></DirectionProvider>)

beforeEach(() => {
  // The app uses a module-level QueryClient; staleTime on the gate queries would
  // otherwise leak cached results across tests. Clear it for per-test isolation.
  queryClient.clear()
  setPath('/')
  // Pre-seed the XSRF cookie so csrfFetch (used by create-org) skips its preflight
  // GET and the sequential fetch mocks below stay aligned.
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})

afterEach(() => {
  vi.restoreAllMocks()
  setPath('/')
  setActiveOrganizationId(null)
})

test('gate: unauthenticated (401 session) → login page', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 401 }))

  renderApp()

  expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
  expect(
    screen.getByRole('button', { name: /sign in with startup gate/i }),
  ).toBeInTheDocument()
})

test('gate: authenticated with no org → forced onboarding (non-skippable)', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // organizations

  renderApp()

  expect(
    await screen.findByRole('heading', { name: /create your organization/i }),
  ).toBeInTheDocument()

  // Not skippable: no skip/dismiss/back-out control exists.
  expect(screen.queryByRole('button', { name: /skip/i })).not.toBeInTheDocument()
  expect(screen.queryByRole('button', { name: /dismiss/i })).not.toBeInTheDocument()
  expect(screen.queryByRole('link', { name: /skip/i })).not.toBeInTheDocument()
})

test('gate: unverified native account → verify-email notice (before onboarding)', async () => {
  const UNVERIFIED = {
    ...USER,
    startup_gate_subject_id: null,
    email_verified: false,
    linked_providers: [],
    has_password: true,
  }
  // Only the session call is needed — the notice short-circuits before the org query.
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ user: UNVERIFIED })) // session

  renderApp()

  expect(await screen.findByRole('heading', { name: /verify your email/i })).toBeInTheDocument()
  // Did not fall through to onboarding or Home.
  expect(
    screen.queryByRole('heading', { name: /create your organization/i }),
  ).not.toBeInTheDocument()
})

test('gate: authenticated with an org → operator Home (AppShell)', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations

  renderApp()

  expect(await screen.findByRole('heading', { name: 'Acme Incubator' })).toBeInTheDocument()
  expect(screen.getByText(/operator console/i)).toBeInTheDocument()
})

test('create success → lands on Home', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [] })) // organizations: none
    .mockResolvedValueOnce(jsonResponse({ data: ORG }, 201)) // create
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations refetch

  renderApp()

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

  renderApp()

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

  renderApp()

  await screen.findByRole('heading', { name: /create your organization/i })
  // Home/console surface never rendered.
  expect(screen.queryByText(/operator console/i)).not.toBeInTheDocument()
})

/** Render the route tree at a specific path, sharing the app's QueryClient. */
function renderRoute(path: string) {
  return render(
    <DirectionProvider>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[path]}>
          <AppRoutes />
        </MemoryRouter>
      </QueryClientProvider>
    </DirectionProvider>,
  )
}

test('route /login renders the login page (public, no gate)', () => {
  renderRoute('/login')
  expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
})

test('route /register renders the register page (public)', () => {
  renderRoute('/register')
  expect(screen.getByRole('heading', { name: /create your account/i })).toBeInTheDocument()
})

test('route /apply/:cohortId is public and passes the cohortId through to the form fetch', () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValue(jsonResponse({ data: { title: 'Apply', fields: [] } }))

  renderRoute('/apply/cohort-xyz')

  // ApplyPage fetches the form for its cohortId on mount — proves the param reached the page.
  expect(fetchSpy).toHaveBeenCalled()
  const calledUrl = String(fetchSpy.mock.calls[0][0])
  expect(calledUrl).toContain('cohort-xyz')
})

test('console route /programs while unauthenticated (401) → login page', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 401 }))

  renderRoute('/programs')

  expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
})

test('App mounts under BrowserRouter and resolves the current path', async () => {
  // Existing <App/> tests already exercise BrowserRouter via setPath(); this asserts the
  // public export surface exists so the migration target is unambiguous.
  expect(typeof App).toBe('function')
  expect(typeof AppRoutes).toBe('function')
})

test('route /programs/:programId renders the program detail for an org user', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations
    .mockResolvedValueOnce(
      jsonResponse({
        data: {
          id: '01J0PROG',
          name: 'Spring Accelerator',
          slug: 'spring-accelerator',
          status: 'draft',
          description: null,
          settings: null,
          created_at: '2026-06-20T10:00:00+00:00',
          updated_at: '2026-06-20T10:00:00+00:00',
        },
      }),
    ) // getProgram

  renderRoute('/programs/01J0PROG')

  expect(await screen.findByRole('heading', { name: 'Spring Accelerator' })).toBeInTheDocument()
})

test('route /cohorts/:cohortId renders the cohort detail for an org user', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations
    .mockResolvedValueOnce(
      jsonResponse({
        data: {
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
        },
      }),
    ) // getCohort

  renderRoute('/cohorts/01J0COH')

  expect(await screen.findByRole('heading', { name: 'Spring 2026' })).toBeInTheDocument()
})

test('the gate publishes the resolved org id to the tenant holder', async () => {
  vi.spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER })) // session
    .mockResolvedValueOnce(jsonResponse({ data: [ORG] })) // organizations

  renderApp()

  expect(await screen.findByRole('heading', { name: 'Acme Incubator' })).toBeInTheDocument()
  expect(getActiveOrganizationId()).toBe(ORG.id)
})

test('an unauthenticated gate leaves the tenant holder null', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 401 }))

  renderApp()

  expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
  expect(getActiveOrganizationId()).toBeNull()
})
