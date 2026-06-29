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
import { CohortSetupWizard } from '../pages/CohortSetupWizard'
import { EnrollmentWindowEditor } from '../pages/EnrollmentWindowEditor'
import { SubmissionsPage } from '../pages/SubmissionsPage'
import { SubmissionDetailPage } from '../pages/SubmissionDetailPage'
import { ComingSoonPage } from '../pages/ComingSoonPage'
import { ProfilePage } from '../pages/ProfilePage'
import { ConsentManagementPage } from '../pages/ConsentManagementPage'
import { FormBuilderPage } from '../pages/FormBuilderPage'
import { FormPreviewPage } from '../pages/FormPreviewPage'
import { FormVersionsPage } from '../pages/FormVersionsPage'
import { StagePipelineBuilderPage } from '../pages/StagePipelineBuilderPage'
import { StagePipelinePreviewPage } from '../pages/StagePipelinePreviewPage'
import { StagePipelineVersionsPage } from '../pages/StagePipelineVersionsPage'
import { ProgramConfigPage } from '../pages/ProgramConfigPage'
import { ScoringModelBuilderPage } from '../pages/ScoringModelBuilderPage'
import { ScoringModelPreviewPage } from '../pages/ScoringModelPreviewPage'
import { ScoringModelVersionsPage } from '../pages/ScoringModelVersionsPage'
import { ReviewQueuePage } from '../pages/ReviewQueuePage'
import { ScorecardPage } from '../pages/ScorecardPage'
import { Spinner } from '../components/Loading'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { StateBlock } from '../components/StateBlock'
import { getSession } from '../api/session'
import { getForm } from '../api/forms'
import { getStagePipeline } from '../api/stages'
import { getScoringModel } from '../api/assessments'
import { getCohort } from '../api/cohorts'
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

function CohortSetupRoute() {
  const { programId } = useParams()
  return <ConsoleGate>{() => <CohortSetupWizard programId={programId!} />}</ConsoleGate>
}

function EnrollmentWindowRoute() {
  const { cohortId } = useParams()
  return <ConsoleGate>{() => <EnrollmentWindowEditor cohortId={cohortId!} />}</ConsoleGate>
}

