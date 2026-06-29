import { describe, expect, it } from 'vitest'
import { scoreCard, aggregate, proposeDecisions } from './scoring'
import type { ScoringCriterion, Scorecard } from '../schemas/assessments'

// ── fixtures ──────────────────────────────────────────────────────────────
const criteria: ScoringCriterion[] = [
  { criterion_id: 'c1', label: 'Innovation', max_points: 10, descriptors: null },
  { criterion_id: 'c2', label: 'Market',     max_points: 15, descriptors: null },
]
// model_max = 25

function makeCard(
  overrides: Partial<Scorecard> & { values: Record<string, number> }
): Scorecard {
  return {
    scorecard_id:     overrides.scorecard_id ?? 'sc-1',
    cohort_id:        'coh-1',
    stage_id:         'stg-1',
    application_id:   overrides.application_id ?? 'app-1',
    reviewer_id:      overrides.reviewer_id ?? 'rev-1',
    model_version_id: 'mv-1',
    values:           overrides.values,
    disqualified:     overrides.disqualified ?? false,
    status:           overrides.status ?? 'submitted',
    submitted_at:     overrides.status === 'draft' ? null : '2026-06-29T00:00:00Z',
  }
}

// ── scoreCard ─────────────────────────────────────────────────────────────
describe('scoreCard', () => {
  it('returns complete:true when all criteria have valid values', () => {
    const result = scoreCard(criteria, { c1: 8, c2: 12 })
    expect(result.earned).toBe(20)
    expect(result.max).toBe(25)
    expect(result.complete).toBe(true)
  })

  it('returns complete:false when a criterion is missing', () => {
    const result = scoreCard(criteria, { c1: 8 }) // c2 missing
    expect(result.earned).toBe(8)
    expect(result.max).toBe(25)
    expect(result.complete).toBe(false)
  })

  it('returns complete:false when a value exceeds max_points', () => {
    const result = scoreCard(criteria, { c1: 11, c2: 12 }) // c1 exceeds 10
    expect(result.complete).toBe(false)
  })

  it('returns complete:false when a value is negative', () => {
    const result = scoreCard(criteria, { c1: -1, c2: 12 })
    expect(result.complete).toBe(false)
  })

  it('handles criteria with descriptors array', () => {
    const withDescriptors: ScoringCriterion[] = [
      { criterion_id: 'x1', label: 'X', max_points: 5, descriptors: ['poor', 'ok', 'good'] },
    ]
    const result = scoreCard(withDescriptors, { x1: 3 })
    expect(result.earned).toBe(3)
    expect(result.max).toBe(5)
    expect(result.complete).toBe(true)
  })
})

// ── aggregate ─────────────────────────────────────────────────────────────
describe('aggregate', () => {
  const card1 = makeCard({ scorecard_id: 'sc-1', reviewer_id: 'rev-1', values: { c1: 8,  c2: 12 } }) // earned 20
  const card2 = makeCard({ scorecard_id: 'sc-2', reviewer_id: 'rev-2', values: { c1: 7,  c2: 10 } }) // earned 17
  const card3 = makeCard({ scorecard_id: 'sc-3', reviewer_id: 'rev-3', values: { c1: 9,  c2: 13 } }) // earned 22
  const draft = makeCard({ scorecard_id: 'sc-4', reviewer_id: 'rev-4', values: { c1: 5,  c2: 8  }, status: 'draft' })

  it('computes mean, model_max, count, min, max over submitted cards', () => {
    const result = aggregate(criteria, [card1, card2, card3])
    expect(result.count).toBe(3)
    expect(result.model_max).toBe(25)
    expect(result.mean).toBe(19.67)   // mean([20,17,22])
    expect(result.min).toBe(17)
    expect(result.max).toBe(22)
    expect(result.disqualified).toBe(false)
  })

  it('excludes draft cards from aggregate', () => {
    const result = aggregate(criteria, [card1, card2, card3, draft])
    expect(result.count).toBe(3)
    expect(result.mean).toBe(19.67)
  })

  it('returns zero-state when all cards are drafts', () => {
    const result = aggregate(criteria, [draft])
    expect(result.count).toBe(0)
    expect(result.mean).toBe(0)
    expect(result.min).toBe(0)
    expect(result.max).toBe(0)
  })

  it('returns zero-state for empty card list', () => {
    const result = aggregate(criteria, [])
    expect(result.count).toBe(0)
    expect(result.mean).toBe(0)
  })

  it('sets disqualified:true when any submitted card is flagged', () => {
    const flagged = makeCard({ scorecard_id: 'sc-5', reviewer_id: 'rev-5', values: { c1: 6, c2: 9 }, disqualified: true })
    const result = aggregate(criteria, [card1, flagged])
    expect(result.disqualified).toBe(true)
  })
})

// ── proposeDecisions ───────────────────────────────────────────────────────
describe('proposeDecisions', () => {
  const cutoff = 18

  it('advances applications at or above the cutoff', () => {
    const rows = [{ application_id: 'app-1', mean: 20, disqualified: false }]
    const decisions = proposeDecisions(rows, cutoff)
    expect(decisions).toEqual([{ application_id: 'app-1', proposal: 'advance' }])
  })

  it('rejects applications below the cutoff', () => {
    const rows = [{ application_id: 'app-2', mean: 15, disqualified: false }]
    const decisions = proposeDecisions(rows, cutoff)
    expect(decisions).toEqual([{ application_id: 'app-2', proposal: 'reject' }])
  })

  it('rejects disqualified applications even when mean is above cutoff', () => {
    const rows = [{ application_id: 'app-3', mean: 22, disqualified: true }]
    const decisions = proposeDecisions(rows, cutoff)
    expect(decisions).toEqual([{ application_id: 'app-3', proposal: 'reject' }])
  })

  it('handles a mixed set correctly', () => {
    const rows = [
      { application_id: 'app-1', mean: 20,   disqualified: false },
      { application_id: 'app-2', mean: 15,   disqualified: false },
      { application_id: 'app-3', mean: 18,   disqualified: false }, // exactly at cutoff → advance
      { application_id: 'app-4', mean: 22,   disqualified: true  }, // disqualified → reject
    ]
    const decisions = proposeDecisions(rows, cutoff)
    expect(decisions).toEqual([
      { application_id: 'app-1', proposal: 'advance' },
      { application_id: 'app-2', proposal: 'reject'  },
      { application_id: 'app-3', proposal: 'advance' },
      { application_id: 'app-4', proposal: 'reject'  },
    ])
  })

  it('returns empty array for empty input', () => {
    expect(proposeDecisions([], cutoff)).toEqual([])
  })
})
