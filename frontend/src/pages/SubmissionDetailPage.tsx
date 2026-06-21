import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getSubmission } from '../api/submissions'
import type { SubmissionDetail } from '../schemas/submissions'
import type { Organization } from '../schemas/organizations'

/**
 * Submission detail (Story 2.8) — the immutable snapshot, read-only. A route
 * (not a modal — preserves Story 1.0's modal deferral). Reached from the
 * Submissions list "Open detail" control.
 */
export function SubmissionDetailPage({
  cohortId,
  submissionId,
  organization,
}: {
  cohortId: string
  submissionId: string
  organization: Organization
}) {
  const query = useQuery({
    queryKey: ['submission', cohortId, submissionId],
    queryFn: () => getSubmission(cohortId, submissionId),
    retry: false,
  })

  return (
    <AppShell rail={<nav aria-label="Sections">Submissions</nav>}>
      <section aria-labelledby="detail-heading">
        <h1 id="detail-heading">
          <bdi>{organization.name}</bdi> — Submission
        </h1>
        <p>
          <Link href={`/cohorts/${cohortId}/submissions`}>← Back to submissions</Link>
        </p>

        {query.isLoading ? (
          <Spinner label="Loading submission…" />
        ) : query.isError ? (
          <StateBlock
            variant="error"
            message="We could not load this submission."
            action={<Button onClick={() => query.refetch()}>Try again</Button>}
          />
        ) : query.data ? (
          <Snapshot detail={query.data} />
        ) : null}
      </section>
    </AppShell>
  )
}

/** Read-only render of the immutable answer snapshot. */
function Snapshot({ detail }: { detail: SubmissionDetail }) {
  const answers =
    detail.snapshot && typeof detail.snapshot.answers === 'object' && detail.snapshot.answers
      ? (detail.snapshot.answers as Record<string, unknown>)
      : {}
  const entries = Object.entries(answers)

  return (
    <>
      <p>
        Reference: <bdi>{detail.reference_number}</bdi>
      </p>
      {entries.length === 0 ? (
        <p>This submission has no recorded answers.</p>
      ) : (
        <dl>
          {entries.map(([key, value]) => (
            <div key={key}>
              <dt>
                <bdi>{key}</bdi>
              </dt>
              <dd>
                <bdi>{formatAnswer(value)}</bdi>
              </dd>
            </div>
          ))}
        </dl>
      )}
    </>
  )
}

function formatAnswer(value: unknown): string {
  if (value == null) return ''
  if (Array.isArray(value)) return value.join(', ')
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}
