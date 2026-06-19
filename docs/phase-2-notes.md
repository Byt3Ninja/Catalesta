# Phase 2 Engineering Notes

Phase 2 (M0–M4) delivered Programs, Cohorts, and Stages with a shared rule-evaluation kernel and versioning/immutability layer. This note covers the internals engineers need to extend or maintain those features.

See also: `docs/architecture/tenancy-isolation.md` (fail-closed tenant isolation), `docs/architecture/data-ownership.md` (Phase 2 tables), `docs/architecture/domain-boundaries.md` (module status).

---

## 1. Rule Expression Format

Stage transition conditions and entry/exit gates are stored as **JSON expression trees** on `stage_rules.expression` (a JSON column). The tree is evaluated by `App\Shared\Rules\ExpressionEvaluator` and validated before persistence by `App\Shared\Rules\ExpressionValidator`.

### Expression shape

A leaf node:

```json
{
  "field": "cohort.is_open",
  "operator": "equals",
  "value": true
}
```

A compound node (logical `and` / `or`):

```json
{
  "logical": "and",
  "conditions": [
    { "field": "participant.score", "operator": "greater_than_or_equal", "value": "70.00" },
    { "field": "cohort.is_open", "operator": "equals", "value": true }
  ]
}
```

### Operators

All valid operators are cases of `App\Shared\Rules\Operator` (string-backed enum):

| Case | Backing value |
|---|---|
| `EQUALS` | `equals` |
| `NOT_EQUALS` | `not_equals` |
| `GREATER_THAN` | `greater_than` |
| `LESS_THAN` | `less_than` |
| `GREATER_THAN_OR_EQUAL` | `greater_than_or_equal` |
| `LESS_THAN_OR_EQUAL` | `less_than_or_equal` |
| `IN` | `in` |
| `NOT_IN` | `not_in` |
| `IS_NULL` | `is_null` |
| `IS_NOT_NULL` | `is_not_null` |
| `CONTAINS` | `contains` |
| `CONTAINS_ANY` | `contains_any` |

Numeric comparisons (`greater_than`, `less_than`, `greater_than_or_equal`, `less_than_or_equal`, `equals` on numeric strings) are decimal-safe via `brick/math` `BigDecimal`. A non-numeric operand returns `false` rather than throwing (CLAUDE.md rule 9: decimal arithmetic; rule 10: no arbitrary code execution).

### No arbitrary code in rules

Field values are resolved **only** through registered `FieldResolver` implementations (see section 2). The expression tree may not embed PHP, SQL, shell, or JavaScript — `ExpressionValidator` rejects any expression referencing an unknown field before it reaches persistence.

---

## 2. Field Resolvers

### Interface

```php
namespace App\Shared\Rules;

interface FieldResolver
{
    public function supports(string $field): bool;
    public function resolve(string $field, array $context): mixed;
    /** @return array<int, string> */
    public function namespaces(): array;
}
```

Fields are namespaced strings (e.g. `cohort.is_open`, `participant.score`). `supports()` returns `true` when the resolver owns that field. `resolve()` reads the value from the `$context` array passed at evaluation time — it must not query the database directly. `namespaces()` declares the namespace prefixes the resolver handles (used by `FieldResolverRegistry::namespaces()`).

### Registry

`App\Shared\Rules\FieldResolverRegistry` holds all registered resolvers. Call `register(FieldResolver $resolver)` to add one. `resolve(string $field, array $context)` delegates to the first resolver that `supports()` the field; throws `UnknownFieldException` if none does.

### Existing resolvers (Phase 2)

All three live in `app/Modules/Stages/Infrastructure/Rules/`:

| Class | Namespace | Fields |
|---|---|---|
| `CohortFieldResolver` | `cohort` | `cohort.is_open` |
| `ContextFieldResolver` | `context` | (context metadata fields) |
| `ParticipantFieldResolver` | `participant` | (participant attribute fields) |

### How to add a field resolver

