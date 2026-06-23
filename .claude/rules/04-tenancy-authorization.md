---
paths:
  - "app/**/*.php"
  - "Modules/Organizations/**/*.php"
  - "Modules/**/Policies/**/*.php"
  - "Modules/**/Authorization/**/*.php"
  - "modules/organizations/**/*.php"
  - "routes/**/*.php"
  - "database/**/*.php"
  - "tests/**/*.php"
---

# Tenancy and Authorization Rules

## Tenant Ownership

- Tenant-owned records require an enforceable organization boundary.
- Prefer direct `organization_id` for records independently queried,
  authorized, exported, audited, searched, queued, or reported.
- Indirect ownership through a parent is allowed only when enforced by schema,
  repository design, and authorization logic.
- Shared/global records must be explicitly identified as such.

## Tenant Resolution

- Resolve tenant context from trusted host, authenticated membership, or
  approved server-side context.
- Never accept request-provided tenant identity as authoritative.
- Reject unknown hosts.
- Never fall back to the first, default, or last-used tenant.
- Validate custom-domain status and ownership before tenant activation.

## Query and Mutation Safety

- Scope reads, writes, updates, deletes, exports, reports, searches, files,
  notifications, and jobs.
- Prevent cross-tenant model binding and indirect-object-reference attacks.
- Validate parent and child records belong to the same organization.
- Restore tenant context in queued jobs and reject execution when context is
  missing or invalid.
- Clear tenant context between long-lived worker jobs.

## Authorization

- Deny by default.
- Validate active membership, role, permission, resource ownership, and state.
- Perform authorization before loading or mutating sensitive resources when
  feasible.
- Frontend permission checks are presentation only.
- Do not rely on hidden fields, route names, or UI absence for protection.

## Required Tests

Every tenant-owned endpoint or service requires tests for:

- Authorized same-tenant access
- Unauthorized same-tenant access
- Cross-tenant read denial
- Cross-tenant mutation denial
- Invalid or inactive membership
- Missing tenant context
- Forged tenant or organization identifier
