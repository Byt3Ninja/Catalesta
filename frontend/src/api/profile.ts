import { API_BASE_URL } from './client'
import { ConsentError, profileSchema, type Profile } from '../schemas/profile'

/**
 * GET /me/profile (auth:sanctum) — the operator's Startup Gate general profile.
 * This is the single profile-read call site (FR-006); every profile read routes
 * through the ConsentProvider seam, never a raw fetch elsewhere. A 403 is the
 * mock's consent gate → CONSENT_REQUIRED, surfaced so the seam can render a
 * neutral affordance instead of leaking or crashing. [Source: backend MeController@profile]
 */
export async function getProfile(): Promise<Profile> {
  const response = await fetch(`${API_BASE_URL}/me/profile`, {
    credentials: 'include',
  })

  if (response.status === 403) {
    throw new ConsentError('CONSENT_REQUIRED')
  }
  if (response.status === 401) {
    throw new ConsentError('UNAUTHENTICATED')
  }
  if (!response.ok) {
    throw new ConsentError('UNKNOWN', `profile fetch failed: ${response.status}`)
  }

  const json: unknown = await response.json()
  return profileSchema.parse(json)
}
