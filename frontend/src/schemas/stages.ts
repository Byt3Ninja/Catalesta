import { z } from 'zod'
import { ApiError } from '../api/errors'
import { visibilityConditionSchema } from './forms'

/** A stage gating rule reuses the 2b visibility condition primitive verbatim —
 *  declarative `Condition` trees only, no expression strings or `eval`. */
export const stageRuleSchema = z.object({
  match: z.enum(['all', 'any']),
  conditions: z.array(visibilityConditionSchema),
})
export type StageRule = z.infer<typeof stageRuleSchema>

export const stageTypeSchema = z.enum(['review', 'interview', 'task', 'decision', 'automated'])
export type StageType = z.infer<typeof stageTypeSchema>

export const stageSchema = z.object({
  stage_id: z.string(),
  name: z.string(),
  type: stageTypeSchema,
  entry_rule: stageRuleSchema.nullable(),
  exit_rule: stageRuleSchema.nullable(),
  next_stage_ids: z.array(z.string()),
  depends_on_stage_ids: z.array(z.string()),
  parallel_group: z.string().nullable(),
  order: z.number().int(),
})
export type Stage = z.infer<typeof stageSchema>

export const stagePipelineVersionSchema = z.object({
  version_id: z.string(),
  pipeline_id: z.string(),
  version: z.number().int(),
  status: z.enum(['draft', 'published']),
  stages: z.array(stageSchema),
  created_at: z.string(),
  published_at: z.string().nullable(),
})
export type StagePipelineVersion = z.infer<typeof stagePipelineVersionSchema>

export const stagePipelineSchema = z.object({
  pipeline_id: z.string(),
  program_id: z.string(),
  name: z.string(),
  latest_version: z.number().int(),
  published_version_ids: z.array(z.string()),
  current_draft_version_id: z.string().nullable(),
  created_at: z.string(),
})
export type StagePipeline = z.infer<typeof stagePipelineSchema>

export const stageTemplateSchema = z.object({
  template_id: z.string(),
  name: z.string(),
  type: stageTypeSchema,
})
export type StageTemplate = z.infer<typeof stageTemplateSchema>

export const stagePipelineListResponseSchema = z.object({ data: z.array(stagePipelineSchema) })
export const stagePipelineResponseSchema = z.object({ data: stagePipelineSchema })
export const stagePipelineVersionResponseSchema = z.object({ data: stagePipelineVersionSchema })
export const stagePipelineVersionListResponseSchema = z.object({ data: z.array(stagePipelineVersionSchema) })
export const stageTemplateListResponseSchema = z.object({ data: z.array(stageTemplateSchema) })

export type StageErrorCode = 'NOT_FOUND' | 'FORBIDDEN' | 'VALIDATION' | 'CONFLICT' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class GetPipelineError extends ApiError<StageErrorCode> {
  constructor(code: StageErrorCode, message?: string) {
    super(code, message)
    this.name = 'GetPipelineError'
  }
}

export class SavePipelineError extends ApiError<StageErrorCode> {
  constructor(code: StageErrorCode, message?: string) {
    super(code, message)
    this.name = 'SavePipelineError'
  }
}

export class PublishPipelineError extends ApiError<StageErrorCode> {
  constructor(code: StageErrorCode, message?: string) {
    super(code, message)
    this.name = 'PublishPipelineError'
  }
}
