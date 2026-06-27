import { afterEach, expect, test, vi } from 'vitest'
import { getConsents, setConsent } from './consent'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

test('getConsents parses the data array', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [{ category: 'profile', granted: false }] }))
  const result = await getConsents()
  expect(result[0]).toEqual({ category: 'profile', granted: false })
})

test('setConsent POSTs the category and flag', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 }))
  await setConsent('profile', true)
  const [, init] = spy.mock.calls[0]
  expect(init?.method).toBe('POST')
  expect(JSON.parse(String(init?.body))).toEqual({ category: 'profile', granted: true })
})
