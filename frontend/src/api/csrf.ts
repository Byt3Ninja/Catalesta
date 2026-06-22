import { API_BASE_URL, APP_BASE_URL } from './client'

/** Read a cookie value by name from document.cookie, or undefined. */
function readCookie(name: string): string | undefined {
  const match = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`))
  return match ? match.slice(name.length + 1) : undefined
}

/**
 * CSRF-aware fetch for state-changing API calls (SP-1b-ii). Sanctum's statefulApi()
 * enforces CSRF for cookie-authenticated requests, so every mutation must carry the
 * X-XSRF-TOKEN header. The preflight runs only when the cookie is absent (idempotent).
 * Laravel URL-encodes the cookie value, so it is decoded before being sent as a header.
 */
export async function csrfFetch(path: string, init: RequestInit = {}): Promise<Response> {
  if (readCookie('XSRF-TOKEN') === undefined) {
    await fetch(`${APP_BASE_URL}/sanctum/csrf-cookie`, { credentials: 'include' })
  }
  const token = readCookie('XSRF-TOKEN')
  const headers = new Headers(init.headers)
  // Default to JSON, but never clobber a Content-Type the caller set (e.g. multipart).
  if (!headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }
  if (token !== undefined) {
    headers.set('X-XSRF-TOKEN', decodeURIComponent(token))
  }
  return fetch(`${API_BASE_URL}${path}`, { ...init, credentials: 'include', headers })
}
