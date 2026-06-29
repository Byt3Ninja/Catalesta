import { describe, expect, it } from 'vitest'
import { sumPoints, mean, roundHalfUp2, format2 } from './decimal'

describe('sumPoints', () => {
  it('sums integer values in cent-integer arithmetic', () => {
    expect(sumPoints([15, 18, 8])).toBe(41)
  })

  it('returns 0 for empty array', () => {
    expect(sumPoints([])).toBe(0)
  })

  it('handles fractional values without float drift', () => {
    // 0.1 + 0.2 would be 0.30000...4 in naive float; cent arithmetic gives 0.30
    expect(sumPoints([0.1, 0.2])).toBe(0.3)
  })
})

describe('mean', () => {
  it('computes mean with half-up rounding to 2dp', () => {
    expect(mean([46, 41, 38])).toBe(41.67)
  })

  it('returns 0 for empty array', () => {
    expect(mean([])).toBe(0)
  })

  it('handles a single value', () => {
    expect(mean([25])).toBe(25)
  })
})

describe('roundHalfUp2', () => {
  it('rounds half-up to 2 decimal places', () => {
    expect(roundHalfUp2(41.665)).toBe(41.67)
  })

  it('rounds down when below .5', () => {
    expect(roundHalfUp2(41.664)).toBe(41.66)
  })
})

describe('format2', () => {
  it('formats a number to exactly 2 decimal places as a string', () => {
    expect(format2(41.67)).toBe('41.67')
  })

  it('pads trailing zero', () => {
    expect(format2(10)).toBe('10.00')
  })
})
