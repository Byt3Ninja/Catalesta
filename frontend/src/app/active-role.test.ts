import { afterEach, expect, test } from 'vitest'
import { getActiveRole, setActiveRole, subscribe } from './active-role'

afterEach(() => setActiveRole('program_manager'))

test('defaults to program_manager', () => {
  expect(getActiveRole()).toBe('program_manager')
})

test('setActiveRole updates and notifies subscribers', () => {
  let notified = 0
  const unsub = subscribe(() => { notified += 1 })
  setActiveRole('founder')
  expect(getActiveRole()).toBe('founder')
  expect(notified).toBe(1)
  unsub()
  setActiveRole('mentor')
  expect(notified).toBe(1) // no longer notified after unsubscribe
})
