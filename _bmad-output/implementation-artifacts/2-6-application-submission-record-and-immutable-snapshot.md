# Story 2.6: Application submission record + immutable snapshot

Status: ready-for-dev

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

> **Epic 2 · first FEATURE story on the reliability gate.** `blocked-by: GATE-E2.0` (now ✅ — Stories 2.1–2.5 in review/merged). This builds the **persistence layer**: the `application_submissions` record + the immutable `submission_snapshot`, pinning content-addressed blobs (Story 2.1) so they survive GC. The **public submit flow / HTTP / idempotency wiring is Story 2.7** — not here.
>
> **DEPENDENCY NOTE (read first):** the snapshot captures the *resolved* form/program/rubric **version ids**. The **form** version-id contract is Story **1.3** (Epic 1, `backlog` — not built). So 2.6 takes the version ids + answers + blob digests **as inputs** to a `RecordSubmission` service (the caller supplies them); the end-to-end wiring to a real published form lands in **2.7** (which depends on 1.3/1.4 for an open cohort + published form). Build 2.6 as the self-contained record/snapshot mechanism; tests provide representative inputs. Do **not** block on Epic 1 here.

## Story

As an **applicant**,
I want my submitted answers frozen at submit time,
so that what I submitted cannot be silently altered later.

## Acceptance Criteria

From epics.md (Story 2.6) + FR-030/031 + AR-4/AR-6 + the ★ Edge-Case Hardening:

1. **Record bound to a cohort.** An `application_submissions` row is stored bound to a **Cohort** (`cohort_id`), tenant-owned (`organization_id`, server-set), with an immutable `submission_snapshot` (jsonb) (FR-030).
2. **Snapshot contents.** The snapshot captures: the **answer values**, the **content-addressed file refs** (sha256 digests from Story 2.1), and the **form / program / rubric version ids** in effect (FR-031, AR-4).
3. **Immutable after write.** Later edits to the source form/program never alter the stored snapshot — asserted by mutating the source and re-reading the snapshot. The row's snapshot is frozen (no update path).
4. **Tenant isolation.** A cross-tenant isolation test covers the new table (AR-6): cross-tenant read → 404 / no rows; `organization_id` is server-set, never mass-assignable.

### ★ Edge-case hardening (must-fix-before-green)

