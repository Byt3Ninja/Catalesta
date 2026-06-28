import { expect, test } from 'vitest'
import type { Stage } from '../schemas/stages'
import { resolveNextStages, validatePipeline } from './stageRouting'

function stage(p: Partial<Stage> & { stage_id: string; order: number }): Stage {
  return {
    name: p.stage_id,
    type: 'review',
    entry_rule: null,
    exit_rule: null,
    next_stage_ids: [],
    depends_on_stage_ids: [],
    parallel_group: null,
    ...p,
  }
}

// ---- resolveNextStages ----

test('linear path resolves the single next stage', () => {
  const stages = [stage({ stage_id: 'a', order: 0, next_stage_ids: ['b'] }), stage({ stage_id: 'b', order: 1 })]
  expect(resolveNextStages(stages, 'a', {})).toEqual(['b'])
})

test('unknown current stage resolves to nothing', () => {
  const stages = [stage({ stage_id: 'a', order: 0, next_stage_ids: ['b'] }), stage({ stage_id: 'b', order: 1 })]
  expect(resolveNextStages(stages, 'zzz', {})).toEqual([])
})

test('conditional branch picks the candidate whose entry rule passes', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, next_stage_ids: ['pass', 'fail'] }),
    stage({ stage_id: 'pass', order: 1, entry_rule: { match: 'all', conditions: [{ field_id: 'decision', operator: 'equals', value: 'yes' }] } }),
    stage({ stage_id: 'fail', order: 2, entry_rule: { match: 'all', conditions: [{ field_id: 'decision', operator: 'not_equals', value: 'yes' }] } }),
  ]
  expect(resolveNextStages(stages, 'a', { decision: 'yes' })).toEqual(['pass'])
  expect(resolveNextStages(stages, 'a', { decision: 'no' })).toEqual(['fail'])
})

test('parallel group fans out to all members together', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, next_stage_ids: ['ref'] }),
    stage({ stage_id: 'ref', order: 1, parallel_group: 'diligence' }),
    stage({ stage_id: 'tech', order: 2, parallel_group: 'diligence' }),
    stage({ stage_id: 'later', order: 3 }),
  ]
  expect(resolveNextStages(stages, 'a', {})).toEqual(['ref', 'tech'])
})

test('a parallel member whose entry rule fails does not activate', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, next_stage_ids: ['ref'] }),
    stage({ stage_id: 'ref', order: 1, parallel_group: 'diligence' }),
    stage({ stage_id: 'tech', order: 2, parallel_group: 'diligence', entry_rule: { match: 'all', conditions: [{ field_id: 'track', operator: 'equals', value: 'deep' }] } }),
  ]
  expect(resolveNextStages(stages, 'a', {})).toEqual(['ref'])
  expect(resolveNextStages(stages, 'a', { track: 'deep' })).toEqual(['ref', 'tech'])
})

test('dependency gating blocks a stage until its dependencies are completed', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, next_stage_ids: ['final'] }),
    stage({ stage_id: 'b', order: 1 }),
    stage({ stage_id: 'final', order: 2, depends_on_stage_ids: ['b'] }),
  ]
  expect(resolveNextStages(stages, 'a', {})).toEqual([])
  expect(resolveNextStages(stages, 'a', { completed_stage_ids: ['b'] })).toEqual(['final'])
})

// ---- validatePipeline ----

test('a valid linear pipeline has no errors', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, next_stage_ids: ['b'] }),
    stage({ stage_id: 'b', order: 1, next_stage_ids: ['c'] }),
    stage({ stage_id: 'c', order: 2 }),
  ]
  expect(validatePipeline(stages)).toEqual({ ok: true, errors: [] })
})

test('detects a routing cycle', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, next_stage_ids: ['b'] }),
    stage({ stage_id: 'b', order: 1, next_stage_ids: ['a'] }),
  ]
  const result = validatePipeline(stages)
  expect(result.ok).toBe(false)
  expect(result.errors.some((e) => e.code === 'cycle')).toBe(true)
})

test('detects an unreachable stage', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, next_stage_ids: ['b'] }),
    stage({ stage_id: 'b', order: 1 }),
    stage({ stage_id: 'orphan', order: 2 }),
  ]
  const result = validatePipeline(stages)
  expect(result.errors.some((e) => e.code === 'unreachable' && e.stage_id === 'orphan')).toBe(true)
})

test('detects a dangling next/dependency reference', () => {
  const stages = [stage({ stage_id: 'a', order: 0, next_stage_ids: ['ghost'], depends_on_stage_ids: ['phantom'] })]
  const result = validatePipeline(stages)
  expect(result.errors.filter((e) => e.code === 'dangling_reference')).toHaveLength(2)
})

test('detects a dependency that is not earlier in order', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, depends_on_stage_ids: ['b'] }),
    stage({ stage_id: 'b', order: 1 }),
  ]
  const result = validatePipeline(stages)
  expect(result.errors.some((e) => e.code === 'dependency_order' && e.stage_id === 'a')).toBe(true)
})

test('rejects a rule using an unknown operator', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, next_stage_ids: ['b'] }),
    stage({
      stage_id: 'b',
      order: 1,
      // operator is intentionally outside the known set (runtime data is untyped)
      entry_rule: { match: 'all', conditions: [{ field_id: 'score', operator: 'greater_than' as never, value: '70' }] },
    }),
  ]
  const result = validatePipeline(stages)
  expect(result.errors.some((e) => e.code === 'unknown_operator' && e.stage_id === 'b')).toBe(true)
})

test('rejects a rule field outside the known field set when provided', () => {
  const stages = [
    stage({ stage_id: 'a', order: 0, next_stage_ids: ['b'] }),
    stage({ stage_id: 'b', order: 1, entry_rule: { match: 'all', conditions: [{ field_id: 'mystery', operator: 'equals', value: 'x' }] } }),
  ]
  expect(validatePipeline(stages, ['score']).errors.some((e) => e.code === 'unknown_field')).toBe(true)
  expect(validatePipeline(stages, ['mystery']).errors.some((e) => e.code === 'unknown_field')).toBe(false)
})
