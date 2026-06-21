import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { listCohorts } from '../api/cohorts'
import { useConsent } from '../app/consent-context'
import { profileDisplayName } from '../schemas/profile'
import type { Cohort } from '../schemas/cohorts'
import type { Organization } from '../schemas/organizations'

/** Human-readable cohort status (text, never colour-alone — a11y). */
const STATUS_LABEL: Record<Cohort['status'], string> = {
  draft: 'Draft',
  open: 'Open',
  closed: 'Closed',
  completed: 'Completed',
}

/**
 * The single next action Home surfaces (AC-1), derived from cohort state:
 *  - a cohort with submissions → score them (most pending first; ties → newest,
 *    which the API already orders),
 *  - else cohorts exist → open a cohort,
 *  - else (day-one) → null; the empty state explains the first action instead.
 */
function nextAction(cohorts: Cohort[]): { label: string; href?: string } | null {
  const withSubs = cohorts
    .filter((c) => (c.submissions_count ?? 0) > 0)
    .sort((a, b) => (b.submissions_count ?? 0) - (a.submissions_count ?? 0))

  if (withSubs.length > 0) {
    const cohort = withSubs[0]
    const n = cohort.submissions_count ?? 0
    // Links to the operator Submissions screen (Story 2.8).
    return {
      label: `${n} submission${n === 1 ? '' : 's'} to score`,
      href: `/cohorts/${cohort.id}/submissions`,
    }
  }
  if (cohorts.length > 0) {
    return { label: 'Open a cohort', href: '/programs' }
  }
  return null
}

/**
 * Operator Home (Story 1.5). Shows the tenant's cohorts and a single next
 * action, with a day-one empty state so a brand-new org is never blank. The
 * operator greeting is a consent-aware profile read via the ConsentProvider
 * seam (FR-006) — a denied/unavailable profile degrades to a neutral greeting,
 * never a crash or leaked data. Renders inside AppShell (a console surface).
 */
export function HomePage({ organization }: { organization: Organization }) {
  const consent = useConsent()
  const cohortsQuery = useQuery({
    queryKey: ['cohorts'],
    queryFn: listCohorts,
    retry: false,
  })

  const cohorts = cohortsQuery.data ?? []
  const action = nextAction(cohorts)

  // Consent-aware greeting: name only when consent is granted (status `ready`);
  // every other state (loading / consent-required / error) shows a neutral line
  // with no profile data.
  const name =
    consent.status === 'ready' && consent.profile
      ? profileDisplayName(consent.profile)
      : undefined

  return (
    <AppShell rail={<nav aria-label="Sections">Home</nav>}>
      <section aria-labelledby="home-heading">
        <h1 id="home-heading">
          <bdi>{organization.name}</bdi>
        </h1>

        {name ? (
          <p>
            Welcome back, <bdi>{name}</bdi>.
          </p>
        ) : (
          <p>Welcome to your operator console.</p>
        )}
        {consent.status === 'consent-required' ? (
          <p className="ds-muted">
            Profile details are hidden until profile access is granted.
          </p>
        ) : null}

        {cohortsQuery.isLoading ? (
          <Spinner label="Loading your cohorts…" />
        ) : cohortsQuery.isError ? (
          <StateBlock
            variant="error"
            message="We could not load your cohorts."
            action={<Button onClick={() => cohortsQuery.refetch()}>Try again</Button>}
          />
        ) : cohorts.length === 0 ? (
          <StateBlock
            variant="empty"
            message="No cohorts yet. Create a program, then open a cohort to start receiving applications."
            action={<Link href="/programs">Go to Programs</Link>}
          />
        ) : (
          <>
            {action ? (
              <p>
                Next:{' '}
                {action.href ? (
                  <Link href={action.href}>{action.label}</Link>
                ) : (
                  <strong>{action.label}</strong>
                )}
              </p>
            ) : null}

            <h2 id="cohorts-heading">Your cohorts</h2>
            <ul aria-labelledby="cohorts-heading">
              {cohorts.map((cohort) => (
                <li key={cohort.id}>
                  <bdi>{cohort.name}</bdi>{' '}
                  <span className="ds-badge" data-status={cohort.status}>
                    {STATUS_LABEL[cohort.status]}
                  </span>
                  {(cohort.submissions_count ?? 0) > 0 ? (
                    <span>
                      {' · '}
                      <bdi>{cohort.submissions_count}</bdi> submitted
                    </span>
                  ) : null}
                </li>
              ))}
            </ul>
          </>
        )}
      </section>
    </AppShell>
  )
}
