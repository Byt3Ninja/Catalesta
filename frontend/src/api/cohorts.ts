import { csrfFetch } from './csrf'
import { apiFetch } from './tenant'
import { firstValidationMessage, readValidationDetails } from './errors'
import {
  BindFormError,
  BindStagePipelineError,
  CreateCohortError,
  GetCohortError,
  OpenCohortError,
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

export { OpenCohortError, BindFormError, BindStagePipelineError }

/**
 * POST /cohorts/{id}/open (auth:sanctum + tenant). Transitions the cohort from
 * draft → open, making enrollment available. 409 when the cohort is already open
 * or in a terminal state. [Source: backend CohortController::open]
 */
export async function openCohort(id: string): Promise<Cohort> {
  const response = await csrfFetch(`/cohorts/${id}/open`, { method: 'POST' })
  if (response.status === 200) {
    return cohortResponseSchema.parse(await response.json()).data
  }
  if (response.status === 401) throw new OpenCohortError('UNAUTHENTICATED')
  if (response.status === 403) throw new OpenCohortError('FORBIDDEN')
  if (response.status === 404) throw new OpenCohortError('NOT_FOUND')
  if (response.status === 409) throw new OpenCohortError('CONFLICT', 'Cohort cannot be opened in its current state.')
  throw new OpenCohortError('UNKNOWN', `Unexpected status ${response.status}`)
}

/**
 * POST /cohorts/{id}/bind-form (auth:sanctum + tenant). Binds a published form
 * version to the cohort. 404 when the cohort is missing, 409 when already bound
 * to a different version (callers should confirm before replacing).
 * [Source: backend CohortController::bindForm]
 */
export async function bindCohortForm(id: string, formVersionId: string): Promise<Cohort> {
  const response = await csrfFetch(`/cohorts/${id}/bind-form`, {
    method: 'POST',
    body: JSON.stringify({ form_version_id: formVersionId }),
  })
  if (response.status === 200) {
    return cohortResponseSchema.parse(await response.json()).data
  }
  if (response.status === 401) throw new BindFormError('UNAUTHENTICATED')
  if (response.status === 403) throw new BindFormError('FORBIDDEN')
  if (response.status === 404) throw new BindFormError('NOT_FOUND')
  if (response.status === 409) throw new BindFormError('CONFLICT', 'A form version is already bound.')
  throw new BindFormError('UNKNOWN', `Unexpected status ${response.status}`)
}

/**
 * POST /cohorts/{id}/bind-stage-pipeline (auth:sanctum + tenant). Binds a
 * published stage-pipeline version to the cohort. Mirrors bindCohortForm:
 * 404 when the cohort is missing, 409 when already bound to a different version.
 */
export async function bindCohortStagePipeline(id: string, stagePipelineVersionId: string): Promise<Cohort> {
  const response = await csrfFetch(`/cohorts/${id}/bind-stage-pipeline`, {
    method: 'POST',
    body: JSON.stringify({ stage_pipeline_version_id: stagePipelineVersionId }),
  })
  if (response.status === 200) {
    return cohortResponseSchema.parse(await response.json()).data
  }
  if (response.status === 401) throw new BindStagePipelineError('UNAUTHENTICATED')
  if (response.status === 403) throw new BindStagePipelineError('FORBIDDEN')
  if (response.status === 404) throw new BindStagePipelineError('NOT_FOUND')
  if (response.status === 409) throw new BindStagePipelineError('CONFLICT', 'A stage pipeline version is already bound.')
  throw new BindStagePipelineError('UNKNOWN', `Unexpected status ${response.status}`)
}
