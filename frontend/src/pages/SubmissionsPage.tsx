import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getFunnel, listSubmissions } from '../api/submissions'
import type { Funnel } from '../schemas/submissions'
import type { Organization } from '../schemas/organizations'

/**
 * Operator Submissions screen (Story 2.8). Shows the funnel (viewed/started/
 * submitted) and the cohort's submission list. Day-one (no submissions) renders an
 * empty state with a copyable share link — never "0/0/0". A console surface, so it
 * renders inside AppShell. This is the target of Story 1-5's "N submissions to
 * score" next-action.
 */
export function SubmissionsPage({
  cohortId,
  organization,
}: {
  cohortId: string
  organization: Organization
}) {
  const funnelQuery = useQuery({
    queryKey: ['funnel', cohortId],
    queryFn: () => getFunnel(cohortId),
    retry: false,
  })
  const submissionsQuery = useQuery({
    queryKey: ['submissions', cohortId],
    queryFn: () => listSubmissions(cohortId),
    retry: false,
  })

  const submissions = submissionsQuery.data ?? []
  const applyUrl = `${window.location.origin}/apply/${cohortId}`

  return (
    <AppShell rail={<nav aria-label="Sections">Submissions</nav>}>
      <section aria-labelledby="subs-heading">
        <h1 id="subs-heading">
          <bdi>{organization.name}</bdi> — Submissions
        </h1>

        {funnelQuery.data ? (
          <FunnelBar funnel={funnelQuery.data} />
        ) : funnelQuery.isError ? (
          <p className="ds-muted">The funnel is unavailable right now.</p>
        ) : null}

        <h2 id="subs-list-heading">Applications</h2>
        {submissionsQuery.isLoading ? (
          <Spinner label="Loading submissions…" />
        ) : submissionsQuery.isError ? (
          <StateBlock
            variant="error"
            message="We could not load submissions."
            action={<Button onClick={() => submissionsQuery.refetch()}>Try again</Button>}
          />
        ) : submissions.length === 0 ? (
          <ZeroDay applyUrl={applyUrl} />
        ) : (
          <ul aria-labelledby="subs-list-heading">
            {submissions.map((s) => (
              <li key={s.reference_number}>
                <bdi>{s.reference_number}</bdi>{' · '}
                <time dateTime={s.submitted_at}>{s.submitted_at}</time>{' '}
                <Link href={`/cohorts/${cohortId}/submissions/${s.reference_number}`}>
                  Open detail
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </AppShell>
  )
}

/** The funnel header. Numerals are bdi-isolated; `viewed` carries the approximate
 *  caveat (best-effort beacons undercount). */
function FunnelBar({ funnel }: { funnel: Funnel }) {
  return (
    <dl className="ds-funnel" role="group" aria-label="Application funnel">
      <div>
        <dt>Viewed</dt>
        <dd>
          <bdi>{funnel.viewed}</bdi>{' '}
          <small>(approximate — counts refreshes and bots)</small>
        </dd>
      </div>
      <div>
        <dt>Started</dt>
        <dd>
          <bdi>{funnel.started}</bdi>
        </dd>
      </div>
      <div>
        <dt>Submitted</dt>
        <dd>
          <bdi>{funnel.submitted}</bdi>
        </dd>
      </div>
    </dl>
  )
}

/** Zero-day state: explain the first action + a copyable public apply link. */
function ZeroDay({ applyUrl }: { applyUrl: string }) {
  const [copied, setCopied] = useState(false)
  return (
    <StateBlock
      variant="empty"
      message="No applications yet. Share your cohort link to start receiving them."
      action={
        <p>
          <code>{applyUrl}</code>{' '}
          <Button
            onClick={() => {
              // Only claim success once the write actually resolves; on a browser
              // without the Clipboard API (insecure origin / old webview) the URL is
              // still shown above for manual copy.
              void navigator.clipboard
                ?.writeText(applyUrl)
                .then(() => setCopied(true))
                .catch(() => {})
            }}
          >
            {copied ? 'Copied' : 'Copy link'}
          </Button>
        </p>
      }
    />
  )
}
