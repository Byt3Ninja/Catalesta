import { expect, test } from 'vitest'
import { handlers } from './handlers'

function hasRoute(method: string, pathFragment: string): boolean {
  return handlers.some(
    (h) => h.info?.method === method && String(h.info?.path ?? '').includes(pathFragment),
  )
}

test('cohort CRUD handlers are registered', () => {
  expect(hasRoute('GET', '/cohorts/:id')).toBe(true)
  expect(hasRoute('POST', '/programs/:programId/cohorts')).toBe(true)
  expect(hasRoute('PATCH', '/cohorts/:id')).toBe(true)
  expect(hasRoute('POST', '/cohorts/:id/open')).toBe(true)
  expect(hasRoute('POST', '/cohorts/:id/bind-form')).toBe(true)
})
