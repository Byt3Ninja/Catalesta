import { describe, expect, it } from 'vitest'
import { contrastRatio } from '../styles/contrast'

/**
 * Story 1.0 Task 5 — verify the DESIGN.md token contrast claims in BOTH modes.
 * Values are the verbatim hex from src/index.css. WCAG floors:
 *   - body / secondary text on its background ≥ 4.5:1 (1.4.3)
 *   - non-text UI (input border) on its surface ≥ 3:1 (1.4.11)
 * The measured ratios are logged so the audit record is reproducible.
 */

const light = {
  bg: '#ffffff',
  surface: '#ffffff',
  surfaceAlt: '#f4f4f5',
  ink: '#09090b',
  inkMuted: '#52525b',
  accentBtn: '#4f46e5',
  inputBorder: '#71717a',
  onAccent: '#ffffff',
  ring: '#6366f1',
}

const dark = {
  bg: '#09090b',
  surface: '#18181b',
  surfaceAlt: '#27272a',
  ink: '#fafafa',
  inkMuted: '#a1a1aa',
  accentBtn: '#4f46e5',
  inputBorder: '#71717a',
  onAccent: '#ffffff',
  ring: '#6366f1',
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
