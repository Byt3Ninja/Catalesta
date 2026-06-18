# Claude Task: Implement Programs, Cohorts, and Stage Engine

## Goal

Implement configurable programs, cohorts, stage definitions, versioning, ordering, entry rules, exit rules, and participant stage state.

## Inputs

- `docs/01-product-scope.md`
- `docs/03-domain-model.md`
- `docs/07-workflow-engine.md`

## Outputs

- Program module
- Cohort module
- Stage module
- Migrations
- Services
- Policies
- APIs
- Tests

## Acceptance Criteria

- Program manager can create a program
- Program manager can add and reorder stages
- Published stage versions are immutable
- Conditional and parallel stages are represented
- Tenant isolation is enforced
- API tests pass
