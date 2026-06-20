import { z } from 'zod'
import { ApiError } from '../api/errors'

/**
 * Organization resource (Story 1.1).
 * [Source: backend OrganizationResource::toArray]
 */
export const organizationSchema = z.object({
  id: z.string(),
  name: z.string(),
  slug: z.string(),
  branding: z.record(z.string(), z.unknown()).nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Organization = z.infer<typeof organizationSchema>

export const organizationResponseSchema = z.object({
  data: organizationSchema,
})

export const organizationListResponseSchema = z.object({
  data: z.array(organizationSchema),
})

/** Typed create-org error the OnboardingPage renders. */
export type CreateOrgErrorCode = 'DUPLICATE_NAME' | 'UNAUTHENTICATED' | 'UNKNOWN'

export class CreateOrgError extends ApiError<CreateOrgErrorCode> {
  constructor(code: CreateOrgErrorCode, message?: string) {
    super(code, message)
    this.name = 'CreateOrgError'
  }
}
