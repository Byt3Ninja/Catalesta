import { Field } from './Field'
import { Button } from './Button'
import { StageRoutingEditor } from './StageRoutingEditor'
import { stageTypeSchema, type Stage, type StageRule } from '../schemas/stages'
import { visibilityOperatorSchema, type VisibilityCondition } from '../schemas/forms'
import { STAGE_TYPE_LABEL } from './stageTypeLabels'

const OPERATOR_LABEL: Record<string, string> = {
  equals: 'equals', not_equals: 'does not equal', includes: 'includes', is_empty: 'is empty',
}

/** A declarative gating-rule editor over the shared 2b `Condition` shape. The
 *  field reference is a free-text participant-state key (stage gating has no fixed
 *  field registry). Emits `null` when there are no conditions (no gate). */
function StageRuleEditor({ idPrefix, legend, rule, onChange, readOnly = false }: {
  idPrefix: string
  legend: string
  rule: StageRule | null
  onChange: (rule: StageRule | null) => void
  readOnly?: boolean
}) {
  const r = rule ?? { match: 'all' as const, conditions: [] as VisibilityCondition[] }
  const emit = (next: StageRule) => onChange(next.conditions.length === 0 ? null : next)
  const addCondition = () => emit({ ...r, conditions: [...r.conditions, { field_id: '', operator: 'equals', value: '' }] })
  const patch = (i: number, p: Partial<VisibilityCondition>) => emit({ ...r, conditions: r.conditions.map((c, j) => (j === i ? { ...c, ...p } : c)) })
  const removeCondition = (i: number) => emit({ ...r, conditions: r.conditions.filter((_, j) => j !== i) })

  return (
    <fieldset className="grid gap-2 rounded-md border border-border p-2">
      <legend className="px-1 text-xs text-muted-foreground">{legend}</legend>
      {r.conditions.length > 1 && (
        <label className="flex items-center gap-2 text-xs">
          Match
          <select value={r.match} disabled={readOnly} onChange={(e) => emit({ ...r, match: e.target.value as 'all' | 'any' })} className="rounded-md border border-input bg-card p-1">
            <option value="all">all</option>
            <option value="any">any</option>
          </select>
          of:
        </label>
      )}
      {r.conditions.map((c, i) => (
        <span key={i} className="flex flex-wrap items-center gap-1">
          <input aria-label={`${idPrefix} field key ${i + 1}`} placeholder="field key" value={c.field_id} disabled={readOnly} onChange={(e) => patch(i, { field_id: e.target.value })} className="rounded-md border border-input bg-card p-1" />
          <select aria-label={`${idPrefix} operator ${i + 1}`} value={c.operator} disabled={readOnly} onChange={(e) => patch(i, { operator: e.target.value as VisibilityCondition['operator'] })} className="rounded-md border border-input bg-card p-1">
            {visibilityOperatorSchema.options.map((op) => <option key={op} value={op}>{OPERATOR_LABEL[op]}</option>)}
          </select>
          {c.operator !== 'is_empty' && (
            <input aria-label={`${idPrefix} value ${i + 1}`} value={c.value ?? ''} disabled={readOnly} onChange={(e) => patch(i, { value: e.target.value })} className="rounded-md border border-input bg-card p-1" />
          )}
          <Button variant="secondary" aria-label={`Remove ${idPrefix} condition ${i + 1}`} disabled={readOnly} onClick={() => removeCondition(i)}>✕</Button>
        </span>
      ))}
      <Button variant="secondary" disabled={readOnly} onClick={addCondition}>Add condition</Button>
    </fieldset>
  )
}

/** Configures the selected stage: name, type, parallel group, dependencies on
 *  earlier stages, and entry/exit gating rules. Pure — every edit is emitted via
 *  `onChange` as a partial patch; the builder applies it to the draft and autosaves. */
export function StageInspector({ stage, priorStages = [], allStages = [], readOnly = false, onChange }: {
  stage: Stage
  priorStages?: Stage[]
  allStages?: Stage[]
  readOnly?: boolean
  onChange: (patch: Partial<Stage>) => void
}) {
  function toggleDependency(id: string, on: boolean) {
    const next = on ? [...stage.depends_on_stage_ids, id] : stage.depends_on_stage_ids.filter((d) => d !== id)
    onChange({ depends_on_stage_ids: next })
  }

  return (
    <div className="grid gap-4 text-sm">
      <Field label="Stage name" name="stage-name" value={stage.name} disabled={readOnly} onChange={(e) => onChange({ name: e.target.value })} />

      <div className="grid gap-1.5">
        <label htmlFor="stage-type" className="font-medium">Stage type</label>
        <select id="stage-type" value={stage.type} disabled={readOnly} onChange={(e) => onChange({ type: e.target.value as Stage['type'] })} className="rounded-md border border-input bg-card p-2">
          {stageTypeSchema.options.map((t) => <option key={t} value={t}>{STAGE_TYPE_LABEL[t] ?? t}</option>)}
        </select>
      </div>

      <Field label="Parallel group" name="stage-parallel-group" value={stage.parallel_group ?? ''} disabled={readOnly} onChange={(e) => onChange({ parallel_group: e.target.value || null })} />

      <fieldset className="grid gap-1.5 rounded-md border border-border p-2">
        <legend className="px-1 text-xs text-muted-foreground">Depends on</legend>
        {priorStages.length === 0 ? (
          <p className="text-xs text-muted-foreground">No earlier stages to depend on.</p>
        ) : (
          priorStages.map((p) => (
            <label key={p.stage_id} className="flex items-center gap-2">
              <input type="checkbox" checked={stage.depends_on_stage_ids.includes(p.stage_id)} disabled={readOnly} onChange={(e) => toggleDependency(p.stage_id, e.target.checked)} />
              <bdi>{p.name}</bdi>
            </label>
          ))
        )}
      </fieldset>

      <StageRuleEditor idPrefix="Entry" legend="Entry rule — enter this stage when" rule={stage.entry_rule} readOnly={readOnly} onChange={(entry_rule) => onChange({ entry_rule })} />
      <StageRuleEditor idPrefix="Exit" legend="Exit rule — leave this stage when" rule={stage.exit_rule} readOnly={readOnly} onChange={(exit_rule) => onChange({ exit_rule })} />

      <StageRoutingEditor stage={stage} allStages={allStages} readOnly={readOnly} onChange={(next_stage_ids) => onChange({ next_stage_ids })} />
    </div>
  )
}
