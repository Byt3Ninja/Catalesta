---
paths:
  - "Modules/Administration/**/*.php"
  - "Modules/Audit/**/*.php"
  - "modules/administration/**/*.php"
  - "modules/audit/**/*.php"
  - "app/**/*Admin*.php"
  - "app/**/*Audit*.php"
  - "tests/**/*Admin*.php"
  - "tests/**/*Audit*.php"
---

# Administration and Audit Rules

- Administrative access is not implicit superuser access across tenants.
- Distinguish platform administration from tenant administration.
- Require explicit permissions for impersonation, support access, exports,
  entitlement overrides, and security-sensitive changes.
- Record reason and ticket/reference for exceptional support operations where
  applicable.
- Impersonation must be visible, time-bounded, auditable, and prohibited for
  operations that policy excludes.
- Audit privileged actions, identity linking, consent, role changes, tenant
  changes, state transitions, exports, domain changes, billing changes, and
  security configuration.
- Audit records must include actor, effective actor, tenant, action, target,
  timestamp, outcome, and correlation identifier.
- Do not store secrets or excessive sensitive payloads in audit logs.
- Audit history must be append-only at the application layer.
- Restrict audit access and export.
- Define retention and integrity controls.
- Administrative bulk operations require preview, bounded scope, authorization,
  idempotency, and a recovery plan.
