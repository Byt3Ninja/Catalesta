import { API_BASE_URL } from './client'
import { fieldMessage, firstValidationMessage, readValidationDetails } from './errors'
import {
  CreateOrgError,
  organizationListResponseSchema,
  organizationResponseSchema,
  type Organization,
} from '../schemas/organizations'

/**
 * GET /organizations (auth:sanctum) — orgs the user has an active membership in.
 * Empty array when the user has none (used by the no-org gate).
 */
export async function listOrganizations(): Promise<Organization[]> {
  const response = await fetch(`${API_BASE_URL}/organizations`, {
    credentials: 'include',
  })
  if (!response.ok) {
    throw new Error(`organizations list failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return organizationListResponseSchema.parse(json).data
}

/**
 * POST /organizations (auth:sanctum) — the creator becomes owner. A duplicate
 * (derived-slug collision) is a clean 422; we map it to a typed DUPLICATE_NAME
 * error carrying the server's field message.
 * [Source: backend OrganizationController::store, StoreOrganizationRequest, bootstrap/app.php]
 */
export async function createOrganization(name: string): Promise<Organization> {
  const response = await fetch(`${API_BASE_URL}/organizations`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name }),
  })

  if (response.status === 201) {
    const json: unknown = await response.json()
    return organizationResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new CreateOrgError('UNAUTHENTICATED')
  }
  if (response.status === 422) {
    // { error: { code:'VALIDATION_ERROR', details: { <field>: [msg] } } }
    // A name-field error is the duplicate-slug collision; any other field is a
    // generic validation failure and must NOT be mislabeled as DUPLICATE_NAME.
    const details = await readValidationDetails(response)

    const nameDetail = details?.name
    if (nameDetail !== undefined) {
      throw new CreateOrgError('DUPLICATE_NAME', fieldMessage(nameDetail))
    }

    throw new CreateOrgError(
      'UNKNOWN',
      firstValidationMessage(details) ?? 'Please check your entries and try again.',
    )
  }
  throw new CreateOrgError('UNKNOWN', `create organization failed: ${response.status}`)
}
