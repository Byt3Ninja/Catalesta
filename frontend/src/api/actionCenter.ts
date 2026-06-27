import { apiFetch } from './tenant'
import { actionCenterResponseSchema, type ActionItem } from '../schemas/actionCenter'
import type { RoleKey } from '../schemas/roles'

/** GET /me/action-center?role= — role-scoped action items (prototype contract). */
export async function getActionCenter(role: RoleKey): Promise<ActionItem[]> {
  const response = await apiFetch(`/me/action-center?role=${role}`)
  if (!response.ok) throw new Error(`action-center failed: ${response.status}`)
  const json: unknown = await response.json()
  return actionCenterResponseSchema.parse(json).data
}
