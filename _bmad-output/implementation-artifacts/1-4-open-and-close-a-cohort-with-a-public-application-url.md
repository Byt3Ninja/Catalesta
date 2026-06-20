---
baseline_commit: 5770664
---
# Story 1.4: Open and close a cohort with a public application URL

Status: review

> **Epic 1.** Opens applications: attach the published form (Story 1.3 ✅), open a cohort with an enrollment window, expose a **public application URL**, and close it. Gated via the **EntitlementService socket (FR-060)** — built here (absent in tree). Together with 1.3 this **unblocks Story 2.7**. The 422-when-closed *submission* behavior is Epic 2 (2.7); 1.4 produces the URL + open/closed state.

## Acceptance Criteria
1. **Open + public URL (FR-011/021):** open a cohort with open/close datetimes and **attach a published `FormVersion`** (1.3) → status `open`, and a **public, no-auth application URL** resolves the cohort (by id) returning its open state + attached form version. `cohort.open` is gated via `EntitlementService` (FR-060, allow-all socket).
2. **Manual close:** the operator can close an open cohort (status `closed`) before its close datetime.
3. **Public open/closed state:** the public resolver reports open only while `now` is within `[opens_at, closes_at]` and status is `open`; otherwise closed (the submission 422 is asserted in 2.7).
4. **Audited:** opening → `cohort.opened`, closing → `cohort.closed` (FR-052 enumerated set).
5. **Entitlement socket (FR-060):** `EntitlementService` interface + allow-all impl, bound; `cohort.open` calls `check('cohort.open')` (never inspects plan names). No live block in P1a.

## Tasks
- [x] **EntitlementService socket** — `app/Shared/Entitlement/EntitlementService.php` (interface `check(string $action): void`) + `AllowAllEntitlementService` (no-op) + bind in `AppServiceProvider`.
- [x] **Schema** — migration `2026_06_20_000800_add_form_version_id_to_cohorts_table.php`: `form_version_id` (ulid, nullable) on `cohorts`.
- [x] **OpenCohort service** — `app/Modules/Cohorts/Application/OpenCohort.php`: `handle(Cohort, FormVersion, opensAt, closesAt)` → `check('cohort.open')` → set `form_version_id` + window + status `Open` → audit `cohort.opened`. `DB::transaction`, mirror `PublishStageVersion`.
- [x] **CloseCohort service** — `handle(Cohort)` → status `Closed` → audit `cohort.closed`.
- [x] **Public resolver** — `GET /v1/apply/{cohort}` (no auth/tenant) → `ApplyController@show`: resolve via `Cohort::withoutGlobalScope('tenant')->find()` (public by design), return `{ open: bool, cohort_id, form_version_id }`; open = status Open AND now ∈ window.
- [x] **Tests** — open (entitlement + form attach + audit + public URL resolves open), close (status + audit + public reports closed), before/after window → closed, public resolver works without tenant context.

## Dev Notes
- **Cohort** model (built): `BelongsToTenant`, `HasUlids`, `CohortStatus` enum (Draft/Open/Closed/Completed), fillable incl. `status`, `enrollment_opens_at`, `enrollment_closes_at`. The public resolver must `withoutGlobalScope('tenant')` (BelongsToTenant fail-closes with no tenant). [Source: app/Modules/Cohorts/Domain/Models/Cohort.php]
- **Audit:** reuse `AuditLogger` + the `AuditAction` enum (Story 2.5) values `cohort.opened`/`cohort.closed`.
- **Routes:** `routes/api.php` `Route::prefix('v1')`; the public apply route goes **outside** the `auth:sanctum`/`tenant` groups.
- **CI:** annotate all `array` params (PHPStan L6); no `@template`; honest types so `is_array` on a typed array isn't "always true" (the PR #8 fix). Tests on SQLite.
- Migration after `2026_06_20_000710` → `2026_06_20_000800`.

## Dev Agent Record
### Agent Model Used
claude-opus-4-8[1m]
### Completion Notes List
### File List
