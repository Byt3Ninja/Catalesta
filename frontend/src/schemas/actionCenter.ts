import { z } from 'zod'

export const ACTION_SECTIONS = [
  'required_actions', 'deadlines', 'current_stage', 'upcoming_sessions',
  'blocked_items', 'recent_decisions', 'progress', 'opportunities',
] as const

export const sectionSchema = z.enum(ACTION_SECTIONS)
export type ActionSection = z.infer<typeof sectionSchema>

export const SECTION_LABEL: Record<ActionSection, string> = {
  required_actions: 'Required actions',
  deadlines: 'Deadlines',
  current_stage: 'Current stage',
  upcoming_sessions: 'Upcoming sessions',
  blocked_items: 'Blocked items',
  recent_decisions: 'Recent decisions',
  progress: 'Progress',
  opportunities: 'Opportunities',
}

export const actionItemSchema = z.object({
  id: z.string(),
  section: sectionSchema,
  what: z.string(),
  why: z.string(),
  deadline: z.string().nullable(),
  who: z.string().nullable(),
  href: z.string().nullable(),
  blocker: z.string().nullable(),
})
export type ActionItem = z.infer<typeof actionItemSchema>

export const actionCenterResponseSchema = z.object({ data: z.array(actionItemSchema) })
