import { apiFetch } from './tenant'
import { csrfFetch } from './csrf'
import {
  GetScoringModelError, SaveScoringModelError, PublishScoringModelError,
  AssignmentError, ScorecardError, DecisionError,
  scoringModelListResponseSchema, scoringModelResponseSchema,
  scoringModelVersionResponseSchema, scoringModelVersionListResponseSchema,
  reviewerAssignmentListResponseSchema, scorecardResponseSchema, decisionSchema,
  type ScoringModel, type ScoringModelVersion, type ScoringCriterion,
  type ReviewerAssignment, type Scorecard, type Decision,
} from '../schemas/assessments'

export { GetScoringModelError, SaveScoringModelError, PublishScoringModelError, AssignmentError, ScorecardError, DecisionError }
export type { Decision }

export async function listScoringModels(programId: string): Promise<ScoringModel[]> {
  const res = await apiFetch(`/programs/${programId}/scoring-models`)
  if (res.status !== 200) throw new GetScoringModelError(res.status === 401 ? 'UNAUTHENTICATED' : 'UNKNOWN')
  return scoringModelListResponseSchema.parse(await res.json()).data
}

export async function getScoringModel(id: string): Promise<ScoringModel> {
  const res = await apiFetch(`/scoring-models/${id}`)
  if (res.status === 200) return scoringModelResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new GetScoringModelError('NOT_FOUND')
  if (res.status === 401) throw new GetScoringModelError('UNAUTHENTICATED')
  throw new GetScoringModelError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function getScoringModelVersion(versionId: string): Promise<ScoringModelVersion> {
  const res = await apiFetch(`/scoring-model-versions/${versionId}`)
  if (res.status === 200) return scoringModelVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new GetScoringModelError('NOT_FOUND')
  throw new GetScoringModelError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function listScoringModelVersions(modelId: string): Promise<ScoringModelVersion[]> {
  const res = await apiFetch(`/scoring-models/${modelId}/versions`)
  if (res.status !== 200) throw new GetScoringModelError(res.status === 404 ? 'NOT_FOUND' : 'UNKNOWN')
  return scoringModelVersionListResponseSchema.parse(await res.json()).data
}

export async function createScoringModel(programId: string, name: string): Promise<ScoringModel> {
  const res = await csrfFetch(`/programs/${programId}/scoring-models`, { method: 'POST', body: JSON.stringify({ name }) })
  if (res.status === 201) return scoringModelResponseSchema.parse(await res.json()).data
  if (res.status === 422) throw new SaveScoringModelError('VALIDATION', 'The scoring model name is required.')
  throw new SaveScoringModelError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function saveScoringModelDraft(modelId: string, criteria: ScoringCriterion[]): Promise<ScoringModelVersion> {
  const res = await csrfFetch(`/scoring-models/${modelId}/draft`, { method: 'PATCH', body: JSON.stringify({ criteria }) })
  if (res.status === 200) return scoringModelVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new SaveScoringModelError('NOT_FOUND')
  if (res.status === 409) throw new SaveScoringModelError('CONFLICT', 'This version is published and read-only.')
  throw new SaveScoringModelError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function publishScoringModel(modelId: string): Promise<ScoringModelVersion> {
  const res = await csrfFetch(`/scoring-models/${modelId}/publish`, { method: 'POST' })
  if (res.status === 200) return scoringModelVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new PublishScoringModelError('NOT_FOUND')
  if (res.status === 409) throw new PublishScoringModelError('CONFLICT', 'Nothing to publish.')
  throw new PublishScoringModelError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function forkScoringModelDraft(modelId: string, fromVersionId: string): Promise<ScoringModelVersion> {
  const res = await csrfFetch(`/scoring-models/${modelId}/fork`, { method: 'POST', body: JSON.stringify({ from_version_id: fromVersionId }) })
  if (res.status === 200 || res.status === 201) return scoringModelVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new SaveScoringModelError('NOT_FOUND')
  throw new SaveScoringModelError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function listAssignments(cohortId: string, stageId: string): Promise<ReviewerAssignment[]> {
  const res = await apiFetch(`/cohorts/${cohortId}/stages/${stageId}/assignments`)
  if (res.status !== 200) throw new AssignmentError(res.status === 401 ? 'UNAUTHENTICATED' : 'UNKNOWN')
  return reviewerAssignmentListResponseSchema.parse(await res.json()).data
}

export async function generateAssignments(
  cohortId: string,
  stageId: string,
  payload: { reviewer_ids: string[]; per_app: number },
): Promise<ReviewerAssignment[]> {
  const res = await csrfFetch(`/cohorts/${cohortId}/stages/${stageId}/assignments`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  if (res.status === 200 || res.status === 201) return reviewerAssignmentListResponseSchema.parse(await res.json()).data
  if (res.status === 401) throw new AssignmentError('UNAUTHENTICATED')
  if (res.status === 422) throw new AssignmentError('VALIDATION', 'Invalid assignment payload.')
  throw new AssignmentError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function getScorecard(cohortId: string, stageId: string, applicationId: string, reviewerId: string): Promise<Scorecard> {
  const res = await apiFetch(`/cohorts/${cohortId}/stages/${stageId}/scorecards/${applicationId}/${reviewerId}`)
  if (res.status === 200) return scorecardResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new ScorecardError('NOT_FOUND')
  if (res.status === 401) throw new ScorecardError('UNAUTHENTICATED')
  throw new ScorecardError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function saveScorecardDraft(
  cohortId: string,
  stageId: string,
  applicationId: string,
  reviewerId: string,
  draft: { values: Record<string, number>; disqualified: boolean; model_version_id?: string },
): Promise<Scorecard> {
  const res = await csrfFetch(
    `/cohorts/${cohortId}/stages/${stageId}/scorecards/${applicationId}/${reviewerId}`,
    { method: 'PATCH', body: JSON.stringify(draft) },
  )
  if (res.status === 200) return scorecardResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new ScorecardError('NOT_FOUND')
  if (res.status === 401) throw new ScorecardError('UNAUTHENTICATED')
  throw new ScorecardError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function submitScorecard(
  cohortId: string,
  stageId: string,
  applicationId: string,
  reviewerId: string,
): Promise<Scorecard> {
  const res = await csrfFetch(
    `/cohorts/${cohortId}/stages/${stageId}/scorecards/${applicationId}/${reviewerId}/submit`,
    { method: 'POST' },
  )
  if (res.status === 200) return scorecardResponseSchema.parse(await res.json()).data
  if (res.status === 422) throw new ScorecardError('VALIDATION', 'All criteria must be scored before submission.')
  if (res.status === 404) throw new ScorecardError('NOT_FOUND')
  if (res.status === 401) throw new ScorecardError('UNAUTHENTICATED')
  throw new ScorecardError('UNKNOWN', `Unexpected status ${res.status}`)
}

// ── Leaderboard ───────────────────────────────────────────────────────────────

export type LeaderboardRow = {
  application_id: string
  mean: number
  model_max: number
  count: number
  min: number
  max: number
  disqualified: boolean
}

/**
 * GET /cohorts/:cohortId/stages/:stageId/leaderboard
 *
 * Returns submitted-scorecard aggregates for every application in the stage,
 * sorted by mean score descending. Application identity is masked server-side;
 * the response carries application_id only (not applicant name or email).
 */
export async function getStageLeaderboard(cohortId: string, stageId: string): Promise<LeaderboardRow[]> {
  const res = await apiFetch(`/cohorts/${cohortId}/stages/${stageId}/leaderboard`)
  if (res.status === 200) return (await res.json() as { data: LeaderboardRow[] }).data
  if (res.status === 404) throw new AssignmentError('NOT_FOUND')
  if (res.status === 401) throw new AssignmentError('UNAUTHENTICATED')
  throw new AssignmentError('UNKNOWN', `Unexpected status ${res.status}`)
}

// ── Decisions ─────────────────────────────────────────────────────────────────

export type DecisionProposal = { application_id: string; proposal: 'advance' | 'reject' }

/**
 * POST /cohorts/:cohortId/stages/:stageId/decisions/propose
 *
 * Runs the threshold-assisted proposal engine server-side (lib/scoring.proposeDecisions).
 * Returns advance/reject proposals for every application that has submitted scorecards.
 * No decision is persisted — this is a read-only planning step.
 */
export async function proposeStageDecisions(
  cohortId: string,
  stageId: string,
  cutoff: number,
): Promise<DecisionProposal[]> {
  const res = await csrfFetch(`/cohorts/${cohortId}/stages/${stageId}/decisions/propose`, {
    method: 'POST',
    body: JSON.stringify({ cutoff }),
  })
  if (res.status === 200) return (await res.json() as { data: DecisionProposal[] }).data
  if (res.status === 404) throw new DecisionError('NOT_FOUND')
  if (res.status === 401) throw new DecisionError('UNAUTHENTICATED')
  throw new DecisionError('UNKNOWN', `Unexpected status ${res.status}`)
}

/**
 * POST /cohorts/:cohortId/stages/:stageId/decisions/commit
 *
 * Persists stage decisions with an immutable snapshot (model_version_id, submitted
 * scorecards, mean) captured at commit time. The `advance` outcome routes the
 * application into the stage's next_stage_ids.
 */
export async function commitStageDecisions(
  cohortId: string,
  stageId: string,
  decisionsPayload: { application_id: string; outcome: 'advance' | 'reject' | 'waitlist' }[],
): Promise<Decision[]> {
  const res = await csrfFetch(`/cohorts/${cohortId}/stages/${stageId}/decisions/commit`, {
    method: 'POST',
    body: JSON.stringify({ decisions: decisionsPayload }),
  })
  if (res.status === 200 || res.status === 201) {
    const payload = (await res.json()) as { data: unknown[] }
    return payload.data.map((d) => decisionSchema.parse(d))
  }
  if (res.status === 404) throw new DecisionError('NOT_FOUND')
  if (res.status === 401) throw new DecisionError('UNAUTHENTICATED')
  throw new DecisionError('UNKNOWN', `Unexpected status ${res.status}`)
}
