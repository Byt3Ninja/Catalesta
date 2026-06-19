# Phase 2 Completion — Tracks, Stage Dependencies, Archival (Design)

Status: Approved (2026-06-19)
Branch: `phase2-completion`
Driver: closes the unimplemented items in the authoritative Phase-2 prompts —
`prompts/05-programs-cohorts-templates.md` (goal: "...templates, cloning, publication, **archival**, **tracks**...")
and `prompts/06-stage-engine.md` (goal: "...ordering, **dependencies**, parallel stages, conditional routing...").
Governing rules: CLAUDE.md 6 (every tenant-owned record has `organization_id`), 7 (tenant isolation),
8 (published stages immutable), 10 (no code execution in rules), 12 (immutable snapshots), 14 (tests+docs).
Builds on merged Phase 2 (M0–M5) + fail-closed tenancy.

## 1. Goal

Close the three unimplemented Phase-2 capabilities (frontend stays with the UX track):
1. **Program tracks** — personalized pathways that filter which stages apply to a participant.
2. **Stage dependencies** — prerequisite edges enforced when a participant enters a stage.
3. **Program archival** — an archive lifecycle action with read-only semantics + listing filter.

## 2. Scope

**In scope:** the three features above, their tenant-owned models/migrations, application services,
policies (reusing existing permission keys), API endpoints, and full tests + docs.

**Out of scope:** frontend screens (UX track, prompts 34–41); a distinct cohort "Archived" state
(cohort `Closed`/`Completed` already exist); OR-semantics for dependencies (AND only); domain
events/jobs and command idempotency (deferred reliability seam — `AuditLogger` records as today).

## 3. Current state (grounding)

- `ParticipantStageStatus` rows are created **lazily** by `AdvanceParticipantStage::enter(Cohort, ExternalUser, ProgramStage, context)`, keyed by `(cohort_id, external_user_id, program_stage_id)`. There is **no per-participant enrollment record** today — participation is implicit in the set of status rows. (Source: `app/Modules/Stages/Application/AdvanceParticipantStage.php`.)
- `ProgramStage`: `program_id, key, name, type, order_index, parallel_group, current_published_version_id`. `StageTransition` models conditional routing (from→to + condition). Neither models prerequisites.
- `ProgramStatus` already has `Draft, Published, Archived, Closed`; there is **no archive action** or read-only/listing semantics — only the enum value exists.
- All Phase-2 models are tenant-owned (`BelongsToTenant`, explicit `$fillable` excluding `organization_id`/`id`; org forced from `TenantContext` on create inside a resolved-tenant request).

## 4. Design

### 4.1 Program Tracks

**`tracks` table** (tenant-owned): `id` ulid pk, `organization_id` ulid index, `program_id` ulid index,
`key` string, `name` string, `description` nullable, `order_index` int default 0, `timestampsTz`;
`unique(['program_id','key'])`. Model `Track`: `final`, `HasUlids`, `BelongsToTenant`,
`$fillable = ['program_id','key','name','description','order_index']`.

**Stage→track applicability** via pivot **`program_stage_track`**: `id` ulid, `organization_id` ulid index,
`program_stage_id` ulid index, `track_id` ulid index, `timestampsTz`; `unique(['program_stage_id','track_id'])`.
**Rule: a stage with NO pivot rows is _global_ (applies to all tracks); rows scope it to exactly those tracks.**

**`cohort_participants` (enrollment aggregate)** — the new per-participant record (confirmed):
`id` ulid pk, `organization_id` ulid index, `cohort_id` ulid index, `external_user_id` ulid index,
`track_id` ulid nullable index (FK→tracks), `status` string default `active`, `enrolled_at` timestampTz,
`timestampsTz`; `unique(['cohort_id','external_user_id'])`. Model `CohortParticipant`: `final`, `HasUlids`,
`BelongsToTenant`, `$fillable = ['cohort_id','external_user_id','track_id','status','enrolled_at']`,
status cast to enum `EnrollmentStatus { Active='active', Withdrawn='withdrawn', Completed='completed' }`.
`track_id` nullable ⇒ untracked participant (global stages only).

**Applicability resolution** — a stage applies to a participant iff: the stage has no `program_stage_track`
rows (global) **OR** one of its rows matches the participant's `track_id`. A participant with `track_id = null`
sees only global stages.

**`AdvanceParticipantStage::enter` guard (new):** before entering a participant into a stage, resolve the
participant's enrollment (`CohortParticipant` by `cohort_id`+`external_user_id`); if the stage is **not
applicable** to their track, throw `StageNotApplicableToTrackException` (HTTP 422) — no status/instance written.
(If no enrollment exists, treat as untracked: global stages allowed, track-scoped stages blocked.)

**API** (all `auth:sanctum`+`tenant`; resolve ids tenant-scoped → foreign id 404; authorize per below):
- `GET  /api/v1/programs/{program}/tracks` · `POST /…/tracks` · `PATCH /api/v1/tracks/{id}` · `DELETE /api/v1/tracks/{id}` — authorize `programs.manage`. **Deleting a track** (transactional) removes its `program_stage_track` rows and sets any dependent `cohort_participants.track_id` to `null` (those participants become untracked → global stages only).
- Stage scoping: `PUT /api/v1/stages/{id}/tracks` body `{ track_ids: [] }` (empty = global) — authorize `stages.manage`. Replaces the stage's pivot rows transactionally; validates track_ids belong to the same program.
- Enrollment: `POST /api/v1/cohorts/{cohort}/participants` body `{ external_user_id, track_id? }` → 201 `CohortParticipantResource`; `PATCH /api/v1/cohort-participants/{id}` `{ track_id?, status? }` — authorize `cohorts.manage`. Validates `track_id` belongs to the cohort's program.

