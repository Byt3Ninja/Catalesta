# FE UI Rebuild — Slice 0: Foundation (shadcn/ui + Tailwind 4 + MSW)

**Date:** 2026-06-26
**Parent:** `2026-06-26-fe-ui-rebuild-program-map-design.md`
**Supersedes:** the "pin Tailwind 3" line in
`2026-06-26-shadcn-tailwind-foundation-design.md` (Tailwind **4** chosen — see below)
**Status:** Draft — pending user review

## Problem

The frontend renders as near-raw HTML on a leftover template stylesheet
(`src/index.css` holds purple template tokens; real tokens live in
`src/styles/tokens.css`). Only low-level `ds-*` primitives exist and `AppShell`
is a 12-line stub. There is no applied app frame, no design system, and no way
to render data-backed screens without the Docker backend. Slice 0 fixes the
foundation so every later slice has a paved road.

## Decision (locked)

Adopt **shadcn/ui on Tailwind 4** as the single design system (full replacement
of `tokens.css` + `ds-*`), build the real **AppShell** with a **context
selector**, add an **MSW** mock-data harness, and prove it on two flagship pages
(Login, Programs).

### Locked choices
- **Tailwind 4** (CSS-first): `@tailwindcss/vite` plugin, `@import "tailwindcss"`
  + `@theme`/CSS variables in one `index.css`. No `tailwind.config.ts`, no
  `postcss.config`. Class-based dark via `@custom-variant dark (&:is(.dark *))`.
- **shadcn** in Vite/manual mode, components owned in `src/components/ui/`.
- **Replacement model:** full replace; shadcn is the only system.
- **Preserved first-class:** RTL + Arabic (Tajawal), dark mode, a11y + contrast
  suites.
- **Visual direction:** fresh neutral SaaS (Linear/Vercel). Base **zinc**, accent
  **indigo `#6366f1`**.
- **Mock data:** MSW intercepts the real `api/` calls (per parent spec).

## Scope

### A. Tooling & file layout
- Add deps: `tailwindcss@^4`, `@tailwindcss/vite`, `clsx`, `tailwind-merge`,
  `class-variance-authority`, `lucide-react`, `tw-animate-css` (the Tailwind-v4
  animation utilities shadcn now uses); dev dep `msw`.
- `vite.config.ts`: add `@tailwindcss/vite` plugin **and** `resolve.alias`
  `{ '@': '/src' }`.
- `tsconfig.app.json`: add `"baseUrl": "."` + `"paths": { "@/*": ["src/*"] }`.
- `src/lib/utils.ts`: export `cn()` (`clsx` + `twMerge`).
- `components.json`: shadcn config in Tailwind-v4 mode (`"tailwind": { "config":
  "", "css": "src/index.css", "baseColor": "zinc", "cssVariables": true }`,
  aliases pointing at `@/components`, `@/lib/utils`).
- **One stylesheet:** rewrite `src/index.css` → `@import "tailwindcss";` +
  `@custom-variant dark` + `@theme`/`:root`/`.dark` variables. **Delete
  `src/styles/tokens.css`.** `main.tsx` imports only `./index.css`.

### B. Theme — zinc + indigo, light / dark / RTL
- shadcn CSS variables for `:root` (light) and `.dark`; `--primary` = indigo
  `#6366f1`; zinc neutral ramp. Map to Tailwind v4 `@theme inline` tokens
  (`--color-background`, `--color-primary`, …).
- **Dark mode:** new `src/app/ThemeProvider.tsx` (+ `theme-context.ts`) toggles
  `.dark` on `<html>`, persisted to `localStorage` (`catalesta.theme`), default
  = system preference. Header gets a theme toggle.
- **RTL:** keep `src/app/DirectionProvider.tsx` (already sets `dir` on `<html>`).
  Use Tailwind logical utilities (`ps-/pe-/ms-/me-`, `start/end`) + `rtl:`
  variants; no extra plugin. `font-family` switches Tajawal (`dir=rtl`) / Inter
  (LTR) via a CSS rule keyed on `[dir="rtl"]`.
- Re-point `src/styles/contrast.ts` token pairs to the new zinc/indigo hex values
  so the contrast gate (`src/tests/`/contrast test) stays green.

### C. Primitives rewritten in place (same import paths + props)
Generated via shadcn, then the existing components rewritten to wrap them at the
**same import paths and prop shapes** so unmigrated pages keep working:
- `components/Button.tsx` → wraps `ui/button` (variants:
  default/secondary/outline/ghost/destructive; `loading` prop preserved).
- `components/Field.tsx` → `ui/label` + `ui/input` + error text, keeping the
  `label/error/help` props and `aria-describedby`/`aria-invalid` wiring.
- `components/Banner.tsx` → `ui/alert` (info/success/destructive).
- `components/Loading.tsx` → `Loader2` spinner, `label` prop preserved.
- `components/StateBlock.tsx` → empty/loading/error block on `ui/card`.
- `components/Link.tsx` → styled `react-router` `Link`.

