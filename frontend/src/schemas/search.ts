import { z } from 'zod'

export const SEARCH_CATEGORIES = ['people', 'programs', 'cohorts', 'documents'] as const
export const searchCategorySchema = z.enum(SEARCH_CATEGORIES)
export type SearchCategory = z.infer<typeof searchCategorySchema>

export const SEARCH_CATEGORY_LABEL: Record<SearchCategory, string> = {
  people: 'People',
  programs: 'Programs',
  cohorts: 'Cohorts',
  documents: 'Documents',
}

export const searchItemSchema = z.object({
  id: z.string(),
  label: z.string(),
  sublabel: z.string().nullable(),
  href: z.string(),
})
export type SearchItem = z.infer<typeof searchItemSchema>

export const searchGroupSchema = z.object({ category: searchCategorySchema, items: z.array(searchItemSchema) })
export type SearchGroup = z.infer<typeof searchGroupSchema>

export const searchResponseSchema = z.object({ data: z.array(searchGroupSchema) })
