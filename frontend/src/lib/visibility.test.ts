import { expect, test } from 'vitest'
import { isFieldVisible } from './visibility'
import type { FormField } from '../schemas/forms'

const base: FormField = { id: 'x', type: 'short_text', label: 'X' }

test('no visibility rule → always visible', () => {
  expect(isFieldVisible(base, {})).toBe(true)
})

test('equals operator shows only when the trigger matches', () => {
  const f: FormField = { ...base, visibility: { match: 'all', conditions: [{ field_id: 't', operator: 'equals', value: 'yes' }] } }
  expect(isFieldVisible(f, { t: 'yes' })).toBe(true)
  expect(isFieldVisible(f, { t: 'no' })).toBe(false)
})

test('not_equals, includes (array), is_empty', () => {
  const ne: FormField = { ...base, visibility: { match: 'all', conditions: [{ field_id: 't', operator: 'not_equals', value: 'a' }] } }
  expect(isFieldVisible(ne, { t: 'b' })).toBe(true)
  const inc: FormField = { ...base, visibility: { match: 'all', conditions: [{ field_id: 't', operator: 'includes', value: 'Fintech' }] } }
  expect(isFieldVisible(inc, { t: ['Health', 'Fintech'] })).toBe(true)
  const emp: FormField = { ...base, visibility: { match: 'all', conditions: [{ field_id: 't', operator: 'is_empty', value: null }] } }
  expect(isFieldVisible(emp, { t: '' })).toBe(true)
  expect(isFieldVisible(emp, { t: 'x' })).toBe(false)
})

test('match all vs any', () => {
  const all: FormField = { ...base, visibility: { match: 'all', conditions: [
    { field_id: 'a', operator: 'equals', value: '1' }, { field_id: 'b', operator: 'equals', value: '2' }] } }
  expect(isFieldVisible(all, { a: '1', b: '2' })).toBe(true)
  expect(isFieldVisible(all, { a: '1', b: 'x' })).toBe(false)
  const any: FormField = { ...all, visibility: { ...all.visibility!, match: 'any' } }
  expect(isFieldVisible(any, { a: '1', b: 'x' })).toBe(true)
})
