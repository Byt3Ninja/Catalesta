# ADR 0003: Mock OIDC Before Real Startup Gate Integration

## Status

Accepted

## Context

Startup Gate is an optional OIDC provider and consented profile-import source
for Catalesta (see [ADR-0004](0004-catalesta-identity-system-of-record.md)). At
project inception the real Startup Gate endpoint was neither stable nor always
available, yet identity, linking, and consented-import flows had to be built and
tested. Building those flows directly against a live external dependency would
couple local development and CI to a system outside the team's control —
unavailable endpoints would block unrelated work, and contract drift would
surface late.

CLAUDE.md also requires that local registration and authentication work fully
independently of Startup Gate, which means the integration must be a replaceable
edge, not a hard dependency baked through the codebase.

## Decision

Use a **local mock OIDC provider and a local mock profile-import source** during
implementation, both behind replaceable interfaces.

- The mock lives under `services/startup-gate-mock/` (sibling of `backend/` and
  `frontend/` per [ADR-0008](0008-repo-layout-backend-frontend-services-siblings.md)).
- All OIDC and profile-import access goes through interfaces in the Identity /
  Integrations modules; the mock and the real Startup Gate are interchangeable
  implementations selected by configuration.
- Tests exercise the interfaces, not a network endpoint, so identity flows are
  deterministic and runnable offline.

## Alternatives Considered

- **Integrate against the live Startup Gate from the start.** Rejected. Couples
  dev/CI to an external system's availability and release cadence; contract
  drift surfaces late; offline and deterministic testing become impossible.
- **No OIDC until the real provider is ready.** Rejected. Identity, linking, and
  import flows are foundational (Epic 4) and cannot wait on an external
  dependency's schedule.

## Consequences

- **Positive:** Identity and import flows are built, tested, and demoed without a
  live Startup Gate; CI is deterministic and offline-capable.
- **Positive:** The replaceable-interface seam directly satisfies the CLAUDE.md
  invariant that local auth works independently of Startup Gate, and lets the
  real provider drop in by swapping the bound implementation.
- **Negative (cost):** The mock must track the real provider's contract; a
  contract test against the real Startup Gate is required before production
  cut-over so the mock's assumptions are validated.
- **Constraint:** No code outside the Integrations / Identity interface boundary
  may call Startup Gate directly.

## References

- CLAUDE.md — § Identity Invariants, § Architecture Ownership (keep integrations
  behind interfaces)
- ADR-0004 — Catalesta as identity system of record (Startup Gate optional)
- ADR-0008 — repo layout (`services/startup-gate-mock/`)
- `docs/project-context.md` — § Identity & email
- `docs/repository-audit.md` — F-011 (this expansion closes that finding)
