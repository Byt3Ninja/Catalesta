import { Button } from './Button'
import type { FormField, VisibilityCondition, VisibilityRule } from '../schemas/forms'
import { visibilityOperatorSchema } from '../schemas/forms'

const OPERATOR_LABEL: Record<string, string> = {
  equals: 'equals',
  not_equals: 'does not equal',
  includes: 'includes',
  is_empty: 'is empty',
}

export function VisibilityEditor({
  field,
  priorFields,
  onChange,
}: {
  field: FormField
  priorFields: FormField[]
  onChange: (v: VisibilityRule | undefined) => void
}) {
  const rule = field.visibility ?? { match: 'all' as const, conditions: [] as VisibilityCondition[] }

  function emit(next: VisibilityRule) {
    onChange(next.conditions.length === 0 ? undefined : next)
  }

  function addCondition() {
    if (priorFields.length === 0) return
    emit({
      ...rule,
      conditions: [
        ...rule.conditions,
        { field_id: priorFields[0].id, operator: 'equals', value: '' },
      ],
    })
  }

  function patch(i: number, p: Partial<VisibilityCondition>) {
    emit({ ...rule, conditions: rule.conditions.map((c, j) => (j === i ? { ...c, ...p } : c)) })
  }

  function removeCondition(i: number) {
    emit({ ...rule, conditions: rule.conditions.filter((_, j) => j !== i) })
  }

  if (priorFields.length === 0) {
    return (
      <p className="text-xs text-muted-foreground">
        No earlier fields to depend on — add fields above this one to set visibility rules.
      </p>
    )
  }

  return (
    <fieldset className="grid gap-2 rounded-md border border-border p-2">
      <legend className="px-1 text-xs text-muted-foreground">Show this field when</legend>
      {rule.conditions.length > 1 && (
        <label className="flex items-center gap-2 text-xs">
          Match
          <select
            value={rule.match}
            onChange={(e) => emit({ ...rule, match: e.target.value as 'all' | 'any' })}
            className="rounded-md border border-input bg-card p-1"
          >
            <option value="all">all</option>
            <option value="any">any</option>
          </select>
          of:
        </label>
      )}
      {rule.conditions.map((c, i) => (
        <span key={i} className="flex flex-wrap items-center gap-1">
          <label className="sr-only" htmlFor={`when-${field.id}-${i}`}>
            When field
          </label>
          <select
            id={`when-${field.id}-${i}`}
            value={c.field_id}
            onChange={(e) => patch(i, { field_id: e.target.value })}
            className="rounded-md border border-input bg-card p-1"
          >
            {priorFields.map((pf) => (
              <option key={pf.id} value={pf.id}>
                {pf.label}
              </option>
            ))}
          </select>
          <select
            aria-label="Operator"
            value={c.operator}
            onChange={(e) => patch(i, { operator: e.target.value as VisibilityCondition['operator'] })}
            className="rounded-md border border-input bg-card p-1"
          >
            {visibilityOperatorSchema.options.map((op) => (
              <option key={op} value={op}>
                {OPERATOR_LABEL[op]}
              </option>
            ))}
          </select>
          {c.operator !== 'is_empty' && (
            <input
              aria-label="Value"
              value={c.value ?? ''}
              onChange={(e) => patch(i, { value: e.target.value })}
              className="rounded-md border border-input bg-card p-1"
            />
          )}
          <Button variant="secondary" aria-label={`Remove condition ${i + 1}`} onClick={() => removeCondition(i)}>
            ✕
          </Button>
        </span>
      ))}
      <Button variant="secondary" onClick={addCondition}>
        Add condition
      </Button>
    </fieldset>
  )
}
