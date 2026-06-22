/**
 * Minimal API base. The backend exposes versioned REST under `/api/v1`
 * (CLAUDE.md rule 12). The base URL is overridable via VITE_API_BASE_URL so
 * the same build runs against local Docker, CI, and deployed environments.
 */
export const API_BASE_URL: string =
  import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1'

/**
 * App-root base (no `/api/v1`). Sanctum's CSRF-cookie route lives at the app root,
 * not under the versioned API prefix. Overridable for environments where stripping
 * the suffix from the API base doesn't hold.
 */
export const APP_BASE_URL: string =
  import.meta.env.VITE_APP_BASE_URL ?? API_BASE_URL.replace(/\/api\/v1\/?$/, '')
