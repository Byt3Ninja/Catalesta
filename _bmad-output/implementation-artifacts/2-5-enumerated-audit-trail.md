---
baseline_commit: a11e4f7f99540abc848448347920f7e1f2416e0a
---
# Story 2.5: Enumerated audit trail

Status: review

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

> **Track A · E2.0 reliability-gate member (5 of 5) — last gate brick.** After this, the E2.0 substrate is complete and **GATE-E2.0 opens**, unblocking Stories 2.6–2.8.
>
> **KEY RECONCILIATION (read first):** the epic names an `audit_events` table, but the brownfield already ships **`audit_logs` + `App\Shared\Audit\AuditLogger`** doing *exactly* "who did what, separate from the value store" — captured actor (`actor_external_user_id`), `organization_id`, `action`, `correlation_id`, `result`, timestamp; already used across Organizations/Identity/Stages/Programs. **Reuse it — do NOT build a parallel `audit_events` store** (CLAUDE.md: reuse the foundation; don't duplicate). This story adds the *enumerated registry*, the *completeness test*, and the *DB-layer immutability* that the existing logger lacks.

## Story

As a **compliance-conscious operator**,
I want significant actions recorded in an append-only audit trail,
so that selection activity is defensible.

## Acceptance Criteria

From epics.md (Story 2.5) + FR-052/NFR-012 + the ★ Edge-Case Hardening:

1. **Enumerated set recorded.** When any of the **P1a audited set** occurs — `program.published`, `cohort.opened`, `cohort.closed`, `application.submitted`, `submission.scored`, `decision.recorded`, `decision.reopened`, `decisions.exported` — an **immutable audit row** is written with **actor (`sub`-backed)**, **organization**, and **timestamp** (FR-052, NFR-012). (Recorded via the existing `AuditLogger` into `audit_logs`.)
2. **Completeness is acceptance-tested.** The enumerated set is a single canonical registry; a test asserts the registry **is exactly these 8 actions** — a missing/renamed action **fails the test**. (Per-operation emission for actions whose operations exist later — applications/scoring/decisions in 2.7/3.x, program/cohort in Epic 1 — is wired in *those* stories against this registry; 2.5 owns the registry + the mechanism.)
3. **Separate from the versioning store.** The audit store records "who did what", not "what the value was" — already true of `audit_logs` (distinct from the versioning kernel). Confirm + state; no merge.

### ★ Edge-case hardening (must-fix-before-green)

4. **DB-layer append-only.** `UPDATE` and `DELETE` on the audit table are **denied at the database layer** (a trigger / rule), **not only in app code**. A test proves an attempted update and an attempted delete are both rejected by the DB and the row is unchanged.

## Tasks / Subtasks

- [x] **Task 1 — Enumerated action registry** (AC: 1, 2)
  - [x] `app/Shared/Audit/AuditAction.php` — a `string`-backed enum with exactly the 8 P1a actions (`ProgramPublished = 'program.published'`, `CohortOpened = 'cohort.opened'`, `CohortClosed = 'cohort.closed'`, `ApplicationSubmitted = 'application.submitted'`, `SubmissionScored = 'submission.scored'`, `DecisionRecorded = 'decision.recorded'`, `DecisionReopened = 'decision.reopened'`, `DecisionsExported = 'decisions.exported'`). Mirror `App\Shared\Versioning\VersionStatus` enum style.
  - [x] **Do not refactor** the existing `AuditLogger` signature or its current callers — it takes a free-string `action` and is used widely. The enum is the **canonical P1a registry** the later emission stories and the completeness test reference. (Optional, only if clean: an `AuditLogger::recordAction(AuditAction $action, …)` convenience overload — but keep the string method intact.)
- [x] **Task 2 — DB-layer immutability** (AC: 4) — new migration `2026_06_20_000500_make_audit_logs_append_only.php`:
  - [x] Create DB triggers that **abort** on `UPDATE` and `DELETE` of `audit_logs`. **Driver-portable** via `DB::connection()->getDriverName()` + `DB::unprepared(...)`:
    - **pgsql:** a `plpgsql` function `RAISE EXCEPTION 'audit_logs is append-only'` + `BEFORE UPDATE` and `BEFORE DELETE` row triggers.
    - **sqlite (tests):** `CREATE TRIGGER … BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT,'audit_logs is append-only'); END;` and the same for `DELETE`.
  - [x] `down()` drops the triggers (and the pgsql function).
  - [x] **Note:** keep INSERT allowed (the logger must still append). Only UPDATE/DELETE are denied.
- [x] **Task 3 — Completeness + immutability tests** (AC: 2, 3, 4) — see Testing Requirements.

## Dev Notes

### Where this fits
Final E2.0 gate story. The other four (2.1 blob, 2.2 idempotency, 2.3 outbox table+producer, 2.4 relay) are in `review`. After 2.5, **GATE-E2.0 opens** and Stories 2.6–2.8 unblock. [Source: epics.md#Epic 2 / E2.0; GATE-E2.0]

### Architecture & conventions — REUSE the existing audit kernel (verified)
- **`App\Shared\Audit\AuditLogger`** (read in full): `record(string $action, ?string $targetType, ?string $targetId, array $before, array $after, string $result='success'): AuditLog` → inserts an `AuditLog` with `actor_external_user_id` (= `optional($request->user())->id`, the Startup-Gate-`sub`-backed external user), `organization_id` (from `TenantContext`), `action`, target, `before/after` jsonb, `ip_address`, `correlation_id`, `result`. **This already satisfies AC-1's actor+org+timestamp+action.** [Source: backend/app/Shared/Audit/AuditLogger.php]
- **`audit_logs` table** (migration `2026_06_18_000100`): `id` ulid PK, `actor_external_user_id`, `organization_id`, `action`, `target_type/target_id`, `before/after` jsonb, `ip_address`, `correlation_id`, `result`, `created_at` (timestampTz useCurrent). **`$timestamps=false`; the model is append-only by usage** — this story makes it append-only by *enforcement*. [Source: backend/database/migrations/2026_06_18_000100_create_audit_logs_table.php; backend/app/Shared/Audit/AuditLog.php]
- Already wired in: Organizations, Identity, Stages, Programs controllers/actions use `AuditLogger`. So the logger is the established path; 2.5 does **not** introduce a new logging API. [Source: grep — `AuditLogger` usages]
- **Reconciliation to record in Completion Notes:** epics §2.5 says `audit_events`; we reuse `audit_logs`. The FR-052 intent (immutable, enumerated, actor+org) is met by `audit_logs` + this story's registry + DB enforcement. Flag for the team so the epic/PRD wording is reconciled (like the OQ9/FR-062/FR-070 doc fixes earlier).
- FR-052 / NFR-012: audit is **enforced**, not opt-in. DB-layer immutability (AC-4) is what turns the brownfield "opt-in, not enforced" audit into an enforced, defensible trail. [Source: prd.md NFR-012; docs/status/implementation-status.md "audit currently opt-in, not enforced"]

### Previous-story intelligence (from 2.1–2.4 — apply directly)
- **Test env = SQLite `:memory:`.** SQLite **supports `CREATE TRIGGER … BEFORE UPDATE/DELETE … RAISE(ABORT,…)`** — so the DB-layer immutability (AC-4) is testable in-memory, not just on Postgres. Implement both dialects; the test runs the sqlite one. [Learned 2.1–2.4]
- Enum style: mirror `App\Shared\Versioning\VersionStatus` (`enum X: string { case … }`). [Source: backend/app/Shared/Versioning/VersionStatus.php]
- A DB-rejected write surfaces as `Illuminate\Database\QueryException` — assert that for the UPDATE/DELETE tests.
- **Pint must pass; PHPStan OOMs env-wide** (flag, validate via `php -l` + suite). [Learned 2.1–2.4]
- Migration date-ordered; latest is `2026_06_20_000400` → use `2026_06_20_000500`.

### Project Structure Notes
- New: `app/Shared/Audit/AuditAction.php` (enum), migration `…000500` (triggers), tests. **Reuse** `AuditLogger`/`AuditLog`/`audit_logs` (no new store, no new logger).
- **Anti-scope:** do **not** wire the actual `program.published`/`cohort.opened`/etc. emissions here — those operations live in Epic 1 (program/cohort) and Stories 2.7/3.x (application/scoring/decision); each emits its enumerated action via `AuditLogger` against this registry. 2.5 establishes the registry + enforcement + completeness mechanism only. Do not refactor existing audit callers.

### References
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.5: Enumerated audit trail]
- [Source: _bmad-output/planning-artifacts/epics.md#Edge-Case Hardening — 2.5 ; #GATE-E2.0]
- [Source: _bmad-output/planning-artifacts/prd.md — FR-052 (enumerated audited set), NFR-012 (audit enforced)]
- [Source: backend/app/Shared/Audit/AuditLogger.php, AuditLog.php ; backend/database/migrations/2026_06_18_000100_create_audit_logs_table.php]
- [Source: backend/app/Shared/Versioning/VersionStatus.php — enum pattern]

### Glossary
- **P1a audited set** — the 8 enumerated actions (FR-052); the canonical registry this story defines as `AuditAction`.
- **audit_logs** — the existing append-only "who did what" store (reused); made DB-immutable here. Distinct from the versioning kernel (AC-3).
- **GATE-E2.0** — opens after this story: blobs verified+GC-protected (2.1 ✓), idempotency 409/422/replay/crash (2.2 ✓), outbox in-txn (2.3 ✓), relay atomic-claim+crash-reclaim (2.4 ✓), **enumerated audit append-only (this)**. → Stories 2.6–2.8 unblock. [Source: epics.md#GATE-E2.0]

## Testing Requirements

PHPUnit class-style, `RefreshDatabase`:
- **Registry completeness (AC-2):** assert `AuditAction::cases()` maps to **exactly** the 8 enumerated string values — a missing/extra/renamed case fails. (This is the "a missing action fails the test" guard.)
- **Records actor + org + timestamp (AC-1):** via `AuditLogger->record(AuditAction::ProgramPublished->value, …)` (authenticated + tenant context) → an `audit_logs` row exists with the action, the actor id, the organization, and a timestamp. (Reuse the `AuditLoggerTest` setup pattern.)
- **★ Append-only at the DB layer (AC-4):** insert an `audit_logs` row, then:
  - `AuditLog::where('id',$id)->update([...])` → expect `QueryException`; re-read row is **unchanged**.
  - `AuditLog::where('id',$id)->delete()` → expect `QueryException`; row **still present**.
  - (Optionally) a raw `DB::table('audit_logs')->update(...)` is *also* rejected — proving it's the DB, not the model, enforcing it.
- **Separate store (AC-3):** assert (structurally) audit writes go to `audit_logs`, not any versioning table — a short documenting assertion.
- Lint: `vendor/bin/pint` pass; `php -l` clean. PHPStan per 2.1–2.4 note.

## Dev Agent Record

### Agent Model Used

claude-opus-4-8[1m] (Claude Opus 4.8, 1M context)

### Debug Log References

- `php artisan test --filter "AuditAction|AuditAppendOnly"` → 5 passed, 8 assertions.
- `php artisan test` (full) → **306 passed, 958 assertions, 0 failures** (no regressions; +5 over 2.4's 301).
- `vendor/bin/pint` → passed (no fixes). `php -l` clean.
- PHPStan: same env-wide OOM as 2.1–2.4 (flagged; validated via `php -l` + suite).

### Completion Notes List

- Ultimate context engine analysis completed — comprehensive developer guide created (2026-06-20).
- **Reconciliation applied (reuse over rebuild):** the epic's `audit_events` was reconciled to the existing **`audit_logs` + `AuditLogger`**, which already records actor (`actor_external_user_id`, sub-backed) + `organization_id` + action + timestamp + correlation_id. No parallel store built (CLAUDE.md: reuse the foundation). **Flag for the team:** reconcile epics §2.5 / PRD FR-052 wording (`audit_events` → `audit_logs`), same as the earlier OQ9/FR-062/FR-070 doc fixes.
- **Net-new this story:** (1) `AuditAction` enum — the canonical FR-052 registry of the 8 P1a actions; (2) **DB-layer append-only enforcement** — triggers that ABORT on UPDATE/DELETE of `audit_logs` (the brownfield audit was "opt-in, not enforced"; this makes it *enforced*, NFR-012).
- **★ DB-not-app enforcement proven:** triggers reject both Eloquent `update()/delete()` **and** a raw `DB::table()->update()` — so the immutability holds even if app code is bypassed. Portable: `plpgsql RAISE EXCEPTION` for Postgres, `RAISE(ABORT)` trigger for SQLite (so it's testable in the `:memory:` suite, not just prod).
- **Completeness guard:** a test asserts `AuditAction::cases()` equals exactly the 8 FR-052 values — a missing/renamed action fails the build.
- **Anti-scope respected:** did **not** wire `program.published`/`cohort.opened`/etc. emissions (those belong to Epic 1 and Stories 2.7/3.x); did not refactor the ~10 existing `AuditLogger` call sites. 2.5 = registry + enforcement + completeness mechanism only.
- **🎉 This completes GATE-E2.0** — all five substrate stories (2.1–2.5) are in `review`; Stories 2.6–2.8 are now unblocked.

### Change Log

| Date | Change |
|---|---|
| 2026-06-20 | Implemented Story 2.5 — `AuditAction` enumerated registry + DB-layer append-only triggers on `audit_logs` (reusing the existing AuditLogger). 5 tests. **Completes the E2.0 reliability gate.** Status → review. |

### File List

**New:**
- `backend/app/Shared/Audit/AuditAction.php`
- `backend/database/migrations/2026_06_20_000500_make_audit_logs_append_only.php`
- `backend/tests/Unit/Audit/AuditActionTest.php`
- `backend/tests/Feature/Audit/AuditAppendOnlyTest.php`

**Reused unchanged:** `backend/app/Shared/Audit/AuditLogger.php`, `AuditLog.php`, `audit_logs` table (brownfield).
