---
baseline_commit: dc657f9
---
# Story 2.8: Operator submission list + funnel

Status: review

> **Epic 2 — operator side of the intake loop.** The operator sees the submissions their cohort received (FR-034) and a funnel telling them whether intake is working. Reads the real `application_submissions` (Story 2.6/2.7).
>
> **SCOPE SPLIT (read first).** Only the **tenant-scoped submission read API (FR-034)** is buildable now and is what this slice delivers. The **funnel** (`N viewed, M started, K submitted`) is sourced from **Learning Telemetry (FR-080), which has no backend infrastructure yet** — no events table, no emit points (the 2.7 telemetry was deferred). Only `submitted` is real (= the list's pagination `meta.total`). The **operator UI** (list/funnel rendering, light/dark, LTR/RTL, focusable controls) depends on **Story 1.0 (frontend foundation, in-progress)**. Both are tracked follow-ups, not built here.

## Acceptance Criteria (this slice)
1. **Tenant-scoped submission list (FR-034):** `GET /v1/cohorts/{cohort}/submissions` (auth:sanctum + tenant) returns the cohort's submissions for the operator's org, newest first, paginated. The cohort is resolved tenant-scoped (foreign/unknown → 404); the submission query is BelongsToTenant-scoped, so isolation is enforced twice (AR-6).
2. **Submission detail:** `GET /v1/cohorts/{cohort}/submissions/{submission}` returns the full immutable snapshot (answers + blob refs + version ids) — the same snapshot Epic-3 scoring reads. 404 cross-tenant.
3. **Submitted count:** the list's `meta.total` is the funnel's `submitted` metric (the only real funnel number until telemetry exists).
4. **Empty/auth states:** empty cohort → `200` with `data: []`, `meta.total: 0`; unauthenticated → `401`.

## Deferred (documented, NOT built here)
- **Funnel `viewed`/`started`** — blocked on Learning Telemetry (FR-080): no events table/emitter exists. The cross-cutting UX rules (zero-day empty state + copyable share link, `viewed` clamped ≥ `started`, "views are approximate" microcopy) are UI concerns on telemetry data that doesn't exist yet.
- **Operator UI** (the Submissions screen + funnel, light/dark, LTR/RTL, focusable "open detail" control — UX-DR2/6) — `blocked-by: Story 1.0`.
- **Learning Telemetry DoD** ("verified in a dashboard a human has looked at") — not satisfiable without the telemetry substrate + UI.

## Dev Notes
- Reuse: `Cohort` tenant-scoped resolution pattern (`Cohort::query()->findOrFail`), `BelongsToTenant` on `ApplicationSubmission` (2.6), `Gate::policy` registration in `AppServiceProvider`, `JsonResource` + paginated collection conventions.
- `ApplicationSubmissionPolicy` mirrors `CohortPolicy`: `viewAny`/`view` return true; the tenant scope + middleware do isolation (no extra permission key). Submissions are write-once → no create/update/delete abilities.
- List stays lightweight (`reference_number`, `cohort_id`, `submitted_at`); the full snapshot is the detail endpoint only.

## Dev Agent Record
### Agent Model Used
claude-opus-4-8[1m]
### Completion Notes List
Backend FR-034 read slice DONE — 345 tests green (344 pass, 1 pgsql-gated skip from 2.7), PHPStan L6 clean, Pint clean, OpenAPI regenerated. 7 new tests in `SubmissionListTest` cover list ordering, empty state, detail snapshot, per-cohort scoping, cross-tenant 404 (list + detail), and unauthenticated 401.
### File List
- `app/Modules/Applications/Http/SubmissionController.php` (new)
- `app/Modules/Applications/Http/Resources/SubmissionResource.php` (new)
- `app/Modules/Applications/Http/Resources/SubmissionDetailResource.php` (new)
- `app/Modules/Applications/Policies/ApplicationSubmissionPolicy.php` (new)
- `app/Providers/AppServiceProvider.php` (register policy)
- `routes/api.php` (list + detail routes)
- `openapi/openapi.json` (regenerated)
- `tests/Feature/Applications/SubmissionListTest.php` (new)
