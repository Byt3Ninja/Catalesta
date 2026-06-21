# Standalone Identity & Profiles — PRD Reconciliation Design

- **Date:** 2026-06-21
- **Status:** Approved (design); pending implementation plan
- **Scope:** A direction change to the PRD/epics/architecture/CLAUDE.md that inverts identity ownership. This is the **reconciliation spec** — it does not implement anything. It defines the new direction and the SSOT edits, then hands off the first sub-project (SP-1) to its own brainstorm → plan → implementation cycle.

## 1. Why this exists

The shipped Story 1.0–1.5 system treats **Startup Gate as the system of record for identity**: the only way to sign up is the first OIDC login, the auth provider model is `ExternalUser` keyed on the SG `sub`, and the PRD/architecture/CLAUDE.md all assert "Startup Gate owns global identity, general profiles, role profiles, memberships, consent, …".

The product direction has changed. Catalesta must be a **fully standalone platform**: native registration, authentication, account management, and locally-owned multi-role profiles, fully operational with **zero** Startup Gate dependency. Startup Gate becomes **one optional linked identity provider** (SSO) and a **consented, field-level profile-import source** — never authoritative.

This spec reconciles the planning SSOT to that direction so all downstream work builds on the new model instead of the old one.

## 2. New direction principle (the one-liner that replaces the old assertion)

> Catalesta is the system of record for accounts, identity, profiles, memberships, and consent. Startup Gate is an optional external identity provider (SSO) and a consented profile-import source — never the system of record. The platform is fully operational without Startup Gate.

## 3. Identity data model (the hinge)

Everything downstream depends on this shape. Detailed schema is SP-1's job; this fixes the shape and the migration intent.

- **`accounts`** — the new primary user key.
  - `id` ULID (PK) — replaces `sub` as the primary user identifier.
  - `email` (unique, citext) — a **local login credential**, not a cross-system identifier.
  - `password_hash` **nullable** — a pure-SSO account can exist with no password.
  - `email_verified_at`, `status`, timestamps.
- **`linked_identities`** — an account links to N external providers.
  - `id` ULID, `account_id` FK.
  - `provider` (enum; `startup_gate` today).
  - `subject_id` — the immutable SG `sub`. **`sub` now lives here, on the link — not on the user.** Unique per `(provider, subject_id)`.
  - `linked_at`, `last_login_at`, encrypted token material (replaces the `ExternalUserToken` association on `ExternalUser`).
- **Profiles** — local, system-of-record.
  - base `profiles(account_id)` — the general profile.
  - `role_profiles(account_id, role_type)` — one per role the account holds (see §5), each with structured data, a completion state, and **per-field source tracking** (which fields are SG-imported vs locally entered/edited).
- **Import** — `profile_imports` (history) + field-level consent records; the "never auto-overwrite local edits" guarantee is enforced via the per-field source tracking + a conflict-preview step before any write.

### 3.1 Migration intent (the riskiest mechanical piece — SP-1 owns it)

