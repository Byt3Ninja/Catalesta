import { apiFetch } from './tenant'
import { consentListResponseSchema, type ConsentCategory, type ConsentEntry } from '../schemas/consent'

/** GET /me/consent — the user's per-category consent grants (prototype contract). */
export async function getConsents(): Promise<ConsentEntry[]> {
  const response = await apiFetch('/me/consent')
  if (!response.ok) throw new Error(`consent list failed: ${response.status}`)
  const json: unknown = await response.json()
  return consentListResponseSchema.parse(json).data
}

/** POST /me/consent — grant or revoke one category. */
export async function setConsent(category: ConsentCategory, granted: boolean): Promise<void> {
  const response = await apiFetch('/me/consent', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ category, granted }),
  })
  if (!response.ok) throw new Error(`consent update failed: ${response.status}`)
}
