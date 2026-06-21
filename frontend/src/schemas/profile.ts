import { z } from 'zod'
import { ApiError } from '../api/errors'

/**
 * The Startup Gate general profile (Story 1.5) — a consent-gated, external
 * resource whose exact shape is owned upstream (P1a: mock). We stay tolerant: a
 * plain string→unknown map, and read the display name from the first present of
 * a few known keys. [Source: backend MeController@profile passthrough]
 */
export const profileSchema = z.record(z.string(), z.unknown())

export type Profile = z.infer<typeof profileSchema>

/** First non-empty of the known name fields, or undefined. */
export function profileDisplayName(profile: Profile): string | undefined {
  for (const key of ['display_name', 'name', 'full_name'] as const) {
    const value = profile[key]
    if (typeof value === 'string' && value.trim().length > 0) return value
  }
  return undefined
}

/**
 * Typed profile-read error. `CONSENT_REQUIRED` (403) is a first-class state, not
 * a failure — the ConsentProvider seam renders a neutral affordance for it.
 */
export type ConsentErrorCode = 'CONSENT_REQUIRED' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class ConsentError extends ApiError<ConsentErrorCode> {
  constructor(code: ConsentErrorCode, message?: string) {
    super(code, message)
    this.name = 'ConsentError'
  }
}
