import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { csrfFetch, CsrfPreflightError } from './csrf'
import { jsonResponse } from '../tests/test-utils'
import { setActiveOrganizationId } from './tenant'

function setCookie(value: string) {
  Object.defineProperty(document, 'cookie', { value, writable: true, configurable: true })
}

beforeEach(() => setCookie(''))
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
  setActiveOrganizationId(null)
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

test('throws CsrfPreflightError when the preflight returns a non-2xx', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 503 }))
  await expect(csrfFetch('/auth/login', { method: 'POST' })).rejects.toMatchObject({
    name: 'CsrfPreflightError',
    status: 503,
  })
})

test('throws CsrfPreflightError when the preflight rejects (network)', async () => {
  vi.spyOn(globalThis, 'fetch').mockRejectedValueOnce(new TypeError('offline'))
  const err = await csrfFetch('/auth/login', { method: 'POST' }).catch((e) => e)
  expect(err).toBeInstanceOf(CsrfPreflightError)
  expect((err as CsrfPreflightError).status).toBe('network')
})

test('does NOT set Content-Type for FormData bodies (browser sets multipart boundary)', async () => {
  setCookie('XSRF-TOKEN=already')
  const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ ok: true }))
  const form = new FormData()
  form.append('x', 'y')

  await csrfFetch('/apply/c/submit', { method: 'POST', body: form })

  const headers = new Headers(fetchMock.mock.calls[0][1]?.headers)
  expect(headers.has('Content-Type')).toBe(false)
})

test('includes X-Organization-Id when an org is active, omits it otherwise', async () => {
  setCookie('XSRF-TOKEN=already')
  const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ ok: true }))

  await csrfFetch('/programs', { method: 'POST', body: '{}' })
  expect(new Headers(fetchMock.mock.calls[0][1]?.headers).has('X-Organization-Id')).toBe(false)

  setActiveOrganizationId('org-9')
  await csrfFetch('/programs', { method: 'POST', body: '{}' })
  expect(new Headers(fetchMock.mock.calls[1][1]?.headers).get('X-Organization-Id')).toBe('org-9')
})
