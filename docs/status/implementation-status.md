# Implementation Status (As-Built)

> Owner: Engineering · Last-updated: 2026-06-27 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

**This is the only place implementation status is tracked.** Scope and plan docs
state intent and order — they must not carry status. Module names and the 24-count
are canonical from `../product/scope-register.md`; this file records only what is
*built*.

As of 2026-06-23: **Phase 1a (Epic 1 + Epic 2 + SP-1) is delivered on `main`**
(384 backend tests passing; sprint-status confirms all stories `done`). Identity
now reflects the ADR-0004 inversion (Catalesta system of record: `accounts` +
`linked_identities`, native auth) — the earlier "OIDC projection" model is gone.

**Update 2026-06-27** — work landed on `main` since the 2026-06-23 baseline,
all merged via PR:
- **Frontend Phase-1a console build-out:** router migration to `react-router-dom`
  (FE-0, #52); Programs lifecycle UI — detail/edit/clone/publish (FE-1); Cohorts
  management UI — list/create/detail/edit (FE-2); tenant-header foundation sending
  `X-Organization-Id` on tenant-scoped reads + mutations (FE-2.5, #54); and a
  shadcn/ui + Tailwind 4 design-system foundation — AppShell, MSW harness,
  Storybook (FE-UI-0/UI-1, #53/#55/#57). Frontend is now **128 TS/TSX files**.
- **Audit enforcement (partial):** RA.2 slice 1 (#51) moved authorization auditing
  from opt-in to **enforced** via a `Gate::after` hook
  (`App\Shared\Audit\AuthorizationAuditRecorder`, FR-126) recording all denials +
  non-read allows to the append-only `audit_logs` substrate. Remaining RA.2 slices
  (link/unlink, consent, profile-import, stage-outcome events; record-every-allow;
  outbox-queued writes) are deferred within Epic R/A.
- **Module-boundary enforcement:** `deptrac` stood up (#50, ADR-0010) with one layer
  per module; CI now fails on uncovered cross-module dependencies. Reliability
  substrate home formalized as `app/Shared/` (ADR-0010).
- **ADRs accepted:** ADR-0005 (single-database row-level tenancy) and ADR-0010
  (cross-cutting substrate home) are now `Accepted` — closing the ADR-0005 open
  decision tracked in `../project-context.md`.

> Test baseline: 387 passed / 1 skipped recorded in PR #51 (2026-06-24); **not
> independently re-run for this 2026-06-27 doc refresh** — treat as `Not verified`
> until the suite is executed. Migrations remain 41; backend test files now 70.

## Module status

| Module | Status | Frontend | Notes |
|---|---|---|---|
| Identity | Implemented | Yes — login, register, verify-email, forgot/reset password, onboarding, auth callback | `app/Modules/Identity` (28 files). Post-SP-1: `accounts` + `linked_identities` + native auth (migrations `..._create_accounts_table`, `..._create_linked_identities_table`, `..._add_native_auth_to_accounts`). System of record per ADR-0004; Startup Gate optional. |
| Organizations | Implemented | Yes — api/schema | `app/Modules/Organizations` — tenancy root, RBAC; cross-tenant show/update returns 404 (see ADR-0009) |
| Programs | Implemented | Yes — Programs list + detail/edit/clone/publish UI (FE-1) + api | `app/Modules/Programs` — CRUD, policies, clone, templates |
| Stages | Implemented | — | `app/Modules/Stages` — versioned stage engine, rules |
| Cohorts | Implemented | Yes — list/create/detail/edit UI (FE-2) + api/schema | `app/Modules/Cohorts` (11 files) — enrollment windows; `form_version_id` binding |
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
| Audit | Partial | — | `App\Shared\Audit` — **authorization auditing enforced** via `Gate::after` (RA.2 slice 1, FR-126): denials + non-read allows → append-only `audit_logs`. Remaining event coverage (link/unlink, consent, profile-import, stage outcomes) + outbox-queued writes deferred within Epic R/A |
| FinalEvaluation | Absent | — | module folder not scaffolded (phase placement: Story 0.7) |
| Notifications | Absent | — | module folder not scaffolded (phase placement: Story 0.7) |
| Search | Absent | — | module folder not scaffolded (phase placement: Story 0.7) |
| Administration | Absent | — | module folder not scaffolded (phase placement: Story 0.7) |

## Cross-cutting

| Area | Status |
|---|---|
| SaaS commercial plane (plans, entitlements, subscriptions, Geidea, domains, branding) | Absent — documented only |
| Frontend (`frontend/`) | Implemented for Phase 1a surfaces (**128 TS/TSX files**): auth, apply, submissions, programs (detail/edit/clone/publish), cohorts (list/create/detail/edit), `react-router-dom` routing, `X-Organization-Id` tenant header, shadcn/ui + Tailwind 4 AppShell + MSW harness + Storybook |
| Reliability substrates (transactional outbox, idempotency, content-addressing) | **P1a enumerated set implemented** (Epic 2 submission gate); home formalized as `app/Shared/` (ADR-0010); generalization for multi-consumer/cross-module use in **Epic R/A** scope |
| Audit enforcement | **Authorization decisions enforced** (RA.2 slice 1 — `Gate::after` recorder, FR-126); broader event coverage + `RecordAuthDecision`-style middleware path continues in **Epic R/A** |
| Module-boundary enforcement | **`deptrac` enforced in CI** (ADR-0010) — one layer per module, fails on uncovered cross-module dependencies |
| Entitlement-enforcement seam | Absent |
| Tenant isolation (fail-closed `BelongsToTenant`) | Implemented; cross-tenant access → neutral 404 (ADR-0009) |
| Tests | 70 backend test files; 41 migrations; 387 passed / 1 skipped recorded in PR #51 (2026-06-24) — `Not verified` since (not re-run for this refresh) |

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
