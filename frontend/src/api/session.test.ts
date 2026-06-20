import { afterEach, expect, test, vi } from 'vitest'
import { beginLogin, completeLogin, getSession } from './session'
import { SessionError } from '../schemas/session'
import { jsonResponse } from '../tests/test-utils'

const USER = {
  id: 'user-1',
  startup_gate_subject_id: 'sub-1',
  email: 'op@example.com',
  display_name: 'Operator',
}

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

test('getSession parses the current user on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ user: USER }))
  await expect(getSession()).resolves.toMatchObject({ startup_gate_subject_id: 'sub-1' })
})

test('getSession throws a typed UNAUTHENTICATED error on 401', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(getSession()).rejects.toMatchObject({
    name: 'SessionError',
    code: 'UNAUTHENTICATED',
  })
})

test('beginLogin redirects to the authorization_url', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ authorization_url: 'https://idp.example/authorize?x=1' }),
  )
  const assign = vi.fn()
  vi.stubGlobal('location', { ...window.location, assign })

  await beginLogin()
  expect(assign).toHaveBeenCalledWith('https://idp.example/authorize?x=1')
})

test('beginLogin stores the current path for post-login redirect', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ authorization_url: 'https://idp.example/authorize' }),
  )
  vi.stubGlobal('location', {
    ...window.location,
    pathname: '/programs/123',
    search: '?tab=stages',
    assign: vi.fn(),
  })
  const setItem = vi.fn()
  vi.stubGlobal('sessionStorage', { setItem, getItem: vi.fn(), removeItem: vi.fn() })

  await beginLogin()
  expect(setItem).toHaveBeenCalledWith('postLoginRedirect', '/programs/123?tab=stages')
})

test('beginLogin does NOT store the path when already on /login', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ authorization_url: 'https://idp.example/authorize' }),
  )
  vi.stubGlobal('location', {
    ...window.location,
    pathname: '/login',
    search: '',
    assign: vi.fn(),
  })
  const setItem = vi.fn()
  vi.stubGlobal('sessionStorage', { setItem, getItem: vi.fn(), removeItem: vi.fn() })

  await beginLogin()
  expect(setItem).not.toHaveBeenCalled()
})

test('completeLogin posts state+code and returns the user', async () => {
  const spy = vi
    .spyOn(globalThis, 'fetch')
    .mockResolvedValueOnce(jsonResponse({ user: USER }))

  await expect(completeLogin('st', 'cd')).resolves.toMatchObject({ id: 'user-1' })

  const [, init] = spy.mock.calls[0]
  expect(init?.method).toBe('POST')
  expect(JSON.parse(String(init?.body))).toEqual({ state: 'st', code: 'cd' })
})

test('SessionError carries its code', () => {
  expect(new SessionError('UNAUTHENTICATED').code).toBe('UNAUTHENTICATED')
})
