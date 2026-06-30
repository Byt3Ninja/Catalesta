# Assessments Phase A — Scoring-Model Authoring + Versioning — Design

> Status: Approved (design) · Date: 2026-07-01 · Branch: `feat/be-assessments-scoring-model-authoring`
> Implements **Phase A** of ADR-0012 (Assessments module). Scoring-model authoring
> + versioning only — a direct twin of the Forms authoring slice (#66). No scoring
> math, no assignments/scorecards/decisions/binding (Phases B–D).

## 1. Goal

Give the already-built FE scoring-model authoring pages
(`ScoringModelBuilderPage`/`PreviewPage`/`VersionsPage`) a real backend: create a
per-program `ScoringModel`, edit its draft criteria, publish an immutable
`ScoringModelVersion`, and fork a published version into a new draft — wired to
the existing FE `api/assessments.ts` authoring calls, replacing MSW.

## 2. Authoritative contract

The backend conforms to the shipped FE shape (the FE does not change):
- `frontend/src/schemas/assessments.ts` — `ScoringModel`, `ScoringModelVersion`,
  `ScoringCriterion`.
- `frontend/src/api/assessments.ts` — the authoring endpoints + status codes:
  `listScoringModels`, `getScoringModel`, `getScoringModelVersion`,
  `listScoringModelVersions`, `createScoringModel`, `saveScoringModelDraft`,
  `publishScoringModel`, `forkScoringModelDraft`.

Field renames the resources must emit (backend column → FE key): `id`→`model_id`
/ `version_id`, `version_number`→`version`, plus derived `latest_version`,
`published_version_ids`, `current_draft_version_id`.

## 3. Decisions (locked, from ADR-0012)

- `ScoringModel` is per-program and versioned; `ScoringModelVersion` is immutable
  + content-addressed via the shared `VersionPublisher`/`ImmutableWhenPublished`
  kernel — identical to `FormVersion`.
- A **criterion** is `{criterion_id, label, max_points, descriptors[]|null}`,
  `max_points` a positive number; criteria are stored as the version's immutable
  jsonb snapshot. **No scoring math in Phase A** — criteria are definitions only.
- Authoring mirrors the Forms slice exactly: persistent draft, `PATCH /draft`
  edits the draft, `POST /publish` seals it (idempotent), `POST /fork` creates a
  new draft from a published version. Editing a published version → 409.
- Deny-by-default; tenant-scoped; cross-tenant `{id}` → 404. New
  `assessments.manage` permission gates create/draft/publish/fork.

## 4. New aggregate (migrations + models)

- `scoring_models` — `id` (ULID), `organization_id`, `program_id` (index),
  `name`, `current_published_version_id` (nullable). One+ per program.
- `scoring_model_versions` — `id` (ULID), `organization_id`, `scoring_model_id`,
  `version_number`, `status` (draft|published), `content_hash` (nullable, set at
  publish), `criteria` (jsonb), `published_at` (nullable), timestamps.
  `UNIQUE(scoring_model_id, content_hash)`.
- Models `ScoringModel`, `ScoringModelVersion` (implements `Versionable`,
  `ImmutableWhenPublished`, `BelongsToTenant`), mirroring `Form`/`FormVersion`
  (`versionParentColumn` = `scoring_model_id`).

## 5. Application services (twins of Forms services)

- `CreateScoringModel::handle(Program, string $name): ScoringModel` — creates the
  model + an initial empty draft version (version_number 0/draft), mirroring
  `CreateForm`.
- `SaveScoringModelDraft::handle(ScoringModel, array $criteria): ScoringModelVersion`
  — upserts criteria onto the current draft version; 409 if no draft / published
  (`ImmutableWhenPublished`). Validates each criterion (§7). Uses
  `$request->input('criteria', [])` semantics so nested keys are not stripped.
- `PublishScoringModel::handle(ScoringModel): ScoringModelVersion` — content-hash
  idempotent publish of the persistent draft via `VersionPublisher::publish`
  (assigns version_number, Published, published_at), sets
  `current_published_version_id`; audits `scoring_model.published`.
- `ForkScoringModelDraft::handle(ScoringModel, ScoringModelVersion $from): ScoringModelVersion`
  — creates a new draft seeded from a published version's criteria.

## 6. HTTP + resources

Endpoints (authenticated, tenant-scoped), matching `api/assessments.ts`:
- `GET /programs/{program}/scoring-models` · `POST /programs/{program}/scoring-models` `{name}` → 201
- `GET /scoring-models/{id}` · `GET /scoring-models/{id}/versions`
- `GET /scoring-model-versions/{id}`
- `PATCH /scoring-models/{id}/draft` `{criteria}` → 200 / 404 / 409
- `POST /scoring-models/{id}/publish` → 200 / 404 (unknown model) / 409 (no draft to
  publish) / 422 (draft has zero criteria — a model with no criteria cannot score).
  Note: the shipped FE `publishScoringModel` maps only 404/409 and surfaces 422 as a
  generic error; a tiny FE follow-up can add a 422 message. Backend stays REST-correct.
- `POST /scoring-models/{id}/fork` `{from_version_id}` → 200/201

`ScoringModelResource`: `model_id`, `program_id`, `name`, `latest_version`
(max published version_number, 0 if none), `published_version_ids` (ULIDs of
published versions), `current_draft_version_id` (draft version id or null),
`created_at`. `ScoringModelVersionResource`: `version_id`, `model_id`, `version`
(`(int)` cast for Scramble), `status`, `criteria` (array), `created_at`,
`published_at`.

## 7. Validation

`criteria` is an array of objects, each: `criterion_id` (string, required),
`label` (string, required), `max_points` (numeric, > 0), `descriptors`
(array of strings, nullable). Reject unknown/forbidden keys defensively; read via
`input('criteria', [])` (not `validated()` nested-stripping). Empty criteria
allowed on a draft; publish allowed with ≥1 criterion (422 otherwise — a scoring
model with no criteria cannot score).

## 8. Authorization, tenancy, audit

- New `ScoringModelPolicy` (view = any tenant member; create/draft/publish/fork =
  `assessments.manage`).
- Add `assessments.manage` to the permission catalog **and** to the owner-role
  grant list in `CreateOrganization` (the hardcoded `whereIn` — the bug the Forms
  review caught); regression-test it in `OrganizationApiTest`.
- New `AuditAction::ScoringModelPublished` (`scoring_model.published`); update the
  FR-052 lockstep test `tests/Unit/Audit/AuditActionTest.php` in the same task
  that adds the case.
- All org-scoped via `BelongsToTenant`; cross-tenant `{id}` → 404.

## 9. Testing

- Authoring: create model (+initial draft); save draft criteria; publish
  (immutable version, content hash, idempotent republish); fork (new draft from
  published); 409 editing a published version; 422 publishing with no criteria;
  422 invalid criterion (`max_points` ≤ 0 / missing label).
- Resources emit the FE shape (`model_id`/`version_id`/`version`/`latest_version`/
  `published_version_ids`/`current_draft_version_id`); cross-tenant 404; 403
  without `assessments.manage`.
- FR-052 lockstep updated; owner-role grant regression.
- Regenerate `backend/openapi/openapi.json` (Scramble); `OpenApiSpecTest` +
  Spectral 0 errors; full backend gate (pint/phpstan/deptrac/test) green.

## 10. Out of scope (Phases B–D / deferred)

- ALL scoring math, the decimal engine, weight normalization, mean aggregation
  (Phase C) — Phase A defines criteria only, computes nothing.
- Reviewer assignments, scorecards, blind review, COI (Phase B).
- Leaderboard, decision propose/record, export (Phase C).
- `cohorts.stage_scoring_model_version_ids` + `bind-stage-scoring-model`
  (Phase D / ADR-0011 Phase 3).
- Stage/cohort coupling of any kind — Phase A is program-scoped authoring only.
