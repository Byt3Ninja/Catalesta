import { z } from 'zod'

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
  created_at: z.string(),
  updated_at: z.string(),
})

export type Cohort = z.infer<typeof cohortSchema>

export const cohortListResponseSchema = z.object({
  data: z.array(cohortSchema),
})
