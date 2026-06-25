import { API_BASE_URL } from './client'

/**
 * The active tenant organization for this page session. The console gate sets it
 * when it resolves the user's org; tenant-scoped API calls send it as the
 * X-Organization-Id header that ResolveTenant requires. Null before login /
 * during onboarding — those calls are not tenant-scoped.
 */
let activeOrganizationId: string | null = null

export function setActiveOrganizationId(id: string | null): void {
  activeOrganizationId = id
}

export function getActiveOrganizationId(): string | null {
  return activeOrganizationId
}

/** The tenant header, or an empty object when no org is active. */
export function tenantHeaders(): Record<string, string> {
  return activeOrganizationId !== null ? { 'X-Organization-Id': activeOrganizationId } : {}
}

/**
 * fetch for tenant-scoped reads: prepends the API base, sends cookies, and adds
 * X-Organization-Id when an org is active. Mutations use csrfFetch (which injects
 * the same header); this is the read-side equivalent.
 */
export function apiFetch(path: string, init: RequestInit = {}): Promise<Response> {
  return fetch(`${API_BASE_URL}${path}`, {
    ...init,
    credentials: 'include',
    headers: { ...init.headers, ...tenantHeaders() },
  })
}
