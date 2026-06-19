# Implementation Status (As-Built)

> Owner: Engineering · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

**This is the only place implementation status is tracked.** Scope and plan docs
state intent and order — they must not carry status. Module names and the 24-count
are canonical from `../product/scope-register.md`; this file records only what is
*built*.

## Module status

| Module | Status | Notes |
|---|---|---|
| Identity | Implemented | `app/Modules/Identity` (21 files) — OIDC, profiles projection |
| Organizations | Implemented | `app/Modules/Organizations` (16) — tenancy root, RBAC |
| Programs | Implemented | `app/Modules/Programs` (22) — CRUD, policies, clone, templates |
| Stages | Implemented | `app/Modules/Stages` (30) — versioned stage engine, rules |
| Cohorts | Implemented | `app/Modules/Cohorts` (7) — enrollment windows |
| Profiles | Scaffold | folder only; consent logic partial under Identity |
| Startups | Scaffold | folder only |
| Forms | Scaffold | folder only |
| Applications | Scaffold | folder only |
| Documents | Scaffold | folder only |
| Assessments | Scaffold | folder only |
| Workflows | Scaffold | folder only |
| RoleAssignments | Scaffold | folder only |
| Tasks | Scaffold | folder only |
| Mentorship | Scaffold | folder only |
| Training | Scaffold | folder only |
| Graduation | Scaffold | folder only |
| Reporting | Scaffold | folder only |
| Integrations | Scaffold | folder only |
| Audit | Scaffold | folder only; audit currently opt-in, not enforced |
| FinalEvaluation | Absent | module folder not scaffolded |
| Notifications | Absent | module folder not scaffolded |
| Search | Absent | module folder not scaffolded |
| Administration | Absent | module folder not scaffolded |

## Cross-cutting

| Area | Status |
|---|---|
| SaaS commercial plane (plans, entitlements, subscriptions, Geidea, domains, branding) | Absent — documented only |
| Frontend (`frontend/`) | Scaffold (~8 TS files) |
| Reliability substrates (transactional outbox, idempotency) | Absent |
| Entitlement-enforcement seam | Absent |
| Tenant isolation (fail-closed `BelongsToTenant`) | Implemented |
| Tests | 28 feature tests; 26 migrations |

## In-flight

- Phase 2 completion (stage dependencies, tracks, archival) — partial, uncommitted
  work on branch `phase2-completion`; see `../plan/phases/2026-06-19-phase2-completion.md`.

## Engineering notes

- `phase-2-notes.md` (this folder) — internals of the Programs/Cohorts/Stages build.
- `bootstrap.md` (this folder) — Phase 0 repository foundation.
