# Claude Task: Surveys, Feedback, and NPS

## Goal

Implement general surveys, session feedback, mentor and trainer feedback, blind mutual feedback, NPS, exit surveys, alumni surveys, anonymous responses, scheduling, and analytics.

> **Schema decision — deferred to implementation (Epic 0 / Story 0.4).** The
> **mutual-feedback table count** (one polymorphic feedback table vs two
> directional tables, e.g. mentor→startup and startup→mentor) is intentionally not
> pinned — the old-pack contradiction is moot (not specified in current docs) and
> this feature is post-MVP. Recommended at build time: **one polymorphic
> blind-feedback table** keyed by `(subject_type, subject_id, author_account_id,
> direction)`, to avoid duplicated NPS/analytics logic across two tables. Log the
> choice in the PRD decision-log when implemented.

## Required Reading

- `CLAUDE.md`
- `docs/product/scope-register.md`
- `docs/architecture/overview.md`
- `docs/architecture/domain-boundaries.md`
- `docs/architecture/data-ownership.md`
- `docs/architecture/security-baseline.md`
- `docs/quality/testing-strategy.md`
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
