import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { NotificationsPage } from './NotificationsPage'
import type { Notification } from '../schemas/notifications'

const NOW = '2026-06-01T00:00:00Z'

const SAMPLE_NOTIFICATIONS: Notification[] = [
  { id: 'n1', type: 'action', title: 'Review delayed applications', body: '4 applications are past the screening SLA.', created_at: '2026-06-26T09:00:00Z', read_at: null, href: '/preview/applicants' },
  { id: 'n2', type: 'message', title: 'New message from Layla', body: 'Confirming Thursday 3pm mentor session.', created_at: '2026-06-25T14:30:00Z', read_at: null, href: '/preview/sessions' },
  { id: 'n3', type: 'system', title: 'Cohort Spring 2026 opened', body: 'Enrollment is now open.', created_at: '2026-06-24T08:00:00Z', read_at: '2026-06-24T10:00:00Z', href: null },
]

function withProviders(notifications: Notification[]) {
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (async (input: RequestInfo | URL) => {
      const url = typeof input === 'string' ? input : String(input)
      const headers = { 'Content-Type': 'application/json' }
      if (url.includes('/me/roles')) {
        return new Response(JSON.stringify({ data: [{ key: 'program_manager', label: 'Program Manager' }] }), { status: 200, headers })
      }
      if (url.includes('/me/action-center')) {
        return new Response(JSON.stringify({ data: [] }), { status: 200, headers })
      }
      if (url.includes('/notifications/read-all')) {
        for (const n of notifications) { n.read_at = NOW }
        return new Response(null, { status: 204 })
      }
      if (url.includes('/notifications/') && url.includes('/read')) {
        return new Response(null, { status: 204 })
      }
      if (url.includes('/notifications')) {
        return new Response(JSON.stringify({ data: notifications }), { status: 200, headers })
      }
      if (url.includes('/me/profile')) {
        return new Response(null, { status: 403 })
      }
      if (url.includes('/me/consent')) {
        return new Response(JSON.stringify({ data: [] }), { status: 200, headers })
      }
      return new Response(JSON.stringify({ data: [] }), { status: 200, headers })
    }) as typeof fetch
    const client = new QueryClient()
    return (
      <DirectionProvider>
        <QueryClientProvider client={client}>
          <Story />
        </QueryClientProvider>
      </DirectionProvider>
    )
  }
}

const meta = {
  title: 'Pages/NotificationsPage',
  component: NotificationsPage,
} satisfies Meta<typeof NotificationsPage>

export default meta
type Story = StoryObj<typeof meta>

/** Two unread + one read notification — typical inbox state. */
export const WithItems: Story = {
  decorators: [withProviders([...SAMPLE_NOTIFICATIONS])],
}
