import { z } from 'zod'

const checkSchema = z.object({
  status: z.enum(['ok', 'error']),
  message: z.string().optional(),
})

export const healthSchema = z.object({
  status: z.enum(['ok', 'degraded']),
  service: z.string(),
  checks: z.object({
    database: checkSchema,
    redis: checkSchema,
    object_storage: checkSchema,
  }),
})

export type Health = z.infer<typeof healthSchema>
