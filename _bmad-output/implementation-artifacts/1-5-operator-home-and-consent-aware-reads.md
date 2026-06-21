---
baseline_commit: 1a02898d4267547ca9ba2ee4435895aa853c2b09
---
# Story 1.5: Operator Home and consent-aware reads (day-one states)

Status: review

> **Epic 1, final story.** Replaces the minimal Home stub (left by Story 1.1) with the real operator Home: current cohorts, a **single next action**, and **day-one empty states** so a brand-new org is never a blank screen. Introduces the **`ConsentProvider` seam (FR-006/NFR-006)** — the first consent-aware profile-read call site, enforced against the Startup Gate mock. Adds the missing **tenant-scoped cohort list** read API that Home needs. Pure read/UI slice — no new write paths. Closes Epic 1's frontend track.

## Story

As an **operator**,
I want a Home that shows my cohorts and the one next action, with sensible day-one empty states,
So that a brand-new organization is not a blank screen.

## Acceptance Criteria

1. **Cohort list + single next action (FR-009 read):** Home shows the operator's current cohorts (name + status) and **one** next action derived from state: a cohort with submissions → "**N submissions to score**" (links to that cohort); cohorts but none with submissions → "**open a cohort**"; zero cohorts → the day-one empty state (AC-2). Exactly one primary next action is shown — never a list of competing CTAs, and **not** the deferred Action Center.
2. **Day-one zero/empty state:** with zero cohorts, Home renders a `StateBlock variant="empty"` that **explains the first action** in words ("Create a program, then open a cohort to start receiving applications") and links to `/programs` — not a blank screen, not an error, not the Action Center.
3. **Consent-aware profile read (FR-006, NFR-006):** the operator's profile is read through the **`ConsentProvider` seam** against the mock (`GET /me/profile`). When the mock grants consent, Home greets the operator by profile name; when consent is **denied/unavailable**, the seam surfaces a neutral "consent required" affordance and Home still renders (no crash, no leaked profile data). FR-006 is satisfied as an **enforced seam** — every profile read on Home routes through `useConsent()`/`ConsentProvider`, never a raw `fetch`. [Source: prds/.../prd.md FR-006]
4. **Cohort read API (server):** `GET /api/v1/cohorts` returns the tenant's cohorts (BelongsToTenant-scoped; cross-tenant cohorts never appear — AR-6) with a `submissions_count`; `viewAny` authorizes any tenant member. Empty array when the tenant has none.
5. **RTL + light/dark (UX-DR5/6):** Home renders correctly in all four targets — {light, dark} × {LTR, RTL}. Interpolated values in copy (org name, profile name, cohort names, the submission count) are `bdi`-isolated; numerals stay Western; layout mirrors via logical properties only (no physical left/right). Home passes the existing axe a11y gate.

## Tasks

### Backend — cohort list read API (the one missing read Home needs)
- [x] **`Cohort::submissions()` relation** — added `HasMany<ApplicationSubmission, $this>` on `Cohort`.
- [x] **`CohortController@index`** — `GET /api/v1/cohorts`: `authorize('viewAny')` + `Cohort::query()->withCount('submissions')->orderByDesc('created_at')->get()` → `CohortResource::collection`. No manual org filter (BelongsToTenant scope).
- [x] **Route** — `cohorts.index` added in the `['auth:sanctum','tenant']` group, above `cohorts.show`.
- [x] **`CohortResource`** — `'submissions_count' => $this->whenCounted('submissions')` (list-only).
- [x] **Feature test** `tests/Feature/Cohorts/CohortIndexTest.php` — 5 tests (count, AR-6 isolation, empty, viewAny without cohorts.manage, 401). All green (17 assertions).

### Frontend — consent seam
- [x] **`schemas/profile.ts`** — tolerant `profileSchema = z.record(z.string(), z.unknown())` + `profileDisplayName()` helper + `ConsentError extends ApiError<'CONSENT_REQUIRED' | 'UNAUTHENTICATED' | 'UNKNOWN'>`.
- [x] **`api/profile.ts`** — `getProfile()`; 403 → `CONSENT_REQUIRED`, 401 → `UNAUTHENTICATED`, else zod-parse. Single profile-read call site.
- [x] **`app/consent-context.ts` + `app/ConsentProvider.tsx`** — `useConsent()` + provider running `useQuery(['profile'])`, exposing `{ status: 'loading'|'ready'|'consent-required'|'error', profile? }`. Mirrors the `DirectionProvider`/context split.

