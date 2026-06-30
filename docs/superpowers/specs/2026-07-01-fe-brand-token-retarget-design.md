# Brand-Token Retarget — Design

> Status: Approved (design) · Date: 2026-07-01 · Branch: `feat/fe-brand-token-retarget`
> Retarget the real frontend's design tokens from the current indigo/purple to the
> Catalesta brand palette, so every surface (including the merged Programs slice #69)
> rebrands from one token change — AA-verified, no per-component hex.

## 1. Goal

Make the real app on-brand by changing **design tokens only**. The
`catalesta-ui/` mockup specifies brand Teal `#1bbcb4`, Orange `#f26b3a`, Navy
`#0d1b2a` (see [[catalesta-ui-mockup-reference]] / `Catalesta_UIUX_Implementation_Scope.md` §1.1),
but the real app currently renders **indigo** (`#4f46e5`, Tailwind layer) and
**purple** (`#6d4aff`, legacy `.ds-*` layer). Retarget both to the brand palette
without violating the WCAG-AA contrast floor that the tokens already guarantee
(rule 08).

**Light theme is the primary/default brand target.** The brand work is judged in
light mode; the dark theme receives only the minimal AA-safe parity needed to stay
consistent (its `--primary`/`--ring` follow light so it isn't left indigo) — it is
explicitly **not** a dark-mode brand redesign, and dark ink/surfaces are unchanged.

## 2. Two token systems (both must change)

| System | File | Brand hue today | Consumers |
|---|---|---|---|
| Tailwind/shadcn (`bg-primary`, `text-primary`, `border-primary`, `ring`, `bg-secondary`, `text-muted-foreground`) | `frontend/src/index.css` `@theme inline` | indigo `--primary:#4f46e5` / `--ring:#6366f1` | Programs slice + all `components/ui/*` + new pages |
| Legacy `.ds-*` classes (`.ds-btn--primary`, `.ds-link`, `.apply-reference`, `.ds-focusable`) | `frontend/src/styles/tokens.css` | purple `--color-accent:#6d4aff` / `--color-accent-btn:#5a38e6` | older hand-built components (Button, Banner, Field, StateBlock, Apply page) |

Retargeting only one leaves the app half-teal/half-indigo. Both change in this slice.

## 3. The contrast wall (the core constraint)

Bright brand colors **fail WCAG AA** as fills-with-white-text or as text-on-white
and therefore cannot be the literal `--primary`/link color:
- White on Teal `#1bbcb4` ≈ **2.4:1** (needs ≥4.5:1). Teal as text on white ≈ 2.4:1.
- White on Orange `#f26b3a` ≈ **3.0:1**. Orange as text on white ≈ 3.0:1.

Each bright brand color gets an **AA-safe darker partner** for anything carrying
text; the bright color is reserved for non-text/decorative use (focus ring,
tints via alpha, dots, swatches):
- Teal fills/links/text → **teal-dark `#0d7a74`** (white-on-fill ≈ **5.2:1** ✓; as text on white ≈ 5.2:1 ✓).
- Orange fills/text → **`#c2410c`** (white-on-fill ≈ **5.2:1** ✓).
- Navy `#0d1b2a` as ink on white ≈ **16:1** ✓ (no partner needed).

These ratios are asserted by the contrast test (§6); the exact computed values
are whatever `contrast.ts` reports — the test enforces the ≥ thresholds, not the
estimates above.

## 4. Token mapping

### 4.1 `frontend/src/index.css` (Tailwind/shadcn)

Light `:root` and `.dark` raw vars, then exposed unchanged through the existing
`@theme inline` block.

- `--primary: #0d7a74` — **light is the authoritative value**; dark mirrors it only
  so dark isn't left indigo (minimal parity, not tuned for dark). `--primary-foreground: #ffffff` (unchanged).
- `--ring: #1bbcb4` (focus ring; non-text; bright teal is fine and visible) — light authoritative, dark mirrors.
- `--foreground: #0d1b2a` and `--card-foreground: #0d1b2a` in **light only**.
  `.dark` keeps `--foreground:#fafafa` / `--card-foreground:#fafafa` (navy on dark fails — unchanged).
- **Unchanged:** `--secondary`, `--secondary-foreground`, `--muted`, `--muted-foreground`,
  `--accent`, `--accent-foreground`, `--border`, `--input`, `--destructive`,
  `--destructive-foreground`, `--popover*`, `--background`, `--card`, radii, fonts.
  (These are neutral surfaces/hovers/red — not brand.)
- **New brand tokens** (raw vars in `:root` + `.dark`, exposed via `@theme inline`
  as `--color-brand`, `--color-brand-strong`, `--color-brand-orange`,
  `--color-brand-orange-strong` so `bg-brand`, `text-brand-strong`,
  `bg-brand-orange`, `text-brand-orange-strong` utilities exist):
  - `--brand: #1bbcb4` (bright teal — decorative/tint)
  - `--brand-strong: #0d7a74` (AA-safe teal)
  - `--brand-orange: #f26b3a` (decorative/tint/dot)
  - `--brand-orange-strong: #c2410c` (AA-safe orange)

### 4.2 `frontend/src/styles/tokens.css` (`.ds-*` legacy)

- `--color-accent-btn: #0d7a74` (real consumers `.ds-btn--primary`, `.ds-link`,
  `.apply-reference` — all white-text / text-on-white → AA teal-dark) — light & dark.
- `--color-accent: #1bbcb4` (bright), `--color-focus: #1bbcb4` (ring) — light & dark.
- `--color-ink: #0d1b2a` (navy) in **light**; `.ds-*` dark block keeps its light ink
  (`#ece9f7`) unchanged.
- Everything else in `tokens.css` (bg/surface/border/success/warning/danger/spacing/
  radii/fonts) unchanged.

Update the header contrast comments in both files to the new values.

## 5. Orange has no consumer yet

No existing component uses orange. To avoid a dead token, orange is **not** wired
into any production page in this slice. It is defined (both bright + AA-safe) and
**demonstrated in a Storybook "Brand palette" swatch story** that renders all four
brand tokens with their hex + intended use + a pass/fail contrast note. Applying
orange to a real CTA/urgent surface is deferred to whichever surface first needs it.

## 6. Testing

- **Contrast test** (extend `frontend/src/styles/contrast.ts` + its test, or add a
  new `*.test.ts`): assert each brand pairing meets its threshold — white on
  `#0d7a74` ≥ 4.5, white on `#c2410c` ≥ 4.5, `#0d1b2a` on white ≥ 4.5, `#0d7a74`
  on white ≥ 4.5 (text-primary), and the focus ring color differs from its
  background. Use the existing `contrast.ts` ratio helper if present; otherwise add
  a minimal `contrastRatio(hex,hex)` and test it against a known pair.
- **Storybook:** a "Design System/Brand palette" story rendering the swatches.
- **Regression:** full FE suite green (`npm run typecheck && npm run lint && npm run test`).
  The Programs and other page tests assert text/roles, not hex, so they stay green;
  any test that *does* assert a specific old hex is updated to the new token value
  (not weakened).
- **Visual sanity:** note in the report that `bg-primary`/`text-primary`/`ring`
  now resolve to teal across the app.

## 7. Authorization / a11y / scope guards

- No backend, no API, no authz change — pure presentational tokens.
- WCAG-AA contrast floor preserved and newly enforced by a test (rule 08).
- Status/meaning still conveyed by text, never colour alone (unchanged).
- No second design system introduced; the two existing systems are retargeted, not replaced.
- Dark-mode ink/foreground intentionally unchanged (navy fails on dark).

## 8. Out of scope (deferred / excluded)

- Tealing neutral surfaces (`secondary`/`muted`/`accent`/`border`/`input`) — they are not brand.
- Wiring orange into a production page (no consumer yet).
- A dark-mode palette redesign (only `--primary`/`--ring`/brand tokens change in dark; ink stays).
- Any per-component hex edit, layout change, or unifying the two token systems into one (separate refactor).
- Replacing the legacy `.ds-*` system (kept; just retargeted).
