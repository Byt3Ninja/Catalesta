import { validatePipeline } from '../lib/stageRouting'
import type { Stage } from '../schemas/stages'

// Routing-relevant validation surfaced inline; rule-operator/field errors belong to the rule editor.
const ROUTING_ERROR_CODES: readonly string[] = ['cycle', 'unreachable', 'dangling_reference', 'dependency_order']

/**
 * Edits a stage's outbound `next_stage_ids`. Candidates that would introduce a
 * routing cycle are disabled (checked by running `validatePipeline` on the trial
 * edge set — same engine as Task 2). Each edge's gating condition is the target
 * stage's entry rule (the model the routing engine evaluates), shown read-only
 * here and edited in that stage's entry-rule section. Pipeline-level validation
 * errors are surfaced inline. Pure — emits the new id list via `onChange`.
 */
export function StageRoutingEditor({ stage, allStages, readOnly = false, onChange }: {
  stage: Stage
  allStages: Stage[]
  readOnly?: boolean
  onChange: (nextStageIds: string[]) => void
}) {
  const candidates = allStages.filter((s) => s.stage_id !== stage.stage_id)
  const edges = new Set(stage.next_stage_ids)

  function wouldCycle(targetId: string): boolean {
    const trial = allStages.map((s) => (s.stage_id === stage.stage_id ? { ...s, next_stage_ids: [...s.next_stage_ids, targetId] } : s))
    return validatePipeline(trial).errors.some((e) => e.code === 'cycle')
  }

  function toggle(targetId: string, on: boolean) {
    onChange(on ? [...stage.next_stage_ids, targetId] : stage.next_stage_ids.filter((id) => id !== targetId))
  }

  const errors = validatePipeline(allStages).errors.filter((e) => ROUTING_ERROR_CODES.includes(e.code))

  return (
    <fieldset className="grid gap-1.5 rounded-md border border-border p-2">
      <legend className="px-1 text-xs text-muted-foreground">Routing — go to</legend>
      {candidates.length === 0 ? (
        <p className="text-xs text-muted-foreground">Add more stages to route between them.</p>
      ) : (
        candidates.map((c) => {
          const checked = edges.has(c.stage_id)
          const cyclic = !checked && wouldCycle(c.stage_id)
          const gated = (c.entry_rule?.conditions.length ?? 0) > 0
          return (
            <label key={c.stage_id} className="flex flex-wrap items-center gap-2">
              <input type="checkbox" checked={checked} disabled={readOnly || cyclic} onChange={(e) => toggle(c.stage_id, e.target.checked)} />
              <bdi>{c.name}</bdi>
              {gated && <span className="text-xs text-muted-foreground">when its entry rule passes</span>}
              {cyclic && <span className="text-xs text-muted-foreground">— would create a cycle</span>}
            </label>
          )
        })
      )}
      {errors.length > 0 && (
        <div role="alert" className="grid gap-0.5 text-xs text-destructive">
          {errors.map((e, i) => <p key={i}>{e.message}</p>)}
        </div>
      )}
    </fieldset>
  )
}
