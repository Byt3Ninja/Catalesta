import { z } from 'zod'
import { ApiError } from '../api/errors'

export const scoringCriterionSchema = z.object({
  criterion_id: z.string(),
  label: z.string(),
  max_points: z.number(),
  descriptors: z.array(z.string()).nullable(),
})
export type ScoringCriterion = z.infer<typeof scoringCriterionSchema>

export const scoringModelSchema = z.object({
  model_id: z.string(),
  program_id: z.string(),
  name: z.string(),
  latest_version: z.number().int(),
  published_version_ids: z.array(z.string()),
  current_draft_version_id: z.string().nullable(),
  created_at: z.string(),
})
export type ScoringModel = z.infer<typeof scoringModelSchema>

export const scoringModelVersionSchema = z.object({
  version_id: z.string(),
  model_id: z.string(),
  version: z.number().int(),
  status: z.enum(['draft', 'published']),
  criteria: z.array(scoringCriterionSchema),
  created_at: z.string(),
  published_at: z.string().nullable(),
})
export type ScoringModelVersion = z.infer<typeof scoringModelVersionSchema>

export const reviewerAssignmentSchema = z.object({
  assignment_id: z.string(),
  cohort_id: z.string(),
  stage_id: z.string(),
  application_id: z.string(),
  reviewer_id: z.string(),
  status: z.enum(['pending', 'submitted']),
})
export type ReviewerAssignment = z.infer<typeof reviewerAssignmentSchema>

export const scorecardSchema = z.object({
  scorecard_id: z.string(),
  cohort_id: z.string(),
  stage_id: z.string(),
  application_id: z.string(),
  reviewer_id: z.string(),
  model_version_id: z.string(),
  values: z.record(z.string(), z.number()),
  disqualified: z.boolean(),
  status: z.enum(['draft', 'submitted']),
  submitted_at: z.string().nullable(),
})
export type Scorecard = z.infer<typeof scorecardSchema>

export const decisionSchema = z.object({
  decision_id: z.string(),
  cohort_id: z.string(),
  stage_id: z.string(),
  application_id: z.string(),
  outcome: z.enum(['advance', 'reject', 'waitlist']),
  snapshot: z.object({
    model_version_id: z.string(),
    scorecards: z.array(scorecardSchema),
    mean: z.string(),
    decided_at: z.string(),
  }),
  decided_by: z.string(),
})
export type Decision = z.infer<typeof decisionSchema>

export const scoringModelListResponseSchema = z.object({ data: z.array(scoringModelSchema) })
export const scoringModelResponseSchema = z.object({ data: scoringModelSchema })
export const scoringModelVersionResponseSchema = z.object({ data: scoringModelVersionSchema })
export const scoringModelVersionListResponseSchema = z.object({ data: z.array(scoringModelVersionSchema) })
export const reviewerAssignmentListResponseSchema = z.object({ data: z.array(reviewerAssignmentSchema) })
export const scorecardResponseSchema = z.object({ data: scorecardSchema })

export type AssessmentErrorCode = 'NOT_FOUND' | 'FORBIDDEN' | 'VALIDATION' | 'CONFLICT' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class GetScoringModelError extends ApiError<AssessmentErrorCode> {
  constructor(code: AssessmentErrorCode, message?: string) {
    super(code, message)
    this.name = 'GetScoringModelError'
  }
}

export class SaveScoringModelError extends ApiError<AssessmentErrorCode> {
  constructor(code: AssessmentErrorCode, message?: string) {
    super(code, message)
    this.name = 'SaveScoringModelError'
  }
}

export class PublishScoringModelError extends ApiError<AssessmentErrorCode> {
  constructor(code: AssessmentErrorCode, message?: string) {
    super(code, message)
    this.name = 'PublishScoringModelError'
  }
}

export class AssignmentError extends ApiError<AssessmentErrorCode> {
  constructor(code: AssessmentErrorCode, message?: string) {
    super(code, message)
    this.name = 'AssignmentError'
  }
}

export class ScorecardError extends ApiError<AssessmentErrorCode> {
  constructor(code: AssessmentErrorCode, message?: string) {
    super(code, message)
    this.name = 'ScorecardError'
  }
}

export class DecisionError extends ApiError<AssessmentErrorCode> {
  constructor(code: AssessmentErrorCode, message?: string) {
    super(code, message)
    this.name = 'DecisionError'
  }
}