### D. AppShell rewrite + Context Selector
Rewrite `components/AppShell.tsx` as the real frame:
- **Header (sticky):** brand, active-org name, theme toggle, context selector.
- **Sidebar:** task-oriented nav (per `docs/ux/navigation.md`: Program Journey /
  Application Setup / Selection / Delivery …); collapses to a shadcn `Sheet` on
  mobile.
- **Page container:** max-width, padding, a `pageHeader` slot (title + actions).
- Keep the `rail`/`children` call sites working (adapter props) so existing pages
  that pass a `rail` don't break.
- **Context Selector** (`components/ContextSelector.tsx`, NEW): role / org /
  program / cohort switcher. Org reads from the active-org holder
  (`api/tenant.ts`); role/program/cohort are presentational stubs in this slice
  (full wiring in slice 1/2). Hidden items when only one option.

### E. MSW harness
- `src/mocks/handlers.ts`: handlers for `GET /session`, `GET /organizations`,
  `GET /programs`, `GET /cohorts` returning fixtures **typed against
  `src/schemas/`** (drift → typecheck/parse failure).
- `src/mocks/browser.ts`: `setupWorker(...handlers)`.
- `public/mockServiceWorker.js`: generated via `msw init public/`.
- `main.tsx`: `if (import.meta.env.DEV && import.meta.env.VITE_USE_MOCKS !==
  'false') await worker.start({ onUnhandledRequest: 'bypass' })` before render.
- `.env.example`/docs note: `VITE_USE_MOCKS` (default on in dev). Slice 9 flips it
  off to hit the live backend.

### F. Flagship pages
- **`LoginPage`** — centered branded auth `Card`; existing form/mutation behavior
  unchanged, only re-skinned.
- **`ProgramsPage`** — `AppShell` + page header (title + "New program" button) +
  card/table list + proper empty/loading/error (`StateBlock`) states; renders
  from MSW fixtures.

### G. Tests / stories / quality gates
- Rewrite the touched primitive tests + `LoginPage`/`ProgramsPage` tests to
  assert on roles/text/behavior, not class names.
- Grep for `ds-` across `src` (source + tests); fix all assertions; delete
  `tokens.css` only once zero references remain.
- Update Storybook stories for touched primitives; `.storybook/preview.tsx`
  imports the new `index.css`.
- MSW in Vitest: a `src/mocks/server.ts` (`setupServer`) wired in the test setup
  for component tests that exercise the real `api/` calls, OR keep existing
  per-test `fetch` mocks — pick per-test, don't force a global rewrite.
- Playwright (`tests/e2e/`) for Login + Programs runs against the dev server with
  MSW on — **no Docker dependency**. (Real-backend e2e: `VITE_USE_MOCKS=false`.)
- Gates green: `lint`, `vitest`, `playwright` (Login/Programs), `build-storybook`,
  a11y + contrast suites.

## Acceptance criteria
1. Tailwind 4 + shadcn installed; `@/*` resolves in app **and** tests; `cn()`
   available; `@tailwindcss/vite` active.
2. Single `index.css` (Tailwind import + theme); `tokens.css` deleted; no `ds-*`
   references remain in source or tests.
3. Theme renders zinc/indigo in light + dark; dark toggle persists; RTL flips
   layout and switches to Tajawal.
4. All listed primitives + `AppShell` (with context selector) rebuilt on shadcn
   at original import paths/props; unmigrated pages still render.
5. MSW serves session/orgs/programs/cohorts in dev; Login + Programs render with
   **no backend running**; `VITE_USE_MOCKS=false` bypasses MSW.
6. Login + Programs fully re-skinned with the app frame + loading/empty/error.
7. `lint`, `vitest`, `playwright` (Login/Programs), `build-storybook` pass; a11y +
   contrast suites pass against the new tokens.

## Out of scope (later slices)
Per-page polish of the other ~13 existing pages (auto-restyled via shared
primitives, polished in their slices). All NEW screens from the parent inventory.
No backend/auth/tenancy changes.

## Risks / call-outs
- **Tailwind 4 vs shadcn templates:** most shadcn snippets assume v4 now; any v3
  remnant (e.g. `tailwind.config.ts` references) is removed during planning.
- **Test churn:** rewriting shared primitives breaks `ds-*` class assertions;
  bounded — counted and fixed in this slice.
- **MSW + Vitest interaction:** jsdom + MSW node server must not double-mock with
  existing per-test `fetch` stubs; resolved per-test, not globally.
- **a11y/contrast regressions:** contrast gate re-pointed to new tokens before
  primitives land; suite must stay green.
- **`verbatimModuleSyntax`/`erasableSyntaxOnly`** in `tsconfig.app.json`: shadcn
  components are plain TSX and comply; watch for any `enum`/namespace snippet
  (rewrite to `const` maps if encountered).
