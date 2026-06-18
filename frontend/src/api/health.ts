import { API_BASE_URL } from './client'
import { healthSchema, type Health } from '../schemas/health'

export async function fetchHealth(): Promise<Health> {
  const response = await fetch(`${API_BASE_URL}/health`)
  const json: unknown = await response.json()
  return healthSchema.parse(json)
}
