import { csrfFetch } from './csrf'
import { ApiError, fieldMessage, firstValidationMessage, readValidationDetails } from './errors'
import { sessionResponseSchema, type SessionUser } from '../schemas/session'

export type NativeAuthCode =
  | 'INVALID_CREDENTIALS'
  | 'EMAIL_TAKEN'
  | 'INVALID_RESET_TOKEN'
  | 'RATE_LIMITED'
  | 'UNKNOWN'

/** Typed native-auth error. Login failures are deliberately collapsed to one code. */
export class NativeAuthError extends ApiError<NativeAuthCode> {
  constructor(code: NativeAuthCode, message?: string) {
    super(code, message)
    this.name = 'NativeAuthError'
  }
}

async function parseUser(response: Response): Promise<SessionUser> {
  const json: unknown = await response.json()
  return sessionResponseSchema.parse(json).user
}

export async function register(input: {
  email: string
  password: string
  displayName?: string
}): Promise<SessionUser> {
  const response = await csrfFetch('/auth/register', {
    method: 'POST',
    body: JSON.stringify({
      email: input.email,
      password: input.password,
      display_name: input.displayName,
    }),
  })
  if (response.status === 201) return parseUser(response)
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  if (response.status === 422) {
    const details = await readValidationDetails(response)
    if (details?.email !== undefined) {
      throw new NativeAuthError('EMAIL_TAKEN', fieldMessage(details.email))
    }
    throw new NativeAuthError('UNKNOWN', firstValidationMessage(details))
  }
  throw new NativeAuthError('UNKNOWN', `register failed: ${response.status}`)
}

export async function passwordLogin(input: {
  email: string
  password: string
}): Promise<SessionUser> {
  const response = await csrfFetch('/auth/password/login', {
    method: 'POST',
    body: JSON.stringify(input),
  })
  if (response.ok) return parseUser(response)
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  // Any other failure (notably 422) is collapsed to one generic code — never
  // inspect the field or reveal user existence (enumeration guard).
  throw new NativeAuthError('INVALID_CREDENTIALS')
}

export async function forgotPassword(email: string): Promise<void> {
  const response = await csrfFetch('/auth/password/forgot', {
    method: 'POST',
    body: JSON.stringify({ email }),
  })
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  // Any non-429 is treated as success-shaped (the endpoint always 200s; no enumeration).
}

export async function resetPassword(input: {
  token: string
  email: string
  password: string
}): Promise<void> {
  const response = await csrfFetch('/auth/password/reset', {
    method: 'POST',
    body: JSON.stringify(input),
  })
  if (response.ok) return
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  if (response.status === 422) {
    const details = await readValidationDetails(response)
    throw new NativeAuthError('INVALID_RESET_TOKEN', firstValidationMessage(details))
  }
  throw new NativeAuthError('UNKNOWN', `reset failed: ${response.status}`)
}

export async function resendVerification(): Promise<void> {
  const response = await csrfFetch('/auth/email/resend', { method: 'POST' })
  if (response.status === 204) return
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  throw new NativeAuthError('UNKNOWN', `resend failed: ${response.status}`)
}
