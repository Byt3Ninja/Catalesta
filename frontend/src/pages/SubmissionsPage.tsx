import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getFunnel, listSubmissions } from '../api/submissions'
import { getStageLeaderboard } from '../api/assessments'
import type { LeaderboardRow } from '../api/assessments'
import type { Funnel } from '../schemas/submissions'
import type { Organization } from '../schemas/organizations'
import { format2 } from '../lib/decimal'

type View = 'list' | 'leaderboard'

export type StageOption = { id: string; name: string }

/**
 * Operator Submissions screen (Story 2.8). Shows the funnel (viewed/started/
 * submitted) and the cohort's submission list. Day-one (no submissions) renders an
 * empty state with a copyable share link — never "0/0/0". A console surface, so it
 * renders inside AppShell. This is the target of Story 1-5's "N submissions to
 * score" next-action.
 *
 * When `stages` is provided the page gains a Leaderboard tab. The leaderboard
 * query is gated on stage selection (no-idle-fetch invariant): it only fires
 * once the user picks a stage, never at mount.
 */
export function SubmissionsPage({
  cohortId,
  organization,
  stages,
}: {
  cohortId: string
  organization: Organization
  stages?: StageOption[]
}) {
  const [view, setView] = useState<View>('list')
  const [selectedStageId, setSelectedStageId] = useState<string | null>(null)

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
  // Leaderboard query: only fires when the user is on the leaderboard tab AND
  // has selected a stage. No fetch at mount (AppShell no-idle-fetch invariant).
  const leaderboardQuery = useQuery({
    queryKey: ['leaderboard', cohortId, selectedStageId],
    queryFn: () => getStageLeaderboard(cohortId, selectedStageId!),
    enabled: selectedStageId !== null,
    retry: false,
  })

  const submissions = submissionsQuery.data ?? []
  const applyUrl = `${window.location.origin}/apply/${cohortId}`
  const hasStages = (stages ?? []).length > 0

  return (
    <AppShell
      rail={
        <nav aria-label="Sections">
          <Button
            onClick={() => setView('list')}
            aria-current={view === 'list' ? 'page' : undefined}
          >
            Submissions
          </Button>
          {hasStages && (
            <Button
              onClick={() => setView('leaderboard')}
              aria-current={view === 'leaderboard' ? 'page' : undefined}
            >
              Leaderboard
            </Button>
          )}
        </nav>
      }
    >
      {view === 'list' ? (
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
      ) : (
        <section aria-labelledby="lb-heading">
          <h1 id="lb-heading">
            <bdi>{organization.name}</bdi> — Leaderboard
          </h1>

          <div>
            <label htmlFor="lb-stage-select">Stage</label>
              <select
              id="lb-stage-select"
              value={selectedStageId ?? ''}
              onChange={(e) => setSelectedStageId(e.target.value || null)}
            >
              <option value="">Select a stage…</option>
              {(stages ?? []).map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </div>

          {selectedStageId === null ? (
            <p className="ds-muted">Select a stage to view the leaderboard.</p>
          ) : leaderboardQuery.isLoading ? (
            <Spinner label="Loading leaderboard…" />
          ) : leaderboardQuery.isError ? (
            <StateBlock
              variant="error"
              message="We could not load the leaderboard."
              action={<Button onClick={() => leaderboardQuery.refetch()}>Try again</Button>}
            />
          ) : (leaderboardQuery.data ?? []).length === 0 ? (
            <p className="ds-muted">No submitted scorecards yet for this stage.</p>
          ) : (
            <LeaderboardTable rows={leaderboardQuery.data ?? []} />
          )}
        </section>
      )}
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

/**
 * Ranked leaderboard table. Application identity is masked — labels are
 * ordinal ("Application #1", "Application #2", …) so no applicant data is
 * shown on this screen. Mean and model_max display via `format2` (2dp half-up).
 */
function LeaderboardTable({ rows }: { rows: LeaderboardRow[] }) {
  return (
    <table aria-label="Stage leaderboard">
      <thead>
        <tr>
          <th scope="col">Rank</th>
          <th scope="col">Application</th>
          <th scope="col">Mean / Max</th>
          <th scope="col">Count</th>
          <th scope="col">Spread</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((row, i) => (
          <tr key={row.application_id}>
            <td>{i + 1}</td>
            <td>Application #{i + 1}</td>
            <td>
              {format2(row.mean)} / {format2(row.model_max)}
            </td>
            <td>{row.count}</td>
            <td>
              {format2(row.min)}–{format2(row.max)}
            </td>
            <td>{row.disqualified ? 'Disqualified' : '—'}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