5. **GC must refuse to collect any blob referenced by a snapshot.** Each referenced blob's refcount is **pinned (`ContentAddressedStore::incrementRef`) before the snapshot is durable** — and inside the *same* DB transaction as the snapshot write — so `blobs:gc` (Story 2.1) can never collect a blob a snapshot points at. A test proves: snapshot referencing blob B → `blobs:gc --apply` does **not** delete B.
6. **Referenced blob must be finalized + verified before referenceable.** A snapshot may only reference a blob that already `exists()` in the store (finalized + sha256-verified by Story 2.1's `store()`); reject a snapshot that references an unknown/half-uploaded digest.
7. **Snapshot binds the resolved version ids at submit time (race-safe vs republish).** The version ids written are the ones resolved at submit time and captured into the jsonb — a later republish of the form/program does not change an existing snapshot (covered by AC-3's mutate-source test; ensure the ids are copied values, not live references).
8. **Boundaries defined.** Null/empty answers and a max-payload boundary are defined and handled (empty answers → a valid empty snapshot; oversize payload → fail-closed, never a truncated snapshot).

## Tasks / Subtasks

- [ ] **Task 1 — Schema: `application_submissions`** (AC: 1, 2, 4) — migration `2026_06_2x_xxxxxx_create_application_submissions_table.php` (after `…000500`):
  - [ ] Columns: `id` (ulid PK), `organization_id` (ulid, indexed — server-set, AR-6), `cohort_id` (ulid, FK→`cohorts`, indexed), `submission_snapshot` (jsonb), `created_at` (timestampTz useCurrent). The snapshot jsonb holds `{answers: {...}, blob_refs: [sha256...], form_version_id, program_version_id, rubric_version_id}`.
  - [ ] Tenant-owned → carries `organization_id` (CLAUDE.md rule 6). Index `(organization_id, cohort_id)`.
- [ ] **Task 2 — `ApplicationSubmission` model** (AC: 1, 3) — `app/Modules/Applications/Domain/Models/ApplicationSubmission.php`:
  - [ ] `final class … extends Model`, `HasUlids`, `$timestamps=false`, casts `submission_snapshot => 'array'`, `created_at => 'datetime'`. Use the **`BelongsToTenant`** trait (`App\Shared\Tenancy`) for fail-closed scoping (AR-6) — mirror an existing tenant-owned model (e.g. a Programs/Cohorts model) for the trait wiring.
  - [ ] **Immutability:** add an `updating` guard (mirror `App\Shared\Versioning\ImmutableWhenPublished`'s `static::updating` pattern, but unconditional) that throws if any persisted row is updated — the snapshot is write-once (AC-3). A test asserts an update attempt throws and the row is unchanged.
- [ ] **Task 3 — `RecordSubmission` service** (AC: 2, 5, 6, 7, 8) — `app/Modules/Applications/Application/RecordSubmission.php`, `final class`, mirror `CreateOrganization` (constructor-injected collaborators, `DB::transaction`):
  - [ ] Signature: `handle(Cohort $cohort, array $answers, array $blobDigests, array $versionIds): ApplicationSubmission` (versionIds = `['form'=>…, 'program'=>…, 'rubric'=>…]`). The caller (Story 2.7) resolves the open cohort + published-form version ids; 2.6 records.
  - [ ] **Inside one `DB::transaction`** (AC-5): for each digest in `$blobDigests` — assert `ContentAddressedStore::exists($digest)` (AC-6, reject unknown) then `incrementRef($digest)` (AC-5 pin) — then build the snapshot jsonb (answers + blob_refs + version ids, all copied values, AC-7) and create the `ApplicationSubmission`. If anything throws, the transaction rolls back (no orphan submission, no leaked refcount — the increments roll back too).
  - [ ] Payload guard (AC-8): reject an oversize answers/snapshot payload (config or a sane constant) fail-closed; empty answers → valid empty snapshot.
  - [ ] **Reuse, do not reinvent:** `ContentAddressedStore` (Story 2.1) for blob existence + refcount; `BelongsToTenant`/`TenantContext` for `organization_id`; the versioning kernel is **not** reused for the snapshot (per ADR-1 the snapshot is a distinct lifecycle — a plain jsonb capture, not a versionable).
- [ ] **Task 4 — Tests** (AC: all, esp. ★ 5/6) — see Testing Requirements.

## Dev Notes

### Where this fits
First feature story after GATE-E2.0. It consumes Story 2.1 (`ContentAddressedStore` — pin blobs) and ADR-1/ADR-4 (snapshot is a distinct store, written transactionally). The **public submit endpoint + idempotency + 422 closed-cohort + receipt is Story 2.7**; 2.6 is the record + snapshot it persists. [Source: epics.md#Epic 2 / Story 2.6; GATE-E2.0]

### Architecture & conventions (verified)
- **New module:** `app/Modules/Applications/` — confirmed **scaffold only** (`.gitkeep`). Lay it out like the built modules: `Domain/Models/`, `Application/` (use-case services). [Source: backend/app/Modules/Applications/; mirror backend/app/Modules/Organizations/]
- **Cohort** model: `app/Modules/Cohorts/Domain/Models/Cohort.php` (exists) — the FK target. [Source: verified]
- **Snapshot vs versioning (ADR-1):** reuse the immutability *primitive idea* but **build a separate `submission_snapshot` jsonb** for user-submitted payloads — distinct lifecycle from published config. Do NOT make the submission a `Versionable`. [Source: architecture.md ADR-1]
- **Blob pinning (AR-5/AR-7 + Story 2.1):** `ContentAddressedStore::exists()` + `incrementRef()` confirmed (public API). The blob `blobs:gc` only deletes `refcount=0`, so pinning is the mechanism that protects referenced blobs (Story 2.6 ★ / Edge-Case 2.6). [Source: backend/app/Shared/Storage/ContentAddressedStore.php]
- **Tenant isolation:** `BelongsToTenant` is **opt-in per table** (ADR-3) → this story MUST add the trait AND an explicit cross-tenant isolation test for `application_submissions` (AR-6). [Source: architecture.md ADR-3]
- **Service pattern:** `final class …Application\X { public function handle(...) { return DB::transaction(...); } }`, collaborators constructor-injected. [Source: backend/app/Modules/Organizations/Application/CreateOrganization.php]

### Previous-story intelligence (from 2.1–2.5 — apply directly)
- **Test env = SQLite `:memory:`**; `jsonb`/`timestampTz`/`ulid` map cleanly; `RefreshDatabase`; PHPUnit class-style. [Learned 2.1–2.5]
- **Reuse the 2.1 blob store** for the GC-protection test: `store()` a blob, reference it in a snapshot (incrementRef), then run `blobs:gc --apply` and assert the blob survives — the ★ AC-5 proof. [Story 2.1 `ContentAddressedStore`/`GarbageCollectBlobs`]
- **PHPStan level 6 runs in CI (not locally — OOMs):** annotate every `array` param with `@param array<...>` (e.g. `array<string, mixed> $answers`, `array<int, string> $blobDigests`, `array<string, string> $versionIds`) — this exact omission red-failed CI on Stories 2.2/2.3. Don't repeat it. [Learned: CI PR #6]
- **No `@template` generics** unless trivially resolvable — they broke PHPStan in 2.2. [Learned]
- Migration date-ordered; latest is `2026_06_20_000500` → use `2026_06_20_000600`.

### Project Structure Notes
- New: migration `…000600`, `app/Modules/Applications/Domain/Models/ApplicationSubmission.php`, `app/Modules/Applications/Application/RecordSubmission.php`, tests.
- **Anti-scope:** no HTTP route, no idempotency wiring, no closed-cohort 422, no receipt/status UI — all **Story 2.7**. 2.6 is the record + snapshot + blob pinning + immutability, exercised by tests (a service call, not an endpoint).

### References
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.6 ; #Edge-Case Hardening — 2.6 ★ ; #Top-3 must-fix-before-green]
- [Source: _bmad-output/planning-artifacts/architecture.md — ADR-1 (snapshot store), ADR-3 (BelongsToTenant opt-in), AR-4/5/6]
- [Source: _bmad-output/implementation-artifacts/2-1-content-addressed-blob-storage.md — ContentAddressedStore API + GC]
- [Source: backend/app/Modules/Organizations/Application/CreateOrganization.php — service pattern]
- [Source: backend/app/Shared/Versioning/ImmutableWhenPublished.php — updating-guard pattern]

### Glossary
- **submission_snapshot** — immutable jsonb written at record time: answer values + content-addressed blob refs + resolved form/program/rubric version ids. Frozen on write; never altered by later source edits. (This story; also 2.7, 3.1.)
- **pin** — `incrementRef(digest)` on every referenced blob, inside the snapshot's transaction, so GC can't collect it (★ AC-5).
- **GATE-E2.0** — open (2.1–2.5 done); this story is its first feature consumer.

## Testing Requirements

PHPUnit class-style, `RefreshDatabase`. Seed a Cohort + organization context:
- **Record + snapshot (AC-1/2):** `RecordSubmission::handle(cohort, answers, [digest], versionIds)` → one `application_submissions` row, `submission_snapshot` contains answers + `blob_refs:[digest]` + the 3 version ids; `organization_id` server-set; bound to `cohort_id`.
- **Immutable after write (AC-3/7):** mutate the source (e.g. change a program/form version row) and re-read the snapshot → unchanged. An attempt to `update()` the submission throws (write-once guard).
- **★ GC-protection (AC-5):** `store()` a blob, record a submission referencing it, run `blobs:gc --apply` → the blob still `exists()` (refcount pinned).
- **★ Unknown digest rejected (AC-6):** record referencing a digest not in the store → throws; no submission row written (rollback).
- **Tenant isolation (AC-4):** two orgs; org A's submission is not visible/queryable under org B (cross-tenant → 404/no rows); `organization_id` not mass-assignable.
- **Boundaries (AC-8):** empty answers → valid empty snapshot; oversize payload → fail-closed (no row).
- Lint: `vendor/bin/pint` pass; `php -l` clean. **PHPStan: annotate all `array` params** (CI runs level 6).

## Dev Agent Record

### Agent Model Used

{{agent_model_name_version}}

### Debug Log References

### Completion Notes List

- Ultimate context engine analysis completed — comprehensive developer guide created (2026-06-20).

### File List
