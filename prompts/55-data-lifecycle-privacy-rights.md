# Claude Task: Data Lifecycle and Privacy Rights

## Goal

Implement retention matrices, anonymization, deletion workflows, legal holds, access requests, correction, portability, consent withdrawal, processing restrictions, and objections.

## Required Reading

- `CLAUDE.md`
- `docs/00-master-scope.md`
- `docs/architecture/overview.md`
- `docs/architecture/domain-boundaries.md`
- `docs/architecture/data-ownership.md`
- `docs/architecture/security-baseline.md`
- `docs/05-testing-strategy.md`
- Relevant domain documents under `docs/`

## Required Deliverables

- domain model
- migrations
- services
- policies
- APIs
- frontend where applicable
- events and jobs
- unit tests
- feature tests
- authorization tests
- tenant-isolation tests
- contract tests where applicable
- documentation
- migration and rollback notes
- security impact assessment

## Constraints

- follow module boundaries
- enforce tenant isolation
- enforce server-side authorization
- use idempotency for retryable commands
- use transactions for multi-record operations
- keep integrations behind interfaces
- do not mark complete while critical tests fail
