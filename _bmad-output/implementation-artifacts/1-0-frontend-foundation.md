# Story 1.0: Frontend foundation (design tokens, component set, a11y gate)

Status: in-progress

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

> **Track B (frontend, parallel) · no backend dependency** — can start day-one alongside the Track A backend gate (Story 2.1). This is the foundation every later UI story consumes. **DoD rule:** later stories MUST consume these primitives and MUST NOT re-implement buttons/inputs/RTL/state-blocks.

## Story

As a **frontend developer**,
I want the DESIGN.md token system, the minimal P1a component set, and the accessibility CI gate in place,
so that every feature story builds on consistent, accessible, RTL-ready primitives instead of re-inventing them.

## Acceptance Criteria

From epics.md (Story 1.0) + UX-DR1/2/6:

1. **Token layer (UX-DR1).** Implement the DESIGN.md tokens as the single source consumed by all components: colors (**light + dark**, incl. `accentBtn`, `inputBorder`), typography (Space Grotesk display / Inter body / Tajawal Arabic / IBM Plex Mono numeric), spacing scale, radii, elevation. Values come **verbatim from DESIGN.md frontmatter** (do not invent hues).
2. **Minimum component set — and ONLY this set** (defer modal/table/dropdown/toast/date-picker/file-chrome to the first feature story that needs them):
   - **Button** — primary (`accentBtn`) / secondary / disabled / loading states
   - **TextInput + field wrapper** — label, `inputBorder`, error + helper, RTL-aware, with `aria-invalid` + `aria-describedby` wiring
   - **Form layout primitive** — field group + inline validation slot
   - **Banner / inline alert** — info / error / success
   - **Spinner + skeleton**
   - **Empty / error / offline state block** — one component, three messages
   - **App shell + direction provider** — live LTR↔RTL switch, font wiring, light/dark token surface
   - **Link / text styles**
3. **Minimal a11y CI gate (UX-DR6)** runs on every build and **fails the build on regression**: contrast (verified token pairs), missing-form-label, and `lang`/`dir` presence.
4. **RTL/bidi owned here.** The direction provider + `dir="auto"` field behavior live in this story, **not re-decided per feature story**.
5. **Dark-mode decision explicit.** Tokens ship for both modes; whether a P1a *toggle* is in scope is **stated, not assumed** (recommend: ship both token sets + the surface mechanism; gate the user-facing toggle to operator settings, decision recorded in the story).

## Tasks / Subtasks

