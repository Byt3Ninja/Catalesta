import { expect, test } from 'vitest'
import { ROLE_NAV } from './role-nav'
import { ROLE_KEYS } from '../schemas/roles'

test('every role has at least an Overview item and Programs only for program_manager', () => {
  for (const key of ROLE_KEYS) {
    expect(ROLE_NAV[key].length).toBeGreaterThan(0)
    expect(ROLE_NAV[key][0]).toEqual({ label: 'Overview', href: '/' })
  }
  expect(ROLE_NAV.program_manager.some((i) => i.href === '/programs')).toBe(true)
  expect(ROLE_NAV.founder.some((i) => i.href === '/programs')).toBe(false)
})
