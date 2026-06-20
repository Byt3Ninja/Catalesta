import { z } from 'zod'
import { ApiError } from '../api/errors'

/**
 * Current-user projection from the Startup Gate session (Story 1.1).
 * Key is the OIDC `sub` (`startup_gate_subject_id`) — never email (CLAUDE.md 4/5).
 * [Source: backend AuthController::session / ::callback]
 */
export const sessionUserSchema = z.object({
  id: z.string(),
  startup_gate_subject_id: z.string(),
  email: z.string().nullable(),
  display_name: z.string().nullable(),
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
