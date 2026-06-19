# Admin Impersonation & Audit

> Owner: Architecture · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

> Status: **Proposed — pending owner ratification** (Phase 5 sale-readiness).

Closes a production-SaaS gap the scope review flagged: staff "log in as a tenant
user" for support, with a non-repudiable audit trail.

## Authorization

- Only platform-staff roles with an explicit `impersonate` permission may start a
  session — never tenant-level admins.
- Impersonation is **scoped** to one target user in one tenant, time-boxed
  (proposed: 30 min, re-auth to extend).

## Guardrails

- A persistent **banner** shows "Acting as <user> — impersonation" for the whole
  session; the operator can end it at any time.
- **Consent / policy:** impersonation is gated by the tenant's support agreement;
  configurable per tenant (opt-in vs. break-glass).
- **Write limits:** destructive/billing actions are blocked or require a second
  confirmation while impersonating (proposed).

## Audit trail

- Every action performed while impersonating is written to the audit log tagged
  with **both** the real operator `sub` and the impersonated user `sub`, plus the
  session id and reason.
- Start and end of each impersonation session are themselves audited.
- Impersonation audit records are retained per `../product/data-residency-retention.md`.
