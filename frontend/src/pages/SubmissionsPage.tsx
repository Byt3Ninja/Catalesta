import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getFunnel, listSubmissions } from '../api/submissions'
import { getStageLeaderboard, proposeStageDecisions, commitStageDecisions } from '../api/assessments'
import type { LeaderboardRow } from '../api/assessments'
import type { Decision } from '../schemas/assessments'
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
  // Threshold-assisted decide state. Reset when the stage selection changes.
  const [cutoff, setCutoff] = useState<number>(0)
  const [rowOutcomes, setRowOutcomes] = useState<Map<string, 'advance' | 'reject' | 'waitlist'>>(new Map())
  const [committedDecisions, setCommittedDecisions] = useState<Decision[]>([])

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

  // Propose and commit are user-triggered mutations — never fire at mount.
  const proposeMutation = useMutation({
    mutationFn: ({ stageId, cutoff: c }: { stageId: string; cutoff: number }) =>
      proposeStageDecisions(cohortId, stageId, c),
    onSuccess: (proposals) => {
      const next = new Map<string, 'advance' | 'reject' | 'waitlist'>()
      for (const p of proposals) next.set(p.application_id, p.proposal)
      // Rows with no proposal (e.g. no submitted scorecards) default to 'reject'.
      for (const row of leaderboardQuery.data ?? []) {
        if (!next.has(row.application_id)) next.set(row.application_id, 'reject')
      }
      setRowOutcomes(next)
    },
  })

  const commitMutation = useMutation({
    mutationFn: ({
      stageId,
      decisions,
    }: {
      stageId: string
      decisions: { application_id: string; outcome: 'advance' | 'reject' | 'waitlist' }[]
    }) => commitStageDecisions(cohortId, stageId, decisions),
    onSuccess: (decisions) => setCommittedDecisions(decisions),
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
              onChange={(e) => {
                setSelectedStageId(e.target.value || null)
                // Reset decide state whenever stage changes.
                setRowOutcomes(new Map())
                setCommittedDecisions([])
              }}
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
            <>
              {/* Threshold-assisted decide controls — user-triggered, never auto-fetched. */}
              <div>
                <label htmlFor="lb-cutoff">Cutoff</label>
                <input
                  id="lb-cutoff"
                  type="number"
                  value={cutoff}
                  min={0}
                  onChange={(e) => setCutoff(Number(e.target.value))}
                />
                <Button
                  onClick={() => proposeMutation.mutate({ stageId: selectedStageId!, cutoff })}
                  disabled={proposeMutation.isPending}
                >
                  {proposeMutation.isPending ? 'Proposing…' : 'Propose'}
                </Button>
              </div>

              <LeaderboardTable
                rows={leaderboardQuery.data ?? []}
                rowOutcomes={rowOutcomes.size > 0 ? rowOutcomes : undefined}
                onOutcomeChange={(appId, outcome) =>
                  setRowOutcomes((prev) => new Map(prev).set(appId, outcome))
                }
                committedDecisions={committedDecisions.length > 0 ? committedDecisions : undefined}
              />

              {rowOutcomes.size > 0 && committedDecisions.length === 0 && (
                <Button
                  onClick={() =>
                    commitMutation.mutate({
                      stageId: selectedStageId!,
                      decisions: [...rowOutcomes.entries()].map(([application_id, outcome]) => ({
                        application_id,
                        outcome,
                      })),
                    })
                  }
                  disabled={commitMutation.isPending}
                >
                  {commitMutation.isPending ? 'Committing…' : 'Commit decisions'}
                </Button>
              )}

              {committedDecisions.length > 0 && (
                <p className="ds-muted">
                  {/* `advance` follows the stage's next_stage_ids to route into the next stage
                      (illustrative in MSW; real routing is pipeline-driven server-side). */}
                  Decisions committed.
                </p>
              )}
            </>
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
 *
 * When `rowOutcomes` is provided the table enters decide mode: an Outcome
 * column appears with a per-row <select> (advance/reject/waitlist) seeded from
 * the proposal (overridable). After commit, `committedDecisions` replaces the
 * selects with read-only outcome text.
 */
function LeaderboardTable({
  rows,
  rowOutcomes,
  onOutcomeChange,
  committedDecisions,
}: {
  rows: LeaderboardRow[]
  rowOutcomes?: Map<string, 'advance' | 'reject' | 'waitlist'>
  onOutcomeChange?: (applicationId: string, outcome: 'advance' | 'reject' | 'waitlist') => void
  committedDecisions?: Decision[]
}) {
  const hasDecideMode = rowOutcomes !== undefined

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
          {hasDecideMode && <th scope="col">Outcome</th>}
        </tr>
      </thead>
      <tbody>
        {rows.map((row, i) => {
          const committed = committedDecisions?.find((d) => d.application_id === row.application_id)
          return (
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
              {hasDecideMode && (
                <td>
                  {committed ? (
                    committed.outcome
                  ) : (
                    <select
                      aria-label={`Outcome for Application #${i + 1}`}
                      value={rowOutcomes!.get(row.application_id) ?? 'reject'}
                      onChange={(e) =>
                        onOutcomeChange?.(
                          row.application_id,
                          e.target.value as 'advance' | 'reject' | 'waitlist',
                        )
                      }
                    >
                      <option value="advance">Advance</option>
                      <option value="reject">Reject</option>
                      <option value="waitlist">Waitlist</option>
                    </select>
                  )}
                </td>
              )}
            </tr>
          )
        })}
      </tbody>
    </table>
  )
}
