# ADR 0001: Modular Monolith

## Status

Accepted

## Context

Catalesta is a configurable, multi-tenant platform spanning many bounded
domains (Identity, Organizations, Profiles, Programs, Cohorts, Stages, Forms,
Applications, Assessments, Workflows, and more — 24 required modules per
CLAUDE.md). The team is small and the modules share transactional data,
authorization, and tenant context. Two structural options were on the table at
project inception: a distributed microservice topology, or a single deployable
application with enforced internal boundaries.

A microservice split would impose network boundaries, distributed transactions,
and independent deploy pipelines before the domain model is stable — cost the
project cannot absorb pre-product-market-fit. At the same time, a conventional
"big ball of mud" Laravel app would let modules reach into each other's tables
and erode the domain boundaries the platform depends on (tenant isolation,
identity ownership, versioned scoring).

## Decision

Use a **Laravel modular monolith** with strict domain boundaries.

- One deployable application; modules live under `backend/app/Modules/<Module>/`.
- Module boundaries are enforced statically (deptrac) — a module may depend on
  another only through published `Contracts/`, never by reaching into its
  models, internal services, or tables.
- Cross-cutting concerns (Tenancy, Storage, Reliability) are siblings of
  `app/Modules/` (e.g. `app/Tenancy/`, `app/Reliability/`), not modules
  themselves, and modules consume them through interfaces.
- Integrations (Startup Gate, payments, notifications) sit behind interfaces so
  the monolith never hard-couples to an external system.

## Alternatives Considered

- **Microservices from day one.** Rejected. Network boundaries, distributed
  transactions, and per-service ops overhead before the domain model has
  stabilized; premature for a small team pre-PMF.
- **Unstructured Laravel monolith (no enforced boundaries).** Rejected. Without
  deptrac-enforced contracts, modules drift into direct cross-table access,
  destroying tenant-isolation and domain-ownership invariants that are
  non-negotiable in CLAUDE.md.

## Consequences

- **Positive:** Single deploy, single database, in-process transactions — the
  simplest substrate that still honours domain boundaries. Boundaries are
  machine-checked, so erosion is a CI failure rather than a silent regression.
- **Positive:** A module can later be extracted to a service by promoting its
  `Contracts/` to a network interface, because callers already depend only on
  contracts.
- **Negative (constraint):** Every cross-module call must route through a
  published contract; convenient direct access is forbidden and will fail
  deptrac. This is deliberate friction.
- **Constraint:** New or overlapping modules require an approved architecture
  decision (CLAUDE.md § Required Modules).

## References

- CLAUDE.md — § Architecture Ownership, § Required Modules
- `docs/project-context.md` — § Module boundaries (deptrac)
- `_bmad-output/planning-artifacts/architecture.md` — Step 6 (structure)
- `docs/repository-audit.md` — F-011 (this expansion closes that finding)
