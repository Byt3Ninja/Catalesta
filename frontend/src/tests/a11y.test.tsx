import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import type { ReactElement } from 'react'
import { afterEach, describe, expect, it, vi } from 'vitest'
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
import { CohortSetupWizard } from '../pages/CohortSetupWizard'
import { EnrollmentWindowEditor } from '../pages/EnrollmentWindowEditor'
import { FormRenderer } from '../components/FormRenderer'
import { FormBindingPicker } from '../components/FormBindingPicker'
import { FormBuilderPage } from '../pages/FormBuilderPage'
import { FormPreviewPage } from '../pages/FormPreviewPage'
import { FormVersionsPage } from '../pages/FormVersionsPage'
import { StagePipelineBuilderPage } from '../pages/StagePipelineBuilderPage'
import { StagePipelinePreviewPage } from '../pages/StagePipelinePreviewPage'
import { StagePipelineVersionsPage } from '../pages/StagePipelineVersionsPage'
import { ProgramConfigPage } from '../pages/ProgramConfigPage'
import { ScoringModelBuilderPage } from '../pages/ScoringModelBuilderPage'
import { ScoringModelPreviewPage } from '../pages/ScoringModelPreviewPage'
import { ScoringModelVersionsPage } from '../pages/ScoringModelVersionsPage'
import { ReviewQueuePage } from '../pages/ReviewQueuePage'
import { ScorecardPage } from '../pages/ScorecardPage'
import { jsonResponse } from './test-utils'

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

// --- Fixtures for Slice 2b mocked a11y cases ---
const A11Y_FORM = {
  id: 'frm_draft',
  name: 'New form',
  description: null,
  latest_version: 1,
  published_version_ids: [],
  current_draft_version_id: 'fv_draft_1',
}
const A11Y_DRAFT = {
  id: 'fv_draft_1',
  form_id: 'frm_draft',
  version: 1,
  status: 'draft',
  fields: [{ id: 'f1', type: 'short_text' as const, label: 'Startup name', required: false }],
  created_at: '2026-06-01T00:00:00Z',
  published_at: null,
}
const A11Y_VERSION = {
  id: 'fv_pub_1',
  form_id: 'frm_pub',
  version: 1,
  status: 'published',
  fields: [{ id: 'f1', type: 'short_text' as const, label: 'Startup name', required: false }],
  created_at: '2026-06-01T00:00:00Z',
  published_at: '2026-06-01T00:00:00Z',
}
const A11Y_VERSIONS_LIST = [A11Y_VERSION]
const A11Y_FORMS_LIST = [
  {
    id: 'frm_pub',
    name: 'Application form',
    description: null,
    latest_version: 1,
    published_version_ids: ['fv_pub_1'],
    current_draft_version_id: null,
  },
]
const A11Y_VERSIONS_FOR_PICKER = [A11Y_VERSION]

