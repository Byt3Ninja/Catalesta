import { z } from 'zod'

/**
 * Public apply-form contract (Story 2.7). The backend ships a versioned form
 * definition; we validate defensively and tolerate extra keys per field so a
 * new field attribute never crashes the page (z.object is non-strict by
 * default, but field types come from a published, immutable version).
 */
export const fieldType = z.enum([
  'short_text',
  'long_text',
  'single_select',
  'multi_select',
  'number',
  'date',
  'file_upload',
  'consent',
])

export type FieldType = z.infer<typeof fieldType>

export const formFieldSchema = z.object({
  type: fieldType,
  label: z.string(),
  // Optional, defensively typed extras.
  key: z.string().optional(),
  options: z.array(z.string()).optional(),
  required: z.boolean().optional(),
  help: z.string().optional(),
})

export type FormField = z.infer<typeof formFieldSchema>

export const applyFormSchema = z.object({
  open: z.boolean(),
  cohort_id: z.string(),
  form_version_id: z.string().nullable(),
  form: z.array(formFieldSchema).nullable(),
})

export type ApplyForm = z.infer<typeof applyFormSchema>

export const receiptSchema = z.object({
  reference_number: z.string(),
  status: z.string(),
  cohort_id: z.string(),
  submitted_at: z.string(),
})

export type Receipt = z.infer<typeof receiptSchema>

/** Discriminated submit-error codes the UI renders calmly. */
export type SubmitErrorCode =
  | 'UNAUTHENTICATED'
  | 'COHORT_CLOSED'
  | 'IDEMPOTENCY_CONFLICT'
  | 'IDEMPOTENCY_IN_FLIGHT'
  | 'VALIDATION_ERROR'
  | 'UNKNOWN'

export class SubmitError extends Error {
  readonly code: SubmitErrorCode
  constructor(code: SubmitErrorCode, message?: string) {
    super(message ?? code)
    this.name = 'SubmitError'
    this.code = code
  }
}
