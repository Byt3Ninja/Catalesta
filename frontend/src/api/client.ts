/**
 * Minimal API base. The backend exposes versioned REST under `/api/v1`
 * (CLAUDE.md rule 12). The base URL is overridable via VITE_API_BASE_URL so
 * the same build runs against local Docker, CI, and deployed environments.
 */
export const API_BASE_URL: string =
  import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1'