### 4.2 Stage Dependencies (prerequisites)

**`stage_dependencies` table** (tenant-owned): `id` ulid pk, `organization_id` ulid index,
`program_stage_id` ulid index (the dependent stage), `depends_on_program_stage_id` ulid index (the prerequisite),
`timestampsTz`; `unique(['program_stage_id','depends_on_program_stage_id'])`. Model `StageDependency`:
`final`, `HasUlids`, `BelongsToTenant`, `$fillable = ['program_stage_id','depends_on_program_stage_id']`.

**Validation on create** (in `AddStageDependency` service): both stages exist, belong to the **same program**
(reject cross-program), `program_stage_id !== depends_on_program_stage_id` (no self-edge), and adding the edge
**introduces no cycle** (DFS over existing edges) → else `InvalidStageDependencyException` (422).

**Semantics (AND):** a stage may be entered only when **every** prerequisite stage is `Completed` for that
participant (in the same cohort). Prerequisites are structural (stage-level, like transitions) — not part of
the versioned `StageVersion` config.

**`AdvanceParticipantStage::enter` guard (new):** before entry, load the stage's `stage_dependencies`; for each
prerequisite, require a `ParticipantStageStatus(cohort, participant, prereq)` with status `Completed`. If any is
unmet, throw `StagePrerequisiteNotMetException` (HTTP 422) — no status/instance written. (Guard order in `enter`:
track-applicability → prerequisites → published-version check → entry-rule evaluation.)

**API:** `GET /api/v1/programs/{program}/stages/{id}/dependencies` · `POST /…/dependencies` `{ depends_on_program_stage_id }`
· `DELETE /api/v1/stage-dependencies/{id}` — authorize `stages.manage`; tenant-scoped resolution.

### 4.3 Program Archival

**Services:** `ArchiveProgram::handle(Program): Program` (set `status=Archived`, audit `program.archived`) and
`UnarchiveProgram::handle(Program): Program` (restore to `Draft`; audit `program.unarchived`). Both transactional.
**API:** `POST /api/v1/programs/{id}/archive`, `POST /api/v1/programs/{id}/unarchive` — authorize `programs.manage`.

**Read-only semantics:** while a program's `status === Archived`, all **mutations within that program tree are
blocked** with HTTP 409 (Conflict) via a shared `EnsureProgramNotArchived` guard invoked by the relevant services /
FormRequests: program update, publish, stage create/update/reorder/publish, track create/update/delete + stage
scoping, dependency add/delete, cohort create/update under it, enrollment create/update, and participant
advance/complete. **Allowed while archived:** all reads, `clone` (clones to a new Draft), and `unarchive`.

**Listing filter:** `GET /api/v1/programs` excludes `Archived` by default; `?status=archived` returns archived,
`?include_archived=1` returns all. (Implemented as an explicit query scope, not by weakening the tenant scope.)

### 4.4 Cross-cutting

- All new tables tenant-owned (`organization_id` forced from context; fail-closed `BelongsToTenant`); every new
  model carries the trait + explicit `$fillable` (never `organization_id`/`id`) and passes the existing
  `TenantIsolationArchTest` (it scans all of `app/`).
- Migrations timestamped **after** `2026_06_18_080000` (e.g. `…_081000` tracks, `_081100` program_stage_track,
  `_081200` cohort_participants, `_081300` stage_dependencies). No FKs that reorder existing migrations.
- Reuse existing permission keys (`programs.manage`, `stages.manage`, `cohorts.manage`) — **no new keys** (avoids
  the `PermissionCatalogSeeder` + `CreateOrganization` dual-update).
- No business logic in controllers (services in `Application/`); DB transactions around multi-row ops; audit via `AuditLogger`.

## 5. Testing

- **Unit:** applicability resolver (global vs track-scoped vs untracked); cycle detection in dependency validation; archived read-only guard.
- **Feature/API:** track CRUD + stage scoping; enrollment create/patch with track; dependency add/remove + cycle/cross-program rejection (422); archive/unarchive + 409 on mutating an archived program + listing filter; `enter()` blocked by track-inapplicability (422) and unmet prerequisite (422), allowed when satisfied.
- **Authorization:** `programs.manage`/`stages.manage`/`cohorts.manage` gates → 403 for members lacking them.
- **Tenant isolation:** extend `Phase2TenantIsolationTest` — cross-tenant access to every new endpoint (tracks, stage scoping, cohort-participants, stage-dependencies, archive/unarchive) is blocked (404 by scope, or 403 by tenant-scoped `authorize()`); new list endpoints hide other-org rows.
- **Regression:** existing 244 tests stay green; `AdvanceParticipantStage` existing behavior preserved when no tracks/dependencies are configured (global stage, no prereqs → unchanged).
- Gate per task: `php artisan test` + `pint --test` + `phpstan analyse --memory-limit=512M`.

## 6. Acceptance criteria

| Criterion | Covered by |
|---|---|
| Tracks: stages filter by participant track; untracked → global only | §4.1, enter guard + applicability tests |
| Enrollment record carries track; tenant-owned | §4.1 `cohort_participants` |
| Stage dependencies enforced (AND) at entry; cycles/cross-program rejected | §4.2 + guard/validation tests |
| Program archive action + read-only (409) + listing filter; clone/unarchive still work | §4.3 + tests |
| All new endpoints tenant-isolated + authz-gated | §4.4, extended Phase2TenantIsolationTest |
| No new permission keys; published-immutability + fail-closed tenancy preserved | §4.4 |

## 7. Rollback

Additive migrations + new models/services/routes; no destructive schema changes. Rollback = drop the four new
tables and revert the `AdvanceParticipantStage` guards + archival guard. No data migration of existing rows.
