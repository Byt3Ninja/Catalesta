import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { SubmissionsPage } from './SubmissionsPage'
import { jsonResponse } from '../tests/test-utils'

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

const ROW = {
  reference_number: '01J0SUB',
  cohort_id: '01J0COH',
  submitted_at: '2026-06-21T10:00:00+00:00',
}

function mockApi(opts: {
  funnel?: { viewed: number; started: number; submitted: number }
  submissions?: unknown[]
  submissionsStatus?: number
}) {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : String(input)
    if (url.includes('/funnel')) {
      return Promise.resolve(
        jsonResponse({ data: opts.funnel ?? { viewed: 0, started: 0, submitted: 0 } }),
      )
    }
    if (url.includes('/submissions')) {
      if (opts.submissionsStatus && opts.submissionsStatus >= 400) {
        return Promise.resolve(new Response(null, { status: opts.submissionsStatus }))
      }
      return Promise.resolve(jsonResponse({ data: opts.submissions ?? [], meta: { total: 0 } }))
    }
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}

function renderPage(dir: 'ltr' | 'rtl' = 'ltr', theme: 'light' | 'dark' = 'light') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider initialDir={dir} initialTheme={theme}>
      <QueryClientProvider client={client}>
        <SubmissionsPage cohortId="01J0COH" organization={ORG} />
      </QueryClientProvider>
    </DirectionProvider>
  )
  return render(ui)
}

afterEach(() => {
  vi.restoreAllMocks()
})

test('renders the funnel with the approximate-views caveat', async () => {
  mockApi({ funnel: { viewed: 9, started: 4, submitted: 2 }, submissions: [ROW] })
  renderPage()

  const funnel = await screen.findByRole('group', { name: /application funnel/i })
  expect(funnel).toHaveTextContent('9')
  expect(funnel).toHaveTextContent('4')
  expect(funnel).toHaveTextContent('2')
  expect(screen.getByText(/approximate/i)).toBeInTheDocument()
})

test('zero-day: no submissions shows the empty state + copyable share link', async () => {
  mockApi({ funnel: { viewed: 0, started: 0, submitted: 0 }, submissions: [] })
  renderPage()

  expect(await screen.findByText(/no applications yet/i)).toBeInTheDocument()
  // The public apply URL is shown, and a copy control flips to "Copied" only once
  // the clipboard write resolves (jsdom has no Clipboard API, so we provide one).
  expect(screen.getByText(/\/apply\/01J0COH/)).toBeInTheDocument()
  const writeText = vi.fn().mockResolvedValue(undefined)
  Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
  fireEvent.click(screen.getByRole('button', { name: /copy link/i }))
  expect(await screen.findByRole('button', { name: /copied/i })).toBeInTheDocument()
  expect(writeText).toHaveBeenCalledWith(expect.stringContaining('/apply/01J0COH'))
})

test('lists submissions with a focusable open-detail link', async () => {
  mockApi({ funnel: { viewed: 3, started: 2, submitted: 1 }, submissions: [ROW] })
  renderPage()

  const link = await screen.findByRole('link', { name: /open detail/i })
  expect(link).toHaveAttribute('href', '/cohorts/01J0COH/submissions/01J0SUB')
})

test('submissions load failure shows an error with retry', async () => {
  mockApi({ funnel: { viewed: 0, started: 0, submitted: 0 }, submissionsStatus: 500 })
  renderPage()

  expect(await screen.findByText(/could not load submissions/i)).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument()
})

test('renders in RTL + dark', async () => {
  mockApi({ funnel: { viewed: 1, started: 1, submitted: 0 }, submissions: [] })
  const { container } = renderPage('rtl', 'dark')

  expect(await screen.findByText(/no applications yet/i)).toBeInTheDocument()
  expect(container.querySelector('bdi')).not.toBeNull()
})
