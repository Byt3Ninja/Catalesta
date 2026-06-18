# Claude Task: Upgrades, Downgrades, and Add-ons

## Goal

Implement immediate upgrades, scheduled downgrades, proration abstraction, impact analysis, add-ons, coupons, and grandfathered plans.

## Required Reading

- `CLAUDE.md`
- `docs/30-saas-commercial-architecture.md`
- `docs/31-plans-entitlements-usage.md`
- `docs/32-subscriptions-billing-lifecycle.md`
- `docs/33-geidea-payment-integration.md`
- `docs/34-custom-domains-branding.md`
- `docs/35-saas-ux-billing-domains.md`
- `docs/36-saas-security-testing.md`
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
