import { z } from 'zod'
import { ApiError } from '../api/errors'

/**
 * Program resource (Story 1.2). A program starts as `draft`; publishing records
 * an immutable version and flips it to `published`.
 * [Source: backend ProgramResource::toArray]
 */
export const programSchema = z.object({
  id: z.string(),
  name: z.string(),
  slug: z.string(),
  status: z.enum(['draft', 'published', 'archived', 'closed']),
  description: z.string().nullable(),
  settings: z.record(z.string(), z.unknown()).nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Program = z.infer<typeof programSchema>

export const programResponseSchema = z.object({
  data: programSchema,
})

export const programListResponseSchema = z.object({
  data: z.array(programSchema),
})

/** Typed create-program error the ProgramsPage renders. */
export type CreateProgramErrorCode = 'VALIDATION' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class CreateProgramError extends ApiError<CreateProgramErrorCode> {
  constructor(code: CreateProgramErrorCode, message?: string) {
    super(code, message)
    this.name = 'CreateProgramError'
  }
}

/** Typed publish-program error the ProgramsPage renders. */
export type PublishProgramErrorCode =
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'UNAUTHENTICATED'
  | 'UNKNOWN'

export class PublishProgramError extends ApiError<PublishProgramErrorCode> {
  constructor(code: PublishProgramErrorCode, message?: string) {
    super(code, message)
    this.name = 'PublishProgramError'
  }
}
