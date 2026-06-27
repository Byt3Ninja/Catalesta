import { type ReactNode } from 'react'
import { BrowserRouter, Routes, Route, useParams } from 'react-router-dom'
import { QueryClientProvider, useQuery } from '@tanstack/react-query'
import { queryClient } from './queryClient'
import { ConsentProvider } from './ConsentProvider'
import { HealthPage } from '../pages/HealthPage'
import { ApplyPage } from '../pages/ApplyPage'
import { LoginPage } from '../pages/LoginPage'
import { AuthCallbackPage } from '../pages/AuthCallbackPage'
import { RegisterPage } from '../pages/RegisterPage'
import { ForgotPasswordPage } from '../pages/ForgotPasswordPage'
import { ResetPasswordPage } from '../pages/ResetPasswordPage'
import { EmailVerifiedPage } from '../pages/EmailVerifiedPage'
import { VerifyEmailNotice } from '../pages/VerifyEmailNotice'
import { OnboardingPage } from '../pages/OnboardingPage'
import { ActionCenterPage } from '../pages/ActionCenterPage'
import { NotificationsPage } from '../pages/NotificationsPage'
import { NotificationPreferencesPage } from '../pages/NotificationPreferencesPage'
import { ProgramsPage } from '../pages/ProgramsPage'
import { ProgramDetailPage } from '../pages/ProgramDetailPage'
import { CohortDetailPage } from '../pages/CohortDetailPage'
import { SubmissionsPage } from '../pages/SubmissionsPage'
import { SubmissionDetailPage } from '../pages/SubmissionDetailPage'
import { ComingSoonPage } from '../pages/ComingSoonPage'
import { ProfilePage } from '../pages/ProfilePage'
import { Spinner } from '../components/Loading'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { getSession } from '../api/session'
import { listOrganizations } from '../api/organizations'
import { setActiveOrganizationId, getActiveOrganizationId } from '../api/tenant'
import type { Organization } from '../schemas/organizations'


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

  // Publish the resolved tenant org so tenant-scoped API calls can send
  // X-Organization-Id (ResolveTenant requires it). Null until an org resolves.
  //
  // Published SYNCHRONOUSLY during render, not in a useEffect: child console
  // surfaces fetch from a child effect, and child effects fire BEFORE a parent
  // effect — so an effect here would publish the org only after the child's
  // first tenant-scoped read already went out headerless and 400'd. The guard
  // keeps the render pure-enough (idempotent: only writes when the value changes).
  const resolvedOrgId =
    orgsQuery.isSuccess && (orgsQuery.data?.length ?? 0) > 0 ? orgsQuery.data![0].id : null
  if (getActiveOrganizationId() !== resolvedOrgId) {
    setActiveOrganizationId(resolvedOrgId)
  }

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

  // Unverified native account → block console/onboarding behind the verify notice.
  // SG-linked accounts are auto-verified and skip this. (sessionQuery.data is defined
  // here: isLoading and isError were both handled above.)
  if (sessionQuery.data?.email_verified === false) {
    return <VerifyEmailNotice />
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

  // Authenticated with an org → render the requested console surface through the
  // gate. The ConsentProvider seam wraps the Home render site only (Home is the
  // sole consent consumer), so other console surfaces don't pay a profile read.
  return children ? (
    children(orgs[0])
  ) : (
    <ConsentProvider>
      <ActionCenterPage organization={orgs[0]} />
    </ConsentProvider>
  )
}

// --- Route elements -------------------------------------------------------
// Thin wrappers read params from react-router (already URL-decoded — no manual
// decodeURIComponent) and pass the existing props into the existing pages.
// Behavior matches the previous regex resolver one-for-one.

function ApplyRoute() {
  const { cohortId } = useParams()
  return <ApplyPage cohortId={cohortId!} />
}

function ProgramsRoute() {
  return <ConsoleGate>{(org) => <ProgramsPage organization={org} />}</ConsoleGate>
}

function ProgramDetailRoute() {
  const { programId } = useParams()
  // Gate admits the console surface; the detail page needs only the id.
  return <ConsoleGate>{() => <ProgramDetailPage programId={programId!} />}</ConsoleGate>
}

function CohortDetailRoute() {
  const { cohortId } = useParams()
  return <ConsoleGate>{() => <CohortDetailPage cohortId={cohortId!} />}</ConsoleGate>
}

function SubmissionsRoute() {
  const { cohortId } = useParams()
  return (
    <ConsoleGate>{(org) => <SubmissionsPage cohortId={cohortId!} organization={org} />}</ConsoleGate>
  )
}

function SubmissionDetailRoute() {
  const { cohortId, submissionId } = useParams()
  return (
    <ConsoleGate>
      {(org) => (
        <SubmissionDetailPage cohortId={cohortId!} submissionId={submissionId!} organization={org} />
      )}
    </ConsoleGate>
  )
}

function PreviewRoute() {
  return <ConsoleGate>{() => <ComingSoonPage />}</ConsoleGate>
}

// Root and any unknown console/onboarding path → the gate decides. Home is the
// consent-aware surface, so it renders inside the ConsentProvider seam (FR-006).
function HomeRoute() {
  return (
    <ConsoleGate>
      {(org) => (
        <ConsentProvider>
          <ActionCenterPage organization={org} />
        </ConsentProvider>
      )}
    </ConsoleGate>
  )
}

export function AppRoutes() {
  return (
    <Routes>
      {/* Public — render directly, no gate. */}
      <Route path="/health" element={<HealthPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/auth/callback" element={<AuthCallbackPage />} />
      <Route path="/auth/reset-password" element={<ResetPasswordPage />} />
      <Route path="/auth/email-verified" element={<EmailVerifiedPage />} />
      <Route path="/apply/:cohortId" element={<ApplyRoute />} />

      {/* Console — gated by ConsoleGate (server-side session/org). */}
      <Route path="/programs" element={<ProgramsRoute />} />
      <Route path="/programs/:programId" element={<ProgramDetailRoute />} />
      <Route
        path="/cohorts/:cohortId/submissions/:submissionId"
        element={<SubmissionDetailRoute />}
      />
      <Route path="/cohorts/:cohortId" element={<CohortDetailRoute />} />
      <Route path="/cohorts/:cohortId/submissions" element={<SubmissionsRoute />} />

      <Route path="/profile" element={<ConsoleGate>{() => <ConsentProvider><ProfilePage /></ConsentProvider>}</ConsoleGate>} />
      <Route path="/notifications" element={<ConsoleGate>{() => <NotificationsPage />}</ConsoleGate>} />
      <Route path="/notifications/preferences" element={<ConsoleGate>{() => <NotificationPreferencesPage />}</ConsoleGate>} />

      <Route path="/preview/:section" element={<PreviewRoute />} />

      {/* Root and any other route → gate decides (today's fallthrough). */}
      <Route path="/" element={<HomeRoute />} />
      <Route path="*" element={<HomeRoute />} />
    </Routes>
  )
}

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AppRoutes />
      </BrowserRouter>
    </QueryClientProvider>
  )
}
