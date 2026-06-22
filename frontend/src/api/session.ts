import { API_BASE_URL } from './client'
import { csrfFetch } from './csrf'
import {
  loginUrlSchema,
  sessionResponseSchema,
  SessionError,
  type SessionUser,
} from '../schemas/session'

/**
 * GET /auth/session (auth:sanctum) — current user, or a typed UNAUTHENTICATED
 * error on 401. Cookie-based session → credentials:'include'.
 */
export async function getSession(): Promise<SessionUser> {
  const response = await fetch(`${API_BASE_URL}/auth/session`, {
    credentials: 'include',
  })
  if (response.status === 401) {
    throw new SessionError('UNAUTHENTICATED')
  }
  if (!response.ok) {
    throw new SessionError('UNKNOWN', `session fetch failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return sessionResponseSchema.parse(json).user
}

/**
 * GET /auth/login — the backend stashes PKCE/state/nonce in the session and
 * returns the IdP authorization URL. We redirect the browser to it.
 */
export async function beginLogin(): Promise<void> {
  // Capture the intended path so AuthCallbackPage can return the user here after
  // the IdP round-trip. Skip the auth pages themselves (they are not destinations).
  const { pathname, search } = window.location
  if (pathname !== '/login' && pathname !== '/auth/callback') {
    sessionStorage.setItem('postLoginRedirect', pathname + search)
  }

  const response = await fetch(`${API_BASE_URL}/auth/login`, {
    credentials: 'include',
  })
  if (!response.ok) {
    throw new SessionError('UNKNOWN', `login initiate failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  const { authorization_url } = loginUrlSchema.parse(json)
  window.location.assign(authorization_url)
}

/**
 * POST /auth/callback — completes the OIDC handshake and sets the Sanctum SPA
 * session cookie. Returns the freshly logged-in user.
 */
export async function completeLogin(state: string, code: string): Promise<SessionUser> {
  const response = await csrfFetch('/auth/callback', {
    method: 'POST',
    body: JSON.stringify({ state, code }),
  })
  if (!response.ok) {
    throw new SessionError('UNKNOWN', `login callback failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return sessionResponseSchema.parse(json).user
}
