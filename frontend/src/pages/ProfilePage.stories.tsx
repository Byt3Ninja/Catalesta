import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ConsentProvider } from '../app/ConsentProvider'
import { ProfilePage } from './ProfilePage'

const PROFILE = { display_name: 'Alice', email: 'alice@catalesta.test', organization: 'Acme Incubator', title: 'Founder' }

function withProviders(profileStatus: number) {
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
      if (url.includes('/me/profile')) {
        if (profileStatus >= 400) return new Response('forbidden', { status: profileStatus })
        return new Response(JSON.stringify(PROFILE), { status: 200, headers })
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
          <ConsentProvider>
            <Story />
          </ConsentProvider>
        </QueryClientProvider>
      </DirectionProvider>
    )
  }
}

const meta = {
  title: 'Pages/ProfilePage',
  component: ProfilePage,
} satisfies Meta<typeof ProfilePage>

export default meta
type Story = StoryObj<typeof meta>

/** Consent required: neutral affordance with "Manage consent" link, no leaked data. */
export const ConsentRequired: Story = {
  decorators: [withProviders(403)],
}

/** Profile granted: full profile fields rendered. */
export const Granted: Story = {
  decorators: [withProviders(200)],
}
