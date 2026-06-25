import { apiFetch } from './tenant'
import {
  funnelResponseSchema,
  submissionDetailResponseSchema,
  submissionListResponseSchema,
  type Funnel,
  type Submission,
  type SubmissionDetail,
} from '../schemas/submissions'

/**
 * GET /cohorts/{cohort}/submissions (auth:sanctum + tenant) — the cohort's
 * submissions, newest first. Empty array when none (drives the zero-day state).
 * [Source: backend SubmissionController@index]
 */
export async function listSubmissions(cohortId: string): Promise<Submission[]> {
  const response = await apiFetch(`/cohorts/${encodeURIComponent(cohortId)}/submissions`)
  if (!response.ok) {
    throw new Error(`submissions list failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return submissionListResponseSchema.parse(json).data
}

/**
 * GET /cohorts/{cohort}/submissions/{submission} — the full immutable snapshot.
 * [Source: backend SubmissionController@show]
 */
export async function getSubmission(
  cohortId: string,
  submissionId: string,
): Promise<SubmissionDetail> {
  const response = await apiFetch(
    `/cohorts/${encodeURIComponent(cohortId)}/submissions/${encodeURIComponent(submissionId)}`,
  )
  if (!response.ok) {
    throw new Error(`submission fetch failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return submissionDetailResponseSchema.parse(json).data
}

/**
 * GET /cohorts/{cohort}/funnel — { viewed, started, submitted }. `submitted` is the
 * durable count; `viewed` is clamped ≥ `started` server-side.
 * [Source: backend FunnelController]
 */
export async function getFunnel(cohortId: string): Promise<Funnel> {
  const response = await apiFetch(`/cohorts/${encodeURIComponent(cohortId)}/funnel`)
  if (!response.ok) {
    throw new Error(`funnel fetch failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return funnelResponseSchema.parse(json).data
}
