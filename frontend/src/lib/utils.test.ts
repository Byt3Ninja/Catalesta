import { expect, test } from 'vitest'
import { cn } from '@/lib/utils'

test('cn merges and de-dupes conflicting tailwind classes', () => {
  expect(cn('px-2', 'px-4')).toBe('px-4')
  const hidden = false
  expect(cn('text-sm', hidden && 'hidden', 'font-bold')).toBe('text-sm font-bold')
})
