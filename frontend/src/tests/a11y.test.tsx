import { render } from '@testing-library/react'
import axe from 'axe-core'
import type { ReactElement } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { ApplyField } from '../pages/ApplyField'
import { DirectionProvider } from '../app/DirectionProvider'
import { LoginPage } from '../pages/LoginPage'
import { OnboardingPage } from '../pages/OnboardingPage'
import { ActionCenterPage } from '../pages/ActionCenterPage'
import { ProgramsPage } from '../pages/ProgramsPage'
import { SubmissionsPage } from '../pages/SubmissionsPage'
import { ConsentProvider } from '../app/ConsentProvider'

function withProviders(ui: ReactElement): ReactElement {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <DirectionProvider>
      <QueryClientProvider client={client}>{ui}</QueryClientProvider>
    </DirectionProvider>
  )
}

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

/**
 * Story 1.0 Task 4 — accessibility CI gate. Runs axe-core over each rendered
 * primitive and fails on structural violations (missing form label, accessible
 * name, invalid ARIA, etc.). Runs in the jsdom Vitest project (the green
 * "Frontend" CI lane), NOT Playwright browser mode — see the story's decision
 * record. `color-contrast` and the page-scoped `region` rule are disabled here:
 * contrast is verified arithmetically in contrast.test.ts (jsdom can't compute
 * rendered colour), and `region` is a page-level rule irrelevant to isolated
 * components.
 */
const AXE_OPTIONS: axe.RunOptions = {
  rules: {
    'color-contrast': { enabled: false },
    region: { enabled: false },
  },
}

async function expectNoViolations(ui: ReactElement): Promise<void> {
  const { container } = render(ui)
  const results = await axe.run(container, AXE_OPTIONS)
  const summary = results.violations
    .map((v) => `${v.id}: ${v.help} (${v.nodes.length})`)
    .join('\n')
  expect(results.violations, summary).toHaveLength(0)
}

describe('a11y gate (axe-core)', () => {
  it('Button — all variants/states', async () => {
    await expectNoViolations(
      <>
        <Button>Save</Button>
        <Button variant="secondary">Cancel</Button>
        <Button disabled>Disabled</Button>
        <Button loading>Saving</Button>
      </>,
    )
  })

  it('Field — labelled, with help and error', async () => {
    await expectNoViolations(
      <FormLayout>
        <Field label="Email" type="email" help="We never share it." />
        <Field label="Name" error="Required." />
      </FormLayout>,
    )
  })

  it('Banner — info/error/success', async () => {
    await expectNoViolations(
      <>
        <Banner variant="info">Heads up.</Banner>
        <Banner variant="error">Something failed.</Banner>
        <Banner variant="success">Saved.</Banner>
      </>,
    )
  })

  it('Loading — spinner', async () => {
    await expectNoViolations(<Spinner />)
  })

  it('StateBlock — empty/error/offline', async () => {
    await expectNoViolations(
      <>
        <StateBlock variant="empty" message="No submissions yet." />
        <StateBlock variant="error" message="Could not load." />
        <StateBlock variant="offline" message="You are offline." />
      </>,
    )
  })

  it('Link — has an accessible name', async () => {
    await expectNoViolations(<Link href="/apply">Apply now</Link>)
  })

  it('AppShell — rail + main', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    await expectNoViolations(
      <DirectionProvider>
        <QueryClientProvider client={client}>
          <AppShell rail={<nav aria-label="Sections">Rail</nav>}>
            <h1>Work area</h1>
          </AppShell>
        </QueryClientProvider>
      </DirectionProvider>,
    )
  })

  it('LoginPage — sign-in entry (Story 1.1)', async () => {
    await expectNoViolations(withProviders(<LoginPage />))
  })

  it('OnboardingPage — create-org form (Story 1.1)', async () => {
    await expectNoViolations(withProviders(<OnboardingPage />))
  })

  it('ActionCenterPage — role-scoped home + consent seam (Story 1.5)', async () => {
    await expectNoViolations(
      withProviders(
        <ConsentProvider>
          <ActionCenterPage organization={ORG} />
        </ConsentProvider>,
      ),
    )
  })

  it('ProgramsPage — list + create + publish (Story 1.2)', async () => {
    await expectNoViolations(withProviders(<ProgramsPage organization={ORG} />))
  })

  it('SubmissionsPage — funnel + list (Story 2.8)', async () => {
    await expectNoViolations(withProviders(<SubmissionsPage cohortId="01J0COH" organization={ORG} />))
  })

  it('ApplyField — representative field types (Story 2.7)', async () => {
    const noop = () => {}
    await expectNoViolations(
      <FormLayout>
        <ApplyField
          field={{ type: 'short_text', label: 'Startup name', required: true }}
          value=""
          onChange={noop}
          onFiles={noop}
        />
        <ApplyField
          field={{ type: 'long_text', label: 'Describe your startup', help: 'Be concise.' }}
          value=""
          onChange={noop}
          onFiles={noop}
        />
        <ApplyField
          field={{ type: 'single_select', label: 'Stage', options: ['Idea', 'MVP'] }}
          value=""
          onChange={noop}
          onFiles={noop}
        />
        <ApplyField
          field={{ type: 'multi_select', label: 'Sectors', options: ['Fintech', 'Health'] }}
          value={[]}
          onChange={noop}
          onFiles={noop}
        />
        <ApplyField
          field={{ type: 'date', label: 'Founded on' }}
          value=""
          onChange={noop}
          onFiles={noop}
        />
        <ApplyField
          field={{ type: 'file_upload', label: 'Pitch deck' }}
          value={[]}
          onChange={noop}
          onFiles={noop}
        />
        <ApplyField
          field={{ type: 'consent', label: 'I agree to the terms.', required: true }}
          value={false}
          onChange={noop}
          onFiles={noop}
        />
      </FormLayout>,
    )
  })
})
