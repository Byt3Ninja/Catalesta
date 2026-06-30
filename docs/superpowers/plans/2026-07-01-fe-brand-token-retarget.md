# FE Brand-Token Retarget Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Retarget the real frontend's two design-token systems from indigo/purple to the Catalesta brand palette (teal/navy/orange) so every surface — including merged Programs #69 — rebrands from one token change, AA-verified.

**Architecture:** Pure presentational change to CSS custom properties only. Two files (`src/index.css` Tailwind/shadcn layer, `src/styles/tokens.css` legacy `.ds-*` layer), an updated contrast gate, and a Storybook swatch story. No backend, API, component, or layout changes.

**Tech Stack:** Tailwind v4 (`@theme inline`), shadcn-style CSS vars, Vitest, Storybook (CSF3, `@storybook/react-vite`).

## Global Constraints

- Commit author `274270+Byt3Ninja@users.noreply.github.com`, name `Byt3 Ninja`; `git -c commit.gpgsign=false`.
- `git add` ONLY the task's files — never `-A`; NEVER add `catalesta-ui/` (separate embedded repo).
- Branch is `feat/fe-brand-token-retarget`; `git branch --show-current` before each commit.
- Brand palette (exact hex): teal-dark `#0d7a74` (AA-safe: fills/links/text/ring), bright teal `#1bbcb4` (decorative-only), navy `#0d1b2a` (light ink), orange `#f26b3a` (decorative), orange-strong `#c2410c` (AA-safe CTA).
- **Light theme is authoritative.** Dark mirrors only `--primary`/`--ring`; dark ink/surfaces UNCHANGED (navy fails on dark).
- AA floor (rule 08) enforced by the contrast gate: text pairings ≥4.5:1, non-text (ring/border) ≥3:1. Bright teal/orange are decorative-only because they fail those floors.
- DO NOT change neutral tokens (`secondary`/`muted`/`accent`/`border`/`input`/`background`/`card`/`popover`), `destructive` (stays red), radii, or fonts. No per-component hex edits, no layout.
- Gate (from `frontend/`): `npm run typecheck && npm run lint && npm run test`.

---

### Task 1: Update the contrast gate to the brand palette (locks the values)

**Files:**
- Modify: `frontend/src/tests/contrast.test.ts`

**Interfaces:**
- Consumes: `contrastRatio(a: string, b: string): number` from `../styles/contrast` (existing, order-independent, 1–21).
- Produces: the AA proof for the new palette. If any assertion fails, a chosen hex violates AA and must change before touching CSS.

