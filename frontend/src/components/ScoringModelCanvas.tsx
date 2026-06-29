import { Button } from './Button'
import { StateBlock } from './StateBlock'
import type { ScoringCriterion } from '../schemas/assessments'

/** Ordered, reorderable list of scoring criteria. Pure presentational — all state
 *  lives in the builder page; reorder uses native up/down buttons (no DnD lib,
 *  matching the 2b form builder). Each row shows the criterion label and a
 *  max-points badge. No parallel-group concept (scoring criteria are flat). */
export function ScoringModelCanvas({
  criteria, selectedId, readOnly, onSelect, onMove, onRemove,
}: {
  criteria: ScoringCriterion[]
  selectedId: string | null
  readOnly: boolean
  onSelect: (id: string) => void
  onMove: (index: number, dir: -1 | 1) => void
  onRemove: (id: string) => void
}) {
  if (criteria.length === 0) {
    return <StateBlock variant="empty" message="No criteria yet. Add one from the palette." />
  }
  return (
    <ul className="grid gap-2">
      {criteria.map((c, idx) => (
        <li
          key={c.criterion_id}
          className={`flex items-center justify-between rounded-md border px-3 py-2 ${selectedId === c.criterion_id ? 'border-primary bg-accent' : 'border-border'}`}
        >
          <button type="button" className="text-left" disabled={readOnly} onClick={() => onSelect(c.criterion_id)}>
            <span className="font-medium"><bdi>{c.label}</bdi></span>
            <span className="ml-2 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
              max {c.max_points} pts
            </span>
          </button>
          <span className="flex gap-1">
            <Button variant="secondary" aria-label={`Move up ${c.label}`} disabled={readOnly || idx === 0} onClick={() => onMove(idx, -1)}>↑</Button>
            <Button variant="secondary" aria-label={`Move down ${c.label}`} disabled={readOnly || idx === criteria.length - 1} onClick={() => onMove(idx, 1)}>↓</Button>
            <Button variant="secondary" aria-label={`Remove ${c.label}`} disabled={readOnly} onClick={() => onRemove(c.criterion_id)}>✕</Button>
          </span>
        </li>
      ))}
    </ul>
  )
}
