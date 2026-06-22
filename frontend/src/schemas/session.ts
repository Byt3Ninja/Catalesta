import { z } from 'zod'
import { ApiError } from '../api/errors'

/**
 * Current-user projection (SP-1b-ii). Keyed on the Account id (ULID) — never email
 * (CLAUDE.md 4/5). `startup_gate_subject_id` is nullable: native accounts have no SG
 * link. `linked_providers`/`has_password` describe which sign-in methods apply.
 * [Source: backend AccountSessionResource]
 */
export const sessionUserSchema = z.object({
  id: z.string(),
  email: z.string().nullable(),
  display_name: z.string().nullable(),
  email_verified: z.boolean(),
  startup_gate_subject_id: z.string().nullable(),
  linked_providers: z.array(z.string()),
  has_password: z.boolean(),
})

export type SessionUser = z.infer<typeof sessionUserSchema>

export const sessionResponseSchema = z.object({
  user: sessionUserSchema,
})

export const loginUrlSchema = z.object({
  authorization_url: z.string(),
})

/** Typed auth error the gate reads. `UNAUTHENTICATED` on 401 (mirrors SubmitError). */
export type SessionErrorCode = 'UNAUTHENTICATED' | 'UNKNOWN'

export class SessionError extends ApiError<SessionErrorCode> {
  constructor(code: SessionErrorCode, message?: string) {
    super(code, message)
    this.name = 'SessionError'
  }
}
