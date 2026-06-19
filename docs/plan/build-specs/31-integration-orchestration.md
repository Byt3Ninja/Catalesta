# Claude Task: Integration Orchestration

## Goal

Integrate all implemented modules into coherent end-to-end workflows.

## Required Reading

- `CLAUDE.md`
- `docs/architecture/integration-strategy.md`
- `docs/plan/dependency-graph.md`
- `docs/architecture/shared-contracts.md`
- `docs/quality/integration-testing.md`
- `docs/plan/release-gates.md`

## Deliverables

1. Shared contract package
2. Event catalog
3. Integration adapters
4. Transactional outbox wiring
5. Cross-module DTOs
6. Contract tests
7. Vertical-slice tests
8. Failure and retry tests
9. End-to-end smoke tests
10. Integration documentation

## Acceptance Criteria

- Login-to-dashboard flow passes.
- Program creation-to-publication flow passes.
- Application-to-admission flow passes.
- Admission-to-graduation flow passes.
- Audit, notifications, search, and reporting receive required events.
- No direct cross-module table access exists outside approved exceptions.
- All integration tests pass.
