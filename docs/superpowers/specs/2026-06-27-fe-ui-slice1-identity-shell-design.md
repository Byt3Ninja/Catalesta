# FE UI Rebuild — Slice 1: Identity & Shell Complete

**Date:** 2026-06-27
**Parent:** `2026-06-26-fe-ui-rebuild-program-map-design.md`
**Builds on:** Slice 0 (shadcn/ui + Tailwind 4 + AppShell + MSW), merged to `main` (#55/#57).
**Status:** Draft — pending user review

## Goal

Complete the application shell and all identity/entry surfaces on the new design
system, and introduce a **mocked multi-role experience** so every role's Action
Center home and navigation can be previewed end-to-end. UI-first: all data from
MSW; real APIs in the slice-9 wiring pass.

## Locked decisions

1. **Mock multi-role experience.** A prototype role concept drives the Action
   Center variant + sidebar nav; the context selector switches role.
2. **All 10 roles** get a mocked Action Center variant (Founder, Co-Founder,
   Mentor, Trainer, Evaluator, Judge, Service Provider, Program Manager, Program
   Coordinator, Organization Administrator).
3. **Profile = view + consent only** (the general profile is a consent-gated,
   read-mostly external Startup Gate resource; no edit form this slice).
4. **Two build plans (sequential):** 1a (shell & identity), then 1b (personal
   surfaces).
5. **No backend/auth/tenancy changes.** Presentation + MSW fixtures only.

## Build decomposition

| Plan | Contents | Screens/units |
|------|----------|---------------|
| **1a** | Role/context foundation, Action Center (10 roles), 7 auth/onboarding re-skins | role store + context selector + nav + ActionCenter + 7 pages |
| **1b** | Profile (view) + Consent, Notifications center + preferences, Global search | ~5 screens |

Each plan = its own implementation plan, built and reviewed before the next.

## 1a — Shell & identity

### Role/context foundation
- **Prototype role contract (MSW, invented — no backend yet):**
  `GET /api/v1/me/roles` → `{ data: Role[] }` where
  `Role = { key: string; label: string }`. `key` is one of the 10 role keys
  (`founder`, `co_founder`, `mentor`, `trainer`, `evaluator`, `judge`,
  `service_provider`, `program_manager`, `program_coordinator`, `org_admin`).
  The demo user is given several roles so the switcher is exercised.
- **Active-role store** `src/app/active-role.ts` — module-level holder with a
  subscription so a role switch re-renders live: `getActiveRole()`,
  `setActiveRole(key)`, `subscribe(cb)`, and a `useActiveRole()` hook built on
  `useSyncExternalStore`. (This is a deliberate step up from Slice 0's render-time
  active-org read, which can't notify subscribers — role switching needs live
  re-render.) Default = `program_manager` (today's operator surface).
- **ContextSelector** (replace the Slice-0 stub): real dropdowns for **role**,
  **program**, **cohort** (program/cohort from existing programs/cohorts MSW;
  org already wired). Hidden when only one option. Switching role updates the
  store → re-renders Action Center + nav.
- **Role-scoped nav** `src/app/role-nav.ts` — a map from role key to its
  task-oriented sidebar items (per `docs/ux/navigation.md` / `strategy.md`:
  Founder = Overview/My Application/Required Actions/Program Journey/Sessions/
  Training/Documents/Messages/My Startup; Program Manager = Program Overview/
  Applicants/Selection/Participants/Program Delivery/Mentors & Trainers/Final
  Evaluation/Reports/Configuration; the other 8 roles get concise task-oriented
  item sets). `AppShell` consumes this for the sidebar.
- **Unbuilt destinations:** nav items whose screens arrive in later slices route
  to a neutral `ComingSoonPage` (`"This screen arrives in a later slice."`)
  inside the shell — the prototype stays navigable and honest, never 404s.

### Action Center (data-driven, all 10 roles)
- **`ActionCard`** component — renders the fixed card structure from
  `dashboard.md`: what is required, why it matters, deadline, responsible person,
  direct action link, blocking dependency. Text-first (a11y), status never
  colour-alone.
- **`ActionCenter`** home — renders the 8 prioritized sections in order
  (Required actions, Deadlines, Current stage, Upcoming sessions, Blocked items,
  Recent decisions, Progress, Relevant opportunities) from role-scoped fixtures;
  empty sections collapse; a clear empty-state for a day-one role.
- Replaces today's operator-only `HomePage` as the home surface; the existing
  consent-aware greeting (name only when consent granted) is preserved.
- **MSW:** `GET /api/v1/me/action-center?role=<key>` → role-scoped fixture per
  role (10 fixtures). Invented contract; typed locally.

### Auth/onboarding re-skin (behavior unchanged; shadcn presentation only)
`RegisterPage`, `ForgotPasswordPage`, `ResetPasswordPage`, `EmailVerifiedPage`,
`VerifyEmailNotice`, `AuthCallbackPage`, `OnboardingPage` → branded shadcn
cards/forms matching the Slice-0 `LoginPage` pattern. Existing forms, mutations,
react-query, and error states are kept; only markup changes. Tests updated to
assert roles/text, not `ds-*`.

## 1b — Personal surfaces

### Profile (view) + Consent
- **ProfilePage** — consent-aware: renders known fields from the tolerant
  `profileSchema` map; on `CONSENT_REQUIRED` (403) shows the neutral
  consent affordance (reuses the existing `ConsentError`/`ConsentProvider` seam),
  not an error. Uses the existing `api/profile.ts` client.
- **ConsentManagementPage** — grant/revoke toggles per data category; mock
  consent state via MSW. Granting re-fetches the profile.
- **MSW:** `GET /api/v1/me/profile` with a flag to return 403 (consent required)
  vs 200 (granted), driven by the mock consent state.

### Notifications
- **NotificationsCenter** — list with unread badge, mark-read (single + all),
  filter by type; empty state. Invented contract:
  `GET /api/v1/notifications` → `{ data: Notification[] }`,
  `Notification = { id, type, title, body, created_at, read_at: string|null,
  href: string|null }`; `POST /api/v1/notifications/{id}/read` (mock).
- **NotificationPreferencesPage** — minimal channel toggles (email/in-app) +
  frequency; presentational (mock persistence).

### Global search
- **GlobalSearch** — header-triggered type-ahead + a results surface with
  categorized results (people / programs / cohorts / documents). Invented
  contract: `GET /api/v1/search?q=` → `{ data: { category, items[] }[] }`.
  Debounced; empty-query and no-results states.

## Architecture / file structure

- `src/app/active-role.ts` — active-role holder + subscription hook.
- `src/app/role-nav.ts` — role → nav items map.
- `src/api/roles.ts`, `src/api/actionCenter.ts`, `src/api/notifications.ts`,
  `src/api/search.ts` — typed clients (consumed by screens; backed by MSW).
- `src/schemas/{roles,actionCenter,notifications,search}.ts` — Zod schemas for
  the invented contracts (so MSW fixtures are typed and the slice-9 wiring has a
  contract to meet).
- `src/components/ActionCard.tsx`, `src/pages/ActionCenterPage.tsx`,
  `src/pages/ComingSoonPage.tsx`, `src/pages/ProfilePage.tsx`,
  `src/pages/ConsentManagementPage.tsx`, `src/pages/NotificationsPage.tsx`,
  `src/pages/NotificationPreferencesPage.tsx`, `src/pages/SearchPage.tsx`.
- Extend `src/mocks/handlers.ts` with the new endpoints.
- Routes added to `src/app/App.tsx` (gated by `ConsoleGate`).

Each new file has one responsibility; fixtures live beside the handlers.

## Testing / quality gates

- Each re-skinned auth page + each new screen: Vitest tests asserting
  roles/text/behavior (not classes), reusing the existing `fetch`-mock pattern.
- Role switching: a test that changing the active role re-renders the Action
  Center sections + nav.
- Consent: a test that `CONSENT_REQUIRED` renders the neutral affordance, not an
  error.
- One Playwright (MSW, no backend) per plan: 1a — sign in → switch role → Action
  Center + nav change; 1b — open Notifications, mark read; run Global search.
- Gates per plan: `typecheck`, `lint`, `vitest`, `playwright`,
  `build-storybook`, a11y + contrast suites all green.

## Out of scope (later slices)
- Real API wiring (slice 9) — all data is MSW here.
- Profile editing; the destination screens behind role nav (Applicants, Sessions,
  etc.) — those are slices 2–5; here they're `ComingSoonPage` placeholders.
- Notification/search backends; real RBAC/permission enforcement.

## Risks / call-outs
- **Invented contracts (roles, action-center, notifications, search)** have no
  backend yet — schemas are our best guess; slice 9 reconciles them. Keep them
  small and obvious.
- **10 role fixtures** are authoring-heavy; keep each Action Center fixture short
  (2–4 cards across a few sections) — enough to show the pattern, not a full
  dataset.
- **Role store is module-level mutable state** (like active-org) — reset it in
  `afterEach` of touched test suites to avoid cross-test leakage.
- **ComingSoonPage** must be unmistakably a placeholder so the prototype is never
  mistaken for a finished destination.

## Next step
Write the **Plan 1a** implementation plan (writing-plans), build it
subagent-driven, then Plan 1b.
