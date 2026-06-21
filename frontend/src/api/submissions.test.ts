import { afterEach, expect, test, vi } from 'vitest'
import { getFunnel, getSubmission, listSubmissions } from './submissions'
import { jsonResponse } from '../tests/test-utils'

const ROW = {
  reference_number: '01J0SUB',
  cohort_id: '01J0COH',
  submitted_at: '2026-06-21T10:00:00+00:00',
}

afterEach(() => {
  vi.restoreAllMocks()
})

test('listSubmissions returns the rows (empty when none)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: [], meta: { total: 0 } }),
  )
  await expect(listSubmissions('01J0COH')).resolves.toEqual([])
})

test('listSubmissions parses a row list', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: [ROW], meta: { total: 1 } }),
  )
  await expect(listSubmissions('01J0COH')).resolves.toHaveLength(1)
})

test('listSubmissions throws on a non-ok response', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(listSubmissions('01J0COH')).rejects.toThrow(/submissions list failed: 401/)
})

test('getFunnel parses the three counts', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: { viewed: 5, started: 3, submitted: 2 } }),
  )
  await expect(getFunnel('01J0COH')).resolves.toEqual({ viewed: 5, started: 3, submitted: 2 })
})

test('getSubmission parses the snapshot detail', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: { ...ROW, snapshot: { answers: { name: 'Omar' } } } }),
  )
  await expect(getSubmission('01J0COH', '01J0SUB')).resolves.toMatchObject({
    reference_number: '01J0SUB',
    snapshot: { answers: { name: 'Omar' } },
  })
})

test('rejects a malformed funnel payload', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { viewed: 'x' } }))
  await expect(getFunnel('01J0COH')).rejects.toThrow()
})
