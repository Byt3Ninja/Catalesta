import { z } from 'zod'

/**
 * Submission list row (Story 2.8, FR-034) — lightweight; the full snapshot is the
 * detail endpoint only. [Source: backend SubmissionResource]
 */
export const submissionSchema = z.object({
  reference_number: z.string(),
  cohort_id: z.string(),
  submitted_at: z.string(),
})

export type Submission = z.infer<typeof submissionSchema>

// Laravel paginated collection: { data: [...], meta: { total, … } }. We only read total.
export const submissionListResponseSchema = z.object({
  data: z.array(submissionSchema),
  meta: z.object({ total: z.number().int() }),
})

/** Detail adds the immutable snapshot (the same one Epic-3 scoring reads). */
export const submissionDetailSchema = submissionSchema.extend({
  snapshot: z.record(z.string(), z.unknown()),
})

export type SubmissionDetail = z.infer<typeof submissionDetailSchema>

export const submissionDetailResponseSchema = z.object({ data: submissionDetailSchema })

/** Operator funnel (Story 2.8, FR-080). [Source: backend FunnelController] */
export const funnelSchema = z.object({
  viewed: z.number().int(),
  started: z.number().int(),
  submitted: z.number().int(),
})

export type Funnel = z.infer<typeof funnelSchema>

export const funnelResponseSchema = z.object({ data: funnelSchema })
