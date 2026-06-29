import { z } from 'zod'
import { ApiError } from '../api/errors'

/**
 * Cohort resource (Story 1.4 shape). The list endpoint (Story 1.5) adds
 * `submissions_count` (present only on the index, hence optional).
 * [Source: backend CohortResource::toArray + CohortStatus enum]
 */
export const cohortSchema = z.object({
  id: z.string(),
  organization_id: z.string(),
  program_id: z.string(),
  name: z.string(),
  slug: z.string(),
  status: z.enum(['draft', 'open', 'closed', 'completed']),
  capacity: z.number().int().nullable(),
  enrollment_opens_at: z.string().nullable(),
  enrollment_closes_at: z.string().nullable(),
  starts_at: z.string().nullable(),
  ends_at: z.string().nullable(),
  timeline: z.record(z.string(), z.unknown()).nullable(),
  submissions_count: z.number().int().optional(),
  bound_form_version_id: z.string().nullable().optional(),
  stage_pipeline_version_id: z.string().nullable().optional(),
  stage_scoring_model_version_ids: z.record(z.string(), z.string()).nullable().optional(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Cohort = z.infer<typeof cohortSchema>

export const cohortListResponseSchema = z.object({
  data: z.array(cohortSchema),
})

export const cohortResponseSchema = z.object({
  data: cohortSchema,
})

/** Typed get-cohort error the CohortDetailPage renders. */
export type GetCohortErrorCode = 'NOT_FOUND' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class GetCohortError extends ApiError<GetCohortErrorCode> {
  constructor(code: GetCohortErrorCode, message?: string) {
    super(code, message)
    this.name = 'GetCohortError'
  }
}

/** Typed create-cohort error. */
export type CreateCohortErrorCode =
  | 'VALIDATION'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'UNAUTHENTICATED'
  | 'UNKNOWN'

export class CreateCohortError extends ApiError<CreateCohortErrorCode> {
  constructor(code: CreateCohortErrorCode, message?: string) {
    super(code, message)
    this.name = 'CreateCohortError'
  }
}

/** Typed update-cohort error. */
export type UpdateCohortErrorCode =
  | 'VALIDATION'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'UNAUTHENTICATED'
  | 'UNKNOWN'

export class UpdateCohortError extends ApiError<UpdateCohortErrorCode> {
  constructor(code: UpdateCohortErrorCode, message?: string) {
    super(code, message)
    this.name = 'UpdateCohortError'
  }
}

/** Typed open-cohort error. */
export type OpenCohortErrorCode = 'FORBIDDEN' | 'NOT_FOUND' | 'CONFLICT' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class OpenCohortError extends ApiError<OpenCohortErrorCode> {
  constructor(code: OpenCohortErrorCode, message?: string) {
    super(code, message)
    this.name = 'OpenCohortError'
  }
}

/** Typed bind-form error. */
export type BindFormErrorCode = 'NOT_FOUND' | 'FORBIDDEN' | 'CONFLICT' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class BindFormError extends ApiError<BindFormErrorCode> {
  constructor(code: BindFormErrorCode, message?: string) {
    super(code, message)
    this.name = 'BindFormError'
  }
}

/** Typed bind-stage-pipeline error (mirrors BindFormError). */
export type BindStagePipelineErrorCode = 'NOT_FOUND' | 'FORBIDDEN' | 'CONFLICT' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class BindStagePipelineError extends ApiError<BindStagePipelineErrorCode> {
  constructor(code: BindStagePipelineErrorCode, message?: string) {
    super(code, message)
    this.name = 'BindStagePipelineError'
  }
}

/** Typed bind-scoring-model error (mirrors BindStagePipelineError). */
export type BindScoringModelErrorCode = 'NOT_FOUND' | 'FORBIDDEN' | 'CONFLICT' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class BindScoringModelError extends ApiError<BindScoringModelErrorCode> {
  constructor(code: BindScoringModelErrorCode, message?: string) {
    super(code, message)
    this.name = 'BindScoringModelError'
  }
}
