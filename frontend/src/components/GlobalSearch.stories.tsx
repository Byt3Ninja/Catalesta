import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { GlobalSearch } from './GlobalSearch'
import type { SearchGroup } from '../schemas/search'

const SEARCH_RESULTS: SearchGroup[] = [
  { category: 'programs', items: [
    { id: 'prog_1', label: 'FinTech Accelerator 2026', sublabel: 'Published', href: '/programs/prog_1' },
  ] },
  { category: 'people', items: [
    { id: 'p1', label: 'Alice Founder', sublabel: 'Founder · Acme', href: '/preview/people/p1' },
  ] },
]

function withProviders(results: SearchGroup[]) {
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (async (input: RequestInfo | URL) => {
      const url = typeof input === 'string' ? input : String(input)
      const headers = { 'Content-Type': 'application/json' }
      if (url.includes('/search')) {
        return new Response(JSON.stringify({ data: results }), { status: 200, headers })
      }
      return new Response(JSON.stringify({ data: [] }), { status: 200, headers })
    }) as typeof fetch
    const client = new QueryClient()
    return (
      <DirectionProvider>
        <QueryClientProvider client={client}>
          <div style={{ padding: '16px' }}>
            <Story />
          </div>
        </QueryClientProvider>
      </DirectionProvider>
    )
  }
}

const meta = {
  title: 'Components/GlobalSearch',
  component: GlobalSearch,
} satisfies Meta<typeof GlobalSearch>

export default meta
type Story = StoryObj<typeof meta>

/** Search results dropdown: programs + people categories. The component
 *  shows results once the debounce fires — type "fintech" in the input. */
export const WithResults: Story = {
  decorators: [withProviders(SEARCH_RESULTS)],
}
