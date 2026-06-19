# Resilience & Disaster Recovery

> Owner: Architecture · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

Resilience portion split from the former `28-resilience-support-guidance` doc.
Concrete DR targets (RPO/RTO), backup/restore procedure, and tenant offboarding
are added in build phase 5 (sale-readiness) — see the placeholders below, to be
ratified by the owner. Support-case behavior moved to
`../product/features/support-cases.md`.

## Startup Gate Outage

Support:

- existing-session continuation policy
- cached profile validity
- deferred synchronization
- deferred achievement publication
- retry queues
- reconciliation
- outage status
- manual recovery

## Disaster recovery targets

> Status: **Proposed — pending owner ratification** (Phase 5 sale-readiness).

- **RPO / RTO:** to be ratified. Proposed baseline: RPO ≤ 15 min, RTO ≤ 4 h.
- **Backup/restore:** automated daily full + continuous WAL/transaction-log
  backup; documented, tested restore runbook; restore drill cadence to ratify.
- **Region failover:** single-region at MVP; multi-region failover is deferred
  (see `../plan/roadmap.md` deferred backlog).

## Tenant offboarding (end-to-end)

> Status: **Proposed — pending owner ratification** (Phase 5 sale-readiness).

On tenant termination, in order:

1. Final data export made available within the export window.
2. Custom domain released; TLS certificate revoked.
3. Billing closed out (final invoice / proration), per `../saas/subscriptions-billing.md`.
4. Tenant data deleted/anonymized per retention policy in
   `../product/data-residency-retention.md`.
5. Startup-Gate de-linking: stop projecting `external_users`; revoke tokens.
6. Offboarding recorded in the audit trail.
