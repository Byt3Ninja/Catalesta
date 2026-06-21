import type { ReactElement } from 'react'
import { useEffect } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { useDirection } from '../app/direction-context'
import { SubmissionsPage } from './SubmissionsPage'

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

function decorator(
  dir: 'ltr' | 'rtl',
  funnel: { viewed: number; started: number; submitted: number },
  submissions: unknown[],
) {
  function ForceDir({ children }: { children: ReactElement }) {
    const { setDir } = useDirection()
    useEffect(() => setDir(dir), [setDir])
    return children
  }
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (async (input: RequestInfo | URL) => {
      const url = typeof input === 'string' ? input : String(input)
      const headers = { 'Content-Type': 'application/json' }
      const body = url.includes('/funnel')
        ? { data: funnel }
        : { data: submissions, meta: { total: submissions.length } }
      return new Response(JSON.stringify(body), { status: 200, headers })
    }) as typeof fetch
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

const meta = {
  title: 'Pages/SubmissionsPage',
  component: SubmissionsPage,
  args: { cohortId: '01J0COH', organization: ORG },
} satisfies Meta<typeof SubmissionsPage>

export default meta
type Story = StoryObj<typeof meta>

export const WithSubmissions: Story = {
  decorators: [decorator('ltr', { viewed: 42, started: 18, submitted: 7 }, [ROW])],
}

/** Day-one: no applications yet → empty state + copyable share link. */
export const ZeroDay: Story = {
  decorators: [decorator('ltr', { viewed: 0, started: 0, submitted: 0 }, [])],
}

export const Arabic: Story = {
  decorators: [decorator('rtl', { viewed: 42, started: 18, submitted: 7 }, [ROW])],
}
