import { render, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ConsentProvider } from '../app/ConsentProvider'
import { HomePage } from './HomePage'
import { jsonResponse } from '../tests/test-utils'

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

function cohort(overrides: Record<string, unknown> = {}) {
  return {
    id: '01J0COH',
    organization_id: ORG.id,
    program_id: '01J0PROG',
    name: 'Spring 2026',
    slug: 'spring-2026',
    status: 'open',
    capacity: null,
    enrollment_opens_at: null,
    enrollment_closes_at: null,
    starts_at: null,
    ends_at: null,
    timeline: null,
    submissions_count: 0,
    created_at: '2026-06-20T10:00:00+00:00',
    updated_at: '2026-06-20T10:00:00+00:00',
    ...overrides,
  }
}

/**
 * URL-routing fetch mock: HomePage fires two queries (the consent profile via
 * ConsentProvider, and the cohort list), in no guaranteed order — so we route by
 * path rather than by call order.
 */
function mockApi(opts: {
  cohorts?: unknown[]
  cohortsStatus?: number
  profile?: Record<string, unknown>
  profileStatus?: number
}) {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : String(input)
    if (url.includes('/me/profile')) {
      if (opts.profileStatus && opts.profileStatus >= 400) {
        return Promise.resolve(new Response(null, { status: opts.profileStatus }))
      }
      return Promise.resolve(jsonResponse(opts.profile ?? {}))
    }
    if (url.includes('/cohorts')) {
      if (opts.cohortsStatus && opts.cohortsStatus >= 400) {
        return Promise.resolve(new Response(null, { status: opts.cohortsStatus }))
      }
      return Promise.resolve(jsonResponse({ data: opts.cohorts ?? [] }))
    }
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}

function renderHome(dir: 'ltr' | 'rtl' = 'ltr', theme: 'light' | 'dark' = 'light') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider initialDir={dir} initialTheme={theme}>
      <QueryClientProvider client={client}>
        <ConsentProvider>
          <HomePage organization={ORG} />
        </ConsentProvider>
      </QueryClientProvider>
    </DirectionProvider>
  )
  return render(ui)
}

afterEach(() => {
  vi.restoreAllMocks()
})

test('day-one: zero cohorts shows the empty state explaining the first action', async () => {
  mockApi({ cohorts: [], profile: { display_name: 'Layla' } })
  renderHome()

  expect(await screen.findByText(/no cohorts yet/i)).toBeInTheDocument()
  expect(screen.getByText(/create a program/i)).toBeInTheDocument()
  expect(screen.getByRole('link', { name: /go to programs/i })).toHaveAttribute('href', '/programs')
  // Not the deferred Action Center, and no "submissions to score" yet.
  expect(screen.queryByText(/to score/i)).not.toBeInTheDocument()
})

test('cohorts with submissions → next action "N submissions to score" (text until 2.8)', async () => {
  mockApi({
    cohorts: [cohort({ submissions_count: 3 })],
    profile: { display_name: 'Layla' },
  })
  renderHome()

  // Surfaced as text, not a link — the scoring UI (Story 2.8) has no route yet,
  // so a link would self-loop to Home.
  expect(await screen.findByText(/3 submissions to score/i)).toBeInTheDocument()
  expect(screen.queryByRole('link', { name: /to score/i })).not.toBeInTheDocument()
})

test('cohorts but none with submissions → next action "Open a cohort"', async () => {
  mockApi({ cohorts: [cohort({ submissions_count: 0 })], profile: {} })
  renderHome()

  expect(await screen.findByRole('link', { name: /open a cohort/i })).toBeInTheDocument()
  expect(screen.queryByText(/to score/i)).not.toBeInTheDocument()
})

test('consent granted → greets the operator by profile name', async () => {
  mockApi({ cohorts: [], profile: { display_name: 'Layla' } })
  renderHome()

  expect(await screen.findByText(/welcome back/i)).toHaveTextContent('Layla')
})

test('consent denied → neutral affordance, no crash, no profile name', async () => {
  mockApi({ cohorts: [], profileStatus: 403 })
  renderHome()

  expect(await screen.findByText(/profile details are hidden/i)).toBeInTheDocument()
  expect(screen.queryByText(/welcome back/i)).not.toBeInTheDocument()
})

test('cohort load failure → error state with retry', async () => {
  mockApi({ cohortsStatus: 500, profile: {} })
  renderHome()

  expect(await screen.findByText(/could not load your cohorts/i)).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument()
})

test('renders in RTL + dark with bdi-isolated interpolated values (UX-DR5/6)', async () => {
  mockApi({ cohorts: [cohort({ name: 'دفعة الربيع', submissions_count: 0 })], profile: {} })
  const { container } = renderHome('rtl', 'dark')

  // Await the cohort row (the query must settle), then assert org + cohort names
  // render and interpolated values are bdi-isolated.
  expect(await screen.findByText('دفعة الربيع')).toBeInTheDocument()
  expect(screen.getByText('Acme Incubator')).toBeInTheDocument()
  expect(container.querySelector('bdi')).not.toBeNull()
})
