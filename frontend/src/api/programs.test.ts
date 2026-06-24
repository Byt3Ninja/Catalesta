import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import {
  cloneProgram,
  createProgram,
  getProgram,
  listPrograms,
  publishProgram,
  updateProgram,
} from './programs'
import { jsonResponse } from '../tests/test-utils'

const PROGRAM = {
  id: '01J0PROG',
  name: 'Spring Accelerator',
  slug: 'spring-accelerator',
  status: 'draft',
  description: null,
  settings: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

function setCookie(value: string) {
  Object.defineProperty(document, 'cookie', { value, writable: true, configurable: true })
}

// createProgram + publishProgram now route through csrfFetch (PR #26 follow-up).
// Pre-set the XSRF-TOKEN cookie so the preflight is skipped and tests can assert
// against a single fetch mock — same pattern as organizations.test.ts.
beforeEach(() => setCookie('XSRF-TOKEN=t'))
afterEach(() => {
  vi.restoreAllMocks()
})

test('listPrograms returns the data array (empty when none)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [] }))
  await expect(listPrograms()).resolves.toEqual([])
})

test('listPrograms parses a program list', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [PROGRAM] }))
  await expect(listPrograms()).resolves.toHaveLength(1)
})

test('createProgram returns the created draft on 201', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: PROGRAM }, 201))
  await expect(createProgram('Spring Accelerator')).resolves.toMatchObject({
    slug: 'spring-accelerator',
    status: 'draft',
  })
})

test('createProgram sends X-XSRF-TOKEN + optional description when provided', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: PROGRAM }, 201))
  await createProgram('Spring Accelerator', 'Cohort for seed startups')
  const init = fetchSpy.mock.calls[0][1]
  const headers = new Headers(init?.headers)
  expect(headers.get('X-XSRF-TOKEN')).toBe('t')
  const body = JSON.parse((init?.body as string) ?? '{}')
  expect(body).toEqual({ name: 'Spring Accelerator', description: 'Cohort for seed startups' })
})

test('createProgram maps 422 → VALIDATION carrying the first field message', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
      422,
    ),
  )
  await expect(createProgram('')).rejects.toMatchObject({
    name: 'CreateProgramError',
    code: 'VALIDATION',
    message: 'The name field is required.',
  })
})

test('createProgram maps 401 → UNAUTHENTICATED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(createProgram('X')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})

test('publishProgram returns the published program on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: { ...PROGRAM, status: 'published' } }),
  )
  await expect(publishProgram('01J0PROG')).resolves.toMatchObject({ status: 'published' })
})

test('publishProgram maps 403 → FORBIDDEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 403 }))
  await expect(publishProgram('01J0PROG')).rejects.toMatchObject({
    name: 'PublishProgramError',
    code: 'FORBIDDEN',
  })
})

test('publishProgram maps 404 → NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(publishProgram('01J0PROG')).rejects.toMatchObject({ code: 'NOT_FOUND' })
})

test('publishProgram maps 401 → UNAUTHENTICATED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(publishProgram('01J0PROG')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})

test('getProgram returns the program on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: PROGRAM }))
  await expect(getProgram('01J0PROG')).resolves.toMatchObject({ slug: 'spring-accelerator' })
})

test('getProgram maps 404 → NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(getProgram('missing')).rejects.toMatchObject({
    name: 'GetProgramError',
    code: 'NOT_FOUND',
  })
})

test('getProgram maps 401 → UNAUTHENTICATED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(getProgram('01J0PROG')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})

test('updateProgram PATCHes name/description and returns the program on 200', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: { ...PROGRAM, name: 'Renamed' } }))
  await expect(
    updateProgram('01J0PROG', { name: 'Renamed', description: null }),
  ).resolves.toMatchObject({ name: 'Renamed' })
  const init = fetchSpy.mock.calls[0][1]
  expect(init?.method).toBe('PATCH')
  expect(JSON.parse((init?.body as string) ?? '{}')).toEqual({ name: 'Renamed', description: null })
})

test('updateProgram maps 422 → VALIDATION with the first field message', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
      422,
    ),
  )
  await expect(updateProgram('01J0PROG', { name: '' })).rejects.toMatchObject({
    name: 'UpdateProgramError',
    code: 'VALIDATION',
    message: 'The name field is required.',
  })
})

test('updateProgram maps 403 → FORBIDDEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 403 }))
  await expect(updateProgram('01J0PROG', { name: 'x' })).rejects.toMatchObject({ code: 'FORBIDDEN' })
})

test('cloneProgram POSTs the name and returns the new draft on 201', async () => {
  const fetchSpy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ data: { ...PROGRAM, id: '01J0NEW', name: 'Copy' } }, 201))
  await expect(cloneProgram('01J0PROG', 'Copy')).resolves.toMatchObject({ id: '01J0NEW' })
  const init = fetchSpy.mock.calls[0][1]
  expect(init?.method).toBe('POST')
  expect(JSON.parse((init?.body as string) ?? '{}')).toEqual({ name: 'Copy' })
})

test('cloneProgram maps 403 → FORBIDDEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 403 }))
  await expect(cloneProgram('01J0PROG', 'Copy')).rejects.toMatchObject({
    name: 'CloneProgramError',
    code: 'FORBIDDEN',
  })
})