### Frontend — cohorts read + Home
- [x] **`schemas/cohorts.ts`** — `cohortSchema` mirroring `CohortResource` (status enum, nullable fields, optional `submissions_count`) + `cohortListResponseSchema`.
- [x] **`api/cohorts.ts`** — `listCohorts()` mirroring `listPrograms`.
- [x] **`pages/HomePage.tsx`** — rebuilt: consent-aware greeting, cohort list (status badge, text-not-colour), single next action (`nextAction()` derivation), day-one empty `StateBlock` → `/programs`, loading Spinner, error+retry. `// Story 1.5:` deferral comment removed.
- [x] **`app/App.tsx`** — `ConsoleGate` render subtree wrapped in `<ConsentProvider>`; no new route.

### Frontend — tests, stories, gates
- [x] **`pages/HomePage.test.tsx`** — 7 tests: day-one empty, "N submissions to score", "open a cohort", consent-granted greeting, consent-denied (no leak/crash), cohort-load error+retry, RTL+dark render with `bdi`.
- [x] **`api/cohorts.test.ts`** — happy/empty/non-ok/malformed (4 tests).
- [x] **`app/ConsentProvider.test.tsx`** — ready/consent-required/error states + outside-provider throw (4 tests).
- [x] **RTL/light-dark render test** — folded into `HomePage.test.tsx` (RTL+dark, `bdi` assertion).
- [x] **`pages/HomePage.stories.tsx`** — DayOne, WithSubmissions, ConsentRequired, Arabic.
- [x] **`tests/a11y.test.tsx`** — Home axe case swapped to the real Home (inside `ConsentProvider`); zero violations.

## Dev Notes

### Current state of files this story changes (READ before editing)
- **`pages/HomePage.tsx`** — minimal stub: `AppShell` + `<h1>{organization.name}</h1>` + a welcome `<p>`, carrying a `// Story 1.5:` deferral comment. This story replaces the body; **keep** the `{ organization }: { organization: Organization }` prop contract (ConsoleGate passes `orgs[0]`). [Source: frontend/src/pages/HomePage.tsx]
- **`app/App.tsx`** — `ConsoleGate` render-prop resolves `session` → `organizations`; root and `/programs` both render through it (`children(orgs[0])`). No router — `resolveRoute()` regex-matches `window.location.pathname`. Wrap the console subtree in `ConsentProvider`; **don't** add a Home route (root already maps to Home). Preserve the existing login/no-org/loading branches. [Source: frontend/src/app/App.tsx L33–116]
- **`CohortController`** — has `store`/`show`/`update` only; **no `index`**. Add it; mirror the `ProgramController@index` shape. `show` uses `Cohort::query()->findOrFail()` (tenant-scoped) — `index` is the same minus the id. [Source: backend .../Cohorts/Http/CohortController.php]
- **`CohortResource`** — fixed field set; add `submissions_count` via `whenCounted` so it's list-only and never `null` on show. [Source: CohortResource.php]
- **`routes/api.php`** — cohort routes live in the `['auth:sanctum','tenant']` group (L74–80); the new `GET /cohorts` belongs there, **above** `cohorts/{id}` is unnecessary (distinct path) but group placement matters for tenant middleware. [Source: routes/api.php]

### What must be preserved (story must leave the system working end-to-end)
- **Tenant isolation is the global scope's job.** `BelongsToTenant` fail-closes; the index must **not** add a manual `organization_id` filter (redundant) and must **not** `withoutGlobalScope('tenant')` (that's only for the public apply resolver). Cross-tenant cohorts must be invisible — assert it (AR-6). [Source: CLAUDE.md rule 7; OrganizationPolicyTest pattern]
- **ConsoleGate's `organization` prop** stays the source of org identity on Home — do not re-fetch orgs inside Home.
- **No new write paths, no entitlement gates.** This is reads + UI. `cohort.open` (the gated action) belongs to Story 1.4, already shipped.
- **`/me` (local projection) is NOT a profile read** and is not consent-gated — it's the local user row. The consent seam wraps **`/me/profile`** (the Startup Gate passthrough), which is the consent-bearing read. Don't route `/me` through the seam.

