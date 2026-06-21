import { API_BASE_URL } from './client'
import { firstValidationMessage, readValidationDetails } from './errors'
import {
  CreateProgramError,
  PublishProgramError,
  programListResponseSchema,
  programResponseSchema,
  type Program,
} from '../schemas/programs'

/**
 * GET /programs (auth:sanctum) — programs in the active tenant. Empty array
 * when the tenant has none yet (drives the first-use empty state).
 */
export async function listPrograms(): Promise<Program[]> {
  const response = await fetch(`${API_BASE_URL}/programs`, {
    credentials: 'include',
  })
  if (!response.ok) {
    throw new Error(`programs list failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return programListResponseSchema.parse(json).data
}

/**
 * POST /programs (auth:sanctum) — the new program starts in `draft`. A 422 is a
 * field-validation failure; we surface the server's first message.
 * [Source: backend ProgramController::store]
 */
export async function createProgram(
  name: string,
  description?: string,
): Promise<Program> {
  const response = await fetch(`${API_BASE_URL}/programs`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(description ? { name, description } : { name }),
  })

  if (response.status === 201) {
    const json: unknown = await response.json()
    return programResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new CreateProgramError('UNAUTHENTICATED')
  }
  if (response.status === 422) {
    const message = firstValidationMessage(await readValidationDetails(response))
    throw new CreateProgramError(
      'VALIDATION',
      message ?? 'Please check your entries and try again.',
    )
  }
  throw new CreateProgramError('UNKNOWN', `create program failed: ${response.status}`)
}

/**
 * POST /programs/{id}/publish (auth:sanctum) — flips a draft to `published`,
 * recording an immutable version. 403 = not permitted, 404 = unknown program.
 * [Source: backend ProgramController::publish]
 */
export async function publishProgram(id: string): Promise<Program> {
  const response = await fetch(`${API_BASE_URL}/programs/${id}/publish`, {
    method: 'POST',
    credentials: 'include',
  })

  if (response.status === 200) {
    const json: unknown = await response.json()
    return programResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new PublishProgramError('UNAUTHENTICATED')
  }
  if (response.status === 403) {
    throw new PublishProgramError('FORBIDDEN')
  }
  if (response.status === 404) {
    throw new PublishProgramError('NOT_FOUND')
  }
  throw new PublishProgramError('UNKNOWN', `publish program failed: ${response.status}`)
}
