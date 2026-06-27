import { afterEach, expect, test, vi } from 'vitest'
import { listNotifications, markNotificationRead } from './notifications'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

const ITEM = {
  id: 'n1', type: 'action', title: 'Review applications', body: '4 are overdue.',
  created_at: '2026-06-26T00:00:00Z', read_at: null, href: '/preview/applicants',
}

test('listNotifications parses the data array', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [ITEM] }))
  const result = await listNotifications()
  expect(result).toHaveLength(1)
  expect(result[0].id).toBe('n1')
})

test('listNotifications rejects a malformed payload', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [{ id: 'x' }] }))
  await expect(listNotifications()).rejects.toThrow()
})

test('markNotificationRead POSTs and resolves on ok', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 }))
  await markNotificationRead('n1')
  expect(spy).toHaveBeenCalledWith(expect.stringContaining('/notifications/n1/read'), expect.objectContaining({ method: 'POST' }))
})
