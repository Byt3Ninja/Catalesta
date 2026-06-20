import type { ReactNode } from 'react'
import { QueryClientProvider, useQuery } from '@tanstack/react-query'
import { queryClient } from './queryClient'
import { HealthPage } from '../pages/HealthPage'
import { ApplyPage } from '../pages/ApplyPage'
import { LoginPage } from '../pages/LoginPage'
import { AuthCallbackPage } from '../pages/AuthCallbackPage'
import { OnboardingPage } from '../pages/OnboardingPage'
import { HomePage } from '../pages/HomePage'
import { Spinner } from '../components/Loading'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { getSession } from '../api/session'
import { listOrganizations } from '../api/organizations'
import type { Organization } from '../schemas/organizations'

/** No router is installed — match routes off the pathname. */
const APPLY_ROUTE = /^\/apply\/([^/]+)\/?$/
const LOGIN_ROUTE = /^\/login\/?$/
const CALLBACK_ROUTE = /^\/auth\/callback\/?$/
const HEALTH_ROUTE = /^\/health\/?$/

/**
 * No-org gate (Story 1.1, AC-1/2/3). Drives a session + org query and decides:
 *  - unauthenticated        → /login (no console surface renders)
 *  - authenticated, no org  → OnboardingPage (non-skippable; no skip/back-out)
 *  - authenticated, has org → operator Home (AppShell)
 * Console surfaces are unreachable without an org because this is the only path
 * that renders them.
 */
function ConsoleGate({ children }: { children?: (org: Organization) => ReactNode }) {
  const sessionQuery = useQuery({
    queryKey: ['session'],
    queryFn: getSession,
    retry: false,
    staleTime: 60_000,
  })

  const orgsQuery = useQuery({
    queryKey: ['organizations'],
    queryFn: listOrganizations,
    retry: false,
    enabled: sessionQuery.isSuccess,
    staleTime: 60_000,
  })

  if (sessionQuery.isLoading) {
    return (
      <section aria-labelledby="gate-heading">
        <h1 id="gate-heading">Loading…</h1>
        <Spinner label="Checking your session…" />
      </section>
    )
  }

  // No session (UNAUTHENTICATED or any session failure) → login.
  if (sessionQuery.isError) {
    return <LoginPage />
  }

  // The org decision must wait until the dependent query has truly succeeded —
  // a just-enabled query can be `!isLoading` with `data` still undefined.
  if (orgsQuery.isError) {
    return (
      <section aria-labelledby="gate-heading">
        <h1 id="gate-heading">Workspace</h1>
        <Banner variant="error">We could not load your workspace. Please try again.</Banner>
        <Button onClick={() => orgsQuery.refetch()}>Try again</Button>
      </section>
    )
  }

  if (!orgsQuery.isSuccess) {
    return (
      <section aria-labelledby="gate-heading">
        <h1 id="gate-heading">Loading…</h1>
        <Spinner label="Loading your workspace…" />
      </section>
    )
  }

  const orgs = orgsQuery.data ?? []

  // Authenticated with no org → forced, non-skippable onboarding.
  if (orgs.length === 0) {
    return <OnboardingPage />
  }

  // Authenticated with an org → render the requested console surface through the gate.
  return children ? children(orgs[0]) : <HomePage organization={orgs[0]} />
}

function resolveRoute() {
  const path = window.location.pathname

  const apply = APPLY_ROUTE.exec(path)
  if (apply) {
    return <ApplyPage cohortId={decodeURIComponent(apply[1])} />
  }
  if (HEALTH_ROUTE.test(path)) {
    return <HealthPage />
  }
  if (LOGIN_ROUTE.test(path)) {
    return <LoginPage />
  }
  if (CALLBACK_ROUTE.test(path)) {
    return <AuthCallbackPage />
  }
  // Root and any other console/onboarding route → the gate decides.
  return <ConsoleGate>{(org) => <HomePage organization={org} />}</ConsoleGate>
}

export function App() {
  return <QueryClientProvider client={queryClient}>{resolveRoute()}</QueryClientProvider>
}