### Consent seam — the FR-006 contract (precise)
FR-006 is "done for P1a when the `ConsentProvider` interface is enforced at **every profile-read call site** against the mock." Concretely for this story: (1) the only profile read on Home goes through `useConsent()`; (2) a denied-consent response renders a neutral affordance, never a crash or a partial/leaked profile; (3) a test exercises the denied path. Production consent integration is **FR-157** (out of scope). Don't over-build: one provider, one call site, one hook — no caching layer, no per-field consent matrix. [Source: prds/.../prd.md FR-006; epics.md §Epic-1 FR-006/NFR-006]

### "N submissions to score" — derivation
Scoring itself is **Epic 3** (not built). In P1a "submissions to score" = the **submitted count** per cohort, taken from `submissions_count` on the cohort index (one call, no N+1). The single next action picks the cohort with the most pending submissions; ties → most recent. If no cohort has submissions, fall back to "open a cohort". This keeps Home a single read (`GET /cohorts`) plus the one profile read.

### Conventions / CI guardrails
- **Backend:** PHPStan L6 — annotate the `submissions()` relation generics (`HasMany<ApplicationSubmission, $this>`); `whenCounted` returns are fine. Pint clean. Tests on SQLite. Add the test under `tests/Feature/Cohorts/`.
- **Frontend:** TS strict; zod-parse every response at the boundary; typed `ApiError` subclasses (no raw throws for expected states); `credentials:'include'` on every call. Reuse `firstValidationMessage`/`readValidationDetails` from `api/errors.ts` only if a 422 path appears (it shouldn't here — reads).
- **a11y:** status as **text + badge**, never colour-alone; `StateBlock` already sets `role`; single `<h1 id>` per surface with `aria-labelledby`.
- **RTL:** `<bdi>` every interpolated value in copy (org/profile/cohort names, counts); logical properties only; the existing `DirectionProvider` drives `dir`/`lang`/`data-theme` — consume it, never re-decide RTL. [Source: epics.md UX-DR5]

### Reuse (don't reinvent)
- `StateBlock` (empty/error/offline) — the day-one and error states. [components/StateBlock.tsx]
- `AppShell`, `Banner`, `Button`, `Spinner`, `Link` — existing primitives.
- `DirectionProvider`/`useDirection` — theme+direction; mirror its context/provider split for `ConsentProvider`.
- `listPrograms`/`programs.ts` + `programSchema` — the exact api+schema pattern to copy for cohorts.
- `ConsoleGate`'s `useQuery` pattern (`retry:false`, `staleTime`) for the cohorts/profile queries.

### Source map
- Story/ACs: `epics.md` L224–236 · FR-006: `prds/prd-Catalesta-2026-06-20/prd.md` L103 · NFR-006: `prd.md` L182 · UX-DR5/6: `epics.md` L107–108.
- Backend: `CohortController.php`, `CohortResource.php`, `CohortPolicy.php` (viewAny=true), `routes/api.php` L74–80, `SubmissionController.php` (count source), `MeController@profile`.
- Frontend: `HomePage.tsx`, `App.tsx` L33–116, `api/programs.ts`, `schemas/organizations.ts`, `api/session.ts`, `components/StateBlock.tsx`, `app/DirectionProvider.tsx`, `tests/a11y.test.tsx`.

## Dev Agent Record
### Agent Model Used
claude-opus-4-8[1m]

### Completion Notes List
- **Backend (one real gap):** added the missing tenant-scoped cohort list. `Cohort::submissions()` HasMany → `withCount` on `CohortController@index`; `submissions_count` exposed list-only via `whenCounted`; `GET /v1/cohorts` route in the `auth:sanctum,tenant` group. Isolation is the `BelongsToTenant` global scope (no manual org filter); AR-6 cross-tenant invisibility asserted. 5 feature tests, PHPStan L6 + Pint clean.
- **Consent seam (FR-006):** `ConsentProvider` + `useConsent()` wraps the single profile-read call site (`getProfile` → `/me/profile`). 403 → `CONSENT_REQUIRED` is a first-class `consent-required` state, not an error — Home degrades to a neutral greeting, never leaking/crashing. Wired at `ConsoleGate` so all console surfaces are consent-aware.
- **Home:** cohort list + single derived next action ("N submissions to score" → cohort's submissions path; else "Open a cohort"; day-one → empty `StateBlock` → `/programs`). All interpolated values `bdi`-isolated; status as text+badge; renders in RTL+dark.
- **Contract:** new route made the OpenAPI baseline stale → regenerated via `scramble:export` and committed `openapi/openapi.json`.
- **Verification:** backend 364 pass / 1 pre-existing skip; frontend 90 pass, typecheck + eslint clean.
- **Deferred (per AC / noted):** the `/cohorts/{id}/submissions` SPA route lands with Story 2.8's UI — until then the next-action deep link resolves to Home via the catch-all (soft fallback). Action Center remains deferred.

### File List
**Backend**
- `backend/app/Modules/Cohorts/Domain/Models/Cohort.php` (M — `submissions()` HasMany)
- `backend/app/Modules/Cohorts/Http/CohortController.php` (M — `index`)
- `backend/app/Modules/Cohorts/Http/Resources/CohortResource.php` (M — `submissions_count`)
- `backend/routes/api.php` (M — `cohorts.index`)
- `backend/tests/Feature/Cohorts/CohortIndexTest.php` (NEW)
- `backend/openapi/openapi.json` (M — regenerated)

**Frontend**
- `frontend/src/schemas/profile.ts` (NEW)
- `frontend/src/api/profile.ts` (NEW)
- `frontend/src/app/consent-context.ts` (NEW)
- `frontend/src/app/ConsentProvider.tsx` (NEW)
- `frontend/src/schemas/cohorts.ts` (NEW)
- `frontend/src/api/cohorts.ts` (NEW)
- `frontend/src/pages/HomePage.tsx` (M — rebuilt)
- `frontend/src/app/App.tsx` (M — ConsentProvider wrap)
- `frontend/src/pages/HomePage.test.tsx` (NEW)
- `frontend/src/api/cohorts.test.ts` (NEW)
- `frontend/src/app/ConsentProvider.test.tsx` (NEW)
- `frontend/src/pages/HomePage.stories.tsx` (NEW)
- `frontend/src/tests/a11y.test.tsx` (M — Home axe case)

### Change Log
- 2026-06-21 — Story 1.5 implemented: operator Home (cohort list + single next action + day-one empty state), `ConsentProvider` consent-aware profile-read seam (FR-006/NFR-006), and tenant-scoped `GET /cohorts` read API with `submissions_count`. Status → review.
- 2026-06-21 — Code-review fixes (3 applied): (1) `ConsentProvider` keeps the last-good profile so a transient background-refetch error no longer drops the granted name; (2) `ConsentProvider` scoped to the Home route in `App.tsx` (removes a wasted `/me/profile` fetch on `/programs`, tightens FR-006 to the consuming call site); (3) "N submissions to score" rendered as text (not a self-looping link) until Story 2.8 ships the scoring UI. Frontend 90 tests + typecheck + lint green.

## Open Questions (resolved with defaults; flag in review if wrong)
- **Q1 — profile name source.** Default: greet using `/me/profile` `display_name`/`name` (consent-gated). The local `/me` `display_name` is the non-consent fallback identity but is **not** used for the greeting, so the consent seam is genuinely exercised. If the mock's `/me/profile` always consents in P1a, the denied path is still covered by a test that stubs a `CONSENT_REQUIRED` response.
- **Q2 — does the mock return a distinct consent-denied shape?** Default: treat **HTTP 403** from `/me/profile` as `CONSENT_REQUIRED`; if the mock signals consent via a body flag instead, map that in `api/profile.ts` (dev verifies against the ProfileProvider mock).
- **Q3 — Action Center.** Explicitly **deferred** (per AC). Home shows exactly one next action; the multi-item Action Center is a later epic.
