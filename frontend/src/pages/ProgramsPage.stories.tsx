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

/** Each story serves a canned program list (a draft + a published program). */
function withProviders(dir: 'ltr' | 'rtl') {
  function ForceDir({ children }: { children: ReactElement }) {
    const { setDir } = useDirection()
    useEffect(() => setDir(dir), [setDir])
    return children
  }
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (async () =>
      new Response(
        JSON.stringify({
          data: [
            {
              id: '01J0PROG1',
              name: 'Spring Accelerator',
              slug: 'spring-accelerator',
              status: 'draft',
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
              description: null,
              settings: null,
              created_at: '2026-06-20T10:00:00+00:00',
              updated_at: '2026-06-20T10:00:00+00:00',
            },
          ],
        }),
        { status: 200, headers: { 'Content-Type': 'application/json' } },
      )) as typeof fetch
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
