# Claude Task: SaaS Administration

## Goal

Implement plan management, tenant subscriptions, manual entitlements, trials, grace periods, suspension, contract billing, usage adjustments, and billing support tools.

## Required Reading

- `CLAUDE.md`
- `docs/saas/commercial-architecture.md`
- `docs/saas/plans-entitlements-usage.md`
- `docs/saas/subscriptions-billing.md`
- `docs/saas/geidea-payments.md`
- `docs/saas/domains-branding.md`
- `docs/ux/saas-billing-ux.md`
- `docs/saas/security-testing.md`
- relevant existing architecture, security, testing, and integration documents

## Required Deliverables

- domain model
- migrations
- provider interfaces and adapters
- services
- policies
- versioned APIs
- frontend where applicable
- events, outbox integration, and jobs
- unit tests
- feature tests
- tenant-isolation tests
- authorization tests
- provider contract tests
- end-to-end tests
- documentation
- migration and rollback notes
- security impact assessment

## Constraints

- never branch domain behavior on plan names
- use centralized entitlements
- never store raw card details
- do not trust browser payment returns as authoritative
- verify provider signatures
- enforce webhook idempotency
- enforce domain ownership verification
- reject unknown host headers
- prohibit arbitrary tenant CSS and JavaScript
- preserve read access and customer data when limits are reached
- do not mark complete while critical tests fail
