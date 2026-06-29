import { apiFetch } from './tenant'
import { csrfFetch } from './csrf'
import {
  GetScoringModelError, SaveScoringModelError, PublishScoringModelError,
  AssignmentError, ScorecardError,
  scoringModelListResponseSchema, scoringModelResponseSchema,
  scoringModelVersionResponseSchema, scoringModelVersionListResponseSchema,
  reviewerAssignmentListResponseSchema, scorecardResponseSchema,
  type ScoringModel, type ScoringModelVersion, type ScoringCriterion,
  type ReviewerAssignment, type Scorecard,
} from '../schemas/assessments'

export { GetScoringModelError, SaveScoringModelError, PublishScoringModelError, AssignmentError, ScorecardError }

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