- [ ] **Task 1 — Token layer** (AC: 1)
  - [ ] Define CSS custom properties from DESIGN.md in `src/index.css` (or `src/styles/tokens.css` imported by `index.css`): `--color-*` for light under `:root`, dark under `[data-theme="dark"]` (or `@media (prefers-color-scheme: dark)` + override). **No CSS framework is installed** (no Tailwind) — tokens are plain CSS variables. [Source: frontend/package.json — confirmed no Tailwind]
  - [ ] Token values (copy exactly from DESIGN.md): light `accentBtn #5A38E6`, `inputBorder #79718F`, `accent #6D4AFF`, `focus #6D4AFF`, `ink #1A1430`, `bg #F7F7FB`, `surface #FFFFFF`, `success #0A7D45`, `warning #9A6410`, `danger #D92D20`; dark `accentBtn #5A38E6`, `inputBorder #6E6788`, `focus #9279FF`, `ink #ECE9F7`, `bg #14121F`, `surface #1E1B2E`, etc. Radii sm 8 / md 10 / lg 14 / pill 999. Spacing base 4, scale [4,8,12,16,20,24,32,40,56]. [Source: DESIGN.md frontmatter — full set]
  - [ ] Wire font faces: Space Grotesk, Inter, Tajawal, IBM Plex Mono (self-host or via a controlled link — no arbitrary external CSS per CLAUDE.md SaaS rule #11). Scores/IDs use `tabular-nums` + mono.
  - [ ] **Optional but recommended:** expose a typed token accessor (TS) so components read tokens by name, not magic strings.
- [ ] **Task 2 — Component set** (AC: 2) — build under `src/components/` (dir exists), each with a `*.stories.tsx` (Storybook 10 is installed) and a `*.test.tsx`:
  - [ ] `Button` (variants + loading/disabled; primary uses `accentBtn`; one primary per screen convention from EXPERIENCE.md)
  - [ ] `TextInput` + `Field` wrapper (label, `inputBorder` 1.5px, error via `aria-invalid`+`aria-describedby`, helper text, `dir="auto"`)
  - [ ] `FormLayout` primitive (field group + inline validation slot) — integrates with the installed `react-hook-form` + `zod` + `@hookform/resolvers`
  - [ ] `Banner` (info/error/success)
  - [ ] `Spinner` + `Skeleton` (skeleton respects `prefers-reduced-motion`)
  - [ ] `StateBlock` (empty/error/offline — one component, three messages)
  - [ ] `AppShell` + `DirectionProvider` (live LTR↔RTL via `dir` on document, light/dark surface, font wiring)
  - [ ] `Link` / text style primitives
- [ ] **Task 3 — Direction + theme providers** (AC: 4, 5)
  - [ ] `DirectionProvider` toggles `document.dir` + `lang`; layout uses **logical CSS properties** (margin-inline, padding-inline) so mirroring is automatic — no per-component RTL CSS. [Source: EXPERIENCE.md#RTL & Bilingual Behavior]
  - [ ] `dir="auto"` on text inputs so an Arabic answer renders RTL in an English UI and vice-versa.
  - [ ] Theme surface toggles `data-theme`; record the dark-mode-toggle scope decision in this file's Dev Agent Record.
- [ ] **Task 4 — a11y CI gate** (AC: 3)
  - [ ] Use the installed **`@storybook/addon-a11y`** + **`@storybook/addon-vitest`** to run axe checks on component stories in Vitest (browser mode via `@vitest/browser-playwright`, already installed). Fail CI on contrast / missing-label violations. [Source: frontend/package.json devDependencies]
  - [ ] Add a check that every rendered surface carries `lang` + `dir` (the provider guarantees it; assert it).
  - [ ] Wire into the build/CI script (a `package.json` script that the pipeline runs and that exits non-zero on violation).
- [ ] **Task 5 — Verify the tokens' contrast claims** (AC: 1, 3)
  - [ ] Confirm `inputBorder` ≥ 3:1 on surface (WCAG 1.4.11) and body/secondary text ≥ 4.5:1 in **both** modes, against the DESIGN.md values — this is the carried UX medium item. Use a tool/axe; record the measured ratios.

## Dev Notes

### Where this fits
First story of Epic 1 and the whole frontend. Track B runs in parallel with the Track A backend gate (Story 2.1) — **no backend dependency**. Everything in Epic 1 (1.1–1.5) and Epic 2/3 UI consumes these primitives. [Source: epics.md#Epic 1 / Story 1.0; implementation-readiness-report-2026-06-20.md#Recommended Next Steps]

### Stack & conventions (verified in-tree)
- **Frontend stack:** Vite 8, React 19.2, TypeScript ~6 **strict**, `@tanstack/react-query` 5, `react-hook-form` 7 + `@hookform/resolvers` + `zod` 4. Test: Vitest 4 (`@vitest/browser-playwright`, `@vitest/coverage-v8`), Testing Library, Playwright 1.61. Stories: Storybook 10 with `addon-a11y`, `addon-vitest`, `addon-docs`. [Source: frontend/package.json]
- **No CSS framework** — implement tokens as CSS custom properties; no Tailwind/MUI to inherit. [Source: frontend/package.json — none present]
- **Existing structure:** `src/components/`, `src/pages/`, `src/hooks/`, `src/api/` (`client.ts`, `health.ts`), `src/schemas/` (zod, e.g. `health.ts`), `src/app/App.tsx`, `src/tests/`, `src/stories/Welcome.stories.tsx`, `src/index.css`, `src/main.tsx`. Build components into `src/components/`; mirror the existing `Welcome.stories.tsx` for Storybook format and the `HealthPage.test.tsx` for test format. [Source: frontend/src/]
- **API/schema pattern already established:** `src/api/client.ts` + zod schemas in `src/schemas/` — feature stories will follow it; this story does not add API calls.

### Design source (authoritative)
- **DESIGN.md** governs the visual layer (tokens, color, type, spacing, component specs); **EXPERIENCE.md** governs behavior (states, a11y floor, RTL). On conflict, the spines win; DESIGN = visual, EXPERIENCE = behavioral. [Source: EXPERIENCE.md header]
- **Component behavior specs** (build to these, even though full screens are later): primary button = one per screen, loading + disabled-with-reason; disabled control never silent (shows why); status by icon **+** text, never color alone; modal semantics (deferred to first modal-using story, NOT built here). [Source: EXPERIENCE.md#Component Patterns, #Accessibility Floor]

### Accessibility floor (P1a — build into the primitives)
Contrast ≥4.5:1 body / ≥3:1 large+UI in both modes; visible `focus` ring never removed; `aria-invalid`+`aria-describedby` error association (not visual-only); status not by color alone; touch targets ≥44px on mobile; respect `prefers-reduced-motion` (gate skeleton shimmer, provide non-animated loading fallback); real semantic elements. Full WCAG 2.2 AA audit is **P4, out of scope** — only the floor + the automated gate ship now. [Source: EXPERIENCE.md#Accessibility Floor (Phase 1a)]

### Project Structure Notes
- New: `src/components/*` (+ `.stories.tsx` + `.test.tsx` each), `src/styles/tokens.css` (or extend `src/index.css`), provider components (`DirectionProvider`, theme surface) likely in `src/app/` or `src/components/`. CI script wiring in `package.json` + the pipeline.
- **No conflicts** — this is greenfield within the frontend scaffold; nothing existing is broken (only `App.tsx`/`main.tsx` may be lightly wired to mount the providers + token CSS).

### References
- [Source: _bmad-output/planning-artifacts/epics.md#Story 1.0: Frontend foundation]
- [Source: _bmad-output/planning-artifacts/epics.md#UX Design Requirements — UX-DR1, UX-DR2, UX-DR6]
- [Source: _bmad-output/planning-artifacts/ux-designs/.../DESIGN.md — token frontmatter (verbatim values)]
- [Source: _bmad-output/planning-artifacts/ux-designs/.../EXPERIENCE.md — Component Patterns, Accessibility Floor, RTL & Bilingual Behavior]
- [Source: frontend/package.json — installed deps incl. Storybook addon-a11y, addon-vitest]
- [Source: frontend/src/ — existing scaffold structure]

### Glossary
- **a11y CI gate** — the minimal automated check (contrast + missing-label + `lang`/`dir`) that fails the build on regression; built here, run on every later UI story. [Source: epics.md UX-DR6]
- **direction provider** — the single owner of LTR↔RTL switching + `dir="auto"` field behavior; feature stories consume it, never re-decide RTL.

## Testing Requirements

Per CLAUDE.md (frontend checks) + Per-story DoD:
- **Component tests (Vitest + Testing Library):** each component renders all states; Button loading disables + shows spinner; TextInput error sets `aria-invalid` and links `aria-describedby`; StateBlock shows the right message per variant.
- **a11y tests (addon-a11y / axe via addon-vitest):** zero contrast/missing-label violations on every component story — **this is the gate**; a deliberately-broken story proves the gate fails the build (the gate's own test).
- **RTL test:** DirectionProvider flips `document.dir`; a component renders correctly under `dir="rtl"` using logical properties (no mirrored-only-in-LTR bug).
- **Contrast verification (Task 5):** record measured ratios for `inputBorder` (≥3:1) and body text (≥4.5:1) in both modes.
- Lint (eslint 10 + typescript-eslint) + `tsc` strict pass.

## Dev Agent Record

### Agent Model Used

{{agent_model_name_version}}

### Debug Log References

### Completion Notes List

- Ultimate context engine analysis completed — comprehensive developer guide created (2026-06-20).
- **Decision to record during implementation:** P1a dark-mode toggle scope (AC-5) — state it here.

### File List

## Implementation Status — Slice 1 (2026-06-20)

**Done (committed, frontend CI-clean: typecheck + lint + 8 Vitest tests):**
- **Design tokens** — `src/styles/tokens.css`: DESIGN.md colors (light + `[data-theme=dark]`, incl. `accent-btn`, `input-border`), fonts (Space Grotesk/Inter/Tajawal/IBM Plex Mono), radii, spacing, focus ring; base component classes.
- **DirectionProvider** (`src/app/DirectionProvider.tsx` + `direction-context.ts`) — live LTR↔RTL + light/dark; sets `dir`/`lang`/`data-theme` on `<html>`. (hook split out for `react-refresh/only-export-components`.)
- **Primitives** — `Button` (primary/secondary, loading→disabled+aria-busy), `Field` (label + `dir=auto` input + `aria-invalid`/`aria-describedby` error association), `StateBlock` (empty/error/offline). Each with Vitest tests.

**Deferred to Slice 2 (fresh session):**
- a11y **CI gate** (Storybook `addon-a11y` + `addon-vitest` axe checks; contrast + missing-label + lang/dir; fail build on regression) — the tooling-heavy part.
- `.stories.tsx` per component (Storybook).
- Remaining components: Banner/alert, Spinner+Skeleton, FormLayout primitive, AppShell, Link/text styles.
- Wire `DirectionProvider` + `tokens.css` into `main.tsx`/`App.tsx` (currently tokens are unimported until the shell lands).
- Contrast verification of `input-border` (≥3:1) with a tool.

**Decision recorded:** dark-mode ships as token set + provider mechanism; a user-facing toggle is later (operator settings).

## Implementation Status — Slice 2 (2026-06-20)

**Done (frontend CI-clean: typecheck + lint + 14 Vitest tests):**
- Components: `Banner` (info/error/success, role alert/status), `Spinner` + `Skeleton` (`Loading.tsx`; skeleton aria-hidden/busy, shimmer respects `prefers-reduced-motion`), `Link`, `FormLayout`, `AppShell` (two-zone rail+work, logical props for RTL).
- Wired `DirectionProvider` + `tokens.css` into `main.tsx` (the app shell now mounts the foundation).
- CSS classes for the new components added to `tokens.css`.

**Still deferred to Slice 3 (the tooling-heavy part):**
- a11y **CI gate** — Storybook `addon-a11y`/`addon-vitest` axe checks (contrast + missing-label + lang/dir) failing the build on regression.
- `.stories.tsx` per component.
- `input-border` contrast verification with a tool.
