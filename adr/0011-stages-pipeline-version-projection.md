# ADR 0011: Reconcile Stages via an Immutable Pipeline-Version Projection over the Backend Engine

## Status

Accepted

## Context

Two divergent "stages" models exist in the repository and must be reconciled
before cohort↔stage binding (and the Slice 2d per-stage scoring binding) can be
wired to a real backend.

- **Backend Stages engine (real, tested, with runtime).** Stages are per-program
  `ProgramStage` rows, each independently versioned (`StageVersion`, parent column
  `program_stage_id`, immutable when published). Routing topology lives in a
  program-level `StageTransition` table (`from_program_stage_id → to_program_stage_id`,
  `condition` jsonb); entry/exit gating lives in `StageRule` (`expression` jsonb,
  evaluated by `ExpressionEvaluator` with `cohort.*`/`participant.*`/`context.*`
  field resolvers). Dependencies are a `StageDependency` edge table (DFS cycle
  check). Participant progression is **DB-persisted**: `ParticipantStageState` +
  `StageInstance`, where `AdvanceParticipantStage` binds the participant to the
  `stage_version_id` at entry time and evaluates exit rules against that bound
  version — a real immutability/runtime invariant. 11-value `StageType` enum.
  Real routes (`programs/{p}/stages`, `stages/{id}/publish`, dependencies) and
  feature tests (`ParticipantStageStateTest`, `AdvancePrerequisiteTest`,
  `StageRuleValidationTest`, plus clone/template paths).

- **Frontend Slice 2c (shipped #62, MSW-only).** A `StagePipeline` container
  (per `program_id`) whose `StagePipelineVersion` is an immutable snapshot
  embedding the whole `stages[]` graph; per-*pipeline* versioning with
  draft/publish/fork. Routing is embedded per stage (`next_stage_ids`,
  `entry_rule`/`exit_rule` as `{match, conditions:[{field_id,operator,value}]}`,
  reusing the 2b visibility-condition schema) and evaluated client-side
  (`lib/stageRouting`). The cohort binds a single `stage_pipeline_version_id`;
  Slice 2d's `stage_scoring_model_version_ids[stageId]` keys on a stage identity
  **within** a pipeline version. 5-value stage-type enum.

The shapes diverge on the versioning unit (per-stage vs per-pipeline), the
routing-rule schema *and* its evaluator, dependency storage, stage-type
vocabulary, the runtime model (DB-persisted vs client-pure), and cohort binding
(absent vs single-version pointer). The backend has **no** pipeline aggregate and
**no** `cohorts.stage_pipeline_version_id` / `stage_scoring_model_version_ids`
columns. This is a material contradiction (CLAUDE.md § Instruction Authority) that
blocks wiring 2c authoring and 2d scoring binding.

## Decision

Reconcile via an **immutable pipeline-version projection** over the existing
backend engine (the "adapter" direction).

1. **The backend per-program Stages engine remains the editing and runtime system
   of record.** Its `ProgramStage`/`StageVersion`/`StageTransition`/`StageRule`/
   `StageDependency` model and the `ParticipantStageState`/`StageInstance` runtime
   are preserved unchanged. Nothing is discarded or duplicated.
2. **A new `StagePipelineVersion` is an immutable, content-addressed snapshot** of
   a program's published stage graph (stages + their published versions +
   transitions + rules + dependencies), produced by a `PublishStagePipeline`
   operation. It follows the same versioning machinery as `FormVersion`
   (`ImmutableWhenPublished`, content hash, idempotent republish). There is **one
   `StagePipeline` per program** (the program's stage configuration *is* its
   pipeline); multiple pipelines / track variants per program are deferred.
3. **Cohorts bind a `StagePipelineVersion`** via `cohorts.stage_pipeline_version_id`
   and `POST /cohorts/{id}/bind-stage-pipeline`, mirroring the form-binding slice
   (ADR-aligned: draft-only, published-version-only → 404, same-idempotent /
   different → 409).
4. **Routing stays backend-native and canonical inside the snapshot.** The
   snapshot stores `StageRule`/`StageTransition` in the backend representation,
   executed at runtime by the existing single `ExpressionEvaluator`. The FE's
   `{match, conditions}` is a builder convenience translated to the backend rule
   format (FE-side); **no second rule engine is introduced in the backend.**
5. **The 11-value backend `StageType` enum is canonical.** The FE's 5 values map
   onto a subset (display labels only).
