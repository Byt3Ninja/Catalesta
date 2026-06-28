import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getStagePipelineVersion } from '../api/stages'
import type { Stage, StageRule } from '../schemas/stages'

const OP_LABEL: Record<string, string> = { equals: '=', not_equals: '≠', includes: 'includes', is_empty: 'is empty' }

/** Render a declarative gating rule as a human-readable clause, or null when there is no gate. */
function describeRule(rule: StageRule | null): string | null {
  if (!rule || rule.conditions.length === 0) return null
  const parts = rule.conditions.map((c) =>
    c.operator === 'is_empty' ? `${c.field_id} is empty` : `${c.field_id} ${OP_LABEL[c.operator] ?? c.operator} ${c.value ?? ''}`.trim(),
  )
  return parts.join(rule.match === 'all' ? ' and ' : ' or ')
}

/** Summarize a stage's outbound routing: each next stage by name, annotated with the
 *  condition (the target's entry rule) that gates entry into it. */
function routingText(stage: Stage, byId: Map<string, Stage>): string {
  if (stage.next_stage_ids.length === 0) return '→ End of pipeline'
  const dests = stage.next_stage_ids.map((id) => {
    const target = byId.get(id)
    const name = target?.name ?? id
    const cond = target ? describeRule(target.entry_rule) : null
    return cond ? `${name} when ${cond}` : name
  })
  return `→ ${dests.join(', ')}`
}

function TypeBadge({ type }: { type: Stage['type'] }) {
  return (
    <span data-status={type} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
      {type}
    </span>
  )
}

function StageCard({ stage, byId }: { stage: Stage; byId: Map<string, Stage> }) {
  const entry = describeRule(stage.entry_rule)
  const exit = describeRule(stage.exit_rule)
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center justify-between gap-2">
        <h3 className="font-medium">{stage.order + 1}. {stage.name}</h3>
        <TypeBadge type={stage.type} />
      </div>
      <dl className="mt-2 grid gap-1 text-sm text-muted-foreground">
        {entry && <div><dt className="inline font-medium">Entry: </dt><dd className="inline">{entry}</dd></div>}
        {exit && <div><dt className="inline font-medium">Exit: </dt><dd className="inline">{exit}</dd></div>}
        {stage.depends_on_stage_ids.length > 0 && (
          <div>
            <dt className="inline font-medium">Depends on: </dt>
            <dd className="inline">{stage.depends_on_stage_ids.map((id) => byId.get(id)?.name ?? id).join(', ')}</dd>
          </div>
        )}
      </dl>
      <p className="mt-2 text-sm text-foreground">{routingText(stage, byId)}</p>
    </div>
  )
}

type Row = { kind: 'single'; stage: Stage } | { kind: 'parallel'; group: string; stages: Stage[] }

/** Group ordered stages into vertical rows; stages sharing a `parallel_group`
 *  collapse into one row (rendered side-by-side) at their first position. */
function buildRows(stages: Stage[]): Row[] {
  const ordered = [...stages].sort((a, b) => a.order - b.order)
  const rows: Row[] = []
  const seen = new Set<string>()
  for (const s of ordered) {
    if (s.parallel_group) {
      if (seen.has(s.parallel_group)) continue
      seen.add(s.parallel_group)
      rows.push({ kind: 'parallel', group: s.parallel_group, stages: ordered.filter((x) => x.parallel_group === s.parallel_group) })
    } else {
      rows.push({ kind: 'single', stage: s })
    }
  }
  return rows
}

export function StagePipelinePreviewPage({ versionId }: { versionId: string }) {
  const versionQuery = useQuery({ queryKey: ['stage-pipeline-version', versionId], queryFn: () => getStagePipelineVersion(versionId), retry: false })
  const v = versionQuery.data
  const byId = new Map((v?.stages ?? []).map((s) => [s.stage_id, s]))
  const rows = v ? buildRows(v.stages) : []

  return (
    <AppShell
      rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><Link href="/programs">Programs</Link></nav>}
      pageHeader={<h1 id="preview-heading" className="text-2xl font-semibold">Participant journey{v ? ` — v${v.version}` : ''}</h1>}
    >
      <section aria-labelledby="preview-heading" className="grid max-w-2xl gap-4">
        {versionQuery.isLoading ? (
          <Spinner label="Loading pipeline…" />
        ) : versionQuery.isError || !v ? (
          <StateBlock variant="error" message="Could not load this pipeline version." />
        ) : rows.length === 0 ? (
          <StateBlock variant="empty" message="This version has no stages yet." />
        ) : (
          rows.map((row) =>
            row.kind === 'single' ? (
              <StageCard key={row.stage.stage_id} stage={row.stage} byId={byId} />
            ) : (
              <div key={`grp-${row.group}`} className="rounded-lg border border-dashed border-border p-3">
                <p className="mb-2 text-xs font-medium text-muted-foreground">Parallel — {row.group}</p>
                <div className="grid gap-3 sm:grid-cols-2">
                  {row.stages.map((s) => <StageCard key={s.stage_id} stage={s} byId={byId} />)}
                </div>
              </div>
            ),
          )
        )}
      </section>
    </AppShell>
  )
}
