# ADR 0005: Single-Database Topology with Row-Level Tenancy

## Status

Accepted (2026-06-23).

## Context

Catalesta is a multi-tenant SaaS platform with 24 canonical modules, of which 20 are scaffolded in `backend/app/Modules/`. Tenant-owned aggregates use the `BelongsToTenant` trait for fail-closed isolation. The platform needed an explicit database topology decision before further work on Reporting, Billing, TenantDomains, Branding, or the Reliability/Audit epic carve-out (PRD §7).

Three candidate topologies were considered:

1. **Database per tenant** — full isolation; one Postgres or MySQL instance per organization.
2. **Schema per tenant** — single instance, one Postgres schema per organization.
3. **Single-DB row-level** — one logical database, multi-tenancy enforced via an `organization_id` column on every tenant-owned table + `BelongsToTenant` global scope.

The codebase already implements option 3 in production. Operationally the platform targets MENA accelerator tenants where infrastructure cost predictability is a buying signal; small pilot tenants would not justify per-tenant infrastructure. Cross-tenant analytics (Reporting module exports to a future warehouse) require a single source.

## Decision

Catalesta runs on **one logical product database** (Postgres or MySQL). Multi-tenancy is row-level via an `organization_id` column, with `BelongsToTenant` (or equivalent fail-closed global scope) on every tenant-owned aggregate.

- **Per-tenant database is forbidden.** No tenant gets its own DB or schema. Any proposal to shard, partition by tenant, or move a single tenant to its own DB requires a superseding ADR.
- **Read replicas** are allowed via Laravel's `read` / `write` connection split. Strongly-consistent reads — post-write reads, authorization checks, idempotency lookups, OIDC callback verification — target the writer. Non-consistency-sensitive reads may use replicas.
- **Out-of-band analytics warehouse / data lake** is allowed for Reporting exports, but **never as a product-code read path**. Controllers, services, jobs, and Policies never read from an analytics store.
- **Non-product stores** — Redis (cache / queue / session), S3 (via Flysystem), and any future blob or object store — are not constrained by this ADR.
- **Architecture-test acceptance** (NFR-015): (1) all Eloquent connections resolve to a single configured product DB outside reporting/admin scopes; (2) any controller, service, job, or Policy importing an analytics-store client class fails the test.

## Alternatives Considered

- **Database per tenant.** Rejected. Operational explosion (one Postgres instance per organization); migration complexity (24 modules × N tenants); backup / restore overhead; no cross-tenant analytics path; infrastructure cost misfit for MENA pilot price points.
- **Schema per tenant.** Rejected. Similar explosion at smaller scale; ORM rewrites for cross-tenant queries; analytics path still blocked; Laravel / Eloquent does not idiomatically support schema-switching per request.
- **Sharding (horizontal partitioning by tenant id).** Deferred — out of scope until measurable scale pressure exists. Revisit only with data.

## Consequences

- **Positive:** Predictable operational cost; single backup pipeline; single migration pipeline; cross-tenant analytics tractable; reuses the existing `BelongsToTenant` substrate without rewrite.
- **Positive:** Reporting + Search + future warehouse pipelines all read from the same source of truth; no replication topology to maintain at the product layer.
- **Negative:** Single failure domain at the database — mitigated by read replicas, DR (NFR-010: RPO ≤ 15 min, RTO ≤ 4 h, ratified at OQ8), and tested restore runbook.
- **Negative:** Noisy-neighbor risk between tenants — mitigated by per-tenant entitlement counters (FR-061), rate limits (NFR-009), and instrumented per-tenant query observability.
- **Constraint:** New tenant-owned models MUST carry `organization_id` server-set (FR-003) and use `BelongsToTenant`. Bypasses require `::withoutTenantScope()` + a `// SECURITY:` comment per `docs/project-context.md` § Tenant isolation. Cross-tenant access returns 404, not 403 (auto-memory `architecture-decisions.md` § 5; FR-004; `Phase2TenantIsolationTest`).

## References

- PRD: `_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md` — §9.1, NFR-015, NFR-001, FR-003, FR-004
- `docs/project-context.md` — § Database Topology
- `docs/repository-audit.md` — F-005 (this ADR closes the missing-ADR gap on architecture decision #5)
- Auto-memory `architecture-decisions.md` § 5 (cross-tenant 404 not 403, related tenancy decision)
- Existing implementation: `backend/app/Modules/*` `BelongsToTenant` trait + `Phase2TenantIsolationTest`
