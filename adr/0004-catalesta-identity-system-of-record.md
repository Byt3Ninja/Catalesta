# ADR 0004: Catalesta as Identity and Profile System of Record

## Status

Accepted — supersedes [ADR-0002](0002-startup-gate-system-of-record.md).

## Context

ADR-0002 (2026-06-18) declared Startup Gate the owner of global identity, profiles, startup memberships, consent, and shared achievements. Catalesta held only projections and immutable snapshots. Subsequent product and operational analysis surfaced four blockers that made the original stance untenable:

1. **Operational coupling.** A Startup Gate outage blocks Catalesta authentication and most authorized actions. The platform is required to function as an independent tenanted product.
2. **Identifier coupling.** Treating the SG `sub` as a primary user key blocks idempotency (a non-SG user has no stable key), constrains the seven local role-profile types (Founder, Startup, Mentor, Service Provider, Investor, Trainer, Judge), and forces every internal join through an external system.
3. **Consent semantics.** Catalesta tenants (MENA accelerators) need local control over field-level consent rules and import policy that Startup Gate's global consent model cannot encode.
4. **Profile editability.** Imported values are local editable copies in practice; treating SG as authoritative requires "auto-overwrite" semantics that violate the established invariant in CLAUDE.md: *"imported profile values are local editable copies and must never automatically overwrite locally modified fields."*

The 2026-06-21 decision (auto-memory `architecture-decisions.md` § 6) inverted ownership. Implementation began with Epic 4 / SP-1 (native registration and authentication, landed before 2026-06-23). This ADR formalizes the decision and supersedes ADR-0002.

## Decision

Catalesta is the system of record for accounts, identity, general profiles, role profiles (seven types), memberships, consent records, and verification state.

- Primary user identifier = local **Account ULID** (`char(26)`).
- External identities are keyed by `(issuer, sub)` on a `linked_identities` row, never on the account row, never as a local primary key.
- Email is a local login credential and verified contact attribute only — **never** a cross-system, cross-tenant, ownership, or linkage key.
- Startup Gate is an **optional** linked SSO provider and a consented field-level profile-import source. Startup Gate is never authoritative.
- Local authentication, registration, password reset, email verification, and session management must work independently of Startup Gate.
- Achievement publication flows tenant → Startup Gate only via trusted publication (attested, snapshot-backed, consent-gated, idempotent).
- Linking and unlinking external identities require authenticated confirmation and audit records.

## Alternatives Considered

- **Maintain ADR-0002 (Startup Gate as system of record).** Rejected. Operational fragility (SG outage blocks Catalesta auth); identifier coupling blocks idempotency and multi-tenant role profiles; MENA consent semantics not expressible.
- **Hybrid — SG owns identity, Catalesta owns profile.** Rejected. Same auth fragility as above; sync direction ambiguous; role-profile coupling remains.
- **Bidirectional sync.** Rejected. Schema mismatch + conflict semantics + consent rules collide; cost of correctness exceeds the cost of full local ownership.

## Consequences

- **Positive:** Catalesta operates fully without Startup Gate; the local identity model carries the seven role-profile types cleanly; idempotency keys are stable; consent semantics are tenant-local; trusted publication preserves the Startup Gate integration story for tenants who opt in.
- **Positive:** Aligns CLAUDE.md identity invariants (Account ULID primary, email never cross-system, external `sub` never a local PK) with implementation reality (Epic 4 / SP-1..SP-4).
- **Negative (cost):** Building local auth + verification + recovery + linking flows (Epic 4 / SP-1..SP-4). Partially complete — SP-1 landed; SP-2 / SP-3 / SP-4 in flight or next-up.
- **Negative (surface area):** Trusted-publication path + link / unlink + consented-import flows are new surfaces requiring security review.
- **Constraint:** Every external-identity reference in code must key on `(issuer, sub)` from `linked_identities`. Email lookups outside `App\Modules\Identity\` are forbidden (CI grep gate per `docs/project-context.md` § Identity & email).

## References

- PRD: `_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md` — §6.1, §9, FR-001, FR-007, FR-008, FR-009, FR-157, NFR-002
- `docs/project-context.md` — § Identity & email
- Auto-memory `architecture-decisions.md` § 6 (2026-06-21 inversion)
- CLAUDE.md — § Identity Invariants
- `docs/repository-audit.md` — F-001 (this ADR closes that finding)
- Superseded ADR: `adr/0002-startup-gate-system-of-record.md`
