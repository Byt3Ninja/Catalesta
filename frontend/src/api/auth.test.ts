import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { register, passwordLogin, forgotPassword, resetPassword, resendVerification } from './auth'
import { jsonResponse } from '../tests/test-utils'

const USER = {
  id: 'u1', email: 'a@b.com', display_name: null,
  email_verified: false, startup_gate_subject_id: null,
  linked_providers: [], has_password: true,
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('register returns the user on 201', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ user: USER }, 201))
  await expect(register({ email: 'a@b.com', password: 'super-secret' })).resolves.toMatchObject({ id: 'u1' })
})

test('register maps a taken email (422 details.email) to EMAIL_TAKEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['The email has already been taken.'] } } }, 422),
  )
  await expect(register({ email: 'a@b.com', password: 'x' })).rejects.toMatchObject({
    name: 'NativeAuthError', code: 'EMAIL_TAKEN',
  })
})

test('passwordLogin maps any 422 to a generic INVALID_CREDENTIALS', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['These credentials do not match our records.'] } } }, 422),
  )
  await expect(passwordLogin({ email: 'a@b.com', password: 'nope' })).rejects.toMatchObject({
    name: 'NativeAuthError', code: 'INVALID_CREDENTIALS',
  })
})

test('passwordLogin maps a non-email 422 to the SAME generic INVALID_CREDENTIALS', async () => {
  // Proves the guard is not email-specific: a different field (or any 422 body) still
  // collapses to one code, never revealing which input was wrong.
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { password: ['The password field is required.'] } } }, 422),
  )
  await expect(passwordLogin({ email: 'a@b.com', password: '' })).rejects.toMatchObject({
    name: 'NativeAuthError', code: 'INVALID_CREDENTIALS',
  })
})

test('register maps a non-email 422 to UNKNOWN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { password: ['The password must be at least 8 characters.'] } } }, 422),
  )
  await expect(register({ email: 'a@b.com', password: 'short' })).rejects.toMatchObject({
    name: 'NativeAuthError', code: 'UNKNOWN',
  })
})

test('passwordLogin returns the user on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ user: USER }))
  await expect(passwordLogin({ email: 'a@b.com', password: 'super-secret' })).resolves.toMatchObject({ id: 'u1' })
})

test('forgotPassword resolves on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ message: 'ok' }))
  await expect(forgotPassword('a@b.com')).resolves.toBeUndefined()
})

test('forgotPassword maps 429 to RATE_LIMITED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ error: { code: 'HTTP_429' } }, 429))
  await expect(forgotPassword('a@b.com')).rejects.toMatchObject({ code: 'RATE_LIMITED' })
})

test('resetPassword maps 422 to INVALID_RESET_TOKEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['This password reset token is invalid or has expired.'] } } }, 422),
  )
  await expect(resetPassword({ token: 't', email: 'a@b.com', password: 'super-secret' })).rejects.toMatchObject({
    code: 'INVALID_RESET_TOKEN',
  })
})

test('resetPassword resolves on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ message: 'ok' }))
  await expect(resetPassword({ token: 't', email: 'a@b.com', password: 'super-secret' })).resolves.toBeUndefined()
})

test('resendVerification resolves on 204', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 }))
  await expect(resendVerification()).resolves.toBeUndefined()
})