6. **The snapshot's `stage_id` is the captured `ProgramStage` ULID**, so 2d's
   `stage_scoring_model_version_ids[stageId]` and reviewer/scorecard `stage_id`
   reference a stable, real stage identity.
7. **Authoring stays on the existing per-program Stages endpoints.** The 2c
   builder/routing editor retarget to them in a later phase; this is not an
   authoring rewrite.

### Phasing

- **Phase 1 — Snapshot + cohort bind.** `StagePipeline` + `StagePipelineVersion`
  + `PublishStagePipeline` (snapshots the program's published stages; **422 if any
  stage lacks a published version**, naming the offenders) + `cohorts.stage_pipeline_version_id`
  + `POST /cohorts/{id}/bind-stage-pipeline` + read endpoints (list/get versions)
  for the FE preview and `StagePipelineBindingPicker`. Unblocks cohort↔pipeline
  binding and establishes the 2d `stage_id` keyspace.
- **Phase 2 — Authoring retarget + routing translator.** The 2c builder and
  routing editor map onto the backend stage / `StageRule` / `StageTransition`
  endpoints (`{match,conditions}` → `expression` jsonb).
- **Phase 3 — 2d per-stage scoring binding.** `cohorts.stage_scoring_model_version_ids`
  + `POST /cohorts/{id}/bind-stage-scoring-model`, keyed on the snapshot's
  `ProgramStage` ids (also depends on the Assessments backend).

## Alternatives Considered

- **Backend adopts the FE pipeline-version aggregate as the primary model.**
  Rejected. To make the pipeline aggregate primary, the existing 32-file tested
  engine and its `ParticipantStageState`/`StageInstance` runtime would either be
  replaced (high cost/risk; loses the `AdvanceParticipantStage` /
  `stage_version_id` binding invariants) or run in parallel with the per-stage
  engine (two stage systems — incoherent).
- **FE reworks Slice 2c to the backend stages-engine shape.** Rejected.
  Substantially reopens approved/shipped FE slice #62, and the backend still has
  no cohort↔stage binding or pipeline-level immutable version, so 2d's per-stage
  scoring binding would still have nothing stable to key against — it does not
  cleanly unblock 2d.
- **A third, neutral normalized routing-rule schema both sides translate to.**
  Rejected for now as heaviest-first: two translators + a new versioned schema,
  when the real engine already has a working evaluator the snapshot can store
  natively.

## Consequences

- **Positive:** Preserves the real, tested backend engine and its DB-persisted
  participant runtime; gives the FE and 2d the immutable, cohort-bindable version
  + stable `stage_id` keyspace they assume; reuses the proven `FormVersion`
  immutability/content-hash machinery and the just-shipped form-binding slice
  shape; ships in independent phases.
- **Constraint:** The FE routing editor must translate `{match,conditions}` ↔
  backend `StageRule` `expression` (Phase 2); the snapshot is the runtime
  authority, so any FE routing the translator cannot express is not executable.
- **Constraint:** `PublishStagePipeline` requires every program stage to have a
  published version (422 otherwise) — a program is not bindable until its stage
  graph is fully published.
- **Constraint:** One pipeline per program in Phase 1; multi-pipeline / track
  variants and the `stage-templates` catalog are deferred.
- **Versioning invariant upheld:** published `StagePipelineVersion` rows are
  immutable; cohorts hold an exact version reference; the snapshot reproduces the
  exact stage graph a cohort opened against (consistent with CLAUDE.md
  § Versioning and Historical Integrity).
- **Negative / debt:** until Phase 2, the FE 2c builder authoring stays on MSW
  (only preview + binding become real); the stage-type and routing translation
  seams are real surface area to maintain.

## References

- Spec: `docs/superpowers/specs/2026-06-30-be-stage-pipeline-snapshot-design.md` (Phase 1)
- Backend engine: `backend/app/Modules/Stages/*` (`ProgramStage`, `StageVersion`,
  `StageTransition`, `StageRule`, `StageDependency`, `AdvanceParticipantStage`,
  `ExpressionEvaluator`)
- FE 2c: `frontend/src/schemas/stages.ts`, `frontend/src/api/stages.ts`,
  `frontend/src/lib/stageRouting.ts`; cohort binding `frontend/src/api/cohorts.ts`
- Related: ADR-0009 (cross-tenant → 404), the form-authoring + cohort
  open/bind-form wiring slices (immutable `FormVersion` + `bind-form` pattern)
- CLAUDE.md § Versioning and Historical Integrity, § Architecture Ownership
