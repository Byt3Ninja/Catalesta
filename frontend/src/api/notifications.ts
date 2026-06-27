import { apiFetch } from './tenant'
import { notificationListResponseSchema, type Notification } from '../schemas/notifications'

/** GET /notifications — the current user's notifications (prototype contract). */
export async function listNotifications(): Promise<Notification[]> {
  const response = await apiFetch('/notifications')
  if (!response.ok) throw new Error(`notifications list failed: ${response.status}`)
  const json: unknown = await response.json()
  return notificationListResponseSchema.parse(json).data
}

/** POST /notifications/{id}/read — mark one notification read. */
export async function markNotificationRead(id: string): Promise<void> {
  const response = await apiFetch(`/notifications/${id}/read`, { method: 'POST' })
  if (!response.ok) throw new Error(`mark read failed: ${response.status}`)
}

/** POST /notifications/read-all — mark every notification read. */
export async function markAllNotificationsRead(): Promise<void> {
  const response = await apiFetch('/notifications/read-all', { method: 'POST' })
  if (!response.ok) throw new Error(`mark all read failed: ${response.status}`)
}
