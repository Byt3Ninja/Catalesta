# Achievements — Trusted Publication

> Owner: Product · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

Build-spec `02` (Identity/Profiles boundary). Defines the previously-undefined
"trusted publication" mechanism.

## Why this exists

Achievements are the **only** data that flows *from* a tenant Program Platform
*to* the global Startup Gate (per `../../architecture/data-ownership.md`). Because
Startup Gate is shared across all tenants, an achievement must be **trustworthy**
before it is published — a tenant cannot simply assert arbitrary claims into a
user's global profile.

## What "trusted publication" means

An achievement is publishable to Startup Gate only when **all** hold:

1. **Earned in-platform:** it references a real, completed program artifact
   (e.g. a graduation record or final-evaluation outcome), not free text.
2. **Attested by an authorized tenant role:** a user holding a program-admin /
   graduation permission attests it (recorded in the audit trail).
3. **Snapshot-backed:** the achievement carries an immutable snapshot of the
   evidence (program, cohort, outcome, date) at attestation time.
4. **Consent-gated:** the participant has consented to publishing this achievement
   to their global profile (consent-aware per CLAUDE rule 11).

## Publication mechanism

- Publication is an **outbound event** to Startup Gate via the integration
  interface (no direct DB write), carrying the snapshot + attestation + signature.
- Delivery is **idempotent** (keyed on achievement id) and verified by Startup
  Gate before it is accepted — a replay or duplicate does not double-publish.
- Revocation: if an achievement is later invalidated, a revocation event is sent;
  Startup Gate removes/marks it.
