import { afterEach, expect, test, vi } from 'vitest'
import { createOrganization, listOrganizations } from './organizations'
import { jsonResponse } from '../tests/test-utils'

const ORG = {
  id: '01J0ORG',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: '2026-06-20T10:00:00+00:00',
  updated_at: '2026-06-20T10:00:00+00:00',
}

afterEach(() => {
  vi.restoreAllMocks()
})

test('listOrganizations returns the data array (empty when no org)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [] }))
  await expect(listOrganizations()).resolves.toEqual([])
})

test('listOrganizations parses an org list', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [ORG] }))
  await expect(listOrganizations()).resolves.toHaveLength(1)
})

test('createOrganization returns the created org on 201', async () => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: ORG }, 201))
  await expect(createOrganization('Acme Incubator')).resolves.toMatchObject({
    slug: 'acme-incubator',
  })
})

test('createOrganization maps 422 → DUPLICATE_NAME carrying the message', async () => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      {
        error: {
          code: 'VALIDATION_ERROR',
          details: { name: ['An organization with a similar name already exists.'] },
        },
      },
      422,
    ),
  )
  await expect(createOrganization('Acme Incubator')).rejects.toMatchObject({
    name: 'CreateOrgError',
    code: 'DUPLICATE_NAME',
    message: 'An organization with a similar name already exists.',
  })
})

test('createOrganization 422 without a name detail does NOT become DUPLICATE_NAME', async () => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse(
      {
        error: {
          code: 'VALIDATION_ERROR',
          details: { branding: ['The branding field is invalid.'] },
        },
      },
      422,
    ),
  )
  await expect(createOrganization('Acme Incubator')).rejects.toMatchObject({
    name: 'CreateOrgError',
    code: 'UNKNOWN',
    message: 'The branding field is invalid.',
  })
})

test('createOrganization maps 401 → UNAUTHENTICATED', async () => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(createOrganization('X')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})
