/**
 * scoring.ts — pure scoring engine
 *
 * No fetch, no React.  All monetary/numeric arithmetic delegates to decimal.ts
 * for consistent half-up, 2dp results.
 */

import type { ScoringCriterion, Scorecard } from '../schemas/assessments'
import { sumPoints, mean as decimalMean } from './decimal'

// ── scoreCard ──────────────────────────────────────────────────────────────

/**
 * Compute the earned/max totals and completeness of a single scorecard.
 *
 * complete = every criterion has a finite value in [0, max_points]
 */
export function scoreCard(
  criteria: ScoringCriterion[],
  values: Record<string, number>,
): { earned: number; max: number; complete: boolean } {
  const earnedValues: number[] = []
  let complete = true

  for (const c of criteria) {
    const v = values[c.criterion_id]
    if (v === undefined || !Number.isFinite(v) || v < 0 || v > c.max_points) {
      complete = false
      // still include present valid values in earned total
      if (v !== undefined && Number.isFinite(v) && v >= 0) {
        earnedValues.push(v)
      }
    } else {
      earnedValues.push(v)
    }
  }

  return {
    earned: sumPoints(earnedValues),
    max: sumPoints(criteria.map(c => c.max_points)),
    complete,
  }
}

// ── aggregate ──────────────────────────────────────────────────────────────

/**
 * Aggregate submitted scorecards for a single application.
 *
 * - Only status === 'submitted' cards contribute.
 * - model_max is the constant denominator (sum of max_points), independent of
 *   how many cards exist.
 * - min/max are the spread of per-card earned values (0 when no submitted cards).
 * - disqualified is true when ANY card (submitted) is flagged.
 */
export function aggregate(
  criteria: ScoringCriterion[],
  cards: Scorecard[],
): {
  mean: number
  model_max: number
  count: number
  min: number
  max: number
  disqualified: boolean
} {
  const model_max = sumPoints(criteria.map(c => c.max_points))
  const submitted = cards.filter(c => c.status === 'submitted')

  if (submitted.length === 0) {
    return { mean: 0, model_max, count: 0, min: 0, max: 0, disqualified: false }
  }

  const earnedList = submitted.map(c => scoreCard(criteria, c.values).earned)
  const disqualified = submitted.some(c => c.disqualified)

  return {
    mean: decimalMean(earnedList),
    model_max,
    count: submitted.length,
    min: Math.min(...earnedList),
    max: Math.max(...earnedList),
    disqualified,
  }
}

// ── proposeDecisions ───────────────────────────────────────────────────────

/**
 * Propose advance/reject decisions for a list of application aggregates.
 *
 * Rules (applied in priority order):
 *  1. disqualified → 'reject' (regardless of mean)
 *  2. mean >= cutoff → 'advance'
 *  3. otherwise → 'reject'
 */
export function proposeDecisions(
  rows: { application_id: string; mean: number; disqualified: boolean }[],
  cutoff: number,
): { application_id: string; proposal: 'advance' | 'reject' }[] {
  return rows.map(row => ({
    application_id: row.application_id,
    proposal: row.disqualified || row.mean < cutoff ? 'reject' : 'advance',
  }))
}
