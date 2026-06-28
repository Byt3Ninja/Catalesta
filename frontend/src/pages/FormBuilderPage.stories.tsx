import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormBuilderPage } from './FormBuilderPage'

const FORM = {
  id: 'frm_draft',
  name: 'New form',
  description: null,
  latest_version: 1,
  published_version_ids: [],
  current_draft_version_id: 'fv_draft_1',
}
const DRAFT = {
  id: 'fv_draft_1',
  form_id: 'frm_draft',
  version: 1,
  status: 'draft',
  fields: [],
  created_at: new Date().toISOString(),
  published_at: null,
}

function withProviders(Decorator: (Story: () => ReactElement) => ReactElement) {
  return Decorator
}

const decorator = withProviders((Story) => {
  globalThis.fetch = (async (input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('/forms/frm_draft/draft'))
      return new Response(JSON.stringify({ data: DRAFT }), { status: 200, headers: { 'Content-Type': 'application/json' } })
    if (url.includes('/form-versions/fv_draft_1'))
      return new Response(JSON.stringify({ data: DRAFT }), { status: 200, headers: { 'Content-Type': 'application/json' } })
    if (url.includes('/forms/frm_draft'))
      return new Response(JSON.stringify({ data: FORM }), { status: 200, headers: { 'Content-Type': 'application/json' } })
    if (url.includes('/sanctum/csrf-cookie'))
      return new Response(null, { status: 204 })
    return new Response(null, { status: 404 })
  }) as typeof fetch

  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <Story />
      </QueryClientProvider>
    </DirectionProvider>
  )
})

const meta = {
  title: 'Pages/FormBuilderPage',
  component: FormBuilderPage,
  args: { formId: 'frm_draft' },
} satisfies Meta<typeof FormBuilderPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {
  decorators: [decorator],
}
