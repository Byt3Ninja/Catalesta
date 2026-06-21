# Story 2.8: Operator submission list + funnel

Status: ready-for-dev

> **Epic 2 — operator side of the intake loop.** The operator sees the submissions their cohort received (FR-034) and a **real funnel** ("N viewed, M started, K submitted") telling them whether intake is working.
>
> **TWO SLICES.** **Slice 1 (DONE, merged)** delivered the tenant-scoped submission **read API** (FR-034). **Slice 2 (this pass)** builds what Slice 1 deferred — now unblocked because **Story 1.0 is done** and the Project Lead chose to build the **FR-080 Learning Telemetry substrate** so the funnel is whole: the telemetry events table + `application.viewed`/`application.started` emit points + the operator **Submissions UI** (list + detail + funnel, light/dark, LTR/RTL) + the funnel read API. This also makes Story 1-5's "N submissions to score" next-action a **real link** (it currently renders as text because this route did not exist).

---

## Slice 1 — Tenant-scoped submission read API (FR-034) — ✅ DONE (merged, baseline dc657f9)

Delivered: `SubmissionController` (list + detail), `SubmissionResource` (`reference_number`, `cohort_id`, `submitted_at`), `SubmissionDetailResource` (+ `snapshot`), `ApplicationSubmissionPolicy` (viewAny/view → true; isolation by tenant scope), routes, OpenAPI regen, `SubmissionListTest` (7 tests: ordering, empty, detail snapshot, per-cohort scoping, AR-6 cross-tenant 404 ×2, 401). `meta.total` = the authoritative `submitted` count. **Do not re-scope; Slice 2 builds on it.**

---

## Slice 2 — Learning Telemetry substrate + full funnel + operator UI (this pass)

