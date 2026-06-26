import { expect, test } from 'vitest'
import { cn } from '@/lib/utils'

test('cn merges and de-dupes conflicting tailwind classes', () => {
  expect(cn('px-2', 'px-4')).toBe('px-4')
  expect(cn('text-sm', false && 'hidden', 'font-bold')).toBe('text-sm font-bold')
})
