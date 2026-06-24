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

/** Typed get-program error the ProgramDetailPage renders. */
export type GetProgramErrorCode = 'NOT_FOUND' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class GetProgramError extends ApiError<GetProgramErrorCode> {
  constructor(code: GetProgramErrorCode, message?: string) {
    super(code, message)
    this.name = 'GetProgramError'
  }
}

/** Typed update-program error. */
export type UpdateProgramErrorCode =
  | 'VALIDATION'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'UNAUTHENTICATED'
  | 'UNKNOWN'

export class UpdateProgramError extends ApiError<UpdateProgramErrorCode> {
  constructor(code: UpdateProgramErrorCode, message?: string) {
    super(code, message)
    this.name = 'UpdateProgramError'
  }
}

/** Typed clone-program error. */
export type CloneProgramErrorCode =
  | 'VALIDATION'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'UNAUTHENTICATED'
  | 'UNKNOWN'

export class CloneProgramError extends ApiError<CloneProgramErrorCode> {
  constructor(code: CloneProgramErrorCode, message?: string) {
    super(code, message)
    this.name = 'CloneProgramError'
  }
}
