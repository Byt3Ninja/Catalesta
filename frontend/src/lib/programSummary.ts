import type { Cohort } from '../schemas/cohorts'

export interface ProgramSummary {
  cohortCount: number
  dateRange: { start: string; end: string } | null
  capacity: number | null
  submissions: number
  activeCohortStatus: Cohort['status'] | null
}

export function deriveProgramSummary(cohorts: Cohort[]): ProgramSummary {
  const starts = cohorts.map((c) => c.starts_at).filter((d): d is string => d !== null)
  const ends = cohorts.map((c) => c.ends_at).filter((d): d is string => d !== null)
  const capacities = cohorts.map((c) => c.capacity).filter((n): n is number => n !== null)

  const dateRange =
    starts.length > 0 && ends.length > 0
      ? { start: starts.reduce((a, b) => (a < b ? a : b)), end: ends.reduce((a, b) => (a > b ? a : b)) }
      : null

  const open = cohorts
    .filter((c) => c.status === 'open')
    .sort((a, b) => (a.created_at < b.created_at ? 1 : -1))
  const mostRecent = [...cohorts].sort((a, b) => (a.created_at < b.created_at ? 1 : -1))[0]

  return {
    cohortCount: cohorts.length,
    dateRange,
    capacity: capacities.length > 0 ? capacities.reduce((a, b) => a + b, 0) : null,
    submissions: cohorts.reduce((sum, c) => sum + (c.submissions_count ?? 0), 0),
    activeCohortStatus: open[0]?.status ?? mostRecent?.status ?? null,
  }
}

export function groupSummariesByProgram(cohorts: Cohort[]): Record<string, ProgramSummary> {
  const byProgram = new Map<string, Cohort[]>()
  for (const c of cohorts) {
    const list = byProgram.get(c.program_id) ?? []
    list.push(c)
    byProgram.set(c.program_id, list)
  }
  const out: Record<string, ProgramSummary> = {}
  for (const [programId, list] of byProgram) out[programId] = deriveProgramSummary(list)
  return out
}
