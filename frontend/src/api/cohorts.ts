import { API_BASE_URL } from './client'
import { cohortListResponseSchema, type Cohort } from '../schemas/cohorts'

/**
 * GET /cohorts (auth:sanctum + tenant) — cohorts in the active tenant, each with
 * a `submissions_count`. Empty array when the tenant has none yet (drives the
 * operator Home day-one state). [Source: backend CohortController@index]
 */
export async function listCohorts(): Promise<Cohort[]> {
  const response = await fetch(`${API_BASE_URL}/cohorts`, {
    credentials: 'include',
  })
  if (!response.ok) {
    throw new Error(`cohorts list failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return cohortListResponseSchema.parse(json).data
}
