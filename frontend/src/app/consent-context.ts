import { createContext, useContext } from 'react'
import type { Profile } from '../schemas/profile'

/**
 * Consent-aware profile-read seam (FR-006/NFR-006). `consent-required` is a
 * first-class state, distinct from `error`, so screens render a neutral
 * affordance and never leak partial profile data.
 */
export type ConsentStatus = 'loading' | 'ready' | 'consent-required' | 'error'

export interface ConsentValue {
  status: ConsentStatus
  /** Present only when status is `ready`. */
  profile?: Profile
}

export const ConsentContext = createContext<ConsentValue | null>(null)

export function useConsent(): ConsentValue {
  const ctx = useContext(ConsentContext)
  if (ctx === null) {
    throw new Error('useConsent must be used within a ConsentProvider')
  }
  return ctx
}