function ProgramConfigRoute() {
  const { programId } = useParams()
  return <ConsoleGate>{() => <ProgramConfigPage programId={programId!} />}</ConsoleGate>
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

// --- Slice 2b: Forms routes ---------------------------------------------------

function FormBuilderRoute() {
  const { formId } = useParams()
  return <ConsoleGate>{() => <FormBuilderPage formId={formId!} />}</ConsoleGate>
}

function FormVersionsRoute() {
  const { formId } = useParams()
  return <ConsoleGate>{() => <FormVersionsPage formId={formId!} />}</ConsoleGate>
}

/** Resolves the correct versionId from the form record, then renders FormPreviewPage.
 *  Prefers the current draft version; falls back to the latest published version.
 *  When the query has resolved but no version exists, shows an error state instead
 *  of an infinite spinner. */
function FormPreviewResolver({ formId }: { formId: string }) {
  const formQuery = useQuery({ queryKey: ['form', formId], queryFn: () => getForm(formId), retry: false })
  if (formQuery.isLoading) return <Spinner label="Loading form…" />
  const form = formQuery.data
  const versionId = form?.current_draft_version_id ?? form?.published_version_ids.at(-1)
  if (!versionId) {
    return (
      <StateBlock
        variant="error"
        message="This form has no versions to preview yet."
      />
    )
  }
  return <FormPreviewPage versionId={versionId} />
}

function FormPreviewRoute() {
  const { formId } = useParams()
  return <ConsoleGate>{() => <FormPreviewResolver formId={formId!} />}</ConsoleGate>
}

// --- Slice 2c: Stages routes --------------------------------------------------

function StageBuilderRoute() {
  const { pipelineId } = useParams()
  return <ConsoleGate>{() => <StagePipelineBuilderPage pipelineId={pipelineId!} />}</ConsoleGate>
}

function StageVersionsRoute() {
  const { pipelineId } = useParams()
  return <ConsoleGate>{() => <StagePipelineVersionsPage pipelineId={pipelineId!} />}</ConsoleGate>
}

/** Resolves the previewable versionId from the pipeline record (current draft, else
 *  latest published), mirroring FormPreviewResolver. */
function StagePreviewResolver({ pipelineId }: { pipelineId: string }) {
  const pipelineQuery = useQuery({ queryKey: ['stage-pipeline', pipelineId], queryFn: () => getStagePipeline(pipelineId), retry: false })
  if (pipelineQuery.isLoading) return <Spinner label="Loading pipeline…" />
  const p = pipelineQuery.data
  const versionId = p?.current_draft_version_id ?? p?.published_version_ids.at(-1)
  if (!versionId) return <StateBlock variant="error" message="This pipeline has no versions to preview yet." />
  return <StagePipelinePreviewPage versionId={versionId} />
}

function StagePreviewRoute() {
  const { pipelineId } = useParams()
  return <ConsoleGate>{() => <StagePreviewResolver pipelineId={pipelineId!} />}</ConsoleGate>
}

// --- Slice 2d: Assessments routes --------------------------------------------

function ScoringModelBuilderRoute() {
  const { modelId } = useParams()
  return <ConsoleGate>{() => <ScoringModelBuilderPage modelId={modelId!} />}</ConsoleGate>
}

function ScoringModelVersionsRoute() {
  const { modelId } = useParams()
  return <ConsoleGate>{() => <ScoringModelVersionsPage modelId={modelId!} />}</ConsoleGate>
}

/** Resolves the previewable versionId from the scoring model (current draft, else
 *  latest published), mirroring StagePreviewResolver. */
function ScoringModelPreviewResolver({ modelId }: { modelId: string }) {
  const modelQuery = useQuery({ queryKey: ['scoring-model', modelId], queryFn: () => getScoringModel(modelId), retry: false })
  if (modelQuery.isLoading) return <Spinner label="Loading scoring model…" />
  const m = modelQuery.data
  const versionId = m?.current_draft_version_id ?? m?.published_version_ids.at(-1)
  if (!versionId) return <StateBlock variant="error" message="This scoring model has no versions to preview yet." />
  return <ScoringModelPreviewPage versionId={versionId} />
}

function ScoringModelPreviewRoute() {
  const { modelId } = useParams()
  return <ConsoleGate>{() => <ScoringModelPreviewResolver modelId={modelId!} />}</ConsoleGate>
}

/** Resolves reviewerId from the cached session query, then renders ReviewQueuePage.
 *  ConsoleGate ensures the session is already loaded before this component mounts,
 *  so the query is served from cache synchronously on the first render. */
function ReviewQueueResolver({ cohortId, stageId }: { cohortId: string; stageId: string }) {
  const sessionQuery = useQuery({ queryKey: ['session'], queryFn: getSession, retry: false, staleTime: 60_000 })
  const reviewerId = sessionQuery.data?.id
  if (!reviewerId) return <Spinner label="Loading session…" />
  return <ReviewQueuePage cohortId={cohortId} stageId={stageId} reviewerId={reviewerId} />
}

function ReviewQueueRoute() {
  const { cohortId, stageId } = useParams()
  return <ConsoleGate>{() => <ReviewQueueResolver cohortId={cohortId!} stageId={stageId!} />}</ConsoleGate>
}

/** Resolves reviewerId (session) and modelVersionId (cohort's stage_scoring_model_version_ids
 *  map) before rendering ScorecardPage. Shows an error StateBlock if no model is bound. */
function ScorecardResolver({
  cohortId,
  stageId,
  applicationId,
}: {
  cohortId: string
  stageId: string
  applicationId: string
}) {
  const sessionQuery = useQuery({ queryKey: ['session'], queryFn: getSession, retry: false, staleTime: 60_000 })
  const cohortQuery = useQuery({ queryKey: ['cohort', cohortId], queryFn: () => getCohort(cohortId), retry: false })
  const reviewerId = sessionQuery.data?.id
  if (!reviewerId || cohortQuery.isLoading) return <Spinner label="Loading…" />
  if (cohortQuery.isError) return <StateBlock variant="error" message="Could not load the cohort." />
  const modelVersionId = cohortQuery.data?.stage_scoring_model_version_ids?.[stageId]
  if (!modelVersionId) return <StateBlock variant="error" message="No scoring model is bound to this stage." />
  return (
    <ScorecardPage
      cohortId={cohortId}
      stageId={stageId}
      applicationId={applicationId}
      reviewerId={reviewerId}
      modelVersionId={modelVersionId}
    />
  )
}

function ScorecardRoute() {
  const { cohortId, stageId, applicationId } = useParams()
  return (
    <ConsoleGate>
      {() => <ScorecardResolver cohortId={cohortId!} stageId={stageId!} applicationId={applicationId!} />}
    </ConsoleGate>
  )
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
      <Route path="/programs/:programId/cohorts/new" element={<CohortSetupRoute />} />
      <Route path="/programs/:programId/config" element={<ProgramConfigRoute />} />
      <Route
        path="/cohorts/:cohortId/submissions/:submissionId"
        element={<SubmissionDetailRoute />}
      />
      <Route path="/cohorts/:cohortId" element={<CohortDetailRoute />} />
      <Route path="/cohorts/:cohortId/enrollment" element={<EnrollmentWindowRoute />} />
      <Route path="/cohorts/:cohortId/submissions" element={<SubmissionsRoute />} />

      <Route path="/profile" element={<ConsoleGate>{() => <ConsentProvider><ProfilePage /></ConsentProvider>}</ConsoleGate>} />
      <Route path="/consent" element={<ConsoleGate>{() => <ConsentManagementPage />}</ConsoleGate>} />
      <Route path="/notifications" element={<ConsoleGate>{() => <NotificationsPage />}</ConsoleGate>} />
      <Route path="/notifications/preferences" element={<ConsoleGate>{() => <NotificationPreferencesPage />}</ConsoleGate>} />

      {/* Slice 2b: Forms */}
      <Route path="/forms/:formId/edit" element={<FormBuilderRoute />} />
      <Route path="/forms/:formId/preview" element={<FormPreviewRoute />} />
      <Route path="/forms/:formId/versions" element={<FormVersionsRoute />} />

      {/* Slice 2c: Stages */}
      <Route path="/programs/:programId/stages/:pipelineId/edit" element={<StageBuilderRoute />} />
      <Route path="/programs/:programId/stages/:pipelineId/preview" element={<StagePreviewRoute />} />
      <Route path="/programs/:programId/stages/:pipelineId/versions" element={<StageVersionsRoute />} />

      {/* Slice 2d: Assessments */}
      <Route path="/programs/:programId/scoring/:modelId/edit" element={<ScoringModelBuilderRoute />} />
      <Route path="/programs/:programId/scoring/:modelId/preview" element={<ScoringModelPreviewRoute />} />
      <Route path="/programs/:programId/scoring/:modelId/versions" element={<ScoringModelVersionsRoute />} />
      <Route path="/cohorts/:cohortId/stages/:stageId/review" element={<ReviewQueueRoute />} />
      <Route path="/cohorts/:cohortId/stages/:stageId/review/:applicationId" element={<ScorecardRoute />} />

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
