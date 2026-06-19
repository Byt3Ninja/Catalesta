# Phase 2 Design — Programs, Cohorts & Stage Engine (+ scoped Phase 1.5 kernel)

Status: Approved (2026-06-18)
Scope: `prompts/02-programs-stages.md`
Source contracts: `docs/01-product-scope.md`, `docs/03-domain-model.md`, `docs/04-data-model.md`, `docs/05-modules.md`, `docs/07-workflow-engine.md`, `docs/06-api-contracts.md`
Builds on: Phase 1 (Identity & Tenancy) — `TenantContext`, `BelongsToTenant`, `ResolveTenant`, RBAC, `AuditLogger`, error envelope.
Governing decisions: `~/.claude/.../memory/architecture-decisions.md` (cohort naming; shared rule/versioning kernel).

## 1. Goal

Implement configurable Programs, Cohorts, and a Stage engine: program CRUD + templates +
cloning, program policies + role requirements, cohorts, and stages with versioning,
ordering, entry/exit rules, parallel/conditional representation, and participant stage
state. Plus the scoped shared kernel (Versioning + Rules expression evaluator) that stages
depend on.

**Out of scope (do NOT implement):** Forms, Applications, Assessments, Documents. The full
Workflow engine (actions/approvals/escalations + rich field resolvers) remains Phase 7;
participant enrollment via Applications remains Phase 5.

## 2. Non-negotiable constraints (CLAUDE.md + docs)

- Modular monolith; modules under `app/Modules/{Programs,Cohorts,Stages}`; shared code under `app/Shared`.
- Every tenant-owned record has `organization_id`; every tenant query is scoped; composite uniques include `organization_id` (rules 6–7).
- Published stage versions are immutable and versioned (rule 8).
- No arbitrary PHP/SQL/JS/shell in rule definitions — rules are structured expression trees evaluated via registered resolvers/handlers only (rule 10).
- Decimal arithmetic for any numeric comparison (rule 9) via `brick/math`.
- Public APIs versioned under `/api/v1` (rule 12); no business logic in controllers (rule 14); sensitive actions server-authorized (rule 17).
- DB transactions around multi-record operations; ULID PKs; UTC storage; ISO 8601 APIs; JSONB only for bounded config.
- Every feature has tests (rule 13); update docs when contracts change (rule 19).

## 3. Module / file structure

```
app/Shared/Versioning/        ImmutableWhenPublished (concern), VersionStatus (enum), VersionPublisher (service)
app/Shared/Rules/             ExpressionEvaluator, FieldResolverRegistry, FieldResolver (contract),
                              ExpressionValidator, Operator (enum)
app/Modules/Programs/         Domain/Models, Application (services), Http (controllers/requests/resources), Policies, Tests
app/Modules/Cohorts/          Domain/Models, Application, Http, Policies, Tests
app/Modules/Stages/           Domain/Models, Application, Http, Policies, Tests
```

## 4. M0 — Shared kernel (scoped Phase 1.5)

### 4.1 Versioning (`app/Shared/Versioning`)
- `VersionStatus` enum: `draft`, `published`, `archived`.
- `ImmutableWhenPublished` model concern: a `booted()` guard throws on `updating`/`deleting`
  when the model's `status === published` (mirrors `ProfileSnapshot` immutability). Allows
  an explicit `archive()` transition (published → archived) via a dedicated path that the
  guard permits (status column change to `archived` only).
- `VersionPublisher` service: `publish($version)` — asserts `draft`, runs the version's
  validation hook, sets `status=published` + `published_at=now()` + assigns the next
  `version_number` for its parent definition, inside a transaction. Generic over any model
  implementing a small `Versionable` contract (`parentKey()`, `validateForPublish()`).

### 4.2 Rules (`app/Shared/Rules`)
- `Operator` enum (docs/07): `equals, not_equals, greater_than, greater_than_or_equal,
  less_than, less_than_or_equal, in, not_in, contains, contains_any, is_null, is_not_null`.
- Expression tree (docs/07 JSON): a node is either a group `{"all":[...]}` / `{"any":[...]}`
  or a leaf `{"field": "...", "operator": "...", "value": ...}`.
- `FieldResolver` contract: `supports(string $field): bool` + `resolve(string $field, array $context): mixed`.
- `FieldResolverRegistry`: holds registered resolvers; `resolve($field,$context)` dispatches
  to the first supporting resolver; throws `UnknownFieldException` if none — fields are
  readable ONLY through registered resolvers (no arbitrary array/object access).
