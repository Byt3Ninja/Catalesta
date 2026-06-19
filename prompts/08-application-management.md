# Claude Task: Application Management

## Goal

Implement drafts, autosave, submissions, snapshots, eligibility, reviews, return-for-update, decisions, transfer, withdrawal, and bulk actions.

## Required Reading

- `CLAUDE.md`
- `docs/00-master-scope.md`
- `docs/architecture/overview.md`
- `docs/architecture/domain-boundaries.md`
- `docs/architecture/data-ownership.md`
- `docs/architecture/security-baseline.md`
- `docs/05-testing-strategy.md`

## Required Deliverables

- Domain model changes
- Database migrations
- Application services
- Policies and permissions
- API endpoints
- Events and jobs where required
- Frontend screens where required
- Unit tests
- Feature tests
- Authorization tests
- Tenant-isolation tests
- Contract or integration tests where required
- Documentation updates
- Migration and rollback notes
- Security impact assessment

## Constraints

- Follow module boundaries.
- Do not place business logic in controllers.
- Do not bypass tenant isolation.
- Do not duplicate Startup Gate-owned data unnecessarily.
- Keep integrations behind interfaces.
- Use idempotency for retryable commands.
- Use transactions for multi-record operations.
- Published versions are immutable.
- No arbitrary code execution in rules.
- Do not mark complete while critical tests fail.

## Execution Procedure

Before coding:
1. Summarize the implementation.
2. List files to create or modify.
3. List schema changes.
4. Identify assumptions.
5. Identify security risks.
6. Identify rollback concerns.

After coding:
1. Run all relevant tests.
2. Report passing and failing tests.
3. Report migration impact.
4. Report security impact.
5. Update documentation.
6. Provide a concise change summary.
