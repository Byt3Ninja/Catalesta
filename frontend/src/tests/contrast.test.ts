import { describe, expect, it } from 'vitest'
import { contrastRatio } from '../styles/contrast'

/**
 * Token contrast gate (Story 1.0 Task 5, brand-retargeted 2026-07-01). Values are
 * the verbatim hex from src/index.css + src/styles/tokens.css. WCAG floors:
 *   - body / secondary text on its background ≥ 4.5:1 (1.4.3)
 *   - non-text UI (input border, focus ring) on its surface ≥ 3:1 (1.4.11)
 * The measured ratios are logged so the audit record is reproducible.
 */

const light = {
  bg: '#ffffff',
  surface: '#ffffff',
  surfaceAlt: '#f4f4f5',
  ink: '#0d1b2a', // brand navy
  inkMuted: '#52525b',
  accentBtn: '#0d7a74', // teal-dark primary fill
  inputBorder: '#71717a',
  onAccent: '#ffffff',
  ring: '#0d7a74', // teal-dark focus ring
}

const dark = {
  bg: '#09090b',
  surface: '#18181b',
  surfaceAlt: '#27272a',
  ink: '#fafafa', // dark ink unchanged (navy fails on dark)
  inkMuted: '#a1a1aa',
  accentBtn: '#0d7a74',
  inputBorder: '#71717a',
  onAccent: '#ffffff',
  ring: '#0d7a74',
}

const brand = {
  teal: '#1bbcb4', // decorative-only
  tealDark: '#0d7a74', // AA text/fill/ring
  navy: '#0d1b2a',
  orange: '#f26b3a', // decorative-only
  orangeStrong: '#c2410c', // AA CTA
  white: '#ffffff',
}

function check(name: string, fg: string, bg: string, min: number): void {
  const ratio = contrastRatio(fg, bg)
  console.log(`contrast ${name}: ${ratio.toFixed(2)}:1 (min ${min}:1)`)
  expect(ratio, `${name} must be ≥ ${min}:1`).toBeGreaterThanOrEqual(min)
}

describe('token contrast (WCAG 1.4.3 / 1.4.11)', () => {
  it('light mode meets the documented floors', () => {
    check('light primary-button text', light.onAccent, light.accentBtn, 4.5)
    check('light input border on surface', light.inputBorder, light.surface, 3)
    check('light body text on surface', light.ink, light.surface, 4.5)
    check('light muted text on surface', light.inkMuted, light.surface, 4.5)
    check('light body text on bg', light.ink, light.bg, 4.5)
    check('light focus ring on surface', light.ring, light.surface, 3)
  })

  it('dark mode meets the documented floors', () => {
    check('dark primary-button text', dark.onAccent, dark.accentBtn, 4.5)
    check('dark input border on surface', dark.inputBorder, dark.surface, 3)
    check('dark body text on surface', dark.ink, dark.surface, 4.5)
    check('dark muted text on surface', dark.inkMuted, dark.surface, 4.5)
    check('dark body text on bg', dark.ink, dark.bg, 4.5)
    check('dark focus ring on surface', dark.ring, dark.surface, 3)
  })
})

describe('brand palette AA (rule 08 floor)', () => {
  it('text-bearing brand colors meet AA 4.5:1', () => {
    check('white on teal-dark (primary fill)', brand.white, brand.tealDark, 4.5)
    check('teal-dark on white (text-primary / link)', brand.tealDark, brand.white, 4.5)
    check('white on orange-strong (CTA fill)', brand.white, brand.orangeStrong, 4.5)
    check('navy ink on white', brand.navy, brand.white, 4.5)
  })

  it('teal-dark focus ring meets the 3:1 non-text floor on white', () => {
    check('teal-dark ring on white', brand.tealDark, brand.white, 3)
  })

  it('bright brand colors are decorative-only (intentionally fail text/ring floors)', () => {
    // White text on bright teal is not AA — never use as a fill with white text.
    expect(contrastRatio(brand.white, brand.teal)).toBeLessThan(4.5)
    // Bright teal on white is < 3:1 — would fail as a focus ring; hence ring is teal-dark.
    expect(contrastRatio(brand.teal, brand.white)).toBeLessThan(3)
    // White text on bright orange is not AA — orange CTAs must use orange-strong.
    expect(contrastRatio(brand.white, brand.orange)).toBeLessThan(4.5)
  })
})