- `ExpressionEvaluator::evaluate(array $tree, array $context): bool` — recursively evaluates
  groups (all = AND, any = OR) and leaves (resolve field → apply operator vs value). Numeric
  comparisons use `brick/math` (`BigDecimal`) for decimal safety; `in/not_in/contains/
  contains_any` operate on arrays/strings; `is_null/is_not_null` on presence.
- `ExpressionValidator::validate(array $tree): void` — structural validation used before
  persisting any rule/condition: only known group keys, known operators, registered field
  namespaces, and value types appropriate to the operator. Rejects anything else
  (`InvalidExpressionException`). No code execution path exists.
- Phase 2 registers a **minimal** resolver set sufficient to prove the engine, e.g.
  `participant.current_stage_status`, `cohort.is_open`, `context.*`. Rich resolvers
  (`assessment.*`, `documents.*`) are registered by their owning modules in later phases —
  the registry is open for extension.

## 5. M1 — Programs

### Tables (all tenant-owned: `organization_id` + `BelongsToTenant`; ULID PKs; UTC)
- `programs` — `id`, `organization_id`, `name`, `slug`, `status` (`draft|published|archived|closed`),
  `description?`, `settings` (JSONB), `template_id?`, timestampsTz. Unique(`organization_id`,`slug`).
- `program_templates` — `id`, `organization_id`, `name`, `slug`, `description?`, `blueprint` (JSONB), timestampsTz. Unique(`organization_id`,`slug`).
- `program_policies` — `id`, `organization_id`, `program_id`, `key`, `value` (JSONB), timestampsTz. Unique(`program_id`,`key`).
- `program_role_requirements` — `id`, `organization_id`, `program_id`, `role_key`, `min_count` (int), `max_count?` (int), `is_required` (bool), timestampsTz. Unique(`program_id`,`role_key`).

### Behavior
- **Lifecycle:** program `status` transitions via a publish endpoint (`draft→published`),
  plus `archive`/`close`. Programs are operational records with a status; they are not
  version-frozen (rule 8 applies to forms/workflows/assessments + stage versions).
- **Cloning** (`CloneProgram` service, transactional): deep-copies a source program into a
  new `draft` program — copies program_policies, program_role_requirements, program_stages
  (each as a fresh `draft` stage_version with copied config + stage_rules), and
  stage_transitions. Does not copy participant/cohort runtime state. New slug derived/uniqued.
- **Templates:** `CreateProgramFromTemplate` = instantiate a `program_templates.blueprint`
  into a new draft program (same deep-copy mechanism). Creating/saving a program as a
  template captures its definition into a blueprint.

## 6. M2 — Cohorts

- `cohorts` — `id`, `organization_id`, `program_id`, `name`, `slug`, `status`
  (`draft|open|closed|completed`), `enrollment_opens_at?`, `enrollment_closes_at?`,
  `starts_at?`, `ends_at?`, `capacity?` (int), `timeline` (JSONB), timestampsTz.
  Unique(`program_id`,`slug`). Belongs to a program.
- CRUD + status transitions (`open`/`close`/`complete`). Capacity + enrollment window are
  represented and validated (e.g. `enrollment_opens_at <= enrollment_closes_at <= starts_at`).
  Actual enrollment of participants is Phase 5.

## 7. M3 — Stages (core)

### Tables (tenant-owned; ULID PKs)
- `program_stages` — `id`, `organization_id`, `program_id`, `key`, `name`, `type`
  (`application|screening|interview|mentorship|training|assignment|review|evaluation|demo|graduation|custom`),
  `order_index` (int), `parallel_group?` (string), `current_published_version_id?`, timestampsTz.
  Unique(`program_id`,`key`); index(`program_id`,`order_index`).
- `stage_versions` — `id`, `organization_id`, `program_stage_id`, `version_number` (int),
  `status` (`draft|published|archived`), `config` (JSONB), `published_at?`, timestampsTz.
  Uses `ImmutableWhenPublished`. Unique(`program_stage_id`,`version_number`).
- `stage_rules` — `id`, `organization_id`, `stage_version_id`, `type` (`entry|exit`),
  `expression` (JSONB; validated tree), timestampsTz. (Rules belong to a version so they
  freeze with it.)
- `stage_transitions` — `id`, `organization_id`, `program_id`, `from_program_stage_id?`
  (null = start), `to_program_stage_id`, `condition` (JSONB?; validated), `order_index` (int),
  timestampsTz. Models sequential flow + conditional branching.