- Each existing `external_users` row → one `accounts` row + one `linked_identities` row `(provider=startup_gate, subject_id=<old startup_gate_subject_id>)`.
- `email` carried from the projected claims where present; `password_hash` null (these are SSO-origin accounts until the user sets a password).
- `organization_memberships.external_user_id` repoints to `account_id` (rename or redefine the column's meaning — SP-1 decides; behavior: a membership belongs to an Account).
- `TestCase::makeExternalUser` and the `actingAs('web')` seam migrate to an account factory.

## 4. SSOT change ledger (edited in lockstep when we implement)

| File / item | Current | Revised |
|---|---|---|
| `prd.md` §1 Overview | "identity is delegated to Startup Gate" | Catalesta owns identity; SG optional |
| `prd.md` §9 Data Ownership | "Startup Gate owns identity (sub), general+role profiles, memberships, consent, verification, directories, achievements" | Catalesta owns all of these as system of record; SG = optional IdP + consented import source |
| `prd.md` FR-001 | auth via SG OIDC | native auth (register/login/session); SG optional |
| `prd.md` FR-007 (reserved) | unused | native registration + email verification + password reset + session |
| `prd.md` FR-008 (reserved) | unused | account linking + SG OIDC as an optional linked SSO provider + unlink |
| `prd.md` FR-009 (reserved) | unused | field-level consented import from SG + source tracking + import history + conflict preview + consent revocation |
| `prd.md` FR-006 / NFR-006 | consent-aware reads via ConsentProvider | extend: consent applies to locally-owned profiles; import consent is field-level |
| `prd.md` NFR-002 | `sub` only cross-system key | Account id (ULID) is the primary user identifier; `sub` is the SG-link key only |
| `prd.md` FR-157 (P4) | "Real Startup Gate cutover (mock→production) + federated SSO" | demote to "optional federated SSO + consented import" (no cutover; SG never becomes authoritative) |
| `epics.md` | Epic 1 done; identity = SG | new foundational epic (§5/§6); Epic 1 impact ledger; Epic 3 sequenced after |
| `architecture.md` lines 30/44/46/57 | SG delegation | Catalesta system-of-record; SG optional IdP/import |
| `CLAUDE.md` rules 2/4/5/11 | see §6 | see §6 |
| `architecture-decisions` memory | "Startup Gate owns identity" decision | reversed — record the new direction |

## 5. Role taxonomy (decision: adopt all 7 as-is)

Seven canonical **role-profile types**, each backed by a `role_profiles` row:

`Founder`, `Startup`, `Mentor`, `Service Provider`, `Investor`, `Trainer`, `Judge`.

- Map legacy names: **Evaluator → Judge**, **Funder → Investor**.
- `Operator/Admin` and `Platform Admin` remain **RBAC roles, not profiles** — they are authorization grants within an organization (and platform), not self-describing identity profiles.

## 6. CLAUDE.md rule rewrites (proposed final text)

- **Rule 2** → "Catalesta owns global identity, accounts, general and role profiles, memberships, and consent as system of record. Startup Gate is an optional external identity provider (SSO) and a consented profile-import source — never the system of record."
- **Rule 4** → "The primary user identifier is the local Account id (ULID). A Startup Gate `sub`, when an account is linked, is the immutable identifier of that linked external identity — unique, never reassigned, never the primary key."
- **Rule 5** (survives, reframed) → "Email is a local login credential only. Never use email as a cross-system, cross-tenant, or external-linkage identifier; use the Account id locally and `sub` for Startup Gate linkage."
- **Rule 11** → "All profile access must be consent-aware, including locally-owned profiles. Importing any field from Startup Gate requires explicit, field-level consent; imported data is a local editable copy and must never auto-overwrite locally modified fields."

## 7. Sequencing (decision: foundational, now)

A new foundational epic is inserted **before** further program work:

**Epic 4 · "Standalone Identity, Accounts & Profiles"** — runs after Epic 2's in-review stories close, **before** Epic 3 begins. Epic 3 is sequenced behind it because program work increasingly assumes the account/profile model.

Epic 2's in-flight stories finish on the current OIDC-mock path; they migrate to the account model once SP-1 lands (the migration in §3.1 keeps them working).

## 8. Sub-projects (dependency order)

Each sub-project gets its own brainstorm → spec → plan → implementation cycle. This spec only fixes their boundaries.

- **SP-1 — Native accounts & auth (foundation).** Local `accounts` with N `linked_identities`; native registration, password, email verification, password reset, session (Sanctum SPA cookie-session, unchanged transport). Migrate existing `ExternalUser` rows (§3.1). **No SG dependency.**
- **SP-2 — SG OIDC as an optional linked provider.** Link/unlink an SG identity to an existing account; "sign in with Startup Gate" resolves to (or links) a local account. Unlink semantics.
- **SP-3 — Multi-role local profiles.** The 7 role types as system-of-record (epic-sized). Base + role profiles, completion state, consent-aware reads over local data.
- **SP-4 — SG import pipeline.** Field-level consent, source tracking, import history, conflict preview, never-auto-overwrite-local-edits, re-import, consent revocation, unlink-import interplay.

**Next action after this spec:** brainstorm **SP-1**.

## 9. Out of scope (YAGNI)

- No second external IdP beyond Startup Gate (the `provider` enum leaves room; we don't build a generic federation framework now).
- No change to the Sanctum SPA cookie-session transport — native login reuses it.
- No SG "cutover": SG never becomes authoritative, so there is no mock→production authority migration to design.
- No profile schema beyond what SP-3 needs; this spec fixes the table shape, not the fields.

## 10. Testing & docs obligations (carried into each SP)

Every SP carries the project's standing gates: unit + feature + authorization + tenant-isolation tests, plus the specific new surfaces — auth/session tests, account-linking tests, import-consent and never-overwrite tests, and the migration's data-integrity test. Docs (PRD/epics/architecture/CLAUDE.md) update in lockstep per §4.
