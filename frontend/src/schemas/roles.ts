import { z } from 'zod'

export const ROLE_KEYS = [
  'founder', 'co_founder', 'mentor', 'trainer', 'evaluator', 'judge',
  'service_provider', 'program_manager', 'program_coordinator', 'org_admin',
] as const

export const roleKeySchema = z.enum(ROLE_KEYS)
export type RoleKey = z.infer<typeof roleKeySchema>

export const roleSchema = z.object({ key: roleKeySchema, label: z.string() })
export type Role = z.infer<typeof roleSchema>

export const roleListResponseSchema = z.object({ data: z.array(roleSchema) })
