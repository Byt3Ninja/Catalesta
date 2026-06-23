# Implementation Status (As-Built)

> Owner: Engineering · Last-updated: 2026-06-23 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

**This is the only place implementation status is tracked.** Scope and plan docs
state intent and order — they must not carry status. Module names and the 24-count
are canonical from `../product/scope-register.md`; this file records only what is
*built*.

As of 2026-06-23: **Phase 1a (Epic 1 + Epic 2 + SP-1) is delivered on `main`**
(384 backend tests passing; sprint-status confirms all stories `done`). Identity
now reflects the ADR-0004 inversion (Catalesta system of record: `accounts` +
`linked_identities`, native auth) — the earlier "OIDC projection" model is gone.

## Module status

| Module | Status | Frontend | Notes |
|---|---|---|---|
| Identity | Implemented | Yes — login, register, verify-email, forgot/reset password, onboarding, auth callback | `app/Modules/Identity` (28 files). Post-SP-1: `accounts` + `linked_identities` + native auth (migrations `..._create_accounts_table`, `..._create_linked_identities_table`, `..._add_native_auth_to_accounts`). System of record per ADR-0004; Startup Gate optional. |
| Organizations | Implemented | Yes — api/schema | `app/Modules/Organizations` — tenancy root, RBAC; cross-tenant show/update returns 404 (see ADR-0009) |
| Programs | Implemented | Yes — ProgramsPage + api | `app/Modules/Programs` — CRUD, policies, clone, templates |
| Stages | Implemented | — | `app/Modules/Stages` — versioned stage engine, rules |
| Cohorts | Implemented | Yes — api/schema | `app/Modules/Cohorts` (11 files) — enrollment windows; `form_version_id` binding |
| Forms | Implemented | Partial — ApplyField / FormLayout render | `app/Modules/Forms` (7 files) — versioned form schema (used by Epic 2 apply flow) |
| Applications | Implemented | Yes — ApplyPage, SubmissionsPage, SubmissionDetailPage + apply/submissions api | `app/Modules/Applications` (13 files) — Epic 2: immutable snapshot, idempotent submit, funnel/list |
| Documents | Partial | — | `app/Modules/Documents` — content-addressed blob store backs Epic 2 file uploads |
| Profiles | Scaffold | Partial — profile api | folder only; consent logic partial under Identity |
| Startups | Scaffold | — | folder only |
| Assessments | Scaffold | — | folder only |
| Workflows | Scaffold | — | folder only |
| RoleAssignments | Scaffold | — | folder only |
| Tasks | Scaffold | — | folder only |
| Mentorship | Scaffold | — | folder only |
| Training | Scaffold | — | folder only |
| Graduation | Scaffold | — | folder only |
| Reporting | Scaffold | — | folder only |
| Integrations | Scaffold | — | folder only |
| Audit | Scaffold | — | folder only; audit **opt-in, not enforced** — enforcement lands in Epic R/A |
| FinalEvaluation | Absent | — | module folder not scaffolded (phase placement: Story 0.7) |
| Notifications | Absent | — | module folder not scaffolded (phase placement: Story 0.7) |
| Search | Absent | — | module folder not scaffolded (phase placement: Story 0.7) |
| Administration | Absent | — | module folder not scaffolded (phase placement: Story 0.7) |

## Cross-cutting

| Area | Status |
|---|---|
| SaaS commercial plane (plans, entitlements, subscriptions, Geidea, domains, branding) | Absent — documented only |
| Frontend (`frontend/`) | Implemented for Phase 1a surfaces (~106 TS/TSX files): auth, apply, submissions, programs |
| Reliability substrates (transactional outbox, idempotency, content-addressing) | **P1a enumerated set implemented** (Epic 2 submission gate); generalization for multi-consumer/cross-module use in **Epic R/A** scope |
| Audit enforcement | Opt-in only until **Epic R/A** lands `RecordAuthDecision` middleware + enforced audit |
| Entitlement-enforcement seam | Absent |
| Tenant isolation (fail-closed `BelongsToTenant`) | Implemented; cross-tenant access → neutral 404 (ADR-0009) |
| Tests | 52 feature-test files; 41 migrations; 384 backend tests passing on `main` |

## In-flight

- **Epic 4 (standalone identity):** SP-1 **done** (native registration + auth +
  `external_users → accounts + linked_identities` migration). SP-2 / SP-3 / SP-4
  next-up. Story-level breakdown for SP-2..SP-4 tracked into `epics.md` (readiness
  2026-06-23 recommendation).
- **Epic 3 (Score & Decide):** code paths shipping; entry gated on Epic 2
  evidence (a first-partner cohort running to decision) per readiness 2026-06-23.

## Engineering notes

- `phase-2-notes.md` (this folder) — internals of the Programs/Cohorts/Stages build.
- `bootstrap.md` (this folder) — Phase 0 repository foundation.
