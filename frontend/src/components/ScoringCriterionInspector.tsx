import { Field } from './Field'
import { Button } from './Button'
import type { ScoringCriterion } from '../schemas/assessments'

/** Configures the selected scoring criterion: label, max points, and optional
 *  guidance descriptors. Pure — every edit is emitted via `onChange` as a
 *  partial patch; the builder applies it to the draft and autosaves. No
 *  rule/dependency/routing editors (criteria are flat and have none). */
export function ScoringCriterionInspector({ criterion, readOnly = false, onChange }: {
  criterion: ScoringCriterion
  readOnly?: boolean
  onChange: (patch: Partial<ScoringCriterion>) => void
}) {
  const descriptors = criterion.descriptors ?? []

  function addDescriptor() {
    onChange({ descriptors: [...descriptors, ''] })
  }

  function updateDescriptor(i: number, value: string) {
    onChange({ descriptors: descriptors.map((d, j) => (j === i ? value : d)) })
  }

  function removeDescriptor(i: number) {
    const next = descriptors.filter((_, j) => j !== i)
    onChange({ descriptors: next.length > 0 ? next : null })
  }

  return (
    <div className="grid gap-4 text-sm">
      <Field
        label="Criterion label"
        name="criterion-label"
        value={criterion.label}
        disabled={readOnly}
        onChange={(e) => onChange({ label: e.target.value })}
      />

      <Field
        label="Max points"
        name="criterion-max-points"
        type="number"
        min={0}
        value={criterion.max_points}
        disabled={readOnly}
        onChange={(e) => onChange({ max_points: Number(e.target.value) })}
      />

      <fieldset className="grid gap-2 rounded-md border border-border p-2">
        <legend className="px-1 text-xs text-muted-foreground">Guidance descriptors</legend>
        {descriptors.map((d, i) => (
          <span key={i} className="flex items-center gap-1">
            <input
              aria-label={`Descriptor ${i + 1}`}
              value={d}
              disabled={readOnly}
              onChange={(e) => updateDescriptor(i, e.target.value)}
              className="flex-1 rounded-md border border-input bg-card p-1"
            />
            <Button
              variant="secondary"
              aria-label={`Remove descriptor ${i + 1}`}
              disabled={readOnly}
              onClick={() => removeDescriptor(i)}
            >
              ✕
            </Button>
          </span>
        ))}
        <Button variant="secondary" disabled={readOnly} onClick={addDescriptor}>
          Add descriptor
        </Button>
      </fieldset>
    </div>
  )
}
