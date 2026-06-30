import type { ReactElement } from 'react'
import { useEffect } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { useDirection } from '../app/direction-context'
import { ProgramsPage } from './ProgramsPage'

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

const PROGRAMS = [
  {
    id: '01J0PROG1',
    name: 'Spring Accelerator',
    slug: 'spring-accelerator',
    status: 'draft',
    type: 'accelerator',
    description: null,
    settings: null,
    created_at: '2026-06-20T10:00:00+00:00',
    updated_at: '2026-06-20T10:00:00+00:00',
  },
  {
    id: '01J0PROG2',
    name: 'Founders Bootcamp',
    slug: 'founders-bootcamp',
    status: 'published',
    type: 'incubator',
    description: null,
    settings: null,
    created_at: '2026-06-20T10:00:00+00:00',
    updated_at: '2026-06-20T10:00:00+00:00',
  },
]

const COHORTS = [
  {
    id: 'c1',
    organization_id: '01J0ORG',
    program_id: '01J0PROG1',
    name: 'Cohort 1',
    slug: 'cohort-1',
    status: 'open',
    capacity: 40,
    enrollment_opens_at: null,
    enrollment_closes_at: null,
    starts_at: '2026-01-01T00:00:00Z',
    ends_at: '2026-06-01T00:00:00Z',
    timeline: null,
    submissions_count: 8,
    created_at: '2026-06-20T10:00:00+00:00',
    updated_at: '2026-06-20T10:00:00+00:00',
  },
  {
    id: 'c2',
    organization_id: '01J0ORG',
    program_id: '01J0PROG2',
    name: 'Cohort 2',
    slug: 'cohort-2',
    status: 'closed',
    capacity: 25,
    enrollment_opens_at: null,
    enrollment_closes_at: null,
    starts_at: '2025-09-01T00:00:00Z',
    ends_at: '2025-12-01T00:00:00Z',
    timeline: null,
    submissions_count: 22,
    created_at: '2026-06-20T10:00:00+00:00',
    updated_at: '2026-06-20T10:00:00+00:00',
  },
]

/** Mock fetch that routes /cohorts and /programs to canned data. */
function mockFetch(programs: unknown[], cohorts: unknown[]) {
  globalThis.fetch = (async (url: RequestInfo | URL) => {
    const urlStr = String(url)
    if (urlStr.includes('/cohorts')) {
      return new Response(JSON.stringify({ data: cohorts }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    }
    return new Response(JSON.stringify({ data: programs }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    })
  }) as typeof fetch
}

/** Each story serves a canned program list (a draft + a published program). */
function withProviders(dir: 'ltr' | 'rtl', programs = PROGRAMS, cohorts = COHORTS) {
  function ForceDir({ children }: { children: ReactElement }) {
    const { setDir } = useDirection()
    useEffect(() => setDir(dir), [setDir])
    return children
  }
  return function Decorator(Story: () => ReactElement) {
    mockFetch(programs, cohorts)
    const client = new QueryClient()
    return (
      <DirectionProvider>
        <QueryClientProvider client={client}>
          <ForceDir>
            <Story />
          </ForceDir>
        </QueryClientProvider>
      </DirectionProvider>
    )
  }
}

function withNeverResolve(dir: 'ltr' | 'rtl') {
  function ForceDir({ children }: { children: ReactElement }) {
    const { setDir } = useDirection()
    useEffect(() => setDir(dir), [setDir])
    return children
  }
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (() => new Promise(() => {})) as typeof fetch
    const client = new QueryClient()
    return (
      <DirectionProvider>
        <QueryClientProvider client={client}>
          <ForceDir>
            <Story />
          </ForceDir>
        </QueryClientProvider>
      </DirectionProvider>
    )
  }
}

function withErrorFetch(dir: 'ltr' | 'rtl') {
  function ForceDir({ children }: { children: ReactElement }) {
    const { setDir } = useDirection()
    useEffect(() => setDir(dir), [setDir])
    return children
  }
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (async (url: RequestInfo | URL) => {
      const urlStr = String(url)
      if (urlStr.includes('/cohorts')) {
        return new Response(JSON.stringify({ data: [] }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }
      return new Response(JSON.stringify({ error: 'server error' }), {
        status: 500,
        headers: { 'Content-Type': 'application/json' },
      })
    }) as typeof fetch
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    return (
      <DirectionProvider>
        <QueryClientProvider client={client}>
          <ForceDir>
            <Story />
          </ForceDir>
        </QueryClientProvider>
      </DirectionProvider>
    )
  }
}

const meta = {
  title: 'Pages/ProgramsPage',
  component: ProgramsPage,
  args: { organization: ORG },
} satisfies Meta<typeof ProgramsPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {
  decorators: [withProviders('ltr')],
}

export const Arabic: Story = {
  decorators: [withProviders('rtl')],
}

export const Empty: Story = {
  decorators: [withProviders('ltr', [], [])],
}

export const Loading: Story = {
  decorators: [withNeverResolve('ltr')],
}

export const Error: Story = {
  decorators: [withErrorFetch('ltr')],
}
