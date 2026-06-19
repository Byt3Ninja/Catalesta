# Security Baseline

> Owner: Architecture · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

OIDC Authorization Code Flow with PKCE, state and nonce validation, issuer and audience validation, token revocation, tenant isolation, server-side authorization, signed URLs, malware scanning, rate limiting, CSP, secure headers, audit logging, signed webhooks, idempotency, backup and recovery, dependency and container scanning.

## Tenant Isolation (Fail-Closed)

Tenant isolation is enforced fail-closed at the data-access layer via `App\Shared\Tenancy\BelongsToTenant` trait:

- **Every tenant-owned model** (with `organization_id` column) must use `BelongsToTenant`. An architecture test (`tests/Architecture/TenantIsolationArchTest.php`) enforces this on every commit.
- **Read queries without a resolved tenant** return no rows (not an error). Queries in queue jobs, console commands, or system contexts run against all tenants only inside `TenantContext::runAsSystem(callable)`.
- **Writes without a resolved tenant** throw `TenantContextMissingException`, making cross-tenant access explicit and auditable.
- **`organization_id` is never mass-assignable** (`$fillable` excludes it); the column is assigned server-side from `app(\App\Shared\Tenancy\TenantContext::class)->organizationId()` at create time.

This model is documented in `tenancy-isolation.md`.

## Secrets management & rotation

> Status: **Proposed — pending owner ratification** (Phase 5 sale-readiness).

- Secrets (DB, S3/MinIO, OIDC client secret, Geidea keys, signing keys) are never
  committed (CLAUDE.md rule 15); they live in the environment / a secrets manager.
- **Rotation:** documented rotation cadence per secret class (proposed: signing
  keys ≤ 90 d, provider API keys ≤ 180 d or on incident); rotation is zero-downtime
  via overlapping validity where supported (e.g. JWKS key rollover).
- **No raw card data / CVV** is ever stored (CLAUDE.md SaaS rule 8); Geidea holds
  card data behind its HPP.
- Access to secrets is least-privilege and audited.
