# Stage-Pipeline Snapshot + Cohort Bind (ADR-0011 Phase 1) — Design

> Status: Approved (design) · Date: 2026-06-30 · Branch: `feat/be-stage-pipeline-snapshot`
> Implements **Phase 1** of ADR-0011 (stages reconciliation via an immutable
> pipeline-version projection over the backend engine).

## 1. Goal

Produce an immutable, content-addressed **`StagePipelineVersion`** by snapshotting
a program's published stage graph, and let a cohort **bind** that version — wiring
the Slice 2c preview + `StagePipelineBindingPicker` to real data. The existing
per-program Stages engine remains the editing + runtime system of record
(unchanged). This phase does **not** retarget the 2c builder authoring, does not
add write-side routing-rule translation, and does not add the 2d scoring binding.

## 2. Authoritative contract

- `frontend/src/api/stages.ts` — the read endpoints the FE expects for pipelines /
  versions (the subset needed for preview + binding; authoring endpoints
  draft/fork/templates are Phase 2).
- `frontend/src/schemas/stages.ts` — `StagePipeline` and `StagePipelineVersion`
  (with embedded `stages[]`) Zod shapes the read responses must satisfy.
- `frontend/src/api/cohorts.ts` — `bindCohortStagePipeline` → `POST
  /cohorts/{id}/bind-stage-pipeline` `{ stage_pipeline_version_id }`, status codes.
- `frontend/src/schemas/cohorts.ts` — `cohort.stage_pipeline_version_id`.

The backend conforms; the FE does not change. Where the backend graph is richer
than the FE shape (11 stage types vs 5; native `StageRule` expressions vs
`{match,conditions}`), the **read resource translates backend → FE** (§6).

## 3. Decisions (locked, from ADR-0011)

- One `StagePipeline` per program (the program's stage configuration is its
  pipeline). `program_id` is the natural key.
- `StagePipelineVersion` is immutable + content-addressed (sha256 of the canonical
  snapshot), idempotent republish, `ImmutableWhenPublished` — same machinery as
  `FormVersion`.
- The snapshot captures the program's stage graph **in the backend-native
  representation** (the canonical store); `stage_id` in the snapshot = the captured
  `ProgramStage` ULID.
- `PublishStagePipeline` requires **every** program stage to have a published
  `StageVersion`; otherwise **422** naming the offending stages.
- Cohort bind mirrors the form-binding slice: draft-only cohort, published-version
  only (404), same-version idempotent (200), different-version (409).
- Backend 11-value `StageType` is canonical; the read resource maps to the FE's
  vocabulary for display.

## 4. New aggregate (migrations + models)

- `stage_pipelines` — `id` (ULID), `organization_id`, `program_id` (unique),
  `name`, `current_published_version_id` (nullable). One per program.
- `stage_pipeline_versions` — `id` (ULID), `organization_id`, `stage_pipeline_id`,
  `version_number`, `status` (draft|published|archived), `content_hash` (nullable,
  set at publish), `snapshot` (jsonb — the captured graph, see §5), `published_at`
  (nullable), timestamps. `UNIQUE(stage_pipeline_id, content_hash)`.
- `cohorts.stage_pipeline_version_id` (nullable ULID) — the bound version.
- Models `StagePipeline`, `StagePipelineVersion` (implements `Versionable`,
  `ImmutableWhenPublished`, `BelongsToTenant`), mirroring `Form`/`FormVersion`.

## 5. Snapshot content (`PublishStagePipeline`)

`PublishStagePipeline::handle(Program $program): StagePipelineVersion` — finds (or
creates) the program's `StagePipeline`, then in a transaction:

1. Load all `ProgramStage` rows for the program (ordered by `order_index`). If any
   lacks a `current_published_version_id` → throw `StagePipelineNotPublishableException`
   (→422) listing the offending stage keys.
2. Build the canonical `snapshot` (jsonb): for each stage — `stage_id`
   (`ProgramStage.id`), `key`, `name`, `type` (backend value), `order_index`,
   the published `StageVersion` `config`, its `StageRule`s (native `expression`),
   `next_stage_ids` (resolved from `StageTransition` `from→to` for that program),
   and `depends_on_stage_ids` (from `StageDependency`). Plus program-level metadata.
3. `content_hash = sha256(canonicalJson(snapshot))`. If a published version with
   that hash exists → idempotent return (discard the redundant draft path; same as
   `PublishForm`). Else create the version, `VersionPublisher::publish` (assigns
   `version_number`, Published, `published_at`), set
   `stage_pipeline.current_published_version_id`.
