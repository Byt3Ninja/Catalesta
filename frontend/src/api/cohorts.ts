import { csrfFetch } from './csrf'
import { apiFetch } from './tenant'
import { firstValidationMessage, readValidationDetails } from './errors'
import {
  CreateCohortError,
  GetCohortError,
  UpdateCohortError,
  cohortListResponseSchema,
  cohortResponseSchema,
  type Cohort,
} from '../schemas/cohorts'

/**
 * GET /cohorts (auth:sanctum + tenant) — cohorts in the active tenant, each with
 * a `submissions_count`. Empty array when the tenant has none yet (drives the
 * operator Home day-one state). [Source: backend CohortController@index]
 */
export async function listCohorts(): Promise<Cohort[]> {
  const response = await apiFetch('/cohorts')
  if (!response.ok) {
    throw new Error(`cohorts list failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return cohortListResponseSchema.parse(json).data
}

/**
 * GET /cohorts/{id} (auth:sanctum + tenant). 404 → the cohort is gone/foreign.
 * [Source: backend CohortController::show]
 */
export async function getCohort(id: string): Promise<Cohort> {
  const response = await apiFetch(`/cohorts/${id}`)
  if (response.status === 200) {
    const json: unknown = await response.json()
    return cohortResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new GetCohortError('UNAUTHENTICATED')
  }
  if (response.status === 404) {
    throw new GetCohortError('NOT_FOUND')
  }
  throw new GetCohortError('UNKNOWN', `get cohort failed: ${response.status}`)
}

/**
 * POST /programs/{programId}/cohorts (auth:sanctum + tenant). Creates a DRAFT
 * cohort under the program. A foreign/missing program → 403.
 * [Source: backend CohortController::store]
 */
export async function createCohort(programId: string, input: { name: string }): Promise<Cohort> {
  const response = await csrfFetch(`/programs/${programId}/cohorts`, {
    method: 'POST',
    body: JSON.stringify(input),
  })

  if (response.status === 201) {
    const json: unknown = await response.json()
    return cohortResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new CreateCohortError('UNAUTHENTICATED')
  }
  if (response.status === 403) {
    throw new CreateCohortError('FORBIDDEN')
  }
  if (response.status === 404) {
    throw new CreateCohortError('NOT_FOUND')
  }
  if (response.status === 422) {
    const message = firstValidationMessage(await readValidationDetails(response))
    throw new CreateCohortError('VALIDATION', message ?? 'Please check the name and try again.')
  }
  throw new CreateCohortError('UNKNOWN', `create cohort failed: ${response.status}`)
}

/**
 * PATCH /cohorts/{id} (auth:sanctum + tenant). Edits metadata only (name,
 * capacity, the enrollment/start/end dates). Backend enforces the date ordering
 * chain. [Source: backend CohortController::update]
 */
export async function updateCohort(
  id: string,
  input: {
    name?: string
    capacity?: number | null
    enrollment_opens_at?: string | null
    enrollment_closes_at?: string | null
    starts_at?: string | null
    ends_at?: string | null
  },
): Promise<Cohort> {
  const response = await csrfFetch(`/cohorts/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(input),
  })

  if (response.status === 200) {
    const json: unknown = await response.json()
    return cohortResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new UpdateCohortError('UNAUTHENTICATED')
  }
  if (response.status === 403) {
    throw new UpdateCohortError('FORBIDDEN')
  }
  if (response.status === 404) {
    throw new UpdateCohortError('NOT_FOUND')
  }
  if (response.status === 422) {
    const message = firstValidationMessage(await readValidationDetails(response))
    throw new UpdateCohortError('VALIDATION', message ?? 'Please check your entries and try again.')
  }
  throw new UpdateCohortError('UNKNOWN', `update cohort failed: ${response.status}`)
}
