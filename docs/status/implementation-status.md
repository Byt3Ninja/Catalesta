# Implementation Status (As-Built)

> Owner: Engineering · Last-updated: 2026-06-29 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

**This is the only place implementation status is tracked.** Scope and plan docs
state intent and order — they must not carry status. This file records what is
*built and functional*, distinguishing **backend functionality actually served**
from **frontend surfaces** (some of which are UI-first on MSW, ahead of the API).

### Verification basis (how this refresh was derived)
- **Backend functionality** — read from `backend/routes/api.php` + `startup-gate-mock.php`
  (the authoritative map of what the server actually serves), `backend/app/Modules/*`
  file inventory, and `backend/tests/*` presence.
- **Backend test pass/fail** — **`Not verified`**: the suite was **not re-run** for this
  refresh. Last green baseline: 387 passed / 1 skipped (PR #51, 2026-06-24). Counts
  below are *file counts*, not a green run.
- **Frontend** — git/PR history (`#52`–`#62`) and a local gate run during Slice 2c
  (vitest 341 passed / 76 files, lint, build, build-storybook, Playwright e2e —
  **verified 2026-06-29**). Frontend is **223 TS/TSX files**.
- **Tenancy/audit/ADR claims** carried from prior doc + ADRs, not independently re-run.

---

## 1. Backend functional capabilities (as served by `routes/api.php`)

These are the capabilities the server **actually exposes today**:

- **Identity & native auth** (`Identity`, 28 files; tests: Feature+Unit) — register,
  password login, logout, forgot/reset password, email verify + resend, session,
  auth callback. **OIDC provider mock**: `/oauth/authorize|token|userinfo|revoke|logout`,
  `/.well-known/openid-configuration` + `jwks.json`. System of record per ADR-0004
  (`accounts` + `linked_identities`); Startup Gate optional.
- **Organizations & tenancy** (`Organizations`, 17 files) — list/get/create/patch
  organizations; list/create memberships. Fail-closed `BelongsToTenant`; cross-tenant
  access → neutral **404** (ADR-0009). `X-Organization-Id` header on tenant-scoped calls.
- **Profiles & consent** (`Profiles`, partial under Identity) — `/me`, `/me/profile`,
  `/me/consents`, `/me/role-profiles`, `/me/startups`, `/profile-update-proposals`,
  `/program-achievements`.
- **Programs** (`Programs`, 30 files; 7 test files) — list/get/create/patch/**publish**/
  **clone**; **program templates** (create + instantiate); **policies**;
  **role-requirements**; **tracks**.
- **Stages engine** (`Stages`, 32 files; 7 test files) — per-program stages
  create/patch/**publish**/**reorder**; **stage dependencies**; **tracks**. Versioned,
  published = immutable. **Shape = `programs/{program}/stages` + `/stages/{id}` +
  dependencies/tracks** — NOT the frontend 2c "stage-pipeline / pipeline-version" shape
  (see §3).
- **Cohorts** (`Cohorts`, 11 files; 3 test files) — list/get/**create**/**patch**;
  `cohorts/{cohort}/funnel`; `cohorts/{cohort}/submissions` (+ detail). Enrollment-window
  fields on the model.
- **Applications / apply (Epic 2)** (`Applications`, 13 files; 4 test files) —
  public `GET /apply/{cohort}`; `POST /apply/{cohort}/submit` (idempotent, immutable
  snapshot); `POST /apply/{cohort}/events`; submissions funnel/list/detail. Backed by
  the `Forms` schema + content-addressed `Documents` blob store for uploads.
- **Audit** (`App\Shared\Audit`, partial) — authorization decisions **enforced** via
  `Gate::after` (FR-126): denials + non-read allows → append-only `audit_logs`.

---

## 2. NOT served by the backend (functionally absent server-side)

The following have **no API routes** — they are scaffold/absent on the backend even
where a frontend exists:

- **Assessments / scoring / decide** — `Assessments` is a **1-file scaffold**; no routes.
  (This is the Slice 2d target; currently in design.)
- **Forms *builder*** — no form create/version/publish/draft routes. The `Forms` module
  (7 files) only provides the **schema consumed by the apply flow**; the 2b builder is
  frontend-only (§3).
- **Cohort lifecycle mutations** beyond create/patch — **no** `open`, `bind-form`, or
  `bind-stage-pipeline` routes (all MSW-only on the FE).
- **Stage *pipeline* authoring** (FE 2c shape: `stage-pipelines`, versions, draft,
  publish, fork, routing) — no routes.
- **Scaffold modules** (1 file each, no routes): Workflows, Tasks, Mentorship, Training,
  Graduation, RoleAssignments, Startups, Documents, Integrations. `Reporting` = 4 files
  (2 tests) but no API routes. `FinalEvaluation`, `Notifications`, `Search`,
  `Administration` = **absent** (no module folder).
- **Commercial plane** — plans, entitlements, subscriptions, Geidea billing, usage
  metering, custom domains, branding: **absent (documented only)**.

---

## 3. Frontend ↔ backend parity (critical — what is real vs mock)

The frontend has shipped **6 UI slices** (`#55`–`#62`). Several are **UI-first on MSW**,
ahead of any backend endpoint:

| Frontend surface | Slice | Backed by real API? |
|---|---|---|
| Auth / onboarding / verify / reset | 1a | **Real** |
| Role-scoped shell, Action Center, context selector | 1a | Action-center data **mock**; auth/roles real |
| Programs: list/detail/edit/clone/publish | FE-1 | **Real** |
| Cohorts: list/create/detail/edit | FE-2/2a | create+patch **real**; **open / bind-form / bind-stage-pipeline = MSW-only** |
| Apply + Submissions (funnel/list/detail) | Epic 2 | **Real** |
| Personal: profile + consent | 1b | **Real** (`/me/*`) |
| Personal: notifications, search | 1b | **MSW-only** |
| Forms builder / versions / conditional logic / binding | 2b | **MSW-only** (no builder routes) |
| Stage-pipeline builder / routing engine / versions / binding / program config hub | 2c | **MSW-only** (and the FE pipeline shape ≠ backend Stages engine shape) |

**Implication:** the selection-authoring UX (forms, stages) and cohort
binding/open are demoed end-to-end against MSW but are **not yet wired to the
backend**. Backend wiring for 2a-mutations / 2b / 2c is outstanding work and a real
risk if the FE pipeline/version model diverges from the backend Stages engine.

---

## 4. Module status (detailed)

| Module | Backend files | Status | Test files | API routes | Frontend |
|---|---|---|---|---|---|
| Identity | 28 | Implemented | ~3 | native auth + OIDC mock | Real (auth/onboarding) |
| Organizations | 17 | Implemented | (Feature) | orgs + memberships | Real (api/schema) |
| Programs | 30 | Implemented | 7 | CRUD/publish/clone/templates/policies/tracks | Real (FE-1) |
| Stages | 32 | Implemented | 7 | stages/deps/tracks/publish/reorder | **FE 2c is MSW-only, different shape** |
| Cohorts | 11 | Implemented | 3 | list/get/create/patch/funnel/submissions | Real for CRUD; open/bind MSW-only |
| Applications | 13 | Implemented | 4 | apply submit/events + submissions | Real (ApplyPage/Submissions) |
| Forms | 7 | Implemented (schema only) | 1 | none (schema used by apply) | Builder MSW-only (2b) |
| Documents | 1 | Partial | — | none (blob store backs uploads) | — |
| Profiles | 1 | Partial (under Identity) | 1 | `/me/*` profile/consent | Partial (profile api) |
| Audit | 1 | Partial | 4 | enforced via `Gate::after` | — |
| Reporting | 4 | Scaffold+ | 2 | none | — |
| Assessments | 1 | **Scaffold** | 0 | none | — (Slice 2d in design) |
| Workflows | 1 | Scaffold | 0 | none | — |
| RoleAssignments | 1 | Scaffold | 0 | none | — |
| Tasks | 1 | Scaffold | 0 | none | — |
| Mentorship | 1 | Scaffold | 0 | none | — |
| Training | 1 | Scaffold | 0 | none | — |
| Graduation | 1 | Scaffold | 0 | none | — |
| Startups | 1 | Scaffold | 0 | `/me/startups` (read) | — |
| Integrations | 1 | Scaffold | 0 | none | — |
| FinalEvaluation | — | Absent | — | — | — |
| Notifications | — | Absent | — | — | MSW-only surface (1b) |
| Search | — | Absent | — | — | MSW-only surface (1b) |
| Administration | — | Absent | — | — | — |

---

## 5. Cross-cutting

| Area | Status |
|---|---|
| Tenant isolation (fail-closed `BelongsToTenant`) | Implemented; cross-tenant → neutral 404 (ADR-0009) |
| Authorization audit | Enforced (`Gate::after`, FR-126); broader event coverage deferred (Epic R/A) |
| Reliability substrates (outbox, idempotency, content-addressing) | P1a enumerated set implemented (Epic 2 submission gate); home = `app/Shared/` (ADR-0010); generalization deferred (Epic R/A) |
| Module-boundary enforcement | `deptrac` enforced in CI (ADR-0010) — fails on uncovered cross-module deps |
| Entitlement-enforcement seam | **Absent** |
| Commercial plane (plans/entitlements/subscriptions/Geidea/domains/branding) | **Absent — documented only** |

---

## 6. Test & build baseline

- **Backend:** ~71 test files (`backend/tests`: 54 Feature, 14 Unit, 2 Contract,
  1 Architecture); 41 migrations. **Suite not re-run this refresh → `Not verified`**
  (last green: 387 passed / 1 skipped, PR #51, 2026-06-24).
- **Frontend:** 223 TS/TSX files. **Verified 2026-06-29** (Slice 2c gate): vitest
  **341 passed / 76 files**, lint clean, `build` + `build-storybook` succeed,
  Playwright slice-2c e2e passes.

---

## 7. Roadmap position & immediate next

- **Phases 0 + 1a delivered** (identity, tenancy, RBAC; programs/cohorts/stages engine;
  Epic 2 applications). MVP spine = **forms → applications → assessments/scoring → decide**:
  - Forms: backend **schema + apply ✓**; **builder UI MSW-only**.
  - Applications: **✓ (backend + FE)**.
  - Stages: backend **engine ✓**; **FE pipeline authoring MSW-only** (shape divergence to reconcile).
  - **Assessments / scoring / decide: not started** — backend scaffold; **Slice 2d in design**.
- **Outstanding integration debt:** wire 2a cohort open/bind, 2b forms builder, and 2c
  stage-pipeline authoring to real backend endpoints (or reconcile the FE pipeline model
  with the backend Stages engine).

## Engineering notes
- `phase-2-notes.md` — internals of the Programs/Cohorts/Stages build.
- `bootstrap.md` — Phase 0 repository foundation.
