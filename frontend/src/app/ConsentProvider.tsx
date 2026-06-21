import { useMemo, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { getProfile } from '../api/profile'
import { ConsentError } from '../schemas/profile'
import { ConsentContext, type ConsentValue } from './consent-context'

/**
 * The consent-aware profile-read seam (FR-006/NFR-006, Story 1.5). Reads the
 * operator profile once and exposes it via useConsent(); feature screens consume
 * the seam rather than fetching profiles directly, so consent is enforced at a
 * single call site against the Startup Gate mock. A denied consent
 * (CONSENT_REQUIRED) is surfaced as the `consent-required` state — not a crash,
 * not leaked data. Production consent integration lands with FR-157.
 */
export function ConsentProvider({ children }: { children: ReactNode }) {
  const profileQuery = useQuery({
    queryKey: ['profile'],
    queryFn: getProfile,
    retry: false,
    staleTime: 60_000,
  })

  const value = useMemo<ConsentValue>(() => {
    // Prefer the last-good profile: a transient background-refetch error (window
    // focus after staleTime + a network blip) flips isError while `data` is still
    // cached — don't drop the already-granted name for it.
    if (profileQuery.data !== undefined) {
      return { status: 'ready', profile: profileQuery.data }
    }
    if (profileQuery.isError) {
      const denied =
        profileQuery.error instanceof ConsentError &&
        profileQuery.error.code === 'CONSENT_REQUIRED'
      return { status: denied ? 'consent-required' : 'error' }
    }
    return { status: 'loading' }
  }, [profileQuery.data, profileQuery.isError, profileQuery.error])

  return <ConsentContext.Provider value={value}>{children}</ConsentContext.Provider>
}