This test hand-mirrors the token hexes (jsdom can't read CSS vars). Updating it first locks the palette math, then Tasks 2–3 edit the CSS to match these exact hexes.

- [ ] **Step 1: Replace the test with the brand-palette version**

Replace the entire contents of `frontend/src/tests/contrast.test.ts` with:

```ts
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
```

- [ ] **Step 2: Run the gate — it must pass**

Run: `cd frontend && npm run test -- src/tests/contrast.test.ts`
Expected: PASS (3 `describe` blocks, all `check`s ≥ their floor; the negative guards confirm the brights fail). A failure here means a chosen hex is not AA — stop and fix the hex, do not proceed.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/tests/contrast.test.ts
git -c commit.gpgsign=false -c user.email=274270+Byt3Ninja@users.noreply.github.com -c user.name="Byt3 Ninja" commit -m "FE brand-token retarget — Task 1: contrast gate locks brand palette

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Retarget the Tailwind/shadcn tokens (`src/index.css`)

**Files:**
- Modify: `frontend/src/index.css`

**Interfaces:**
- Consumes: the palette locked in Task 1.
- Produces: `--color-primary`/`--color-ring`/`--color-foreground` now teal/navy; new `--color-brand`, `--color-brand-strong`, `--color-brand-orange`, `--color-brand-orange-strong` utilities (`bg-brand`, `text-brand-strong`, etc.).

- [ ] **Step 1: Edit the light `:root` block**

In `frontend/src/index.css`, in `:root`, change these four lines (leave every other line untouched):

```css
  --primary: #0d7a74; /* teal-dark — white text ≈5.2:1 (passes 4.5) */
  --foreground: #0d1b2a; /* brand navy — ≈16:1 on white */
  --card-foreground: #0d1b2a;
  --ring: #0d7a74; /* teal-dark — ≈5.2:1 on white (passes 3:1 non-text); bright #1bbcb4 fails */
```

Then add the four brand raw vars at the end of the `:root` block (before its closing `}`):

```css
  --brand: #1bbcb4; /* bright teal — decorative/tint only (fails text/ring floors) */
  --brand-strong: #0d7a74; /* AA-safe teal for fills/text */
  --brand-orange: #f26b3a; /* decorative/tint/dot only */
  --brand-orange-strong: #c2410c; /* AA-safe orange CTA — white text ≈5.2:1 */
```

(`--primary-foreground: #ffffff` stays. Do NOT touch `--secondary*`, `--muted*`, `--accent*`, `--border`, `--input`, `--destructive*`, `--popover*`, `--background`, `--card`, `--radius`.)

- [ ] **Step 2: Edit the `.dark` block (mirror primary/ring + brand vars only)**

In the `.dark` block, change exactly two lines:

```css
  --primary: #0d7a74;
  --ring: #0d7a74;
```

Leave `.dark`'s `--foreground: #fafafa` and `--card-foreground: #fafafa` UNCHANGED. Add the same four brand vars to the end of the `.dark` block:

```css
  --brand: #1bbcb4;
  --brand-strong: #0d7a74;
  --brand-orange: #f26b3a;
  --brand-orange-strong: #c2410c;
```

- [ ] **Step 3: Expose the brand tokens in `@theme inline`**

In the `@theme inline { … }` block, add these four mappings (next to `--color-ring: var(--ring);`):

```css
  --color-brand: var(--brand);
  --color-brand-strong: var(--brand-strong);
  --color-brand-orange: var(--brand-orange);
  --color-brand-orange-strong: var(--brand-orange-strong);
```

- [ ] **Step 4: Verify the gate stays green**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test`
Expected: typecheck clean, lint clean, full suite 0 failures (CSS vars aren't parsed by jsdom; no test asserts a hex except the Task-1 gate, which already matches). If any test fails on an old hex string, update that string to the new value — do NOT weaken the test.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/index.css
git -c commit.gpgsign=false -c user.email=274270+Byt3Ninja@users.noreply.github.com -c user.name="Byt3 Ninja" commit -m "FE brand-token retarget — Task 2: Tailwind tokens → teal/navy + brand vars

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Retarget the legacy `.ds-*` tokens (`src/styles/tokens.css`)

**Files:**
- Modify: `frontend/src/styles/tokens.css`

**Interfaces:**
- Consumes: the palette locked in Task 1.
- Produces: `.ds-btn--primary`/`.ds-link`/`.apply-reference`/`.ds-focusable` now teal; `--color-ink` navy (light).

- [ ] **Step 1: Edit the `:root` block**

In `frontend/src/styles/tokens.css`, change these four lines in `:root` (leave all others):

```css
  --color-accent: #1bbcb4; /* bright teal — decorative (no .ds-* rule renders text on it) */
  --color-accent-btn: #0d7a74; /* teal-dark fill/link, white text ≈5.2:1 */
  --color-ink: #0d1b2a; /* brand navy */
  --color-focus: #0d7a74; /* teal-dark focus outline, ≥3:1 on surface */
```

(`--color-on-accent: #ffffff` stays. Do NOT touch `--color-bg`, `--color-surface*`, `--color-border`, `--color-input-border`, `--color-success/warning/danger`, spacing, radii, fonts.)

- [ ] **Step 2: Edit the `[data-theme='dark']` block**

Change exactly three lines (leave `--color-ink: #ece9f7` UNCHANGED):

```css
  --color-accent: #1bbcb4;
  --color-accent-btn: #0d7a74;
  --color-focus: #0d7a74;
```

- [ ] **Step 3: Update the header contrast comment**

Replace the comment line near the top (currently `* Verified-contrast pairs: accentBtn (white text >=4.5:1), inputBorder (>=3:1).`) with:

```css
 * Verified-contrast pairs: accentBtn #0d7a74 (white text >=4.5:1), inputBorder (>=3:1),
 * focus #0d7a74 (>=3:1 on surface). Brand teal #1bbcb4 is decorative-only.
```

- [ ] **Step 4: Verify the gate stays green**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/styles/tokens.css
git -c commit.gpgsign=false -c user.email=274270+Byt3Ninja@users.noreply.github.com -c user.name="Byt3 Ninja" commit -m "FE brand-token retarget — Task 3: legacy .ds-* tokens → teal/navy

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Brand-palette Storybook story + full sweep + spec status

**Files:**
- Create: `frontend/src/components/BrandPalette.stories.tsx`
- Modify: `docs/superpowers/specs/2026-07-01-fe-brand-token-retarget-design.md` (status → Implemented)

**Interfaces:**
- Consumes: the brand utility classes from Task 2 (`bg-brand`, `bg-brand-strong`, `bg-brand-orange`, `bg-brand-orange-strong`) and `text-foreground`.
- Produces: the documented home/consumer for the brand tokens (especially orange, which has no production consumer yet).

- [ ] **Step 1: Create the swatch story (CSF3, matches existing stories)**

`frontend/src/components/BrandPalette.stories.tsx`:

```tsx
import type { Meta, StoryObj } from '@storybook/react-vite'

type Swatch = { name: string; cls: string; hex: string; use: string; aa: string }

const SWATCHES: Swatch[] = [
  { name: '--brand-strong', cls: 'bg-brand-strong', hex: '#0d7a74', use: 'Primary fills, links, text-primary, focus ring', aa: 'AA — white text ≈5.2:1' },
  { name: '--brand', cls: 'bg-brand', hex: '#1bbcb4', use: 'Decorative tints, dots, progress (behind dark text only)', aa: 'Decorative-only — fails text/ring floors' },
  { name: 'navy ink', cls: 'bg-foreground', hex: '#0d1b2a', use: 'Headings / body ink (light theme)', aa: 'AA — ≈16:1 on white' },
  { name: '--brand-orange-strong', cls: 'bg-brand-orange-strong', hex: '#c2410c', use: 'Orange CTA / urgent fills (when a surface needs one)', aa: 'AA — white text ≈5.2:1' },
  { name: '--brand-orange', cls: 'bg-brand-orange', hex: '#f26b3a', use: 'Decorative orange tints / dots', aa: 'Decorative-only — fails text floor' },
]

function Palette() {
  return (
    <div className="grid gap-3 p-4 text-foreground" style={{ maxWidth: 560 }}>
      <h2 className="text-lg font-semibold">Catalesta brand palette</h2>
      {SWATCHES.map((s) => (
        <div key={s.name} className="flex items-center gap-4 rounded-md border border-border p-3">
          <span className={`${s.cls} inline-block h-10 w-10 rounded-md`} aria-hidden="true" />
          <span className="grid gap-0.5 text-sm">
            <span className="font-medium">{s.name} <span className="text-muted-foreground">{s.hex}</span></span>
            <span className="text-muted-foreground">{s.use}</span>
            <span className="text-xs text-muted-foreground">{s.aa}</span>
          </span>
        </div>
      ))}
    </div>
  )
}

const meta = {
  title: 'Design System/Brand palette',
  component: Palette,
} satisfies Meta<typeof Palette>
export default meta
type Story = StoryObj<typeof meta>

export const Swatches: Story = {}
```

- [ ] **Step 2: Verify the story compiles + full sweep**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test`
Expected: all green (the story is type-checked + lint-checked; if a Storybook/Vitest addon runs stories as tests, the swatch story renders without error).

- [ ] **Step 3: Confirm Storybook builds (the story has no runtime errors)**

Run: `cd frontend && npm run build-storybook 2>&1 | tail -5`
Expected: build completes without error (confirms `bg-brand*` utilities exist and the story is valid). If `build-storybook` is slow/unavailable in the environment, note that and rely on typecheck+lint+test instead.

- [ ] **Step 4: Mark the spec Implemented**

In `docs/superpowers/specs/2026-07-01-fe-brand-token-retarget-design.md`, change `Status: Approved (design)` to `Status: Implemented` and append one deviation line: note that the focus ring uses teal-dark `#0d7a74` (not bright `#1bbcb4`) because bright teal fails the 3:1 non-text floor — caught by the contrast gate. (If there were no other deviations, state exactly that.)

- [ ] **Step 5: Commit**

```bash
git add frontend/src/components/BrandPalette.stories.tsx docs/superpowers/specs/2026-07-01-fe-brand-token-retarget-design.md
git -c commit.gpgsign=false -c user.email=274270+Byt3Ninja@users.noreply.github.com -c user.name="Byt3 Ninja" commit -m "FE brand-token retarget — Task 4: brand-palette story + spec status

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- §2 both token systems retargeted → Tasks 2 (index.css) + 3 (tokens.css). ✓
- §3 contrast wall / AA-safe partners → Task 1 gate asserts white-on-`#0d7a74`/`#c2410c` ≥4.5, navy ≥4.5, ring ≥3, and the brights fail. ✓
- §3 ring correction (teal-dark not bright) → Task 1 (ring `#0d7a74`, negative guard) + Task 2 Step 1/2 + Task 3 Step 1/2. ✓
- §4.1 mapping (primary/ring/foreground/card-foreground + 4 brand vars + `@theme`) → Task 2. ✓
- §4.2 mapping (accent-btn/accent/focus/ink + comment) → Task 3. ✓
- §5 orange has no consumer → demonstrated in Task 4 story; not wired to a page. ✓
- §6 testing → Task 1 (gate update), Task 4 (story + sweep). ✓
- §7/§8 scope guards (neutral tokens, destructive, dark ink unchanged, light authoritative, no layout) → enforced by the explicit "leave unchanged" lists in Tasks 2–3 and the Global Constraints. ✓

**Placeholder scan:** none — every CSS edit lists exact lines/hex; the test and story are complete code; the only conditional is Task 4 Step 3's `build-storybook` fallback, which is a real environment caveat, not a placeholder.

**Type/value consistency:** hexes identical across Task 1 (test mirrors), Task 2 (index.css), Task 3 (tokens.css), Task 4 (story): teal-dark `#0d7a74`, bright `#1bbcb4`, navy `#0d1b2a`, orange `#f26b3a`, orange-strong `#c2410c`. `--ring`/`--color-focus` both `#0d7a74`. Brand var names (`--brand`, `--brand-strong`, `--brand-orange`, `--brand-orange-strong`) and their `--color-*`/utility forms consistent between Tasks 2 and 4.
