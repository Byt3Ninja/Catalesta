---
baseline_commit: a300e61
---
# Story 1.3: Build and publish an application form (content-addressed version)

Status: review

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

> **Epic 1 · the net-new Forms module.** Epic 1's other backend (Programs/Cohorts/Stages) is already built; this story is the real new work. It produces the **content-addressed form version id** that Story 2.6's snapshot binds and Story 2.7's submit flow attaches — so this **unblocks 2.7** (together with 1.4's open cohort). `blocked-by: GATE-E2.0` is N/A (this is Epic 1), but it is the upstream contract for 2.6/2.7.

## Story

As an **operator**,
I want to assemble and publish an application form from the supported field types,
so that applicants have a stable, immutable form to fill in.

## Acceptance Criteria

From epics.md (Story 1.3) + FR-020/012/022 + NFR-005 + the ★ Edge-Case Hardening:

1. **Publish → immutable, content-addressed version id.** Given a program in the operator's org, when they add fields (the **8 enumerated types**: short text, long text, single-select, multi-select, number, date, file upload, consent checkbox) and publish, the form is **published immutably** and exposes an **immutable, content-addressed version id = `sha256` of the canonical (stable-key-ordered) serialization of the definition** — the Epic 2 snapshot contract (FR-020, FR-012).
2. **Declarative only (NFR-005).** The form definition is declarative data only; an attempt to embed code/expressions or any field type **outside the allowed set** **fails validation** — covered by a test (FR-022, NFR-005).
3. **Edit published → new version; prior resolvable.** Editing a published form produces a **new version with a new version id**; the **prior version id remains resolvable** (versions are not mutated in place — `ImmutableWhenPublished`).

### ★ Edge-case hardening (must-fix-before-green)

4. **Version id is org-scoped.** The `sha256` content hash is **org-scoped** — a tenant **cannot enumerate another tenant's form by content hash** (the hash is not a global lookup key; resolution is always within the form/org via `BelongsToTenant`).
5. **Identical republish is idempotent.** Republishing **identical** canonical content does **not** create a duplicate version row — return the existing version (defined owner/refcount). The canonicalization must be **stable** (same logical definition → same hash regardless of key order / whitespace).

## Tasks / Subtasks

- [x] **Task 1 — Schema** (AC: 1, 3, 4)
  - [x] `forms` table (the parent, org-scoped): `id` ulid PK, `organization_id`, `program_id` (ulid, indexed — the owning program), `name`, `created_at`. Mirror the **Stages parent** (`program_stages`).
  - [x] `form_versions` table (the versionable): `id` ulid PK, `form_id` (ulid, indexed — the version parent), `version_number` (unsigned int), `status` (string: draft/published/archived), `content_hash` (string(64), the sha256 version id), `definition` (jsonb — the field list), `published_at` (timestampTz nullable), `created_at`. **`unique(['form_id','content_hash'])`** so identical content can't duplicate (AC-5). Mirror `stage_versions`.
  - [x] Migrations date-ordered after `2026_06_20_000600` → `…000700`/`…000710`.
- [x] **Task 2 — Models** (AC: 1, 3, 4) — `app/Modules/Forms/Domain/Models/`:
  - [x] `Form` — `final`, `HasUlids`, **`BelongsToTenant`** (org-scoped, AR-6), `hasMany(FormVersion)`. Mirror `ProgramStage`.
  - [x] `FormVersion` — `final`, `HasUlids`, implements **`App\Shared\Versioning\Versionable`** (`versionParentColumn()` → `'form_id'`; `validateForPublish()` → assert definition valid), uses **`App\Shared\Versioning\ImmutableWhenPublished`** (blocks update/delete once published). Casts `definition => 'array'`, `published_at => 'datetime'`, status enum. **Read `app/Modules/Stages/Domain/Models/StageVersion.php` and mirror it exactly** — it is the working precedent for Versionable + ImmutableWhenPublished + VersionPublisher. [Source hint]
- [x] **Task 3 — Field-type validator** (AC: 2) — `app/Modules/Forms/Domain/FieldType.php` (enum of the 8 types) + a `FormDefinitionValidator` that:
  - [x] Accepts only a list of fields each with a `type` in the `FieldType` enum + declarative props (label, required, options for selects). **Rejects** any field with an unknown type, or any node carrying code/expression keys (e.g. `expr`, `code`, `formula`, `script`) — declarative data only (NFR-005). Throws a domain `InvalidFormDefinitionException` with a test that an embedded PHP/SQL/JS/expression fails validation.