- `participant_stage_statuses` — `id`, `organization_id`, `cohort_id`, `external_user_id`,
  `program_stage_id`, `status` (`not_started|in_progress|completed|skipped|blocked`),
  `entered_at?`, `completed_at?`, timestampsTz. Unique(`cohort_id`,`external_user_id`,`program_stage_id`).
- `stage_instances` — `id`, `organization_id`, `participant_stage_status_id`,
  `stage_version_id` (the PUBLISHED version bound at entry), `started_at`, timestampsTz.

### Behavior
- **Add stage:** creates a `program_stage` + an initial `draft` `stage_version`. `order_index`
  appended.
- **Reorder** (`POST /stages/reorder`): updates `order_index` for a program's stages — allowed
  while editing; published versions are untouched (ordering is a program_stage attribute, not
  frozen in the version).
- **Publish version** (`VersionPublisher`): freezes a stage_version (config + its stage_rules);
  sets `program_stages.current_published_version_id`. Published versions reject mutation
  (model guard) → editing requires a new draft version.
- **Parallel:** stages sharing a `parallel_group` run concurrently. **Conditional:**
  `stage_transitions.condition` (validated expression) gates a transition.
- **Participant stage state:** `AdvanceParticipantStage` service sets/advances
  `participant_stage_statuses` and, on entry, creates a `stage_instance` bound to the stage's
  current published version. Entry/exit rules are evaluated via `ExpressionEvaluator` against
  the available context; rich data fields arrive in later phases. (Phase 2 tests use a
  directly-created participant reference + synthetic context.)

### Invariants
1. A published `stage_version` cannot be modified (model guard).
2. A `stage_instance` binds to the published version active at entry.
3. All stage tables carry `organization_id` and are tenant-scoped.
4. Entry/exit/transition expressions are validated before persistence; evaluation goes only
   through registered field resolvers.

## 8. API (docs/06; all under `/api/v1`, `auth:sanctum` + `tenant` middleware)

- `GET /programs`, `POST /programs`, `GET /programs/{id}`, `PATCH /programs/{id}`,
  `POST /programs/{id}/publish`, `POST /programs/{id}/clone`.
- `POST /programs/{program}/cohorts`, `GET /cohorts/{id}`, `PATCH /cohorts/{id}`.
- `GET /programs/{program}/stages`, `POST /programs/{program}/stages`, `PATCH /stages/{id}`,
  `POST /stages/{id}/publish`, `POST /stages/reorder`.
- Form requests validate input (incl. expression-tree validation for rules/conditions);
  API resources shape output; standard error envelope with `correlation_id`.

## 9. Authorization

New permission keys added to `PermissionCatalogSeeder` and the owner system role:
`programs.manage`, `programs.publish`, `cohorts.manage`, `stages.manage`.
Policies (`ProgramPolicy`, `CohortPolicy`, `StagePolicy`) check `TenantContext->can(...)`;
unauthorized → 403. Audit: `program.created/updated/published/cloned`,
`cohort.created/updated`, `stage.created/reordered/published`.

## 10. Testing (TDD; docs/12)

- **Unit:** ExpressionEvaluator (every operator; nested all/any; resolver registry dispatch;
  decimal comparison correctness); ExpressionValidator (rejects unknown operator, unknown
  field namespace, malformed shape, and any non-structured/"code" value); VersionPublisher
  (draft→published, version_number increment) + ImmutableWhenPublished guard (update/delete
  on published throws); CloneProgram deep-copy; reorder; parallel_group + transition
  representation; participant stage-state transitions + stage_instance binds published version.
- **Feature:** program CRUD + publish + clone; cohort CRUD + status; stage add/reorder/publish;
  **published stage version immutable** (PATCH a published version → rejected); program
  cloning produces editable drafts; **tenant isolation** (cross-org access → 403, scope
  exclusion); **authz** (missing permission → 403); validation errors → 422 envelope.
- Coverage focus on the rule engine, versioning/immutability, tenant isolation, authorization.

## 11. Dependencies

No new Composer packages required (`brick/math` already present from Phase 1). Reuses
Sanctum/TenantContext/AuditLogger/error-envelope from Phase 1.

## 12. Acceptance-criteria mapping (prompts/02)

| Criterion | Covered by |
|---|---|
| Program manager can create a program | §5, §8 |
| Add and reorder stages | §7 (add, reorder), §8 |
| Published stage versions immutable | §4.1, §7 invariant 1, feature test §10 |
| Conditional and parallel stages represented | §4.2, §7 (parallel_group + transitions) |
| Tenant isolation enforced | §2, §7 invariant 3, tenant-isolation tests §10 |
| API tests pass | §10 feature suite |
