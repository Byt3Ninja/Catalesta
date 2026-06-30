import { describe, expect, it } from 'vitest'
import { deriveProgramSummary, groupSummariesByProgram } from './programSummary'
import type { Cohort } from '../schemas/cohorts'

function cohort(partial: Partial<Cohort>): Cohort {
  return {
    id: 'c', organization_id: 'o', program_id: 'p', name: 'C', slug: 'c',
    status: 'draft', capacity: null, enrollment_opens_at: null, enrollment_closes_at: null,
    starts_at: null, ends_at: null, timeline: null, created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z', ...partial,
  }
}

describe('deriveProgramSummary', () => {
  it('returns empty summary for no cohorts', () => {
    expect(deriveProgramSummary([])).toEqual({
      cohortCount: 0, dateRange: null, capacity: null, submissions: 0, activeCohortStatus: null,
    })
  })

  it('spans earliest start to latest end, ignoring nulls', () => {
    const s = deriveProgramSummary([
      cohort({ starts_at: '2025-03-01T00:00:00Z', ends_at: '2025-06-01T00:00:00Z' }),
      cohort({ starts_at: '2025-01-01T00:00:00Z', ends_at: null }),
      cohort({ starts_at: null, ends_at: '2025-09-01T00:00:00Z' }),
    ])
    expect(s.dateRange).toEqual({ start: '2025-01-01T00:00:00Z', end: '2025-09-01T00:00:00Z' })
  })

  it('sums capacities (null when all null) and submissions', () => {
    const s = deriveProgramSummary([
      cohort({ capacity: 20, submissions_count: 5 }),
      cohort({ capacity: 30, submissions_count: 7 }),
      cohort({ capacity: null }),
    ])
    expect(s.capacity).toBe(50)
    expect(s.submissions).toBe(12)
  })

  it('capacity is null when no cohort has capacity', () => {
    expect(deriveProgramSummary([cohort({}), cohort({})]).capacity).toBeNull()
  })

  it('activeCohortStatus prefers latest open cohort', () => {
    const s = deriveProgramSummary([
      cohort({ status: 'completed', created_at: '2025-01-01T00:00:00Z' }),
      cohort({ status: 'open', created_at: '2025-02-01T00:00:00Z' }),
      cohort({ status: 'open', created_at: '2025-03-01T00:00:00Z' }),
    ])
    expect(s.activeCohortStatus).toBe('open')
  })

  it('falls back to most recent cohort status when none open', () => {
    const s = deriveProgramSummary([
      cohort({ status: 'draft', created_at: '2025-01-01T00:00:00Z' }),
      cohort({ status: 'completed', created_at: '2025-05-01T00:00:00Z' }),
    ])
    expect(s.activeCohortStatus).toBe('completed')
  })

  it('groups by program_id', () => {
    const g = groupSummariesByProgram([
      cohort({ program_id: 'p1', submissions_count: 1 }),
      cohort({ program_id: 'p2', submissions_count: 2 }),
      cohort({ program_id: 'p1', submissions_count: 3 }),
    ])
    expect(g.p1.submissions).toBe(4)
    expect(g.p2.submissions).toBe(2)
  })
})
