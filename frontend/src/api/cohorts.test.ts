import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { createCohort, getCohort, listCohorts, openCohort, updateCohort } from './cohorts'
import { jsonResponse } from '../tests/test-utils'

const COHORT_FIXTURE = {
  id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026',
  slug: 'spring-2026', status: 'draft' as const, capacity: null,
  enrollment_opens_at: null, enrollment_closes_at: null, starts_at: null, ends_at: null,
  timeline: null, created_at: '2026-06-20T10:00:00+00:00', updated_at: '2026-06-20T10:00:00+00:00',
}

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

// create/update route through csrfFetch — pre-seed the XSRF cookie so the
// preflight is skipped and a single fetch mock stays aligned.
beforeEach(() => {
  Object.defineProperty(document, 'cookie', {
    value: 'XSRF-TOKEN=t',
    writable: true,
    configurable: true,
  })
})

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

test('getCohort returns the cohort on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: COHORT }))
  await expect(getCohort('01J0COH')).resolves.toMatchObject({ slug: 'spring-2026' })
})

test('getCohort maps 404 → NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(getCohort('missing')).rejects.toMatchObject({
    name: 'GetCohortError',
    code: 'NOT_FOUND',
  })
})

test('getCohort maps 401 → UNAUTHENTICATED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(getCohort('01J0COH')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})

test('createCohort POSTs under the program and returns the draft on 201', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, status: 'draft' } }, 201))
  await expect(createCohort('01J0PROG', { name: 'Spring 2026' })).resolves.toMatchObject({
    status: 'draft',
  })
  const [url, init] = fetchSpy.mock.calls[0]
  expect(String(url)).toContain('/programs/01J0PROG/cohorts')
  expect(init?.method).toBe('POST')
  expect(JSON.parse((init?.body as string) ?? '{}')).toEqual({ name: 'Spring 2026' })
})

test('createCohort maps 422 → VALIDATION with the first field message', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
      422,
    ),
  )
  await expect(createCohort('01J0PROG', { name: '' })).rejects.toMatchObject({
    name: 'CreateCohortError',
    code: 'VALIDATION',
    message: 'The name field is required.',
  })
})

test('createCohort maps 403 → FORBIDDEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 403 }))
  await expect(createCohort('01J0PROG', { name: 'x' })).rejects.toMatchObject({ code: 'FORBIDDEN' })
})

test('updateCohort PATCHes the metadata and returns the cohort on 200', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: { ...COHORT, capacity: 50 } }))
  await expect(
    updateCohort('01J0COH', { name: 'Spring 2026', capacity: 50, enrollment_opens_at: '2026-07-01' }),
  ).resolves.toMatchObject({ capacity: 50 })
  const init = fetchSpy.mock.calls[0][1]
  expect(init?.method).toBe('PATCH')
  expect(JSON.parse((init?.body as string) ?? '{}')).toEqual({
    name: 'Spring 2026',
    capacity: 50,
    enrollment_opens_at: '2026-07-01',
  })
})

test('updateCohort maps 422 → VALIDATION', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { ends_at: ['The ends at must be a date after starts at.'] } } },
      422,
    ),
  )
  await expect(updateCohort('01J0COH', { ends_at: '2020-01-01' })).rejects.toMatchObject({
    name: 'UpdateCohortError',
    code: 'VALIDATION',
  })
})

test('openCohort: 200 returns the opened cohort', async () => {
  const opened = { ...COHORT_FIXTURE, status: 'open' }
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: opened }))
  const result = await openCohort('coh_1')
  expect(result.status).toBe('open')
  const init = (globalThis.fetch as unknown as { mock: { calls: [string, RequestInit][] } }).mock.calls[0][1]
  expect(init.method).toBe('POST')
})

test('openCohort: 409 throws CONFLICT', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 409 }))
  await expect(openCohort('coh_1')).rejects.toMatchObject({ code: 'CONFLICT' })
})
