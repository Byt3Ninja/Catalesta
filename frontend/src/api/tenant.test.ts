import { afterEach, expect, test, vi } from 'vitest'
import {
  apiFetch,
  getActiveOrganizationId,
  setActiveOrganizationId,
  tenantHeaders,
} from './tenant'

afterEach(() => {
  setActiveOrganizationId(null) // module state leaks across tests
  vi.restoreAllMocks()
})

test('set/get/clear the active organization id', () => {
  expect(getActiveOrganizationId()).toBeNull()
  setActiveOrganizationId('org-1')
  expect(getActiveOrganizationId()).toBe('org-1')
  setActiveOrganizationId(null)
  expect(getActiveOrganizationId()).toBeNull()
})

test('tenantHeaders carries X-Organization-Id only when an org is active', () => {
  expect(tenantHeaders()).toEqual({})
  setActiveOrganizationId('org-1')
  expect(tenantHeaders()).toEqual({ 'X-Organization-Id': 'org-1' })
})

test('apiFetch includes credentials and the tenant header when an org is active', async () => {
  setActiveOrganizationId('org-7')
  const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response('{}', { status: 200 }))

  await apiFetch('/programs')

  const [url, init] = fetchSpy.mock.calls[0]
  expect(String(url)).toContain('/programs')
  expect(init?.credentials).toBe('include')
  expect(new Headers(init?.headers).get('X-Organization-Id')).toBe('org-7')
})

test('apiFetch omits the tenant header when no org is active', async () => {
  const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response('{}', { status: 200 }))

  await apiFetch('/programs')

  const [, init] = fetchSpy.mock.calls[0]
  expect(new Headers(init?.headers).has('X-Organization-Id')).toBe(false)
})
