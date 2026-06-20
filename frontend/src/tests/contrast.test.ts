import { describe, expect, it } from 'vitest'
import { contrastRatio } from '../styles/contrast'

/**
 * Story 1.0 Task 5 — verify the DESIGN.md token contrast claims in BOTH modes.
 * Values are the verbatim hex from src/styles/tokens.css. WCAG floors:
 *   - body / secondary text on its background ≥ 4.5:1 (1.4.3)
 *   - non-text UI (input border) on its surface ≥ 3:1 (1.4.11)
 * The measured ratios are logged so the audit record is reproducible.
 */

const light = {
  bg: '#f7f7fb',
  surface: '#ffffff',
  surfaceAlt: '#f1effa',
  ink: '#1a1430',
  inkMuted: '#5b5470',
  accentBtn: '#5a38e6',
  inputBorder: '#79718f',
  onAccent: '#ffffff',
}

const dark = {
  bg: '#14121f',
  surface: '#1e1b2e',
  surfaceAlt: '#26223a',
  ink: '#ece9f7',
  inkMuted: '#a9a2c2',
  accentBtn: '#5a38e6',
  inputBorder: '#6e6788',
  onAccent: '#ffffff',
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
  })

  it('dark mode meets the documented floors', () => {
    check('dark primary-button text', dark.onAccent, dark.accentBtn, 4.5)
    check('dark input border on surface', dark.inputBorder, dark.surface, 3)
    check('dark body text on surface', dark.ink, dark.surface, 4.5)
    check('dark muted text on surface', dark.inkMuted, dark.surface, 4.5)
    check('dark body text on bg', dark.ink, dark.bg, 4.5)
  })
})