4. Audit `stage_pipeline.published` (add `AuditAction::StagePipelinePublished`;
   update the FR-052 lockstep test — `tests/Unit/Audit/AuditActionTest.php`).

The snapshot stores routing/rules natively; it is the runtime authority for any
future per-version execution.

## 6. Read endpoints + resource translation

Endpoints (authenticated, tenant-scoped; mirror the FE `api/stages.ts` read paths):
- `GET /programs/{program}/stage-pipelines` — the program's pipeline(s) (0 or 1).
- `GET /stage-pipelines/{id}` — a pipeline (`latest_version`,
  `published_version_ids`, `current_draft_version_id` derived — draft is null in
  Phase 1 since authoring is deferred).
- `GET /stage-pipelines/{pipeline}/versions` and `GET /stage-pipeline-versions/{id}`.
- `POST /programs/{program}/stage-pipelines/publish` — runs `PublishStagePipeline`.
  **Note:** this is program-scoped (snapshot-the-program-now), deliberately
  diverging from the FE's authoring-driven `POST /stage-pipelines/{id}/publish`.
  In Phase 1 there is no draft pipeline to publish by id; the FE builder's publish
  button stays on MSW until Phase 2 retargets authoring. An operator (or a thin
  "snapshot now" affordance) drives this endpoint to mint real versions the
  binding picker can consume.

`StagePipelineVersionResource` translates the native `snapshot` → the FE
`StagePipelineVersion` shape: `stages[]` with `stage_id`, `name`, `type` (mapped to
the FE vocabulary; backend-only types fall back to a documented default label),
`order`, `next_stage_ids`, `depends_on_stage_ids`. Entry/exit rules are translated
backend `expression` → FE `{match, conditions}` **best-effort**: expressions that
map cleanly to `{field_id, operator, value}` are emitted; anything the simple
mapping cannot express is emitted as `null` (preview degrades to structure-only).
Full bidirectional rule translation is Phase 2.

## 7. Cohort bind

`BindCohortStagePipeline::handle(Cohort, string $versionId): Cohort` mirrors
`BindCohortForm`: draft-only (else 409 `CohortStateException`); `StagePipelineVersion`
looked up scoped to tenant + `status=published` (else 404); same version →
idempotent 200; different version bound → 409; else set
`stage_pipeline_version_id`, audit `cohort.stage_pipeline_bound`, return.
`CohortController@bindStagePipeline` (POST `/cohorts/{id}/bind-stage-pipeline`),
`CohortPolicy@bindStagePipeline` → `cohorts.manage`. `CohortResource` emits
`stage_pipeline_version_id`.

## 8. Authorization, tenancy, audit

New `StagePipelinePolicy` (view any tenant member; publish requires `stages.manage`)
and the cohort bind ability above. All org-scoped via `BelongsToTenant`;
cross-tenant `{id}` → 404. New audit actions `StagePipelinePublished`,
`CohortStagePipelineBound` (+ FR-052 lockstep test update).

## 9. Testing

- `PublishStagePipeline`: snapshots a fully-published program (immutable version,
  content hash, idempotent republish); **422** when a stage is unpublished (names
  it); does not mutate the underlying `ProgramStage`/`StageVersion` rows.
- Read endpoints: pipeline list/get, version list/get; resource emits the FE shape
  (`stage_id` = ProgramStage ULID; topology populated; type mapped); cross-tenant 404.
- Bind: 200 / idempotent / 409 different / 409 non-draft cohort / 404 cohort /
  404 non-published-or-foreign version / cross-tenant 404 / 403 without
  `cohorts.manage` (reuse the cohort-bind test patterns).
- Existing Stages engine + participant-runtime tests stay green (the engine is
  untouched).
- Regenerate `backend/openapi/openapi.json` (Scramble); `OpenApiSpecTest` +
  Spectral + full backend gate green.

## 10. Explicitly out of scope (later phases / deferred)

- FE 2c **authoring** retarget (builder create/draft/fork) and the **write-side**
  `{match,conditions}` → `expression` translator (Phase 2).
- Slice 2d `cohorts.stage_scoring_model_version_ids` + `bind-stage-scoring-model`
  (Phase 3; also needs the Assessments backend).
- Multiple pipelines / track variants per program; the `stage-templates` catalog.
- A no-form-style unbind/replace path for a bound pipeline (consistent with the
  form-binding slice's deferral).
