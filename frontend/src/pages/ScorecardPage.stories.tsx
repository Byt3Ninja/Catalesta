import type { ReactElement } from 'react'
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ScorecardPage } from './ScorecardPage'

// ── shared fixtures ───────────────────────────────────────────────────────────

const MODEL_VERSION = {
  version_id: 'smv_story',
  model_id: 'sm_story',
  version: 1,
  status: 'published' as const,
  criteria: [
    {
      criterion_id: 'c_innovation',
      label: 'Innovation',
      max_points: 10,
      descriptors: ['Highly innovative', 'Somewhat innovative', 'Not innovative'],
    },
    {
      criterion_id: 'c_market',
      label: 'Market Opportunity',
      max_points: 10,
      descriptors: ['Large market', 'Medium market', 'Small market'],
    },
    {
      criterion_id: 'c_team',
      label: 'Team Strength',
      max_points: 10,
      descriptors: ['Strong team', 'Adequate team', 'Weak team'],
    },
  ],
  created_at: '2026-06-01T00:00:00Z',
  published_at: '2026-06-01T00:00:00Z',
}

/** Partially-filled draft: c_innovation scored (7), others absent. */
const PARTIAL_SCORECARD = {
  scorecard_id: 'sc_story_1',
  cohort_id: 'coh_story',
  stage_id: 'stg_story',
  application_id: 'app_story_1',
  reviewer_id: 'rev_story',
  model_version_id: 'smv_story',
  values: { c_innovation: 7 },
  disqualified: false,
  status: 'draft' as const,
  submitted_at: null,
}

/** Fully scored, ready to submit. */
const COMPLETE_SCORECARD = {
  ...PARTIAL_SCORECARD,
  values: { c_innovation: 8, c_market: 9, c_team: 7 },
}

/** Already submitted. */
const SUBMITTED_SCORECARD = {
  ...COMPLETE_SCORECARD,
  status: 'submitted' as const,
  submitted_at: '2026-06-29T10:00:00Z',
}

// ── story decorator ───────────────────────────────────────────────────────────

function withProviders(
  scorecardPayload: typeof PARTIAL_SCORECARD | null,
  modelVersionPayload: typeof MODEL_VERSION,
) {
  return function Decorator(Story: () => ReactElement) {
    globalThis.fetch = (async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      const method = (init?.method ?? 'GET').toUpperCase()

      if (url.includes('/sanctum/csrf-cookie')) {
        return new Response(null, { status: 204 })
      }
      if (url.includes('/submit') && method === 'POST') {
        return new Response(
          JSON.stringify({
            data: { ...COMPLETE_SCORECARD, status: 'submitted', submitted_at: new Date().toISOString() },
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } },
        )
      }
      if (url.includes('/scorecards/') && method === 'PATCH') {
        const body = JSON.parse(init?.body as string) as Record<string, unknown>
        return new Response(
          JSON.stringify({ data: { ...PARTIAL_SCORECARD, ...body } }),
          { status: 200, headers: { 'Content-Type': 'application/json' } },
        )
      }
      if (url.includes('/scorecards/') && method === 'GET') {
        return scorecardPayload === null
          ? new Response(null, { status: 404 })
          : new Response(JSON.stringify({ data: scorecardPayload }), {
              status: 200,
              headers: { 'Content-Type': 'application/json' },
            })
      }
      if (url.includes('/scoring-model-versions/') && method === 'GET') {
        return new Response(JSON.stringify({ data: modelVersionPayload }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      }
      // Roles / other endpoints
      return new Response(JSON.stringify({ data: [] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
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

// ── meta ──────────────────────────────────────────────────────────────────────

const meta = {
  title: 'Pages/ScorecardPage',
  component: ScorecardPage,
  args: {
    cohortId: 'coh_story',
    stageId: 'stg_story',
    applicationId: 'app_story_1',
    reviewerId: 'rev_story',
    modelVersionId: 'smv_story',
  },
} satisfies Meta<typeof ScorecardPage>

export default meta
type Story = StoryObj<typeof meta>

// ── stories ───────────────────────────────────────────────────────────────────

/** Fresh scorecard — no existing draft. Submit is disabled until all criteria filled. */
export const Fresh: Story = {
  decorators: [withProviders(null, MODEL_VERSION)],
}

/**
 * Partially-filled card — seeded from an existing draft.
 * Innovation has score 7; Market Opportunity and Team Strength are blank.
 * Submit remains disabled.
 */
export const PartiallyFilled: Story = {
  decorators: [withProviders(PARTIAL_SCORECARD, MODEL_VERSION)],
}

/** All criteria scored — Submit is enabled and ready. */
export const ReadyToSubmit: Story = {
  decorators: [withProviders(COMPLETE_SCORECARD, MODEL_VERSION)],
}

/** Scorecard already submitted — shows the success state (read-only). */
export const AlreadySubmitted: Story = {
  decorators: [withProviders(SUBMITTED_SCORECARD, MODEL_VERSION)],
}
