import { z } from 'zod'

export const CONSENT_CATEGORIES = ['profile', 'contact', 'documents'] as const
export const consentCategorySchema = z.enum(CONSENT_CATEGORIES)
export type ConsentCategory = z.infer<typeof consentCategorySchema>

export const CONSENT_CATEGORY_LABEL: Record<ConsentCategory, string> = {
  profile: 'Profile details',
  contact: 'Contact information',
  documents: 'Documents',
}

export const consentEntrySchema = z.object({ category: consentCategorySchema, granted: z.boolean() })
export type ConsentEntry = z.infer<typeof consentEntrySchema>

export const consentListResponseSchema = z.object({ data: z.array(consentEntrySchema) })
