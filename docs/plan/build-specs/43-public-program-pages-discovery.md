# Claude Task: Public Program Pages and Discovery

## Goal

Implement public program pages, slugs, timelines, eligibility summaries, benefits, partners, FAQs, deadlines, SEO, social metadata, related programs, public preview, publication approval, and registration of interest.

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