- [x] **Task 4 — `PublishForm` service** (AC: 1, 3, 5) — `app/Modules/Forms/Application/PublishForm.php`, `final`, mirror `PublishStageVersion` + `CreateOrganization`:
  - [x] `handle(Form $form, array $definition): FormVersion` — within `DB::transaction`: validate the definition (Task 3) → compute `content_hash = sha256(canonicalJson($definition))` where `canonicalJson` recursively ksorts keys (stable, AC-5) → if a `form_versions` row already exists for `(form_id, content_hash)`, **return it** (idempotent republish, AC-5) → else create a draft FormVersion and **publish it via `App\Shared\Versioning\VersionPublisher`** (assigns the next `version_number`, sets status Published + `published_at`). The returned version's `content_hash` is the **content-addressed version id**.
  - [x] `@param array<string, mixed> $definition` and annotate every `array` param (CI runs PHPStan L6 — this red-failed CI on 2.2/2.3; do not repeat). No `@template` generics.
- [x] **Task 5 — Tests** (AC: all) — see Testing Requirements.

## Dev Notes

### Where this fits
The net-new Forms module (currently `.gitkeep` scaffold). Produces the form `content_hash` that **Story 2.6's `form_version_id`** binds and **Story 2.7** attaches to a cohort + submit. Epic 1's Programs/Cohorts/Stages are already built — reuse them. [Source: epics.md#Story 1.3; implementation-status.md]

### Reuse — the versioning kernel is the spine (verified API)
- **`App\Shared\Versioning\Versionable`** (interface): `versionParentColumn(): string`, `validateForPublish(): void`. **`ImmutableWhenPublished`** (trait): blocks `updating`/`deleting` once published. **`VersionPublisher::publish(Model&Versionable $v)`**: assigns the next `version_number` within the parent scope, sets status Published + `published_at` in a transaction. **`VersionStatus`** enum (draft/published/archived). [Source: app/Shared/Versioning/*]
- **The Stages module is the working precedent** — `ProgramStage` (parent) + `StageVersion` (versionable) + `PublishStageVersion` (service). **Read those three files and mirror them** for Form/FormVersion/PublishForm. [Source: app/Modules/Stages/]
- **Content addressing pattern** = the same `sha256` + canonical-serialization idea as Story 2.1's blob digest, but over the **definition** (not file bytes) and **org-scoped** (not global). Do NOT reuse the blob store; this hash lives on `form_versions.content_hash`. [Source: 2-1 story / epics.md glossary]
- **Tenancy:** `BelongsToTenant` on `Form` (org_id server-set, fail-closed). `FormVersion` is reached via its `Form`, so it inherits org-scoping through the parent; still add an org-scoped resolution test (AC-4, AR-6). [Source: app/Shared/Tenancy/BelongsToTenant.php]

### Previous-story intelligence (from 2.1–2.6 — apply directly)
- **Test env = SQLite `:memory:`**; `jsonb`/`timestampTz`/`ulid` fine; `RefreshDatabase`; PHPUnit class-style. Tenant test setup: `[$user,$org] = $this->bootUserWithOrg(); $this->actingAsTenant($user,$org);` (base `TestCase` helpers). [Learned 2.6]
- **CI runs PHPStan level 6** (not locally — OOMs): annotate **every** `array` param `@param array<...>`; **no `@template` generics**. These exact omissions red-failed CI on 2.2/2.3. [Learned: CI PR #6]
- **CI test job now boots** (APP_KEY fix merged) — your tests run on CI on SQLite, same as local.
- Migration date-ordered; latest is `2026_06_20_000600` → use `2026_06_20_000700`/`000710`.

### Project Structure Notes
- New: `app/Modules/Forms/{Domain/Models/Form.php, Domain/Models/FormVersion.php, Domain/FieldType.php, Domain/FormDefinitionValidator.php (+ Exceptions), Application/PublishForm.php}`; 2 migrations; tests.
- **Anti-scope:** **attaching** a published form to a cohort + the public URL is **Story 1.4**; the public application page + submit is **2.7**. 1.3 = build + publish the form and expose its content-addressed version id. No HTTP controller required (a service + tests), unless trivially mirroring an existing publish controller.

### References
- [Source: _bmad-output/planning-artifacts/epics.md#Story 1.3 ; #Edge-Case Hardening — 1.3 ; Glossary (content-addressed version id)]
- [Source: _bmad-output/planning-artifacts/architecture.md — FR-012/020/022, NFR-005]
- [Source: app/Shared/Versioning/{Versionable,ImmutableWhenPublished,VersionPublisher,VersionStatus}.php — the kernel to reuse]
- [Source: app/Modules/Stages/{Domain/Models/StageVersion.php, Application/PublishStageVersion.php} — the precedent to mirror]
- [Source: app/Modules/Cohorts/Domain/Models/Cohort.php — BelongsToTenant/HasUlids model shape]

### Glossary
- **content-addressed version id** — `sha256` of the canonical (stable-key-ordered) serialization of a published form definition; **org-scoped**; stored on `form_versions.content_hash`. The id Story 2.6's snapshot pins.
- **canonical serialization** — recursively key-sorted JSON of the definition, so the same logical form always hashes identically (AC-5).

## Testing Requirements

PHPUnit class-style, `RefreshDatabase`, tenant via `bootUserWithOrg()` + `actingAsTenant()`:
- **Publish + content hash (AC-1):** publish a valid 8-field-type definition → a published `FormVersion` with `status=published`, `version_number=1`, `content_hash == sha256(canonical(def))`.
- **Declarative-only (AC-2):** a definition with an unknown field type, or a node containing `expr`/`code`/`formula`/`script`, → `InvalidFormDefinitionException`; nothing published.
- **Edit → new version, prior resolvable (AC-3):** publish v1, publish a changed definition → v2 (new `content_hash`, `version_number=2`); v1 still resolvable by its id; v1 cannot be updated (ImmutableWhenPublished throws).
- **★ Idempotent republish (AC-5):** publish identical content twice → same row returned, no duplicate (`assertDatabaseCount('form_versions', 1)`); key-reordered/whitespace-different but logically identical content → same hash.
- **★ Org-scoped (AC-4):** org A's form/version is not resolvable under org B's context (BelongsToTenant on `Form`); a content hash from org A does not surface org B data.
- Lint: `vendor/bin/pint` pass; `php -l` clean. **PHPStan: annotate all `array` params.**

## Dev Agent Record

### Agent Model Used

claude-opus-4-8[1m] (Claude Opus 4.8, 1M context)

### Debug Log References

- `php artisan test --filter PublishFormTest` → 6 passed (first run). Full suite → **321 passed, 0 failures** (+6). Pint clean. All `array` params annotated for PHPStan L6.

### Completion Notes List

- Built the net-new `App\Modules\Forms`: `Form` (parent, BelongsToTenant) + `FormVersion` (Versionable + ImmutableWhenPublished, mirrors `StageVersion`) + `FormDefinitionValidator` + `FieldType` enum + `PublishForm` service (mirrors `PublishStageVersion`).
- **Content-addressed version id (AC-1):** `content_hash = sha256(canonicalJson(definition))`, canonicalJson recursively ksorts keys → stable. Publishes via the reused `VersionPublisher`. Audited `form.published`.
- **Declarative-only (AC-2/NFR-005):** rejects unknown field types AND any node carrying code/expression keys (recursive).
- **Edit → new version, prior resolvable + immutable (AC-3):** new id/version_number; prior still resolvable; published update throws `VersionStateException`.
- **★ Idempotent republish (AC-5):** `unique(form_id, content_hash)` + pre-check return the existing version; key-reordered identical content hashes the same.
- **★ Org-scoped (AC-4):** `BelongsToTenant` on both models; a content hash from org A returns 0 rows under org B.
- **Unblocks Story 2.7** (with 1.4) — `form_version.content_hash` is the `form_version_id` 2.6's snapshot binds.

### Change Log

| Date | Change |
|---|---|
| 2026-06-20 | Implemented Story 1.3 — Forms module: published immutable form + content-addressed version id, declarative validation, org-scoped, idempotent republish. 6 tests. Status → review. |

### File List

**New:**
- `backend/database/migrations/2026_06_20_000700_create_forms_table.php`
- `backend/database/migrations/2026_06_20_000710_create_form_versions_table.php`
- `backend/app/Modules/Forms/Domain/FieldType.php`
- `backend/app/Modules/Forms/Domain/FormDefinitionValidator.php`
- `backend/app/Modules/Forms/Domain/Exceptions/InvalidFormDefinitionException.php`
- `backend/app/Modules/Forms/Domain/Models/Form.php`
- `backend/app/Modules/Forms/Domain/Models/FormVersion.php`
- `backend/app/Modules/Forms/Application/PublishForm.php`
- `backend/tests/Feature/Forms/PublishFormTest.php`
