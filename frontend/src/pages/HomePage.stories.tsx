import type { ReactElement } from 'react'
import { useEffect } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { useDirection } from '../app/direction-context'
import { ConsentProvider } from '../app/ConsentProvider'
import { HomePage } from './HomePage'

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

const COHORT = {
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
  submissions_count: 3,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

/** Routes /me/profile and /cohorts to canned responses for the story state. */
function decorator(
  dir: 'ltr' | 'rtl',
  cohorts: unknown[],
  profileStatus = 200,
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
      if (url.includes('/me/profile')) {
        if (profileStatus >= 400) return new Response(null, { status: profileStatus })
        return new Response(JSON.stringify({ display_name: 'Layla' }), { status: 200, headers })
      }
      return new Response(JSON.stringify({ data: cohorts }), { status: 200, headers })
    }) as typeof fetch
    const client = new QueryClient()
    return (
      <DirectionProvider>
        <QueryClientProvider client={client}>
          <ConsentProvider>
            <ForceDir>
              <Story />
            </ForceDir>
          </ConsentProvider>
        </QueryClientProvider>
      </DirectionProvider>
    )
  }
}

const meta = {
  title: 'Pages/HomePage',
  component: HomePage,
  args: { organization: ORG },
} satisfies Meta<typeof HomePage>

export default meta
type Story = StoryObj<typeof meta>

/** Day-one: brand-new org, zero cohorts. */
export const DayOne: Story = {
  decorators: [decorator('ltr', [])],
}

/** Cohorts with submissions → "N submissions to score" next action. */
export const WithSubmissions: Story = {
  decorators: [decorator('ltr', [COHORT])],
}

/** Profile consent denied → neutral affordance, no leaked name. */
export const ConsentRequired: Story = {
  decorators: [decorator('ltr', [{ ...COHORT, submissions_count: 0 }], 403)],
}

/** Arabic / RTL. */
export const Arabic: Story = {
  decorators: [decorator('rtl', [{ ...COHORT, name: 'دفعة الربيع' }])],
}
