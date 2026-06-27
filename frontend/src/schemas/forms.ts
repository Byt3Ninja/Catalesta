import { z } from 'zod'
import { ApiError } from '../api/errors'
import { formFieldSchema as applyFieldSchema } from './apply'

export const fieldValidationSchema = z
  .object({
    min_length: z.number().int(),
    max_length: z.number().int(),
    pattern: z.string(),
    min_selections: z.number().int(),
    max_selections: z.number().int(),
  })
  .partial()
export type FieldValidation = z.infer<typeof fieldValidationSchema>

export const visibilityOperatorSchema = z.enum(['equals', 'not_equals', 'includes', 'is_empty'])
export type VisibilityOperator = z.infer<typeof visibilityOperatorSchema>

export const visibilityConditionSchema = z.object({
  field_id: z.string(),
  operator: visibilityOperatorSchema,
  value: z.string().nullable(),
})
export type VisibilityCondition = z.infer<typeof visibilityConditionSchema>

export const visibilityRuleSchema = z.object({
  match: z.enum(['all', 'any']),
  conditions: z.array(visibilityConditionSchema),
})
export type VisibilityRule = z.infer<typeof visibilityRuleSchema>

/** A builder field = the apply base field + a stable id + optional validation/visibility.
 *  ApplyField only reads type/label/options/required/help, so this is structurally
 *  compatible with <ApplyField field={...} />. */
export const formFieldSchema = applyFieldSchema.extend({
  id: z.string(),
  validation: fieldValidationSchema.optional(),
  visibility: visibilityRuleSchema.optional(),
})
export type FormField = z.infer<typeof formFieldSchema>

export const formVersionSchema = z.object({
  id: z.string(),
  form_id: z.string(),
  version: z.number().int(),
  status: z.enum(['draft', 'published']),
  fields: z.array(formFieldSchema),
  created_at: z.string(),
  published_at: z.string().nullable(),
})
export type FormVersion = z.infer<typeof formVersionSchema>

export const formSchema = z.object({
  id: z.string(),
  name: z.string(),
  description: z.string().nullable(),
  latest_version: z.number().int(),
  published_version_ids: z.array(z.string()),
  current_draft_version_id: z.string().nullable(),
})
export type Form = z.infer<typeof formSchema>

export const formListResponseSchema = z.object({ data: z.array(formSchema) })
export const formResponseSchema = z.object({ data: formSchema })
export const formVersionResponseSchema = z.object({ data: formVersionSchema })
export const formVersionListResponseSchema = z.object({ data: z.array(formVersionSchema) })

export type FormErrorCode = 'NOT_FOUND' | 'FORBIDDEN' | 'VALIDATION' | 'CONFLICT' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class GetFormError extends ApiError<FormErrorCode> {
  constructor(code: FormErrorCode, message?: string) {
    super(code, message)
    this.name = 'GetFormError'
  }
}

export class SaveFormError extends ApiError<FormErrorCode> {
  constructor(code: FormErrorCode, message?: string) {
    super(code, message)
    this.name = 'SaveFormError'
  }
}

export class PublishFormError extends ApiError<FormErrorCode> {
  constructor(code: FormErrorCode, message?: string) {
    super(code, message)
    this.name = 'PublishFormError'
  }
}
