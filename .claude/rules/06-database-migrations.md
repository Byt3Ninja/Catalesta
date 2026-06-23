---
paths:
  - "database/**/*.php"
  - "database/**/*.sql"
  - "Modules/**/Database/**/*.php"
  - "modules/**/database/**/*.php"
  - "app/Models/**/*.php"
  - "Modules/**/Models/**/*.php"
  - "tests/**/*Migration*.php"
---

# Database and Migration Rules

- Inspect existing schema, constraints, indexes, and representative data first.
- Never edit a migration already deployed to a shared environment.
- Make migrations deterministic and reversible where technically possible.
- Provide an explicit rollback and recovery strategy.
- Preserve existing data unless destructive change is explicitly approved.
- Use staged expand-migrate-contract changes for risky or high-volume tables.
- Avoid long blocking table operations without an operational plan.
- Add foreign keys, unique constraints, check constraints, and indexes for
  critical invariants where supported.
- Model issuer-plus-subject uniqueness at database level.
- Model tenant uniqueness with `organization_id` in the relevant composite key.
- Store monetary and scoring values as fixed-precision decimals.
- Use timezone-aware timestamps according to repository convention.
- Avoid nullable columns when absence is not a valid domain state.
- Do not encode evolving business state as database enums unless explicitly
  approved.
- Backfills must be resumable, observable, bounded, and safe to rerun.
- Test migration forward path, rollback path, and representative legacy data.
- Verify application compatibility during rolling or zero-downtime deployment.
