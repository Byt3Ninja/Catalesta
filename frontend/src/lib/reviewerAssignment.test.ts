import { describe, expect, it } from 'vitest'
import { assign } from './reviewerAssignment'

describe('assign', () => {
  const apps  = ['app-1', 'app-2', 'app-3', 'app-4']
  const panel = ['A', 'B', 'C', 'D']

  it('gives each app exactly perApp distinct reviewers', () => {
    const result = assign(apps, panel, 2)
    for (const row of result) {
      expect(row.reviewer_ids).toHaveLength(2)
      // distinct within the app
      expect(new Set(row.reviewer_ids).size).toBe(2)
    }
  })

  it('deterministic round-robin produces correct reviewer sets', () => {
    const result = assign(apps, panel, 2)
    // rotating pointer: 0→A,B / 2→C,D / 4%4→A,B / 6%4→C,D
    expect(result[0].reviewer_ids).toEqual(['A', 'B'])
    expect(result[1].reviewer_ids).toEqual(['C', 'D'])
    expect(result[2].reviewer_ids).toEqual(['A', 'B'])
    expect(result[3].reviewer_ids).toEqual(['C', 'D'])
  })

  it('produces balanced load — each reviewer count within ±1', () => {
    const result = assign(apps, panel, 2)
    const counts: Record<string, number> = {}
    for (const row of result) {
      for (const r of row.reviewer_ids) {
        counts[r] = (counts[r] ?? 0) + 1
      }
    }
    const values = Object.values(counts)
    expect(Math.max(...values) - Math.min(...values)).toBeLessThanOrEqual(1)
  })

  it('clamps perApp to panel length when perApp exceeds panel size', () => {
    const smallPanel = ['A', 'B']
    const result = assign(['app-1'], smallPanel, 5) // 5 > 2
    expect(result[0].reviewer_ids).toHaveLength(2)
    expect(result[0].reviewer_ids).toEqual(['A', 'B'])
  })

  it('returns empty reviewer_ids for each app when panel is empty', () => {
    const result = assign(apps, [], 2)
    for (const row of result) {
      expect(row.reviewer_ids).toEqual([])
    }
  })

  it('returns empty array when applicationIds is empty', () => {
    const result = assign([], panel, 2)
    expect(result).toEqual([])
  })

  it('preserves application_id in each result row', () => {
    const result = assign(apps, panel, 2)
    expect(result.map(r => r.application_id)).toEqual(apps)
  })
})
