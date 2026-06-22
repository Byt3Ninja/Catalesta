import { API_BASE_URL, APP_BASE_URL } from './client'

/** Read a cookie value by name from document.cookie, or undefined. */
function readCookie(name: string): string | undefined {
  const match = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`))
  return match ? match.slice(name.length + 1) : undefined
}

/**
 * Thrown when the Sanctum CSRF preflight (`GET /sanctum/csrf-cookie`) fails
 * — either a network error or a non-2xx response. Without this we'd silently
 * proceed tokenless and surface a misleading 419 from the actual request.
 */
export class CsrfPreflightError extends Error {
  readonly status: number | 'network'
  constructor(status: number | 'network', message: string) {
    super(message)
    this.name = 'CsrfPreflightError'
    this.status = status
  }
}

/**
 * CSRF-aware fetch for state-changing API calls (SP-1b-ii). Sanctum's statefulApi()
 * enforces CSRF for cookie-authenticated requests, so every mutation must carry the
 * X-XSRF-TOKEN header. The preflight runs only when the cookie is absent (idempotent).
 * Laravel URL-encodes the cookie value, so it is decoded before being sent as a header.
 *
 * Preflight failures are surfaced as CsrfPreflightError instead of being swallowed
 * (PR #26 follow-up). FormData bodies skip the JSON default so the browser sets the
 * multipart boundary itself.
 */
export async function csrfFetch(path: string, init: RequestInit = {}): Promise<Response> {
  if (readCookie('XSRF-TOKEN') === undefined) {
    let pre: Response
    try {
      pre = await fetch(`${APP_BASE_URL}/sanctum/csrf-cookie`, { credentials: 'include' })
    } catch (e) {
      throw new CsrfPreflightError(
        'network',
        `CSRF preflight network failure: ${e instanceof Error ? e.message : String(e)}`,
      )
    }
    if (!pre.ok) {
      throw new CsrfPreflightError(pre.status, `CSRF preflight failed: ${pre.status}`)
    }
  }
  const token = readCookie('XSRF-TOKEN')
  const headers = new Headers(init.headers)
  // Default to JSON, but never clobber a Content-Type the caller set (e.g. multipart)
  // and never set it for FormData — the browser must add its own multipart boundary.
  if (!headers.has('Content-Type') && !(init.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  if (token !== undefined) {
    headers.set('X-XSRF-TOKEN', decodeURIComponent(token))
  }
  return fetch(`${API_BASE_URL}${path}`, { ...init, credentials: 'include', headers })
}
