import { z } from 'zod'

export const NOTIFICATION_TYPES = ['action', 'message', 'system'] as const
export const notificationTypeSchema = z.enum(NOTIFICATION_TYPES)
export type NotificationType = z.infer<typeof notificationTypeSchema>

export const NOTIFICATION_TYPE_LABEL: Record<NotificationType, string> = {
  action: 'Action',
  message: 'Message',
  system: 'System',
}

export const notificationSchema = z.object({
  id: z.string(),
  type: notificationTypeSchema,
  title: z.string(),
  body: z.string(),
  created_at: z.string(),
  read_at: z.string().nullable(),
  href: z.string().nullable(),
})
export type Notification = z.infer<typeof notificationSchema>

export const notificationListResponseSchema = z.object({ data: z.array(notificationSchema) })
