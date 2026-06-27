import { expect, test } from 'vitest'
import { handlers } from './handlers'

function hasRoute(method: string, frag: string): boolean {
  return handlers.some((h) => (h.info?.method as string) === method && String(h.info?.path ?? '').includes(frag))
}

test('forms handlers are registered', () => {
  expect(hasRoute('GET', '/forms')).toBe(true)
  expect(hasRoute('POST', '/forms')).toBe(true)
  expect(hasRoute('PATCH', '/forms/:id/draft')).toBe(true)
  expect(hasRoute('POST', '/forms/:id/publish')).toBe(true)
  expect(hasRoute('POST', '/forms/:id/fork')).toBe(true)
  expect(hasRoute('GET', '/forms/:id/versions')).toBe(true)
  expect(hasRoute('GET', '/form-versions/:versionId')).toBe(true)
})
