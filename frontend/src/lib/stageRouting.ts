import type { Stage } from '../schemas/stages'
import { KNOWN_OPERATORS, evaluateRule } from './visibility'

/** Participant state key holding the ids of stages already completed. Stage
 *  dependencies are satisfied from this list (authoring-time convention; the
 *  runtime advancement engine is a later slice). */
const COMPLETED_KEY = 'completed_stage_ids'

export interface PipelineError {
  code: 'cycle' | 'unreachable' | 'dangling_reference' | 'dependency_order' | 'unknown_operator' | 'unknown_field'
  message: string
  /** The offending stage, when the error is attributable to one. */
  stage_id?: string
}

function depsSatisfied(s: Stage, state: Record<string, unknown>): boolean {
  if (s.depends_on_stage_ids.length === 0) return true
  const done = state[COMPLETED_KEY]
  const completed = Array.isArray(done) ? done.map(String) : []
  return s.depends_on_stage_ids.every((d) => completed.includes(d))
}

/** A stage may be entered when its dependencies are met and its entry rule passes. */
function canEnter(s: Stage, state: Record<string, unknown>): boolean {
  return depsSatisfied(s, state) && evaluateRule(s.entry_rule, state)
}

/**
 * Resolve the stage(s) a participant advances to from `currentStageId`. Walks the
 * current stage's `next_stage_ids`, keeps candidates whose entry rule passes and
 * whose dependencies are satisfied, and fans a `parallel_group` out to all of its
 * members that can be entered. Pure — no fetch, no React. Returns ids ordered by
 * `order` then id, deduped.
 */
export function resolveNextStages(stages: Stage[], currentStageId: string, state: Record<string, unknown>): string[] {
  const byId = new Map(stages.map((s) => [s.stage_id, s]))
  const current = byId.get(currentStageId)
  if (!current) return []

  const activated = new Map<string, Stage>()
  for (const nid of current.next_stage_ids) {
    const next = byId.get(nid)
    if (!next || !canEnter(next, state)) continue
    activated.set(next.stage_id, next)
    if (next.parallel_group) {
      for (const sib of stages) {
        if (sib.parallel_group === next.parallel_group && canEnter(sib, state)) activated.set(sib.stage_id, sib)
      }
    }
  }

  return [...activated.values()]
    .sort((a, b) => a.order - b.order || a.stage_id.localeCompare(b.stage_id))
    .map((s) => s.stage_id)
}

/** Cycle check over the `next_stage_ids` graph (valid edges only). */
function hasCycle(stages: Stage[], ids: Set<string>): boolean {
  const byId = new Map(stages.map((s) => [s.stage_id, s]))
  const visiting = new Set<string>()
  const done = new Set<string>()

  const walk = (id: string): boolean => {
    if (visiting.has(id)) return true
    if (done.has(id)) return false
    visiting.add(id)
    for (const n of byId.get(id)?.next_stage_ids ?? []) {
      if (ids.has(n) && walk(n)) return true
    }
    visiting.delete(id)
    done.add(id)
    return false
  }
  return stages.some((s) => walk(s.stage_id))
}

/** Stage ids not reachable from the pipeline entry (lowest `order`) via routing. */
function unreachableStages(stages: Stage[]): string[] {
  if (stages.length === 0) return []
  const byId = new Map(stages.map((s) => [s.stage_id, s]))
  const entry = stages.reduce((min, s) => (s.order < min.order ? s : min), stages[0])

  const seen = new Set<string>([entry.stage_id])
  const queue = [entry.stage_id]
  while (queue.length) {
    const id = queue.shift()!
    for (const n of byId.get(id)?.next_stage_ids ?? []) {
      if (byId.has(n) && !seen.has(n)) {
        seen.add(n)
        queue.push(n)
      }
    }
  }
  return stages.filter((s) => !seen.has(s.stage_id)).map((s) => s.stage_id)
}

/**
 * Static validation of a pipeline's stage graph. Detects routing cycles,
 * unreachable stages, dangling next/dependency references, dependencies that are
 * not earlier in order, and rules using unknown operators. When `knownFieldIds`
 * is supplied, also flags conditions referencing fields outside that set. Pure.
 */
export function validatePipeline(stages: Stage[], knownFieldIds?: string[]): { ok: boolean; errors: PipelineError[] } {
  const errors: PipelineError[] = []
  const ids = new Set(stages.map((s) => s.stage_id))
  const byId = new Map(stages.map((s) => [s.stage_id, s]))

  for (const s of stages) {
    for (const n of s.next_stage_ids) {
      if (!ids.has(n)) errors.push({ code: 'dangling_reference', stage_id: s.stage_id, message: `${s.stage_id} routes to unknown stage ${n}` })
    }
    for (const d of s.depends_on_stage_ids) {
      if (!ids.has(d)) {
        errors.push({ code: 'dangling_reference', stage_id: s.stage_id, message: `${s.stage_id} depends on unknown stage ${d}` })
        continue
      }
      const dep = byId.get(d)!
      if (dep.order >= s.order) errors.push({ code: 'dependency_order', stage_id: s.stage_id, message: `${s.stage_id} depends on ${d}, which is not earlier in the pipeline` })
    }
    for (const rule of [s.entry_rule, s.exit_rule]) {
      if (!rule) continue
      for (const c of rule.conditions) {
        if (!KNOWN_OPERATORS.includes(c.operator)) errors.push({ code: 'unknown_operator', stage_id: s.stage_id, message: `${s.stage_id} uses unknown operator "${c.operator}"` })
        if (knownFieldIds && !knownFieldIds.includes(c.field_id)) errors.push({ code: 'unknown_field', stage_id: s.stage_id, message: `${s.stage_id} references unknown field "${c.field_id}"` })
      }
    }
  }

  if (hasCycle(stages, ids)) errors.push({ code: 'cycle', message: 'pipeline routing contains a cycle' })
  for (const id of unreachableStages(stages)) errors.push({ code: 'unreachable', stage_id: id, message: `${id} is unreachable from the pipeline start` })

  return { ok: errors.length === 0, errors }
}
