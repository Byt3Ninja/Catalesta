import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { csrfFetch } from './csrf'
import { jsonResponse } from '../tests/test-utils'

function setCookie(value: string) {
  Object.defineProperty(document, 'cookie', { value, writable: true, configurable: true })
}

beforeEach(() => setCookie(''))
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

test('preflights the csrf cookie when XSRF-TOKEN is absent, then sends the decoded header', async () => {
  const fetchMock = vi
    .spyOn(globalThis, 'fetch')
    .mockImplementationOnce(async () => {
      // Simulate Sanctum setting the (URL-encoded) cookie during preflight.
      setCookie('XSRF-TOKEN=tok%2Bvalue')
      return new Response(null, { status: 204 })
    })
    .mockResolvedValueOnce(jsonResponse({ ok: true }, 200))

  await csrfFetch('/auth/register', { method: 'POST', body: JSON.stringify({}) })

  expect(fetchMock.mock.calls[0][0]).toContain('/sanctum/csrf-cookie')
  const [, init] = fetchMock.mock.calls[1]
  const headers = new Headers(init?.headers)
  expect(headers.get('X-XSRF-TOKEN')).toBe('tok+value') // decoded
  expect(init?.credentials).toBe('include')
})

test('skips the preflight when the cookie is already present', async () => {
  setCookie('XSRF-TOKEN=already')
  const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ ok: true }))

  await csrfFetch('/auth/login', { method: 'POST', body: '{}' })

  expect(fetchMock).toHaveBeenCalledTimes(1)
  expect(fetchMock.mock.calls[0][0]).toContain('/auth/login')
})
