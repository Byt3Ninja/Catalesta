import { afterEach, expect, test, vi } from 'vitest'
import { listCohorts } from './cohorts'
import { jsonResponse } from '../tests/test-utils'

const COHORT = {
  id: '01J0COH',
  organization_id: '01J0ORG',
  program_id: '01J0PROG',
  name: 'Spring 2026',
  slug: 'spring-2026',
  status: 'open',
  capacity: null,
  enrollment_opens_at: null,
  enrollment_closes_at: null,
  starts_at: null,
  ends_at: null,
  timeline: null,
  submissions_count: 3,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

afterEach(() => {
  vi.restoreAllMocks()
})

test('listCohorts returns the data array (empty when none)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [] }))
  await expect(listCohorts()).resolves.toEqual([])
})

test('listCohorts parses a cohort with its submissions_count', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [COHORT] }))
  const cohorts = await listCohorts()
  expect(cohorts).toHaveLength(1)
  expect(cohorts[0]).toMatchObject({ status: 'open', submissions_count: 3 })
})

test('listCohorts throws on a non-ok response', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(listCohorts()).rejects.toThrow(/cohorts list failed: 401/)
})

test('listCohorts rejects a malformed payload', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [{ id: 1 }] }))
  await expect(listCohorts()).rejects.toThrow()
})
