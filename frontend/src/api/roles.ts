import { apiFetch } from './tenant'
import { roleListResponseSchema, type Role } from '../schemas/roles'

/** GET /me/roles — the current user's role memberships (prototype contract). */
export async function listMyRoles(): Promise<Role[]> {
  const response = await apiFetch('/me/roles')
  if (!response.ok) throw new Error(`roles list failed: ${response.status}`)
  const json: unknown = await response.json()
  return roleListResponseSchema.parse(json).data
}