describe('a11y gate (axe-core)', () => {
  afterEach(() => vi.restoreAllMocks())
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

  it('CohortSetupWizard — step 1 (Slice 2a)', async () => {
    await expectNoViolations(withProviders(<CohortSetupWizard programId="prog_1" />))
  })

  it('EnrollmentWindowEditor — loading shell (Slice 2a)', async () => {
    // fetch is unmocked here → query stays pending → renders the Spinner shell, which must be a11y-clean
    await expectNoViolations(withProviders(<EnrollmentWindowEditor cohortId="coh_1" />))
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

  // --- Slice 2b a11y cases ---

  it('FormRenderer — 2-field fixture (Slice 2b)', async () => {
    const fields = [
      { id: 'f1', type: 'short_text' as const, label: 'Startup name', required: true },
      { id: 'f2', type: 'single_select' as const, label: 'Stage', options: ['Idea', 'MVP'] },
    ]
    await expectNoViolations(
      withProviders(
        <FormLayout>
          <FormRenderer fields={fields} answers={{}} onChange={() => {}} />
        </FormLayout>,
      ),
    )
  })

  it('FormBuilderPage — real palette/canvas renders (Slice 2b)', async () => {
    // Mock getForm + draft fetch so the palette and canvas fully render (not a spinner).
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = String(input)
      if (url.includes('/forms/frm_draft/draft')) return Promise.resolve(jsonResponse({ data: A11Y_DRAFT }))
      if (url.includes('/form-versions/fv_draft_1')) return Promise.resolve(jsonResponse({ data: A11Y_DRAFT }))
      if (url.includes('/forms/frm_draft')) return Promise.resolve(jsonResponse({ data: A11Y_FORM }))
      return Promise.resolve(new Response(null, { status: 404 }))
    })
    const { container } = render(withProviders(<FormBuilderPage formId="frm_draft" />))
    // Wait for the builder heading to confirm the real surface has rendered
    await screen.findByRole('heading', { name: /form builder|new form/i })
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('FormPreviewPage — real fields render (Slice 2b)', async () => {
    // Mock getFormVersion so the field list renders instead of a spinner.
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: A11Y_VERSION }))
    const { container } = render(withProviders(<FormPreviewPage versionId="fv_pub_1" />))
    // Wait for the field label to appear — confirms the real form DOM is in the container
    await screen.findByText('Startup name')
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('FormVersionsPage — real version list renders (Slice 2b)', async () => {
    // Mock listFormVersions so the version rows render instead of the loading state.
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = String(input)
      if (url.includes('/forms/frm_pub/versions')) return Promise.resolve(jsonResponse({ data: A11Y_VERSIONS_LIST }))
      return Promise.resolve(new Response(null, { status: 404 }))
    })
    const { container } = render(withProviders(<FormVersionsPage formId="frm_pub" />))
    // Wait for a version row to confirm the list has rendered
    await screen.findByText(/Version 1/i)
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('FormBindingPicker — labelled select is in audited DOM (Slice 2b select-name fix)', async () => {
    // Mock listForms + listFormVersions so the labelled <select id="form-binding-select"> renders.
    // The select is only rendered after loading completes, so we must await it before axe runs —
    // this is the critical assertion: the label+id wiring is in the live DOM, not just static JSX.
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(jsonResponse({ data: A11Y_FORMS_LIST }))
      .mockResolvedValueOnce(jsonResponse({ data: A11Y_VERSIONS_FOR_PICKER }))
    const { container } = render(
      withProviders(
        <FormBindingPicker cohortId="coh_1" boundVersionId={null} onBound={() => {}} />,
      ),
    )
    // Await the labelled <select> — this confirms the select-name fix is in the audited DOM
    await screen.findByLabelText('Published version')
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  // --- Slice 2c a11y cases ---

  const A11Y_PIPELINE = { pipeline_id: 'pl_draft', program_id: 'prog_1', name: 'New pipeline', latest_version: 1, published_version_ids: [], current_draft_version_id: 'plv_draft_1', created_at: 'x' }
  const A11Y_STAGE = { stage_id: 's_screen', name: 'Screening', type: 'review' as const, entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, order: 0 }
  const A11Y_STAGE_DRAFT = { version_id: 'plv_draft_1', pipeline_id: 'pl_draft', version: 1, status: 'draft', stages: [A11Y_STAGE], created_at: 'x', published_at: null }
  const A11Y_STAGE_PUB = { version_id: 'plv_pub_1', pipeline_id: 'pl_pub', version: 1, status: 'published', stages: [A11Y_STAGE], created_at: 'x', published_at: 'x' }

  it('StagePipelineBuilderPage — real palette/canvas renders (Slice 2c)', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = String(input)
      if (url.includes('/stage-pipeline-versions/plv_draft_1')) return Promise.resolve(jsonResponse({ data: A11Y_STAGE_DRAFT }))
      if (url.includes('/stage-pipelines/pl_draft')) return Promise.resolve(jsonResponse({ data: A11Y_PIPELINE }))
      return Promise.resolve(new Response(null, { status: 404 }))
    })
    const { container } = render(withProviders(<StagePipelineBuilderPage pipelineId="pl_draft" />))
    await screen.findByRole('heading', { name: /stage builder|new pipeline/i })
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('StagePipelinePreviewPage — real stages render (Slice 2c)', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: A11Y_STAGE_PUB }))
    const { container } = render(withProviders(<StagePipelinePreviewPage versionId="plv_pub_1" />))
    await screen.findByText('1. Screening')
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('StagePipelineVersionsPage — real version list renders (Slice 2c)', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = String(input)
      if (url.includes('/stage-pipelines/pl_pub/versions')) return Promise.resolve(jsonResponse({ data: [A11Y_STAGE_PUB] }))
      if (url.includes('/stage-pipelines/pl_pub')) return Promise.resolve(jsonResponse({ data: { ...A11Y_PIPELINE, pipeline_id: 'pl_pub', published_version_ids: ['plv_pub_1'] } }))
      return Promise.resolve(new Response(null, { status: 404 }))
    })
    const { container } = render(withProviders(<StagePipelineVersionsPage pipelineId="pl_pub" />))
    await screen.findByText(/Version 1/i)
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('ProgramConfigPage — forms + stages cards render (Slice 2c)', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = String(input)
      if (url.includes('/programs/prog_1/stage-pipelines')) return Promise.resolve(jsonResponse({ data: [{ ...A11Y_PIPELINE, pipeline_id: 'pl_pub', name: 'Acceleration pipeline', published_version_ids: ['plv_pub_1'] }] }))
      if (url.includes('/forms')) return Promise.resolve(jsonResponse({ data: A11Y_FORMS_LIST }))
      if (url.includes('/programs/prog_1/scoring-models')) return Promise.resolve(jsonResponse({ data: [] }))
      return Promise.resolve(new Response(null, { status: 404 }))
    })
    const { container } = render(withProviders(<ProgramConfigPage programId="prog_1" />))
    await screen.findByText('Acceleration pipeline')
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  // --- Slice 2d a11y cases ---

  const A11Y_SCORING_MODEL = { model_id: 'sm_draft', program_id: 'prog_1', name: 'Market Fit Assessment', latest_version: 1, published_version_ids: [], current_draft_version_id: 'smv_draft_1', created_at: 'x' }
  const A11Y_SCORING_DRAFT = { version_id: 'smv_draft_1', model_id: 'sm_draft', version: 1, status: 'draft', criteria: [{ criterion_id: 'c1', label: 'Innovation', max_points: 10, descriptors: null }], created_at: 'x', published_at: null }
  const A11Y_SCORING_PUB = { version_id: 'smv_pub_1', model_id: 'sm_pub', version: 1, status: 'published', criteria: [{ criterion_id: 'c1', label: 'Innovation', max_points: 10, descriptors: null }], created_at: 'x', published_at: 'x' }
  const A11Y_SCORING_MODELS_LIST = [
    { model_id: 'sm_pub', program_id: 'prog_1', name: 'Technical Assessment', latest_version: 1, published_version_ids: ['smv_pub_1'], current_draft_version_id: null, created_at: 'x' },
  ]

  it('ScoringModelBuilderPage — real canvas renders (Slice 2d)', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = String(input)
      if (url.includes('/scoring-model-versions/smv_draft_1')) return Promise.resolve(jsonResponse({ data: A11Y_SCORING_DRAFT }))
      if (url.includes('/scoring-models/sm_draft')) return Promise.resolve(jsonResponse({ data: A11Y_SCORING_MODEL }))
      return Promise.resolve(new Response(null, { status: 404 }))
    })
    const { container } = render(withProviders(<ScoringModelBuilderPage modelId="sm_draft" />))
    await screen.findByRole('heading', { name: /scoring model builder/i })
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('ScoringModelPreviewPage — real criteria render (Slice 2d)', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: A11Y_SCORING_PUB }))
    const { container } = render(withProviders(<ScoringModelPreviewPage versionId="smv_pub_1" />))
    // "1. Innovation" is the criterion heading text — use regex to match substring
    await screen.findByText(/Innovation/i)
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('ScoringModelVersionsPage — real version list renders (Slice 2d)', async () => {
    const A11Y_SM_PUB_META = { model_id: 'sm_pub', program_id: 'prog_1', name: 'Technical Assessment', latest_version: 1, published_version_ids: ['smv_pub_1'], current_draft_version_id: null, created_at: 'x' }
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = String(input)
      if (url.includes('/scoring-models/sm_pub/versions')) return Promise.resolve(jsonResponse({ data: [A11Y_SCORING_PUB] }))
      if (url.includes('/scoring-models/sm_pub')) return Promise.resolve(jsonResponse({ data: A11Y_SM_PUB_META }))
      return Promise.resolve(new Response(null, { status: 404 }))
    })
    const { container } = render(withProviders(<ScoringModelVersionsPage modelId="sm_pub" />))
    await screen.findByText(/Version 1/i)
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('ReviewQueuePage — empty queue renders (Slice 2d)', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: [] }))
    const { container } = render(withProviders(<ReviewQueuePage cohortId="coh_1" stageId="s_screen" reviewerId="acc_demo" />))
    // Wait for the empty-state message to confirm the assignments query has resolved
    await screen.findByText(/no applications assigned/i)
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('ScorecardPage — criteria form renders (Slice 2d)', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = String(input)
      // 404 on scorecard → first-time scoring (null scorecard, criteria form renders)
      if (url.includes('/scorecards/')) return Promise.resolve(new Response(null, { status: 404 }))
      if (url.includes('/scoring-model-versions/smv_pub_1')) return Promise.resolve(jsonResponse({ data: A11Y_SCORING_PUB }))
      return Promise.resolve(new Response(null, { status: 404 }))
    })
    const { container } = render(
      withProviders(
        <ScorecardPage
          cohortId="coh_1"
          stageId="s_screen"
          applicationId="app_1"
          reviewerId="acc_demo"
          modelVersionId="smv_pub_1"
        />,
      ),
    )
    // Wait for the criterion label to confirm the scoring form is in the DOM
    await screen.findByText(/Innovation/i)
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })

  it('ProgramConfigPage — scoring card renders with data (Slice 2d)', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = String(input)
      if (url.includes('/programs/prog_1/stage-pipelines')) return Promise.resolve(jsonResponse({ data: [] }))
      if (url.includes('/programs/prog_1/scoring-models')) return Promise.resolve(jsonResponse({ data: A11Y_SCORING_MODELS_LIST }))
      if (url.includes('/forms')) return Promise.resolve(jsonResponse({ data: [] }))
      return Promise.resolve(new Response(null, { status: 404 }))
    })
    const { container } = render(withProviders(<ProgramConfigPage programId="prog_1" />))
    await screen.findByText('Technical Assessment')
    const results = await axe.run(container, AXE_OPTIONS)
    const summary = results.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`).join('\n')
    expect(results.violations, summary).toHaveLength(0)
  })
})
