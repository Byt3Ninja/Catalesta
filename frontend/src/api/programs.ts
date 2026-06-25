import { csrfFetch } from './csrf'
import { apiFetch } from './tenant'
import { firstValidationMessage, readValidationDetails } from './errors'
import {
  CloneProgramError,
  CreateProgramError,
  GetProgramError,
  PublishProgramError,
  UpdateProgramError,
  programListResponseSchema,
  programResponseSchema,
  type Program,
} from '../schemas/programs'

/**
 * GET /programs (auth:sanctum) — programs in the active tenant. Empty array
 * when the tenant has none yet (drives the first-use empty state).
 */
export async function listPrograms(): Promise<Program[]> {
  const response = await apiFetch('/programs')
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
  const response = await csrfFetch(`/programs`, {
    method: 'POST',
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
  const response = await csrfFetch(`/programs/${id}/publish`, {
    method: 'POST',
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

/**
 * GET /programs/{id} (auth:sanctum). 404 → the program is gone/never existed.
 * [Source: backend ProgramController::show]
 */
export async function getProgram(id: string): Promise<Program> {
  const response = await apiFetch(`/programs/${id}`)
  if (response.status === 200) {
    const json: unknown = await response.json()
    return programResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new GetProgramError('UNAUTHENTICATED')
  }
  if (response.status === 404) {
    throw new GetProgramError('NOT_FOUND')
  }
  throw new GetProgramError('UNKNOWN', `get program failed: ${response.status}`)
}

/**
 * PATCH /programs/{id} (auth:sanctum). Mutates the live program in place (audited);
 * works on published programs too — editing does NOT create a new version.
 * [Source: backend ProgramController::update]
 */
export async function updateProgram(
  id: string,
  input: { name?: string; description?: string | null },
): Promise<Program> {
  const response = await csrfFetch(`/programs/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(input),
  })

  if (response.status === 200) {
    const json: unknown = await response.json()
    return programResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new UpdateProgramError('UNAUTHENTICATED')
  }
  if (response.status === 403) {
    throw new UpdateProgramError('FORBIDDEN')
  }
  if (response.status === 404) {
    throw new UpdateProgramError('NOT_FOUND')
  }
  if (response.status === 422) {
    const message = firstValidationMessage(await readValidationDetails(response))
    throw new UpdateProgramError('VALIDATION', message ?? 'Please check your entries and try again.')
  }
  throw new UpdateProgramError('UNKNOWN', `update program failed: ${response.status}`)
}

/**
 * POST /programs/{id}/clone (auth:sanctum). Deep-copies into a new DRAFT; the 201
 * body is the new program. Requires a name.
 * [Source: backend ProgramController::clone]
 */
export async function cloneProgram(id: string, name: string): Promise<Program> {
  const response = await csrfFetch(`/programs/${id}/clone`, {
    method: 'POST',
    body: JSON.stringify({ name }),
  })

  if (response.status === 201) {
    const json: unknown = await response.json()
    return programResponseSchema.parse(json).data
  }
  if (response.status === 401) {
    throw new CloneProgramError('UNAUTHENTICATED')
  }
  if (response.status === 403) {
    throw new CloneProgramError('FORBIDDEN')
  }
  if (response.status === 404) {
    throw new CloneProgramError('NOT_FOUND')
  }
  if (response.status === 422) {
    const message = firstValidationMessage(await readValidationDetails(response))
    throw new CloneProgramError('VALIDATION', message ?? 'Please check the name and try again.')
  }
  throw new CloneProgramError('UNKNOWN', `clone program failed: ${response.status}`)
}
