# FE-UI Foundation — shadcn/ui + Tailwind 3

**Date:** 2026-06-26
**Slice:** FE-UI-1 (Foundation)
**Status:** Approved (design)

## Problem

The frontend renders as near-raw HTML. Two conflicting global stylesheets
(`src/index.css` template leftovers vs `src/styles/tokens.css` real tokens),
only low-level `ds-*` primitives exist, and there is no applied app frame
(header, sidebar, page container, surfaces, hierarchy). Pages look unstyled.

## Decision

Replace the custom `tokens.css` + `ds-*` component layer with **shadcn/ui on
Tailwind 3** as the single design system. This supersedes the project memory
note "no second design system without approval" — approval granted by the user
for this replacement.

### Locked choices
- **Replacement model:** full replace. shadcn/ui is the only system.
- **Preserved first-class:** RTL + Arabic (Tajawal), dark mode, accessibility
  (a11y + contrast suites).
- **Visual direction:** fresh neutral SaaS (Linear/Vercel admin aesthetic).
- **Palette:** base neutral **zinc**, accent **indigo `#6366f1`**.
- **Sequencing:** foundation slice first (tooling + theme + primitives + app
  shell + 2 flagship pages); remaining pages in later slices.

## Scope — Foundation Slice

### 1. Tooling & file layout
- Pin `tailwindcss@^3.4` + `postcss` + `autoprefixer`; add `tailwind.config.ts`
  + `postcss.config.js`. Tailwind **3** (init in shadcn's v3-compatible mode;
  shadcn now defaults to v4).
- shadcn in **Vite/manual mode**: `components.json`, `@/*` path alias added to
  `vite.config.ts` + `tsconfig`, `lib/utils.ts` exporting `cn()`
  (`clsx` + `tailwind-merge`). Components copied into `src/components/ui/`
  (owned in-repo, not an npm dependency). `lucide-react` for icons.
- **One global stylesheet:** rewrite `src/index.css` to hold `@tailwind`
  directives + theme CSS variables. Delete `src/styles/tokens.css` and the stray
  template content in `index.css`. `main.tsx` imports only `./index.css`.

### 2. Theme — zinc + indigo, light/dark/RTL
- shadcn CSS variables: zinc neutrals, `--primary` = indigo `#6366f1`, defined
  for `:root` (light) and `.dark`.
- **Dark mode:** Tailwind `darkMode:'class'`; small `ThemeProvider` toggles
  `.dark` on `<html>` (localStorage-persisted), replacing `data-theme`.
- **RTL:** keep `DirectionProvider` (sets `dir` on `<html>`); Tailwind logical
  utilities (`ps-/pe-/ms-/me-`, `start/end`) + `rtl:`/`ltr:` variants, no extra
  plugin. Tajawal for `dir=rtl`; Inter for LTR body.

### 3. Primitives (this slice)
shadcn-generated, then existing components rewritten **in place at the same
import paths + props** so unmigrated pages keep rendering and inherit the new
look:
- `Button` → ui/button (default/secondary/outline/ghost/destructive)
- `Field` → ui/label + ui/input + error text (react-hook-form friendly)
- `Banner` → ui/alert (info / success / destructive)
- `Loading` → Loader2 spinner
- `StateBlock` → empty/loading/error block on Card
- `Link` → styled react-router link
- **`AppShell`** rewritten as the real frame: sticky header (brand, active org
  name, theme toggle) + left sidebar nav (Programs, Cohorts, …) + page
  container (max-width, padding, page-header slot). Sidebar collapses on mobile.

### 4. Pages fully re-skinned (this slice only)
- **LoginPage** — centered branded auth card.
- **ProgramsPage** — shell + page header (title + "New program") + card/table
  list + empty/loading/error states.

These are the proven pattern. The other ~13 pages still render (auto-restyled
through the shared primitives) and receive full per-page polish in later slices.

### 5. Tests, stories, quality gates
- Rewrite tests for the touched primitives + 2 pages to assert on roles / text /
  behavior, not class names.
- Grep-and-fix any remaining `ds-*` class assertions elsewhere; delete
  `tokens.css` only once no references remain.
- Update Storybook stories for touched primitives; load `index.css` in
  `.storybook/preview`.
- Re-point `styles/contrast.ts` to the new zinc/indigo variables; keep the a11y
  suite green.
- Gates kept green for touched scope: `lint`, `vitest`, `playwright`
  (Login/Programs), `storybook build`.

## Out of scope (later slices)
Per-page polish of Register / Forgot / Reset / Verify / Onboarding / Apply /
Submissions / ProgramDetail / Cohorts / CohortDetail / Home / Health + members
section.

## Risks / call-outs
- **Bounded test churn:** rewriting shared primitives in place breaks any other
  test asserting `ds-*` classes. Exact count sized during planning; all fixed in
  this slice.
- **Partial polish:** unmigrated pages look better (new primitives) but not fully
  polished until their own slices.
- **No backend/tenant/auth/authorization changes.** Purely presentation. The
  `X-Organization-Id` header behavior and Sanctum session auth are untouched.

## Acceptance criteria
1. Tailwind 3 + shadcn installed; `@/*` alias resolves; `cn()` available.
2. Single `index.css`; `tokens.css` and stray template styles removed; no
   `ds-*` references remain in source or tests.
3. Theme renders zinc/indigo in light and dark; dark toggle persists; RTL flips
   layout and switches to Tajawal.
4. All listed primitives + `AppShell` rebuilt on shadcn at original import paths.
5. LoginPage and ProgramsPage fully re-skinned with the app frame and proper
   loading/empty/error states.
6. `lint`, `vitest`, `playwright` (Login/Programs), `storybook build` pass;
   a11y + contrast suites pass against the new tokens.
