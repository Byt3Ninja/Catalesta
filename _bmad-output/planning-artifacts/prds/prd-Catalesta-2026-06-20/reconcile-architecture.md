---
artifact: prd-reconcile-input
input_name: architecture-docs
input_files:
  - docs/architecture/overview.md
  - docs/architecture/domain-boundaries.md
  - docs/architecture/tenancy-isolation.md
  - docs/architecture/data-ownership.md
prd: _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
prd_sections_compared:
  - "6.13 Six-Bucket Scope Classification"
  - "8 Cross-cutting NFRs"
  - "9 Data Ownership & Domain Boundaries"
  - "9.1 Database Topology (pending ADR-0005)"
generated: 2026-06-23
gap_count: 5
conflict_count: 3
status: surface-only
---

# Reconciliation — PRD vs. Architecture Docs

## Scope

Compare PRD §9 / §9.1 / §6.13 / §8 NFR-001 against the four canonical
architecture sources. Surface divergences only; no edits applied.

## Gaps & [CONFLICT] findings

### G-1 [CONFLICT] — Data ownership inversion not yet reflected

`docs/architecture/data-ownership.md` (last-updated 2026-06-19) and
`docs/architecture/overview.md` (same date) still encode the **pre-inversion**
authority model:

- `data-ownership.md` table: *User identity*, *General profile*, *Role profiles*,
  *Startup memberships*, *Consent* → **owner: Startup Gate**.
- `overview.md` § Platform Boundary: "Startup Gate owns identity and reusable
  profile data."

PRD §9 (and §6.13 FR-001/FR-006/FR-007/FR-009; auto-memory §6; CLAUDE.md
"Identity Invariants") asserts the **opposite**: Catalesta is the system of
record for accounts, identity, general + role profiles, memberships, consent;
Startup Gate is an *optional* SSO + consented-import source. `repository-audit.md`
F-001 also pre-flagged this.

Both architecture docs need rewriting to match the inverted ownership model
once ADR-0004 lands.

### G-2 [CONFLICT] — `overview.md` does not encode the §9.1 single-DB topology

PRD §9.1 (added 2026-06-23, pending ADR-0005) commits to: one logical
PostgreSQL/MySQL, row-level tenancy via `organization_id`, no DB-per-tenant /
no schema-per-tenant, no product-code read path on an analytics warehouse.

`docs/architecture/overview.md` § Stack lists "PostgreSQL" but is silent on:

- single-DB vs per-tenant DB,
- read/write connection split (writer for strong-consistency reads),
- analytics-warehouse-not-a-product-read-path rule.

`overview.md` § Platform Boundary states "No direct database sharing" — this
phrase is ambiguous and (read literally against the new §9.1) could be
mis-interpreted as forbidding the single-DB-with-row-level-tenancy decision
between Catalesta modules. It needs disambiguation: the no-sharing rule is
about Startup Gate vs Catalesta, not about Catalesta's internal modules.

No architecture doc currently *contradicts* per-tenant DB outright, leaving
§9.1 unbacked at the architecture layer until ADR-0005 ships.

### G-3 [CONFLICT] — 404-not-403 cross-tenant decision absent from `tenancy-isolation.md`

PRD §8 NFR-001 and §6.13 FR-004 commit to: "cross-tenant access returns **404**"
(auto-memory §5: "cross-tenant org access → 404 not 403"; landed in S1.1 per
the §6.13 source-signal column).

`docs/architecture/tenancy-isolation.md` describes fail-closed behaviour
exhaustively (empty result set on unresolved tenant, exception on writes) but
**never specifies the HTTP status for a cross-tenant *resolved* request**. A
reader implementing a new policy or controller has no architectural guidance
to choose 404 over 403, even though the decision is canonical in the PRD.

Add an explicit "Cross-tenant access surfaces as 404, not 403" rule to
`tenancy-isolation.md` (likely under § Key Rules or a new § HTTP Surface
section).

### G-4 — Reliability/Audit epic substrates not anchored in architecture

PRD §6.13 reclassifies FR-126 (audit enforced platform-wide) to a new
**Reliability/Audit epic** before P2, bundling outbox + idempotency +
audit-enforced + signed-webhooks (per `repository-audit.md` F-009 + F-010).

`overview.md` § Stack lists "Transactional outbox" and "Signed webhooks" as
stack items, but no architecture doc:

- assigns ownership of the audit-enforced + outbox + idempotency + signed-webhooks
  *as one coherent substrate epic*,
- describes phasing (P1a primitive depth → Reliability/Audit-epic generalization
  → P2 multi-consumer) — only the PRD § 7 "slice depth" paragraph captures this,
- documents the idempotency primitive at all (it is named in PRD FR-051 and the
  §6.13 source-signal column but absent from architecture).

No architecture doc commits to an *incompatible* phasing, so this is a gap
(missing anchor), not a conflict. A dedicated `docs/architecture/reliability-
substrate.md` (or extension of `overview.md`) would close it.

### G-5 — Module catalogue divergence (24 vs. 23)

`domain-boundaries.md` enumerates 23 modules (Identity, Profiles, Organizations,
Startups, Programs, Cohorts, Stages, Forms, Applications, Documents, Assessments,
Workflows, Role Assignments, Tasks, Mentorship, Training, Final Evaluation,
Graduation, Notifications, Integrations, Reporting, Search, Administration,
Audit). It is missing **Billing, Entitlements, TenantDomains, Branding** that
CLAUDE.md § Required Modules names, and missing the **identity ownership** module
realignment from G-1.

Auto-memory §2 records "24 modules" as a confirmed resolution; CLAUDE.md lists
~28 module names. `domain-boundaries.md` needs an explicit reconciliation pass
once the canonical count is fixed (likely under ADR-0004 / Epic 0 hygiene).

## Summary

- **Input name:** architecture docs (overview, domain-boundaries,
  tenancy-isolation, data-ownership)
- **Gap count:** 5 (G-1…G-5)
- **[CONFLICT] count:** 3 (G-1 ownership inversion, G-2 single-DB topology,
  G-3 404-not-403 status)
- **Non-conflict gaps:** 2 (G-4 Reliability/Audit epic anchoring, G-5 module
  catalogue divergence)
- **Drivers:** all four architecture docs carry `Last-updated: 2026-06-19`
  and predate the 2026-06-23 PRD updates (§9.1 topology, §6.13 six-bucket
  overlay, FR-126 Reliability/Audit reclassification) and the ADR-0004
  identity-ownership inversion. Reconciliation is unblocked by ADR-0004 +
  ADR-0005 landing and a coordinated architecture-doc refresh.
- **No PRD changes warranted by this surface** — gaps point to architecture
  docs needing updates, not PRD revisions. Confirmed by `repository-audit.md`
  F-001 (identity ownership), F-005 (cross-tenant 404), F-009/F-010
  (Reliability/Audit substrate scope).
