# FE-0: Router + Console Shell Foundation — Design

> Date: 2026-06-24 · Status: Approved (brainstorming) · Scope: first slice of the
> "complete the Phase-1a frontend end-to-end" initiative.

## Context

The frontend (`frontend/`, ~106 TS/TSX files) covers the Phase-1a flows whose
backend exists — auth, apply, submissions, a programs list, and the org/onboarding
gate. The decision (2026-06-23) is to make the six Phase-1a flows
(auth · organizations/memberships · programs · cohorts · apply · submissions)
genuinely end-to-end against the live API, then move to the next backend slice.

A structural blocker: **there is no router.** `App.tsx` matches routes by
hand-rolled regex on `window.location.pathname` (`resolveRoute()`). That holds for
~10 routes but won't scale to the operator-console screens the remaining slices
need (program detail/edit/publish, cohort management, members, org settings).

## Initiative roadmap (each slice = its own spec → plan → PR)

| Slice | Scope |
|---|---|
| **FE-0 (this spec)** | react-router foundation + console shell; migrate existing routes |
| FE-1 | Programs lifecycle UI (list → detail → create/edit → publish → clone) |
| FE-2 | Cohorts management UI (list/detail, open/close, form-version binding) |
| FE-3 | Members + Org settings UI (invite/list members, org settings/edit) |
| FE-4 | Cross-flow polish + E2E (rule-08 states, RTL, responsive, a11y, Playwright) |

## FE-0 goal

Replace the regex route resolver with `react-router-dom`, preserving all current
behavior exactly, and add a `ConsoleLayout` nav shell that the later console
screens mount into. **Behavior-preserving migration — no redesign, no new flows.**

## Architecture

- Add `react-router-dom` (v7, current stable) as a `frontend` dependency.
- `App.tsx`: replace `resolveRoute()` with `<BrowserRouter>` + a `<Routes>` tree.
  `QueryClientProvider` and `DirectionProvider` (RTL) stay at the root, outside the
  router.
- **Public routes** render the page directly (no gate): `/login`, `/register`,
  `/forgot-password`, `/auth/callback`, `/auth/reset-password`,
  `/auth/email-verified`, `/apply/:cohortId`, `/health`.
- **Console routes** render inside a new `<ConsoleLayout>` which:
  - wraps the **existing `ConsoleGate`** unchanged (session → email-verified →
    no-org onboarding → org), so the auth/verify/onboarding gating is preserved
    bit-for-bit;
  - renders a persistent nav shell (app header + sidebar) around an `<Outlet />`;
    nav links: Home, Programs, (Cohorts/Members/Settings links land in later
    slices — FE-0 ships Home + Programs links only, no dead links per rule 08);
  - passes the resolved `organization` to children via context (replacing the
    current render-prop `children(org)`), so pages read it from a
    `useCurrentOrganization()` hook.
- **Console route set migrated in FE-0** (existing surfaces only):
  - `/` → Home (inside `ConsentProvider`, as today)
  - `/programs` → ProgramsPage
  - `/cohorts/:cohortId/submissions` → SubmissionsPage
  - `/cohorts/:cohortId/submissions/:submissionId` → SubmissionDetailPage
- **Params** come from `useParams()` (with `decodeURIComponent` semantics preserved)
  instead of regex capture groups. Page component prop shapes are unchanged; thin
  route-element wrappers read params/org and pass them as the existing props, so
  page internals and their tests are untouched.

## Data flow

Unchanged. `ConsoleGate` still drives the `session` + `organizations` queries via
react-query; `ConsoleLayout` consumes the gate's resolved org and exposes it via
context. No new API calls. No change to `api/*` or `schemas/*`.

## Error / edge handling (preserved, not new)

- Unauthenticated on a console route → `ConsoleGate` renders `LoginPage` (current
  behavior). A catch-all `*` route renders the gate/Home decision for unknown
  console paths, matching today's "any other route → gate decides".
- Loading/error states inside the gate are unchanged.
- `DirectionProvider` continues to set `dir` at the root, so RTL is unaffected by
  the router.

## Testing (characterization-first — it's a migration)

1. Before changing `App.tsx`, ensure the existing `App.test.tsx` route assertions
   are the safety net; adapt them to drive routes via `MemoryRouter`
   (`initialEntries`) instead of stubbing `window.location.pathname`.
2. One test per migrated route: the correct page renders at its path.
3. Console-route guard: an unauthenticated session on `/programs` (and `/`)
   renders `LoginPage`.
4. Param routes: `/cohorts/abc/submissions/xyz` passes `cohortId=abc`,
   `submissionId=xyz` to `SubmissionDetailPage`.
5. Existing per-page tests (LoginPage, ApplyPage, SubmissionsPage, …) stay green
   untouched — proof the migration preserved behavior.
6. `npm run typecheck`, `npm run lint`, `npm run test` all green.

## Out of scope (FE-0)

- Any new screen (programs detail/edit/publish, cohorts, members, settings) — those
  are FE-1..FE-3.
- Visual/design-system changes beyond a minimal nav shell.
- Mobile/RTL/a11y polish pass — that's FE-4 (existing behavior preserved here).

## Risks

- **Route-coverage drift:** a path silently changing render output. Mitigated by a
  per-route test before/after.
- **`apply` deep-link + Sanctum stateful domains:** `/apply/:cohortId` must remain
  a public, directly-rendered route (no gate) — covered by an explicit test.
- **History mode:** `BrowserRouter` needs the dev server + container to serve
  `index.html` for unknown paths (SPA fallback). Verify Vite dev + the Docker
  `react-web` service already do (they serve the SPA today); note if a fallback
  rule must be added.