1. Create a class implementing `App\Shared\Rules\FieldResolver` in the relevant module's `Infrastructure/Rules/` directory.
2. Implement `supports()`, `resolve()`, and `namespaces()`. Read values from the `$context` array — do not run queries inside `resolve()`.
3. Register the resolver in the module's service provider (or in `StagesServiceProvider` for stage-related resolvers) by calling `$registry->register(new MyFieldResolver())`.
4. The new field is now available in expression trees and will pass `ExpressionValidator` validation.

---

## 3. Versioning and Immutability

### Per-stage versions

Each `ProgramStage` has one or more `StageVersion` rows. The active published version is pointed to by `program_stages.current_published_version_id`. New stages start with a single `draft` version; publishing advances the pointer.

### `StageVersion` status lifecycle

Managed by `App\Shared\Versioning\VersionStatus` (enum):

- `Draft` → editable
- `Published` → immutable (see below)
- `Archived` → the only transition permitted from Published

### Immutability (`ImmutableWhenPublished`)

`StageVersion` uses the `App\Shared\Versioning\ImmutableWhenPublished` trait. This registers Eloquent `updating` and `deleting` hooks that throw `VersionStateException` if the record's current `status` is `Published`, with one narrow exception: a status-only change from `Published` → `Archived` is permitted (CLAUDE.md rule 8: published forms/stages are immutable and versioned).

### Publishing a stage version

Use `App\Modules\Stages\Application\PublishStageVersion::handle(StageVersion $version)`. This:

1. Delegates to `App\Shared\Versioning\VersionPublisher::publish()`, which increments `version_number`, sets `status = Published`, and records `published_at` — all inside a DB transaction.
2. Updates `program_stages.current_published_version_id` to point to the newly published version.
3. Writes an audit record (`stage.published`).

Do not write directly to `stage_versions.status` to publish — always go through `PublishStageVersion` or `VersionPublisher::publish()`.

---

## 4. Clone and Template Behavior

### Clone (`CloneProgram`)

Route: `POST /programs/{id}/clone`
Service: `App\Modules\Programs\Application\CloneProgram::handle(Program $source, string $newName)`
Permission: `programs.manage` (same as create/update)

**What is copied:**

- Program metadata (name, description, settings); slug is uniqued within the tenant.
- `program_policies` rows.
- `program_role_requirements` rows.
- All `program_stages` (order preserved). Each stage gets a **fresh `draft` `StageVersion`** with config and rules copied from the source stage's published version (or latest draft if no published version exists). Stage transitions are remapped to new stage ids.

**What is NOT copied:**

- Cohorts.
- Participant state (`participant_stage_statuses`, `stage_instances`).
- `current_published_version_id` (the clone starts with no published version).

All `organization_id` columns are stamped by `BelongsToTenant` (never passed in create arrays). The clone is always `Draft` status, regardless of the source program's status.

### Templates (`SaveProgramAsTemplate` / `CreateProgramFromTemplate`)

Routes:
- `POST /program-templates` — calls `SaveProgramAsTemplate::handle(Program $program, string $name)` to serialize a program into a `ProgramTemplate`.
- `POST /program-templates/{id}/instantiate` — calls `CreateProgramFromTemplate` to materialize a new `Draft` program from the blueprint.

**Blueprint format** (stored in `program_templates.blueprint`, a JSON column):

```json
{
  "program": { "name": "...", "description": "...", "settings": {} },
  "stages": [
    {
      "key": "...", "name": "...", "type": "...",
      "order_index": 0, "parallel_group": null,
      "config": {}, "rules": [{ "type": "...", "expression": {} }]
    }
  ],
  "policies": [{ "key": "...", "value": "..." }],
  "role_requirements": [{ "role_key": "...", "min_count": 1, "max_count": null, "is_required": true }],
  "transitions": [{ "from_stage_key": "...", "to_stage_key": "...", "condition": {}, "order_index": 0 }]
}
```

Transitions reference **stage keys** (not ids), making the blueprint id-independent. Materialization resolves keys back to freshly-created stage ids.

Materialization behavior mirrors clone: new `Draft` program, fresh `Draft` `StageVersion` per stage, no cohorts or participant state, slug uniqued within tenant, `organization_id` stamped by `BelongsToTenant`.

Both operations require the `programs.manage` permission and resolve all ids tenant-scoped (see `docs/architecture/tenancy-isolation.md`).
