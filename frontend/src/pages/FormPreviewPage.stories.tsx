import type { ReactElement } from 'react'
import { useEffect } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { useDirection } from '../app/direction-context'
import { FormPreviewPage } from './FormPreviewPage'

const VERSION = {
  id: 'fv_pub_1',
  form_id: 'frm_pub',
  version: 1,
  status: 'published',
  published_at: '2026-06-20T10:00:00+00:00',
  created_at: '2026-06-20T10:00:00+00:00',
  fields: [
    { id: 'f_name', type: 'short_text', label: 'Startup name', required: true },
    { id: 'f_desc', type: 'long_text', label: 'Describe your startup', required: false },
    { id: 'f_stage', type: 'single_select', label: 'Stage', required: true, options: ['Idea', 'Pre-seed', 'Seed'] },
  ],
}

function withProviders(dir: 'ltr' | 'rtl') {
  function ForceDir({ children }: { children: ReactElement }) {
    const { setDir } = useDirection()
    useEffect(() => setDir(dir), [setDir])
    return children
  }
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (async () =>
      new Response(JSON.stringify({ data: VERSION }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })) as typeof fetch
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
  title: 'Pages/FormPreviewPage',
  component: FormPreviewPage,
  args: { versionId: 'fv_pub_1' },
} satisfies Meta<typeof FormPreviewPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {
  decorators: [withProviders('ltr')],
}

export const Arabic: Story = {
  decorators: [withProviders('rtl')],
}
