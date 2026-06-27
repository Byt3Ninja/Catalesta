import { apiFetch } from './tenant'
import { searchResponseSchema, type SearchGroup } from '../schemas/search'

/** GET /search?q= — categorized global search results (prototype contract). */
export async function search(q: string): Promise<SearchGroup[]> {
  const response = await apiFetch(`/search?q=${encodeURIComponent(q)}`)
  if (!response.ok) throw new Error(`search failed: ${response.status}`)
  const json: unknown = await response.json()
  return searchResponseSchema.parse(json).data
}
