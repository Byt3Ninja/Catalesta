import { render, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { DirectionProvider } from '../app/DirectionProvider'
import { ConsentProvider } from '../app/ConsentProvider'
import { ActionCenterPage } from './ActionCenterPage'
import { setActiveRole, getActiveRole } from '../app/active-role'
import { jsonResponse } from '../tests/test-utils'

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

/** Fixture: program_manager items (mirrors handlers.ts ACTION_CENTER). */
const PM_ITEMS = [
  { id: 'pm1', section: 'required_actions', what: 'Review 4 delayed applications', why: 'Past the screening SLA', deadline: 'Today', who: 'You', href: '/preview/applicants', blocker: null },
  { id: 'pm2', section: 'required_actions', what: 'Assign evaluators to Spring 2026', why: '12 submissions unassigned', deadline: 'Jun 30', who: 'You', href: '/preview/selection', blocker: null },
  { id: 'pm3', section: 'blocked_items', what: 'Approve stage transition', why: 'Cohort cannot advance', deadline: null, who: 'You', href: '/preview/configuration', blocker: 'Missing evaluator coverage' },
]

/** Fixture: founder items (mirrors handlers.ts ACTION_CENTER). */
const FOUNDER_ITEMS = [
  { id: 'f1', section: 'required_actions', what: 'Complete the Team section', why: 'Application is 80% done', deadline: 'Jul 2', who: 'You', href: '/preview/my-application', blocker: null },
  { id: 'f2', section: 'required_actions', what: 'Upload the rejected pitch deck', why: 'Reviewer requested a new version', deadline: 'Jul 1', who: 'You', href: '/preview/documents', blocker: null },
  { id: 'f3', section: 'upcoming_sessions', what: 'Confirm mentor session', why: 'With Layla, Thu 3pm', deadline: 'Wed', who: 'You', href: '/preview/sessions', blocker: null },
]

/**
 * URL-routing fetch mock: ActionCenterPage fires two queries (profile via
 * ConsentProvider and action-center by role), in no guaranteed order — route by
 * path rather than call order.
 */
function mockApi(opts: {
  role?: string
  items?: unknown[]
  profile?: Record<string, unknown>
  profileStatus?: number
  actionCenterStatus?: number
}) {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : String(input)
    if (url.includes('/me/profile')) {
      if (opts.profileStatus && opts.profileStatus >= 400) {
        return Promise.resolve(new Response(null, { status: opts.profileStatus }))
      }
      return Promise.resolve(jsonResponse(opts.profile ?? {}))
    }
    if (url.includes('/me/action-center')) {
      if (opts.actionCenterStatus && opts.actionCenterStatus >= 400) {
        return Promise.resolve(new Response(null, { status: opts.actionCenterStatus }))
      }
      return Promise.resolve(jsonResponse({ data: opts.items ?? [] }))
    }
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <MemoryRouter>
        <QueryClientProvider client={client}>
          <ConsentProvider>
            <ActionCenterPage organization={ORG} />
          </ConsentProvider>
        </QueryClientProvider>
      </MemoryRouter>
    </DirectionProvider>
  )
  return { result: render(ui), client }
}

afterEach(() => {
  vi.restoreAllMocks()
  // Reset active role to default after each test.
  const current = getActiveRole()
  if (current !== 'program_manager') {
    setActiveRole('program_manager')
  }
})

test('program_manager: renders Required actions heading and a card', async () => {
  mockApi({ items: PM_ITEMS, profile: { display_name: 'Alice' } })
  renderPage()

  expect(await screen.findByText('Required actions')).toBeInTheDocument()
  expect(screen.getByText('Review 4 delayed applications')).toBeInTheDocument()
})

test('program_manager: renders Blocked items section', async () => {
  mockApi({ items: PM_ITEMS, profile: {} })
  renderPage()

  expect(await screen.findByText('Blocked items')).toBeInTheDocument()
  expect(screen.getByText('Approve stage transition')).toBeInTheDocument()
})

test('founder: after setActiveRole, renders founder card "Complete the Team section"', async () => {
  // Start as program_manager, then switch to founder before render so the
  // query key ['action-center', 'founder'] is used from the start.
  setActiveRole('founder')
  mockApi({ items: FOUNDER_ITEMS, profile: {} })
  renderPage()

  expect(await screen.findByText('Complete the Team section')).toBeInTheDocument()
  expect(screen.getByText('Upcoming sessions')).toBeInTheDocument()
})

test('consent granted → greets the user by profile display name', async () => {
  mockApi({ items: PM_ITEMS, profile: { display_name: 'Alice' } })
  renderPage()

  expect(await screen.findByText(/welcome back/i)).toBeInTheDocument()
})

test('consent denied → shows neutral action center greeting, no profile name', async () => {
  mockApi({ items: PM_ITEMS, profileStatus: 403 })
  renderPage()

  // Wait for action center items to load so the render has settled.
  expect(await screen.findByText('Review 4 delayed applications')).toBeInTheDocument()
  expect(screen.queryByText(/welcome back/i)).not.toBeInTheDocument()
  expect(screen.getByText('Your action center.')).toBeInTheDocument()
})

test('day-one: empty items shows empty state message', async () => {
  mockApi({ items: [], profile: {} })
  renderPage()

  expect(await screen.findByText(/nothing needs your attention/i)).toBeInTheDocument()
})

test('action-center load failure → error state with retry button', async () => {
  mockApi({ actionCenterStatus: 500, profile: {} })
  renderPage()

  expect(await screen.findByText(/could not load your action center/i)).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument()
})

test('sections render in ACTION_SECTIONS order: required_actions before blocked_items', async () => {
  mockApi({ items: PM_ITEMS, profile: {} })
  renderPage()

  await screen.findByText('Required actions')
  const headings = screen.getAllByRole('heading', { level: 2 })
  const labels = headings.map((h) => h.textContent)
  const reqIdx = labels.indexOf('Required actions')
  const blkIdx = labels.indexOf('Blocked items')
  expect(reqIdx).toBeGreaterThanOrEqual(0)
  expect(blkIdx).toBeGreaterThan(reqIdx)
})
