/**
 * decimal.ts — half-up decimal arithmetic for scoring
 *
 * All sums accumulate in integer "cents" (×100) to prevent IEEE 754 float
 * drift.  Rounding is half-up (away from zero for positive numbers), matching
 * the scoring model spec.
 */

/** Convert a number to its cent-integer representation (half-up). */
const cents = (n: number): number => Math.round(n * 100)

/** Round n to 2 decimal places (half-up). */
export const roundHalfUp2 = (n: number): number => cents(n) / 100

/** Sum an array of point values using cent-integer arithmetic. */
export const sumPoints = (values: number[]): number =>
  values.reduce((acc, v) => acc + cents(v), 0) / 100

/**
 * Mean of an array of values, rounded half-up to 2 decimal places.
 * Returns 0 for an empty array.
 */
export const mean = (values: number[]): number =>
  values.length === 0
    ? 0
    : Math.round(values.reduce((acc, v) => acc + cents(v), 0) / values.length) / 100

/** Format a number to exactly 2 decimal places as a string. */
export const format2 = (n: number): string => n.toFixed(2)
