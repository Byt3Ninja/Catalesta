# ADR 0009: Cross-Tenant Access Returns Neutral 404, Not 403

## Status

Accepted

## Context

Catalesta is multi-tenant; every tenant-owned aggregate is isolated by an
organization boundary (CLAUDE.md § Tenant Invariants). When an authenticated
user requests a resource belonging to a tenant they are not a member of, the
response must not leak whether that resource exists. A `403 Forbidden`
distinguishes "exists but you can't see it" from "does not exist" — an existence
oracle an attacker can use to enumerate org IDs, program IDs, or application IDs
across tenant boundaries.

Most resources route through `BelongsToTenant`, which already fails closed with a
404. The lone divergence was the Organization show/update path: `Organization` is
the tenant **root** (not itself `BelongsToTenant`-scoped), so the `ResolveTenant`
middleware returned `403` before the controller ran, producing an inconsistent
contract (403 for orgs, 404 for everything else).

This resolution is recorded in auto-memory `architecture-decisions.md` § 5
(decided 2026-06-20, Story 1.1) and is the remaining item-5 piece of audit F-005
(items 1/2/3 are ADR-0006/0007/0008).

## Decision

Cross-tenant and non-existent resource access both return a **neutral 404**
("Not found or you don't have access") — never 403.

- `GET` / `PATCH` `/api/v1/organizations/{id}` for a non-member returns **404**,
  not 403, with no existence leak (FR-004, `docs/product/flows.md`).
- The organization show/update path resolves membership **itself** and 404s,
  rather than letting `ResolveTenant` 403 first (because `Organization` is the
  tenant root, not a `BelongsToTenant` resource).
- All `BelongsToTenant` resources already 404 by default; this ADR makes the org
  root consistent with them.

## Alternatives Considered

- **Return 403 for cross-tenant access.** Rejected. Leaks resource existence
  across tenant boundaries (enumeration oracle); inconsistent with every
  `BelongsToTenant` path which already 404s.
- **404 for unknown, 403 for known-but-forbidden.** Rejected. The "known"
  branch is exactly the existence leak; the distinction is the vulnerability.

## Consequences

- **Positive:** Uniform contract — no existence oracle, org root behaves like all
  other tenant resources. Denies cross-tenant existence inference by default.
- **Status:** Already implemented (Story 1.1). Flipped 6 existing assertions from
  403 → 404 (`OrganizationApiTest:142,268`,
  `TenantIsolationTest:53,75,174,213`); `Phase2TenantIsolationTest` already
  expected 404.
- **Constraint:** Any future tenant-root resource (a path not behind
  `BelongsToTenant`) must resolve membership itself and 404 — never rely on
  middleware 403, never return 403 for a cross-tenant miss.

## References

- Auto-memory `architecture-decisions.md` § 5 (2026-06-20, Story 1.1)
- `docs/product/flows.md` — cross-tenant / missing → neutral 404 (line 143)
- PRD FR-004
- CLAUDE.md — § Tenant Invariants ("Cross-tenant access is denied by default"),
  auto-memory recall (cross-tenant org access → 404 not 403)
- `docs/repository-audit.md` — F-005 item 5 (this ADR closes that remaining piece)
