import { Button } from './Button'
import { StateBlock } from './StateBlock'
import { STAGE_TYPE_LABEL } from './stageTypeLabels'
import type { Stage } from '../schemas/stages'

/** Ordered, reorderable list of pipeline stages. Pure presentational — all state
 *  lives in the builder page; reorder uses native up/down buttons (no DnD lib,
 *  matching the 2b form builder). Parallel-group membership is shown as a badge;
 *  it is configured in the inspector (Task 5). */
export function StagePipelineCanvas({
  stages, selectedId, readOnly, onSelect, onMove, onRemove,
}: {
  stages: Stage[]
  selectedId: string | null
  readOnly: boolean
  onSelect: (id: string) => void
  onMove: (index: number, dir: -1 | 1) => void
  onRemove: (id: string) => void
}) {
  if (stages.length === 0) {
    return <StateBlock variant="empty" message="No stages yet. Add one from the palette." />
  }
  return (
    <ul className="grid gap-2">
      {stages.map((s, idx) => (
        <li
          key={s.stage_id}
          data-parallel-group={s.parallel_group ?? ''}
          className={`flex items-center justify-between rounded-md border px-3 py-2 ${selectedId === s.stage_id ? 'border-primary bg-accent' : 'border-border'}`}
        >
          <button type="button" className="text-left" disabled={readOnly} onClick={() => onSelect(s.stage_id)}>
            <span className="font-medium"><bdi>{s.name}</bdi></span>
            <span className="ml-2 text-xs text-muted-foreground">{STAGE_TYPE_LABEL[s.type] ?? s.type}</span>
            {s.parallel_group && (
              <span className="ml-2 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">∥ {s.parallel_group}</span>
            )}
          </button>
          <span className="flex gap-1">
            <Button variant="secondary" aria-label={`Move up ${s.name}`} disabled={readOnly || idx === 0} onClick={() => onMove(idx, -1)}>↑</Button>
            <Button variant="secondary" aria-label={`Move down ${s.name}`} disabled={readOnly || idx === stages.length - 1} onClick={() => onMove(idx, 1)}>↓</Button>
            <Button variant="secondary" aria-label={`Remove ${s.name}`} disabled={readOnly} onClick={() => onRemove(s.stage_id)}>✕</Button>
          </span>
        </li>
      ))}
    </ul>
  )
}
