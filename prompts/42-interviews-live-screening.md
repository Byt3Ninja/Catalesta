# Claude Task: Interviews and Live Screening

## Goal

Implement configurable interview rounds, interview panels, scheduling, question banks, scorecards, conflict checks, attendance, notes, consent, rescheduling, outcomes, and workflow integration.

## Required Reading

- `CLAUDE.md`
- `docs/00-master-scope.md`
- `docs/01-architecture.md`
- `docs/02-domain-boundaries.md`
- `docs/03-data-ownership.md`
- `docs/04-security-baseline.md`
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
