import { useState } from 'react'
import { Field } from './Field'
import { Button } from './Button'
import type { ScoringCriterion } from '../schemas/assessments'

/** Monotonic counter for stable descriptor row IDs (module-level, never resets). */
let _descSeq = 0
function nextDescId() { return ++_descSeq }

interface DescriptorRow { id: number; text: string }

/** Configures the selected scoring criterion: label, max points, and optional
 *  guidance descriptors. Pure — every edit is emitted via `onChange` as a
 *  partial patch; the builder applies it to the draft and autosaves. No
 *  rule/dependency/routing editors (criteria are flat and have none). */
export function ScoringCriterionInspector({ criterion, readOnly = false, onChange }: {
  criterion: ScoringCriterion
  readOnly?: boolean
  onChange: (patch: Partial<ScoringCriterion>) => void
}) {
  // Local row state with stable IDs — avoids React reconciling survivors against
  // wrong DOM nodes (stale input values) when a middle descriptor is removed.
  const [rows, setRows] = useState<DescriptorRow[]>(() =>
    (criterion.descriptors ?? []).map((text) => ({ id: nextDescId(), text }))
  )
  // Render-time reset keyed on criterion_id (same "adjust state when source changes"
  // pattern as the builder's version-id seeding). Do NOT use useEffect+setState.
  const [seededCritId, setSeededCritId] = useState(criterion.criterion_id)
  if (criterion.criterion_id !== seededCritId) {
    setSeededCritId(criterion.criterion_id)
    setRows((criterion.descriptors ?? []).map((text) => ({ id: nextDescId(), text })))
  }

  function emitRows(next: DescriptorRow[]) {
    const texts = next.map((r) => r.text)
    onChange({ descriptors: texts.length > 0 ? texts : null })
  }

  function addDescriptor() {
    const next = [...rows, { id: nextDescId(), text: '' }]
    setRows(next)
    emitRows(next)
  }

  function updateDescriptor(id: number, value: string) {
    const next = rows.map((r) => (r.id === id ? { ...r, text: value } : r))
    setRows(next)
    emitRows(next)
  }

  function removeDescriptor(id: number) {
    const next = rows.filter((r) => r.id !== id)
    setRows(next)
    emitRows(next)
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
        {rows.map((row, i) => (
          <span key={row.id} className="flex items-center gap-1">
            <input
              aria-label={`Descriptor ${i + 1}`}
              value={row.text}
              disabled={readOnly}
              onChange={(e) => updateDescriptor(row.id, e.target.value)}
              className="flex-1 rounded-md border border-input bg-card p-1"
            />
            <Button
              variant="secondary"
              aria-label={`Remove descriptor ${i + 1}`}
              disabled={readOnly}
              onClick={() => removeDescriptor(row.id)}
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
