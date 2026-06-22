import { afterEach, expect, test, vi } from 'vitest'
import { consumePostLoginRedirect } from './postLoginRedirect'

afterEach(() => vi.unstubAllGlobals())

test('returns and clears a same-origin path', () => {
  const removeItem = vi.fn()
  vi.stubGlobal('sessionStorage', { getItem: () => '/programs?tab=stages', removeItem, setItem: vi.fn() })
  expect(consumePostLoginRedirect()).toBe('/programs?tab=stages')
  expect(removeItem).toHaveBeenCalledWith('postLoginRedirect')
})

test('rejects protocol-relative and absolute URLs, falling back to /', () => {
  vi.stubGlobal('sessionStorage', { getItem: () => '//evil.example/x', removeItem: vi.fn(), setItem: vi.fn() })
  expect(consumePostLoginRedirect()).toBe('/')
})

test('rejects backslash-host (/\\evil.example) — some UAs treat \\ as /', () => {
  vi.stubGlobal('sessionStorage', { getItem: () => '/\\evil.example/x', removeItem: vi.fn(), setItem: vi.fn() })
  expect(consumePostLoginRedirect()).toBe('/')
})

test('falls back to / when nothing is stored', () => {
  vi.stubGlobal('sessionStorage', { getItem: () => null, removeItem: vi.fn(), setItem: vi.fn() })
  expect(consumePostLoginRedirect()).toBe('/')
})