### Acceptance Criteria
1. **Telemetry substrate (FR-080):** a new append-only `learning_events` table + a `LearningTelemetry` recorder. Events are **tenant-scoped** (explicit `organization_id`) and carry `cohort_id`, `event_name`, `occurred_at`. The three funnel events emit: `application.viewed`, `application.started`, `application.submitted`. Append-only at the DB level (UPDATE/DELETE rejected, like `audit_logs`). The model carries `BelongsToTenant` so `TenantIsolationArchTest` passes (NFR-001).
2. **Privacy (NFR-013/NFR-006):** public `viewed`/`started` events store **no actor identity and no IP** — only `organization_id`, `cohort_id`, `event_name`, `occurred_at`. (Applicants are pre-auth/pre-consent; telemetry must not become a PII store.)
3. **`viewed` emit (server):** `ApplyController::show` (public `GET /v1/apply/{cohort}`) records `application.viewed`, inside the existing `TenantContext::runAsSystem` resolution, stamping the **cohort's** `organization_id`. Best-effort — a telemetry failure must never break the public apply page.
4. **`started` emit (client → public beacon):** a new public best-effort endpoint `POST /v1/apply/{cohort}/events` (no auth, no tenant; cohort resolved under `runAsSystem`) records `application.started`. The client fires it once, on the **first answer entered** (`useApplyDraft.setAnswer`), deduped per cohort via the existing localStorage draft (no idempotency-kernel write — telemetry is best-effort by design).
5. **Funnel read API (operator):** `GET /v1/cohorts/{cohort}/funnel` (`auth:sanctum` + `tenant`) returns `{ viewed, started, submitted }` integers. `submitted` = the durable `application_submissions` count (authoritative, never the lossy telemetry count); `viewed`/`started` = telemetry counts. **Clamp `viewed = max(viewed, started)` server-side** (beacon-loss undercount). Cohort resolved tenant-scoped → cross-tenant **404** (AR-6).
6. **Operator Submissions screen:** new `/cohorts/{id}/submissions` route renders the funnel header + the submission list (consuming Slice-1's FR-034 API). The funnel shows `viewed`/`started`/`submitted` with **"views are approximate" microcopy** by `viewed`. Each list row exposes a **focusable "open detail" control** (a `Link`, distinct from any future bulk control). Loading → `Spinner`; error → `StateBlock` + retry.
7. **Zero-day funnel (UX):** when the cohort has **no submissions**, render the **empty state + a copyable share link** (the public apply URL) — never "0/0/0". Reuse the day-one/empty pattern; the share-link control copies `…/apply/{cohort}`.
8. **Submission detail:** an "open detail" control opens `/cohorts/{id}/submissions/{submission}` (a route, **not** a modal — preserves Story 1.0's modal deferral) rendering the immutable snapshot from Slice-1's detail endpoint.
9. **RTL + light/dark (UX-DR2/6):** the Submissions screen, funnel, and detail render in {light,dark}×{LTR,RTL}; interpolated values (`bdi`), Western numerals, focusable row control with visible focus ring; passes the axe gate.
10. **1-5 link handoff:** `HomePage.nextAction` "N submissions to score" becomes a real `Link` to `/cohorts/{id}/submissions` (was `<strong>` text pending this route).
11. **FR-080 DoD predicate:** a feature test drives a full `viewed → started → submitted` flow for one cohort and asserts all three counts increment and the funnel endpoint returns all three — closing the "events emit + are queryable" gap. The operator funnel screen is the human-looked-at surface.

### Tasks

#### Backend — telemetry substrate
- [ ] **Migration** `…_create_learning_events_table.php` — `ulid id` PK, `ulid organization_id` (index), `ulid cohort_id` (index), `string event_name` (index), `jsonb payload` nullable, `timestampTz occurred_at`, `timestampsTz`. Composite index `(cohort_id, event_name)` for the funnel aggregation. Shape it like `outbox_events` **plus** the explicit `organization_id` (like `audit_logs`) so it's tenant-queryable for the band. [Source: outbox + audit migrations]
- [ ] **Append-only migration** `…_make_learning_events_append_only.php` — triggers rejecting UPDATE/DELETE, mirroring `2026_06_20_000500_make_audit_logs_append_only.php`.
- [ ] **`LearningEvent` model** (`app/Modules/Reporting/Domain/Models/LearningEvent.php` — Reporting module owns telemetry) — `BelongsToTenant` + `HasUlids`; `event_name`/`cohort_id`/`occurred_at`/`payload` fillable; `organization_id` server-set (not mass-assignable). `BelongsToTenant` satisfies `TenantIsolationArchTest`.
- [ ] **`LearningTelemetry` recorder** (`app/Shared/Telemetry/` or Reporting/Application) — `record(string $eventName, string $cohortId, string $organizationId, array $payload = [])`; **explicit-org-wins** like `AuditLogger` (public events have no TenantContext → resolve cohort under `runAsSystem`, stamp the cohort's org). Best-effort: wrap the write so a telemetry failure is swallowed/logged, never thrown into the request path. NO idempotency-kernel use.

#### Backend — emit points + funnel API
- [ ] **`viewed` emit** — in `ApplyController::show`, after resolving the open cohort under `runAsSystem`, record `application.viewed` with the cohort's org. Guarded best-effort.
- [ ] **`started` beacon endpoint** — `POST /v1/apply/{cohort}/events` (public, no auth/tenant, outside the auth groups like `apply.show`). Validate `event` ∈ {`started`} (extensible). Resolve cohort under `runAsSystem`; record `application.started`. Returns `204`. Best-effort.
- [ ] **`submitted` emit** — in the existing submit path (`SubmitApplication`), record `application.submitted` inside the transaction (taxonomy completeness for the band). NOTE: the funnel's `submitted` number does NOT read this — it counts `application_submissions` (authoritative).
- [ ] **Funnel endpoint** — `GET /v1/cohorts/{cohort}/funnel` → `FunnelController@show`: resolve cohort tenant-scoped (AR-6 404); `viewed`/`started` = `LearningEvent` counts (tenant-scoped) per `event_name`; `submitted` = `ApplicationSubmission` count; clamp `viewed = max(viewed, started)`; return `{ data: { viewed, started, submitted } }`. Authorize `viewAny` (any tenant member, like submissions).
- [ ] **Route** `cohorts.funnel` in the `['auth:sanctum','tenant']` group beside `cohorts.submissions.index`.
- [ ] **OpenAPI** — `php artisan scramble:export`, commit `openapi/openapi.json` (new routes → contract test).

#### Backend — tests
- [ ] **`LearningEventTest`** — append-only (UPDATE/DELETE rejected); `BelongsToTenant` scope; explicit-org stamping from a public (no-tenant) context.
- [ ] **`FunnelTest`** — full flow: seed cohort, hit apply (viewed), beacon (started), submit (submitted) → funnel returns the three counts; clamp asserted (started > raw viewed → viewed == started); **AR-6 cross-tenant 404**; empty cohort → `{0,0,0}`; 401 unauth.
- [ ] **Telemetry best-effort** — a telemetry write failure does not 500 the public apply/beacon request.

#### Frontend — Submissions screen + funnel
- [ ] **`schemas/submissions.ts`** — `submissionSchema` (`reference_number`, `cohort_id`, `submitted_at`) + `submissionListResponseSchema` (`{ data: [...], meta: { total } }` — note: model `meta.total`, unlike the cohorts schema) + `submissionDetailSchema` (+ `snapshot`) + `funnelSchema` (`{ viewed, started, submitted }` ints). [Source: SubmissionResource/DetailResource shapes]
- [ ] **`api/submissions.ts`** — `listSubmissions(cohortId)`, `getSubmission(cohortId, id)`, `getFunnel(cohortId)`; mirror `api/cohorts.ts` (credentials, zod-parse).
- [ ] **`api/apply.ts`** — add `recordStarted(cohortId)` → `POST /apply/{cohort}/events` `{ event: 'started' }`, best-effort (swallow errors; never block the form).
- [ ] **`useApplyDraft.ts`** — fire `recordStarted` once on the first `setAnswer`, deduped via a per-cohort localStorage flag (reuse the existing draft key namespace).
- [ ] **`pages/SubmissionsPage.tsx`** — `AppShell` console surface; `useQuery(['funnel', cohortId])` + `useQuery(['submissions', cohortId])`. Funnel header (viewed/started/submitted, "views are approximate" microcopy, `bdi` numerals). Zero submissions → `StateBlock` empty + **copyable share link** (`…/apply/{cohort}`). List rows with a focusable "open detail" `Link` → detail route. Loading/error/retry states. Derive `cohortId` from the path.
- [ ] **`pages/SubmissionDetailPage.tsx`** — read-only snapshot view (answers + version ids) from `getSubmission`; route, not modal.
- [ ] **`app/App.tsx`** — add `SUBMISSIONS_ROUTE` (`/cohorts/{id}/submissions`) and `SUBMISSION_DETAIL_ROUTE` (`…/{submission}`) through `ConsoleGate`, mirroring `PROGRAMS_ROUTE`.
- [ ] **`pages/HomePage.tsx`** — restore the "N submissions to score" next-action to a real `Link` to `/cohorts/{id}/submissions` (give `nextAction` its `href` back); update/relax the HomePage test that currently asserts it is text-not-link.

#### Frontend — tests, stories, gates
- [ ] **`pages/SubmissionsPage.test.tsx`** — funnel render (clamped numbers, microcopy); zero-day empty + copyable share link; list rows + focusable detail link; loading/error/retry; RTL+dark render with `bdi`.
- [ ] **`api/submissions.test.ts`** — list (with `meta.total`), funnel, detail parse; 401/non-ok; malformed rejected.
- [ ] **`pages/SubmissionsPage.stories.tsx`** — populated funnel, zero-day, RTL.
- [ ] **`tests/a11y.test.tsx`** — add `SubmissionsPage` (+ detail) to the axe gate; zero violations.
- [ ] **HomePage test** — assert the next-action is now a `Link` to `/cohorts/{id}/submissions` (reverting the Story 1-5 text-only assertion).

## Dev Notes

### Decisions (resolving the open questions — flag in review if wrong)
- **`submitted` = durable `application_submissions` count**, never telemetry — telemetry is lossy/best-effort; the submissions table is authoritative. Only `viewed`/`started` come from telemetry. This is the single most important design choice.
- **Clamp `viewed = max(viewed, started)` server-side** in the funnel endpoint (beacon loss can drop `viewed` below `started`). The "approximate" microcopy is the UX accommodation for the same lossiness.
- **Telemetry is best-effort, not idempotent** — high-volume fire-and-forget; routing pageviews through the idempotency kernel is the wrong tradeoff. `started` deduped cheaply client-side (per-cohort localStorage), `viewed` may double-count on refresh (accepted).
- **No PII on public events** — `viewed`/`started` carry only org+cohort+event+timestamp; no `sub`, no IP. Keeps telemetry out of PDPL/retention scope (NFR-013).
- **`started` fires on first answer entered** (`useApplyDraft.setAnswer`), the cleanest "began filling" signal; naturally ≤ `viewed` in the common path, and the server clamp covers the beacon-loss case.
- **Detail is a route, not a modal** — preserves Story 1.0's modal deferral (no modal system built yet).
- **FR-080 DoD** ("verified in a dashboard a human has looked at"): the operator funnel screen IS that surface; the `FunnelTest` full-flow assertion is the "events emit + queryable" predicate the adversarial review flagged as missing.

### Current state / what must be preserved
- **Slice-1 read API is DONE and merged** — reuse `SubmissionController`, `SubmissionResource`, `ApplicationSubmissionPolicy`, the `cohorts.submissions.*` routes; do not modify their contracts (the funnel + UI consume them as-is).
- **`ApplyController::show`** resolves the public cohort via **`TenantContext::runAsSystem`** (NOT `withoutGlobalScope('tenant')` — the tenancy arch test forbids raw scope removal). The `viewed` emit rides inside that same system context. [Source: ApplyController.php]
- **Tenant stamping for public events** mirrors how submissions/audit do it: resolve the cohort's `organization_id` under system context and stamp it explicitly (`AuditLogger`'s explicit-org-wins arg is the template). [Source: AuditLogger, ApplicationSubmission]
- **`HomePage.nextAction`** currently returns the "score" action with **no href** → rendered `<strong>` text with a comment pointing at this story. Giving it `href: /cohorts/{id}/submissions` closes the loop; the existing render branch already handles `href ? <Link> : <strong>`. [Source: frontend/src/pages/HomePage.tsx]

### Patterns to mirror
- **Telemetry table** = `outbox_events` shape + explicit `organization_id` (audit-style) + append-only triggers (audit-style). **Do NOT reuse** `outbox_events` (delivery substrate, drained) or `audit_logs` (compliance record). [Source: outbox/audit migrations + producers]
- **Recorder** = `AuditLogger::record` API shape (explicit org wins). [Source: app/Shared/Audit/AuditLogger.php]
- **Frontend read** = `api/cohorts.ts` + `schemas/cohorts.ts`; **list page** = `ProgramsPage.tsx` (useQuery + StateBlock states); **route wiring** = `App.tsx resolveRoute` `PROGRAMS_ROUTE` block; **empty/share** = `EXPERIENCE.md` "No applications yet. Share your cohort link." [Source: cited files]
- **AR-6** = the cross-tenant-404 test pair in `SubmissionListTest.php`; replicate for the funnel endpoint.
- **Contract** = `OpenApiSpecTest` requires `scramble:export` + baseline re-commit for the new routes.

### Source map
- FR-080 taxonomy: `prds/prd-Catalesta-2026-06-20/prd.md` L146 · World-A/B band: `prd.md` L31/L73 · Learning-Telemetry cross-cutting + DoD: `epics.md` L138-139, L443 · 2.8 quality bar (clamp + share link + microcopy): `epics.md` L423 · empty-state copy: `EXPERIENCE.md` L69 · stepped-form started/abandoned: `EXPERIENCE.md` L114-118.
- Backend: `SubmissionController.php`, `SubmissionResource.php`, `ApplyController.php`, `SubmitController.php`/`SubmitApplication`, `app/Shared/Audit/*`, `app/Shared/Outbox/*`, `app/Shared/Tenancy/BelongsToTenant.php`, `ApplicationSubmission.php` + migration, `tests/Feature/Applications/SubmissionListTest.php`, `tests/Contract/OpenApiSpecTest.php`.
- Frontend: `pages/ApplyPage.tsx`, `pages/useApplyDraft.ts`, `api/apply.ts`, `api/cohorts.ts`, `schemas/cohorts.ts`, `pages/ProgramsPage.tsx`, `app/App.tsx`, `pages/HomePage.tsx`, `tests/a11y.test.tsx`.

## Dev Agent Record
### Agent Model Used
claude-opus-4-8[1m]  (Slice 1)
### Completion Notes List
- **Slice 1 (DONE, merged @ dc657f9):** Backend FR-034 read slice — 345 tests green, PHPStan L6 + Pint clean, OpenAPI regenerated. 7 `SubmissionListTest` cases.
### File List
*Slice 1 (merged):* `SubmissionController.php`, `SubmissionResource.php`, `SubmissionDetailResource.php`, `ApplicationSubmissionPolicy.php`, `AppServiceProvider.php` (policy reg), `routes/api.php` (list+detail), `openapi/openapi.json`, `SubmissionListTest.php`.
### Change Log
- (Slice 1) Backend FR-034 read API — merged at baseline `dc657f9`.
- 2026-06-21 — Slice 2 scoped (full funnel): FR-080 Learning Telemetry substrate + viewed/started emit points + funnel read API + operator Submissions UI (list/detail/funnel, zero-day share link, RTL/dark) + 1-5 link handoff. Status review → ready-for-dev.

## Open Questions (resolved with defaults above; flag in review if wrong)
- **Q1 — `started` trigger:** first `setAnswer` (decided). If product wants "first focus" instead, the client emit point moves but the contract is unchanged.
- **Q2 — `viewed` dedup:** none server-side (best-effort); refresh double-counts, clamp + microcopy absorb it. Per-session client dedup is a cheap later refinement if the number looks inflated.
- **Q3 — full taxonomy:** this slice builds only the 3 funnel events. `application.abandoned{step}`, `submission.scored`, `decision.recorded`, export-then-leave (rest of FR-080) belong to their own stories (Epic 2/3) on the same substrate.
