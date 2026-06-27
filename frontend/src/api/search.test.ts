import { afterEach, expect, test, vi } from 'vitest'
import { search } from './search'
import { jsonResponse } from '../tests/test-utils'

afterEach(() => vi.restoreAllMocks())

test('search encodes the query and parses groups', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ data: [{ category: 'people', items: [{ id: 'p1', label: 'Alice', sublabel: 'Founder', href: '/preview/people/p1' }] }] }),
  )
  const result = await search('al ice')
  expect(spy).toHaveBeenCalledWith(expect.stringContaining('q=al%20ice'), expect.anything())
  expect(result[0].category).toBe('people')
  expect(result[0].items[0].label).toBe('Alice')
})

test('rejects a malformed payload', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [{ category: 'nope', items: [] }] }))
  await expect(search('x')).rejects.toThrow()
})
