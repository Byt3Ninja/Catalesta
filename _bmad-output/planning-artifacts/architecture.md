---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
lastStep: 8
status: 'complete'
completedAt: '2026-06-23'
updated: '2026-06-23'
inputDocuments:
  - _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
  - _bmad-output/planning-artifacts/ux-designs/ux-Catalesta-2026-06-20/DESIGN.md
  - _bmad-output/planning-artifacts/ux-designs/ux-Catalesta-2026-06-20/EXPERIENCE.md
  - docs/product/scope-register.md
  - docs/plan/roadmap.md
  - docs/product/product-brief.md
  - docs/architecture/  (existing technical decisions — domain-boundaries, data-ownership, security-baseline, shared-contracts, resilience-dr, integration-strategy, devops-observability, tenancy-isolation, data-privacy-rights, admin-impersonation-audit, overview)
  - "BROWNFIELD: live backend/ codebase — Laravel modular monolith; built modules: Identity, Organizations, Programs, Cohorts, Stages + fail-closed tenancy, decimal-scoring rule kernel, versioning/immutability kernel"
workflowType: 'architecture'
project_name: 'Catalesta'
user_name: 'Byteninja'
date: '2026-06-20'
scope: 'Phase 1a (Selection MVP) — building on existing modules'
---

# Architecture Decision Document — Catalesta

_This document builds collaboratively through step-by-step discovery. Sections are appended as we work through each architectural decision together._

_Scope: **Phase 1a (Selection MVP)** per `roadmap.md` — a brownfield design on top of the 5 already-built modules._

## Project Context Analysis

### Requirements Overview

**Functional Requirements (Phase 1a, ~30 FRs):**
- Reuse (built): Identity/auth (Sanctum SPA session), Organizations/tenancy/RBAC, Programs, Cohorts, Stages, versioning/immutability, decimal rule kernel. **(Identity inverts to native Catalesta accounts under Epic 4 — see PRD §9; the `sub`-keyed SG-OIDC path demotes to an optional linked provider.)**
- New (thin): Forms (attach-only, 8 field types — FR-020/021/022); Applications (FR-030/031/032/033/034 — cohort-bound, immutable snapshot, idempotent submit); Assessment persistence + Decision (FR-040/041/042/043 — decimal scoring, accept/reject/reopen, CSV export).
- New (seams, "first-slice depth"): transactional outbox (FR-050), idempotency on submit+callback (FR-051), enumerated audit (FR-052), EntitlementService socket allow-all (FR-060), PaymentProvider interface + Geidea sandbox (FR-070..073), instrumentation events FR-080/081.

**Non-Functional Requirements (architecture drivers):**
- NFR-001 fail-closed tenant isolation (C1=0) · NFR-003 immutability/versioning · NFR-004 decimal · NFR-005 no-code-in-rules · NFR-006 consent-aware seam · NFR-007 payment integrity · NFR-008 data-respecting limits · NFR-009 security baseline · NFR-011 EN/AR RTL + P1a a11y floor · NFR-012 observability/enforced audit · NFR-013 data governance (residency before pilot) · NFR-014 performance budget.

**Scale & Complexity:**
- Primary domain: full-stack (Laravel modular monolith backend; web operator console + mobile-web public flow).
- Complexity level: medium-high, de-risked by brownfield reuse of tenancy + kernels.
- Estimated new architectural components (P1a): ~6 (Forms-lite, Applications, Assessment-persistence, Outbox, Idempotency, Entitlement+Payment seams) + 2 frontend surfaces.

### Technical Constraints & Dependencies
- Brownfield: must reuse `BelongsToTenant`, the rule/decimal kernel, the versioning/immutability kernel, and the existing module layout; no foundation re-architecture.
- External integrations behind interfaces only: Startup Gate OIDC as an **optional** linked SSO/import provider (FR-157), Geidea (sandbox, no real charge in P1a).
- Content-addressed file storage introduced by FR-031 (blob store dependency).
- Non-negotiables (CLAUDE.md): organization_id on every tenant row; **Account id (ULID) is the primary user key, `sub` is the SG-link key, email is a local credential only**; no raw card/CVV; published artifacts immutable.

### Cross-Cutting Concerns Identified
Tenant isolation · immutability/versioning · enforced audit · transactional outbox + idempotency · entitlement seam · consent-aware access · i18n/RTL + a11y floor · observability/correlation IDs · payment-provider isolation.

## Foundation (brownfield — established stack)

Full-stack; **brownfield, no starter selected**. Versions pinned by repo.
- **Backend:** PHP 8.3.31, Laravel 13.8, Sanctum 4, `firebase/php-jwt` 7 (JWKS), `brick/math` 0.17 (decimal). Modular monolith `app/Modules/*` + `app/Shared/*`.
- **Frontend:** React 19.2, TS 6 strict, Vite 8, React Query 5, Zod 4; Vitest 4 + Playwright 1.61.
- **Infra (docker-compose, provisioned):** postgres, redis, queue-worker, scheduler, minio (S3), startup-gate-mock, mailpit, nginx.
- **Already fixed:** fail-closed `BelongsToTenant`; decimal kernel; versioning/immutability kernel; Sanctum SPA + JWKS-OIDC (the JWKS-OIDC path becomes the optional SG-link adapter under Epic 4; native-account auth is net-new). No tenancy/kernel init story needed.

## Foundation Stress-Test (Advanced Elicitation — Winston + Amelia, code-verified 2026-06-20)

**Amended claim.** "Reuse the foundation, build only the seams" holds for *runtime plumbing* but is **false at the contract/persistence layer**. Verified: `app/Shared/Outbox/` and `app/Shared/Idempotency/` are **empty (0 files)**; no `*outbox*`/`*idempotent*` migrations. These are net-new schema/semantics, not reuse. (Built shared kernels: Rules 7, Tenancy 4, Versioning 5, Audit 2.)

**Load-bearing primitive = Idempotency** — guards *both* application submit (FR-032) and the Geidea callback (FR-072/073). Do **not** stretch the versioning/immutability kernel to cover it ("is this value frozen?" ≠ "did this operation already happen?"). Build clean.

**Decisions (ADRs):**
- **ADR-1** Reuse versioning kernel's immutability primitive; **build a separate `submission_snapshot` (jsonb)** for user-submitted payloads (FR-031) — distinct lifecycle from published config.
- **ADR-2** **Build `idempotency_keys` fresh** (postgres): `UNIQUE(scope, key)` + stored **`request_fingerprint`** + stored response; same key + diff fingerprint → **422**, same+same → replay. Durable replay > redis TTL for callbacks.
- **ADR-3** Reuse `BelongsToTenant` — but it is **opt-in per new table** → build explicit cross-tenant isolation tests for every new tenant-owned table.
- **ADR-4** Reuse outbox infra; **build the relay worker + `outbox_events` (`dispatched_at`)** with **atomic claim** (`UPDATE … SET dispatched_at WHERE dispatched_at IS NULL RETURNING`, never SELECT-then-UPDATE) + consumer-side `event_id` idempotency.
- **ADR-5** Content-addressing = **`sha256` key + refcount over MinIO**; **GC deferred to a manual command** (ticketed debt).

**Failure-mode guards (code-review tripwires):** outbox insert lives *inside the same DB transaction* as the domain write (no direct dispatch from handlers); idempotency claims the key (insert-first) before doing work; Geidea callback is signature-verified before any state change; browser return only *reads* status.

> Note: this architecture doc is scoped to the P1a foundation + substrate decisions needed for story creation; remaining architecture steps (full data models, API contracts) can be completed later via bmad-create-architecture.

## Core Architectural Decisions (added 2026-06-23 — back-filling Step 4)

> Both decisions below were resolved earlier (2026-06-21 identity inversion; 2026-06-23 DB topology) and live in `docs/project-context.md`, `_bmad-output/.../prd-Catalesta-2026-06-20/prd.md` §6.1 / §9 / §9.1, and `docs/repository-audit.md`. This Step-4 pass promotes them to architecture-document sections and produces sibling ADR files (`adr/0004-catalesta-identity-system-of-record.md`, `adr/0005-single-database-row-level-tenancy.md`) with `adr/0002-startup-gate-system-of-record.md` marked Superseded by ADR-0004.

### Decision Priority Analysis

**Critical (Block Implementation):**
- **AD-1** Identity ownership inversion — codified by ADR-0004 (supersedes ADR-0002)
- **AD-2** Database topology — codified by ADR-0005

**Important (Already decided upstream):**
- Modular monolith (ADR-0001); mocked OIDC first (ADR-0003); 24-module canonical scope (auto-memory `architecture-decisions.md` § 2 + `scope-register.md`); repo layout `backend/` + `frontend/` + `services/` (auto-memory § 3); cross-tenant access returns 404 not 403 (auto-memory § 5; PRD FR-004; `Phase2TenantIsolationTest`).

**Deferred (subsequent Step-4 passes):**
- API / Communication (Cat 3), Frontend (Cat 4), Infrastructure / Deployment (Cat 5).

### Authentication & Identity (AD-1, ADR-0004)

**Decision:** Catalesta is the system of record for accounts, identity, general + role profiles, memberships, consent, verification. Startup Gate is an optional linked SSO provider + consented field-level profile-import source. Primary user key = local Account ULID. External identities keyed by `(issuer, sub)` on `linked_identities`. Email = local login credential only, never a cross-system identifier.

**Rationale (one line):** Local auth must work without SG; cross-system identifier coupling blocks idempotency, multi-tenant role profiles (7 types), and MENA-tenant control over consent semantics.

**Affects:** Identity, Organizations (membership), Profiles, RBAC, Audit, Integration adapters. Implementation carried by Epic 4 / SP-1..SP-4 (SP-1 in production).

**Status:** Carried by PRD §6.1, §9, FR-001 / FR-007 / FR-008 / FR-009 / FR-157, NFR-002. **ADR-0002 marked Superseded by ADR-0004 in `adr/`.**

### Data Architecture — Database Topology (AD-2, ADR-0005)

**Decision:** One logical product database (Postgres or MySQL). Multi-tenancy is row-level via `organization_id`. **Per-tenant DB and schema-per-tenant forbidden.** Read replicas allowed via Laravel `read` / `write` split; strongly-consistent reads (post-write, authorization, idempotency, OIDC callback) target the writer. Out-of-band analytics warehouse allowed for Reporting exports only, **never as a product-code read path.** Redis and S3 unconstrained.

**Rationale (one line):** Predictable operational cost at MENA pilot scale; existing `BelongsToTenant` substrate carries the model; cross-tenant analytics tractable via warehouse export; noisy-neighbor risk mitigated by entitlements (FR-061) + rate limits (NFR-009).

**Affects:** All 24 modules, NFR-015 architecture-test acceptance, reporting/warehouse pipeline, DR (NFR-010), monitoring strategy.

**Status:** Carried by PRD §9.1, NFR-015. Cross-references `docs/project-context.md` § Database Topology.

### Decision Impact Analysis

**Implementation sequence:** Both decisions are partially implemented — Epic 4 / SP-1 in production (auth path inverted); `BelongsToTenant` row-level scope in production. Outstanding work: ADR-0004 closes ADR-0002 in `adr/` (status flip — done in this session); ADR-0005's architecture-test commitment (NFR-015 acceptance test) lands as a story under the Reliability/Audit epic carve-out (per PRD §7 R/A row).

**Cross-component dependencies:** AD-1 and AD-2 reinforce each other — single-DB row-level tenancy assumes a single canonical Account ULID (AD-1); AD-1's local-auth requirement assumes the product DB is source of truth, not a cross-tenant SG cache. Neither makes sense without the other.

## Implementation Patterns & Consistency Rules (added 2026-06-23)

> The pattern canon lives in `docs/project-context.md` § PHP / Laravel rules (18 subsections covering language features, identity, decimal/money, tenant isolation, mass-assignment, module boundaries, authorization, FormRequest/controllers, file uploads, queues, logging, timing-safe equality, deserialization, time, service resolution, Redis, migrations, performance, escape-hatches). This section records the 8 patterns Step 5's framework asks about that project-context did not yet pin, plus the enforcement story for all of them. No duplication of what already lives in project-context.

### Pattern Categories Defined

**Critical conflict points identified:** 8 backend gaps + 2 deferred (frontend patterns; OpenAPI → zod codegen pipeline).

### Naming Patterns

#### Database (P1, P2)

- **Tables:** `snake_case` plural — `organizations`, `accounts`, `linked_identities`, `applications`, `submissions`.
- **Columns:** `snake_case` singular — `organization_id`, `issuer`, `sub`, `created_at`. Foreign keys = `<entity>_id`. Timestamps = Laravel defaults (`created_at` / `updated_at`). ULID PKs are `id char(26)` (per ADR-0005 + project-context § Identity & email).
- **Indexes:** `<table>_<column>_<type>` — e.g. `linked_identities_issuer_sub_unique`, `applications_organization_id_index`.

#### API endpoints (P3)

- **Pattern:** REST plural + version in path — `/api/v1/{resource}/{id}` and `/api/v1/{parent}/{parent_id}/{child}`. Examples: `/api/v1/organizations/{organization}`, `/api/v1/cohorts/{cohort}/submissions`, `/api/v1/applications/{application}/decisions`.
- **Route parameters:** Laravel `{param}` style (route-model-binding friendly), not Rails-style `:param`.
- **Major version in path** (`/api/v1/...`); minor revisions are non-breaking. Breaking changes ship `/api/v2/...`.
- **Idempotency:** `POST /api/v1/{resource}` accepts `Idempotency-Key` header for FR-032 / FR-051-class operations.

### Format Patterns

#### JSON field naming (P4)

- **Wire format:** `snake_case` in request and response bodies — server-side everywhere. Matches DB columns; Scramble emits as-is.
- **Frontend:** React side may transform at the boundary if it prefers `camelCase` — convention is one rename layer (the generated zod schema or a thin adapter), never per-component.

#### API response envelope (P5)

- **Single resource:** `{ "data": { ... } }` — Eloquent API Resource.
- **Collection with pagination:** `{ "data": [ ... ], "meta": { ... }, "links": { ... } }`. Without pagination: `{ "data": [ ... ] }`.
- **No `error` envelope on success.** Success = `2xx` with the resource(s).
- **Created:** `201 Created` + the created resource in `data` (or `204 No Content` if the operation does not produce a representation).

#### Error response structure (P6)

- **422 Unprocessable Entity** (validation): `{ "message": "...", "errors": { "field": ["msg1", "msg2"] } }` — Laravel validator default.
- **4xx / 5xx (other):** `{ "message": "..." }`; optional `"code"` for application-level error codes when HTTP status is not specific enough.
- **404 for cross-tenant access** (FR-004; auto-memory § 5; ADR-0005). **Never 403** across tenants.
- **401 vs 403:** 401 for unauthenticated; 403 for authenticated-but-unauthorized within the *same* tenant.

### Communication Patterns

#### Domain event naming (P7)

- **Format:** `module.entity.event` lowercase dotted — `programs.program.published`, `applications.submission.scored`, `decisions.decision.recorded`, `decisions.decision.reopened`, `decisions.decisions.exported`.
- **Maps onto PRD FR-080 taxonomy** with the module prefix added.
- **Trusted-publication outbound** (tenant → SG, per ADR-0004): `integrations.startup_gate.achievement_published`.

#### Domain event versioning (P8)

- **Every event payload includes `event_version: int`**, defaulting to `1`. Bump on breaking schema change. Consumers MUST switch on `(event_name, event_version)`, not name alone.
- **Outbox guarantee:** producers never publish without `event_version`. Architecture test asserts payload schema includes the field.
- **Versioning policy:** breaking = remove or rename a field, change type, change semantics. Adding an optional field = non-breaking, no bump.

### Pattern Enforcement

| Pattern | Mechanism | Status |
|---|---|---|
| P1, P2 (DB naming) | Code review + migration template review | Convention-only today; consider PHPStan rule on column refs in Eloquent `$fillable` |
| P3 (API endpoint naming) | Route definitions + Spectral OAS lint | Spectral configured (`.spectral.yaml`); custom rule for plural-resource naming to be added |
| P4 (JSON snake_case) | Eloquent API Resource defaults + Scramble OpenAPI output | Automatic if no `$resourceUsesCamelCase` overrides; CI Spectral lints emitted spec |
| P5 (envelope) | Eloquent API Resource (`new XResource(...)`, `XResource::collection(...)`) | Convention-only today; PR review |
| P6 (error envelope) | Laravel default ExceptionHandler + FormRequest validator | Default behavior; verify no controller throws raw responses |
| P7 (event naming) | Custom PHPStan rule on dispatcher: event name must match `^[a-z_]+\.[a-z_]+\.[a-z_]+$` | Story under Reliability/Audit epic |
| P8 (event versioning) | Architecture test `tests/Architecture/EventVersioningTest.php` | Story under Reliability/Audit epic |
| OpenAPI → zod codegen | Generate zod schemas from Scramble OpenAPI on frontend build | **OPEN:** codegen pipeline story to be filed (decided: generate, not hand-author) |

### Cross-references (no duplication)

- **All other backend patterns** — `docs/project-context.md` § PHP / Laravel rules.
- **Decimal arithmetic** — same file § Decimal & money.
- **Tenant isolation patterns** — same file § Tenant isolation + ADR-0005.
- **Identity patterns** — same file § Identity & email + ADR-0004.

### Deferred patterns

- **Frontend patterns** (state management, loading states, error boundaries, query-key conventions, suspense). `[NEEDS USER CALL]` — deferred to a `bmad-ux` / `frontend-design` pass. Tentative direction: `@tanstack/react-query` already in stack; default colocated query keys per module, `useQuery` / `useMutation`, route-level error boundaries, no global error store. Confirm or override during the deferred pass.
- **OpenAPI → zod codegen pipeline.** Decided: **generate (not hand-author)** — prevents drift. Implementation story to be filed: `npm run codegen:zod` invoking `@hey-api/openapi-ts` or `openapi-zod-client` against `storage/api-docs/openapi.yaml`. Frontend imports from `frontend/src/api/__generated__/`. Slots into Frontend Foundation or the Reliability/Audit epic carve-out.

### Architecture-test commitments emerging from this section

- `tests/Architecture/EventNamingTest.php` — every dispatched event matches `^[a-z_]+\.[a-z_]+\.[a-z_]+$`.
- `tests/Architecture/EventVersioningTest.php` — every event payload contains `event_version: int`.
- `tests/Architecture/ApiVersioningTest.php` — every `api.php` route prefix matches `/api/v{int}/`.
- Existing: `Phase2TenantIsolationTest` (404-not-403); EntitlementService arch test (FR-060); NFR-015 architecture test (DB topology — coming in Reliability/Audit epic).

## Project Structure & Boundaries (added 2026-06-23)

> Brownfield orientation: most of this section *documents* what is already on disk (20 of 24 modules scaffolded; cross-cutting `app/Tenancy/` and `app/Storage/`; OpenAPI under `backend/openapi/`; Scramble + Spectral wired). The new content names the directories and files this session's decisions add — without creating them. Module creation lives in scoping stories.

### Current as-built structure

```
Catalesta/
├─ backend/                      # Laravel ^13.8 modular monolith
│  ├─ app/
│  │  ├─ Modules/                # 20 of 24 modules scaffolded
│  │  │  ├─ Identity/            # Implemented (21 files) — Account, sessions, OIDC linking
│  │  │  ├─ Organizations/       # Implemented (16) — tenancy root + RBAC
│  │  │  ├─ Programs/            # Implemented (22) — CRUD, clone, templates
│  │  │  ├─ Cohorts/             # Implemented (7) — enrollment windows
│  │  │  ├─ Stages/              # Implemented (30) — versioned stage engine + rules
│  │  │  ├─ Profiles/            # Scaffold — consent logic partial
│  │  │  ├─ Startups/            # Scaffold
│  │  │  ├─ Forms/               # Implemented post-2026-06-19 (status doc stale — F-003)
│  │  │  ├─ Applications/        # Implemented post-2026-06-19 (status doc stale)
│  │  │  ├─ Documents/           # Scaffold
│  │  │  ├─ Assessments/         # Scaffold (Epic 3 in-flight)
│  │  │  ├─ Workflows/           # Scaffold (P2)
│  │  │  ├─ RoleAssignments/     # Scaffold
│  │  │  ├─ Tasks/               # Scaffold
│  │  │  ├─ Mentorship/          # Scaffold (P2)
│  │  │  ├─ Training/            # Scaffold (P2)
│  │  │  ├─ Graduation/          # Scaffold (P2)
│  │  │  ├─ Reporting/           # Scaffold (P3)
│  │  │  ├─ Integrations/        # Scaffold (SG-specific code lives here)
│  │  │  └─ Audit/               # Scaffold — opt-in today; ENFORCEMENT moves to app/Reliability/Audit/
│  │  ├─ Http/                   # Controllers (thin per project-context)
│  │  ├─ Providers/              # ServiceProviders bind module Contracts
│  │  ├─ Storage/                # Cross-cutting: Flysystem facade (S3 + tenant disks)
│  │  └─ Tenancy/                # Cross-cutting: BelongsToTenant + Middleware (SetTenantContext)
│  ├─ config/                    # Laravel config + per-area
│  ├─ database/migrations/       # 41 files as of 2026-06-23
│  ├─ openapi/                   # Scramble-generated specs (Spectral-linted)
│  ├─ routes/                    # api.php, console.php, startup-gate-mock.php, web.php
│  ├─ tests/                     # PHPUnit: Unit, Feature, Contract
│  └─ phpstan.neon               # larastan level 6
├─ frontend/                     # React 19 + TS 6 + Vite 8 + vitest + Playwright
│  └─ src/                       # Scaffold (~8 TS files per status doc; recently expanding)
├─ services/
│  └─ startup-gate-mock/         # Local OIDC mock provider
├─ adr/                          # ADRs (5 — including 0004 + 0005 from this session)
├─ docs/                         # Long-form product, architecture, SaaS, UX, status, plan docs
│  ├─ project-context.md         # Authoritative project context (created this session)
│  └─ repository-audit.md        # Drift findings (created this session)
├─ _bmad/                        # BMM v6 install
├─ _bmad-output/                 # BMAD planning + implementation artifacts
└─ graphify-out/                 # Repo knowledge-graph snapshot (2026-06-22)
```

### Module → FR / Epic mapping

| Module | Primary FRs | Epic / Story |
|---|---|---|
| Identity | FR-001, FR-007, FR-008, FR-009 + NFR-002 | Epic 1 / S1.1, S1.5 + Epic 4 / SP-1..SP-4 |
| Organizations | FR-002, FR-003, FR-004, FR-005 + NFR-001 | Epic 1 / S1.1 (foundation) |
| Profiles | FR-006, FR-009 + NFR-006 | Epic 1 / S1.5 + Epic 4 / SP-3, SP-4 |
| Programs | FR-010, FR-012, FR-013, FR-060 | Epic 1 / S1.2 |
| Cohorts | FR-011, FR-021, FR-060 | Epic 1 / S1.4 |
| Stages | FR-012 + cross-cutting rule kernel | Epic 1 (foundation) |
| Forms | FR-020, FR-022, NFR-005 | Epic 1 / S1.3 |
| Applications | FR-030, FR-031, FR-032, FR-033, FR-034 + AR-4, AR-6 | Epic 2 / S2.6, S2.7, S2.8 |
| Documents | AR-5 (content-addressed blobs); supports FR-031 | Epic 2 / S2.1 |
| Assessments | FR-040, FR-041, FR-042, FR-043, FR-081 | Epic 3 / S3.1, S3.2, S3.3 (in-flight) |
| Audit (domain) | FR-052 substrate (Story 2.5) — read/query | Epic 2 / S2.5 |
| Integrations | FR-008, FR-009 SG integration; trusted publication | Epic 4 / SP-2, SP-4 |
| Workflows, Mentorship, Training, Graduation, Reporting, RoleAssignments, Tasks, Startups | FR-100…108 capability set | P2 / P3 capabilities |

### Cross-cutting (under `app/Shared/`, not `app/Modules/`)

> **Corrected 2026-06-23 — see [ADR-0010](../../adr/0010-cross-cutting-substrate-home-app-shared.md).** Earlier drafts placed cross-cutting concerns at top-level `app/<concern>/` and proposed a new `app/Reliability/`. The as-built reality: all cross-cutting substrate lives under **`app/Shared/`**, and the Reliability substrate (Audit/Outbox/Idempotency) already exists there from Epic 2. Paths below are corrected; any remaining `app/Reliability/*` reference elsewhere in this doc reads as `app/Shared/*` per ADR-0010.

| Path | Purpose | Status |
|---|---|---|
| `app/Shared/Tenancy/` | `BelongsToTenant` + `SetTenantContext` middleware; row-level scoping per ADR-0005 | Implemented |
| `app/Shared/Storage/` | Content-addressed blob store (`ContentAddressedStore`); S3/MinIO disk | Implemented |
| `app/Shared/Audit/` | Audit substrate (enforcement-layer home) | Implemented (opt-in); enforcement in RA.2 |
| `app/Shared/Outbox/` | Transactional outbox + relay worker (Epic 2 P1a) | Implemented |
| `app/Shared/Idempotency/` | Idempotency-key store + middleware (Epic 2 P1a) | Implemented |
| `app/Shared/Webhooks/` | Signed-webhook substrate | New — added by Story RA.3 |
| `app/Shared/{Entitlement,Telemetry,Rules,Versioning,Support}/` | Other cross-cutting concerns | Implemented / scaffold |

### Reliability/Audit home — decision (2026-06-23; corrected by ADR-0010)

A comparative-analysis matrix scored three candidates: (1) sibling module `app/Modules/Reliability/`, (2) a cross-cutting home, (3) spread across existing modules. **Opt 2 won (425 vs 310 vs 280)** — the substrate is *cross-cutting*, not a domain; modelling it as a domain module would weaken the modules-as-domains discipline deptrac is meant to enforce. **Per ADR-0010 the cross-cutting home is the existing `app/Shared/`**, not a new top-level `app/Reliability/`. The original premise that the repo houses substrate at top-level `app/<concern>/` was incorrect — it houses it at `app/Shared/<concern>/`, where Audit/Outbox/Idempotency already live (Epic 2).

**Canonical shape (`App\Shared\*`; Audit/Outbox/Idempotency already exist, Webhooks added by RA.3):**

```
backend/app/Shared/
├─ Audit/                        # Enforcement-layer home for FR-126 (platform-wide audit)
│  ├─ Contracts/AuditEmitter.php
│  ├─ Middleware/RecordAuthDecision.php   # added by RA.2
│  └─ Services/EnforcedAuditWriter.php
├─ Outbox/                       # Transactional outbox (Epic 2 P1a; generalized by RA.4)
│  ├─ Contracts/EventDispatcher.php
│  ├─ Models/OutboxEvent.php
│  └─ Workers/RelayWorker.php
├─ Idempotency/                  # Idempotency-key store + middleware (Epic 2 P1a; generalized by RA.5)
│  ├─ Contracts/IdempotencyStore.php
│  └─ Middleware/IdempotencyMiddleware.php
└─ Webhooks/                     # Signed-webhook substrate (new — RA.3)
   ├─ Contracts/WebhookSigner.php
   ├─ Contracts/WebhookVerifier.php
   └─ Middleware/VerifySignature.php
```

Division of responsibility:
- `app/Modules/Audit/` keeps its existing domain role (audit log query / read API).
- `app/Shared/Audit/` owns the **enforcement** layer — the middleware that makes authorization decisions, identity link / unlink, consent grants, profile imports, and stage outcomes record audit rows by default, closing F-010 + CLAUDE.md "audit-bearing events" baseline.
- `app/Modules/Integrations/StartupGate/` keeps SG-specific code (firebase/php-jwt, trusted-publication payloads). Signed-webhook plumbing comes from `app/Shared/Webhooks/`.

### Deltas this session adds (folders + files to be created in scoping stories)

| Path | Purpose | Carrier story |
|---|---|---|
| `backend/app/Shared/Webhooks/` | Only new sub-area — signed-webhook substrate (Audit/Outbox/Idempotency already exist under `app/Shared/`; see ADR-0010) | Story RA.3 |
| `backend/app/Modules/FinalEvaluation/` | New module — currently absent (F-007); part of P2 | F-007 / P2 epic |
| `backend/app/Modules/Notifications/` | New module — currently absent (F-007); needed before audit/event consumers | F-007 / Reliability/Audit epic |
| `backend/app/Modules/Search/` | New module — currently absent (F-007); P3 | F-007 / P3 |
| `backend/app/Modules/Administration/` | New module — currently absent (F-007); P3 | F-007 / P3 |
| `backend/deptrac.yaml` | Module-boundary enforcement (project-context committed) | Epic 0 hygiene |
| `backend/phpstan/rules/NoFloatInDecimalPaths.php` | Decimal-paths PHPStan rule | Epic 0 hygiene |
| `backend/phpstan/rules/NoNumberFormatOnBigDecimal.php` | brick/math display rule | Epic 0 hygiene |
| `backend/config/decimal-paths.php` | Namespace allowlist for decimal rule | Epic 0 hygiene |
| `backend/tests/Architecture/EventNamingTest.php` | Step-5 P7 enforcement | R/A epic |
| `backend/tests/Architecture/EventVersioningTest.php` | Step-5 P8 enforcement | R/A epic |
| `backend/tests/Architecture/ApiVersioningTest.php` | Step-5 P3 enforcement | R/A epic |
| `backend/tests/Architecture/DatabaseTopologyTest.php` | NFR-015 acceptance test (ADR-0005) | R/A epic |
| `frontend/src/api/__generated__/` | OpenAPI → zod generated client | Frontend Foundation / R/A epic |

### Canonical module skeleton (for new + existing domain modules)

```
backend/app/Modules/<ModuleName>/
├─ Contracts/                    # PUBLIC interfaces other modules import
│  ├─ <Capability>Reader.php     # Read-side contracts
│  └─ <Capability>Writer.php     # Write-side contracts (events or commands)
├─ Services/                     # Private — concrete implementations of contracts
├─ Models/                       # Eloquent — module-private; never imported elsewhere
├─ Http/
│  ├─ Controllers/               # Thin (≤15 lines) — resolve FormRequest, call Service, return Resource
│  ├─ Requests/                  # FormRequest validation
│  └─ Resources/                 # Eloquent API Resources (envelope per Step 5 P5)
├─ Events/                       # Domain events (Step 5 P7 naming)
├─ Listeners/                    # Subscribers; restore tenant context first
├─ Policies/                     # Authorization (deny-by-default)
├─ Database/
│  ├─ Migrations/                # Local to module; aggregated by Laravel
│  └─ Factories/
├─ Providers/
│  └─ <Module>ServiceProvider.php  # Binds Contracts ↔ Services
└─ Tests/                        # Per-module Unit + Feature; Contract tests live in tests/Contract/
```

> Cross-module imports MUST go through `App\Modules\<X>\Contracts\` (project-context § Module boundaries + deptrac enforcement). Concrete `Services\` and `Models\` are module-private.

### Integration Boundaries

#### API boundary (HTTP)
- `backend/routes/api.php` — versioned (`/api/v1/...`); Sanctum SPA cookie or API token; server-side authorization at the Policy layer (deny-by-default).
- `backend/openapi/` — Scramble-emitted; Spectral-linted in CI. Frontend codegen reads `storage/api-docs/openapi.yaml`.

#### Module boundary (in-process)
- **Read:** call `App\Modules\<X>\Contracts\<...>Reader` via DI; never reach into `Services\` or `Models\`.
- **Write:** dispatch a domain event (preferred) or call a documented command in `Contracts\<...>Writer`.
- **Enforced by deptrac** (`backend/deptrac.yaml`, to be created — Epic 0 hygiene).

#### Tenancy boundary (data)
- `app/Tenancy/Middleware/SetTenantContext.php` resolves `tenant_id` per request and per job; `BelongsToTenant` trait fails closed on writes without resolved context (ADR-0005 + project-context § Tenant isolation).
- Cross-tenant access returns **404** (FR-004; auto-memory § 5). No 403 across tenants.

#### Identity boundary (external)
- `App\Modules\Integrations\StartupGate\` is the only home for `firebase/php-jwt` decoding (project-context § Identity & email). All OIDC verification flows through the shared Identity verifier.

#### Storage boundary
- `App\Storage\` owns Flysystem; modules call `Storage::disk('s3-tenant-{id}')`. Direct `AwsS3V3Adapter` instantiation forbidden outside `app/Storage/`.

#### Reliability boundary (new)
- `App\Reliability\Audit\` is the only home for audit-enforcement middleware; domain modules emit via `Contracts\AuditEmitter`, never write audit rows directly.
- `App\Reliability\Outbox\` is the only home for the outbox table + relay worker; modules dispatch via `Contracts\EventDispatcher`.
- `App\Reliability\Idempotency\` is the only home for the idempotency-key store; controllers attach the middleware, never roll their own key handling.
- `App\Reliability\Webhooks\` is the only home for inbound/outbound signature handling (Geidea callbacks, future webhook subscribers, SG trusted-publication outbound).

#### Frontend ↔ backend boundary
- Single contract source = OpenAPI emitted from controllers via Scramble.
- Frontend zod schemas are **generated** from that spec (deferred Step 5 decision; story to be filed).
- No frontend-side state assumptions about server response shape outside the generated client.

## Architecture Validation Results (2026-06-23)

### Coherence Validation ✅

**Decision Compatibility:**
- AD-1 (Catalesta-as-Identity-SoR, ADR-0004) and AD-2 (Single-DB row-level, ADR-0005) reinforce each other — recorded in § Decision Impact Analysis. No contradiction.
- ADR-0004 supersedes ADR-0002 (Status flipped to Superseded with forward-reference). No live ADR conflict remains in `adr/`.
- ADR-0001 (modular monolith) and ADR-0003 (mocked OIDC first) compatible with AD-1 and AD-2; the OIDC mock path under `services/startup-gate-mock/` becomes "optional linked provider mock" under the inverted ownership model.
- Patterns Step 5 P1–P8 + project-context.md form a non-conflicting set; project-context.md is the canonical pattern home and Step 5 explicitly records only what project-context did not yet pin.

**Pattern Consistency:**
- Step 5 P3 (`/api/v1/...` + `{param}`) matches existing `backend/routes/api.php` Laravel idiom.
- Step 5 P4 (snake_case wire) matches DB columns (P2), Scramble emission default, and the zod-codegen plan.
- Step 5 P5 (envelope) matches Eloquent API Resource defaults; no controller rewrite implied.
- Step 5 P6 (error envelope) matches Laravel validator default + FR-004's 404-cross-tenant rule (auto-memory § 5).
- Step 5 P7 (event naming) maps onto PRD FR-080 taxonomy with a module prefix added.
- Step 5 P8 (event versioning) addresses Reporting's downstream consumer durability.

**Structure Alignment:**
- Step 6 Reliability home (Opt 2: cross-cutting substrate) honors the existing precedent (`app/Shared/Tenancy/`, `app/Shared/Storage/`) and preserves the modules-as-domains discipline that deptrac is being set up to enforce. **Per ADR-0010 the home is the existing `app/Shared/`, not a new `app/Reliability/`.**
- Canonical module skeleton matches the 20 existing scaffolded modules; no relocation churn introduced.
- Boundary contracts (`App\Modules\<X>\Contracts\`) align with the cross-module access rule in project-context § Module boundaries.

### Requirements Coverage Validation

**Epic / Story Coverage:**
- Epic 1 (Identity / Tenancy / Programs / Cohorts / Stages) ✅ — architecturally complete; existing modules carry it.
- Epic 2 (Applications / Idempotency / Outbox / Audit substrate) ✅ — architecturally complete; existing modules carry it.
- Epic 3 (Assessment / Scoring / Decision) ✅ — `Assessments` module scaffold has architectural home; decimal rules + immutability invariants in place; awaiting implementation.
- Epic 4 / SP-1..SP-4 (Identity inversion delivery) ✅ — ADR-0004 + project-context § Identity & email + PRD FR-007..009 / FR-157 carry the architectural shape. SP-1 in production.
- Reliability/Audit epic ⚠ — architecturally named (Opt 2 home + 4 sub-areas) but **not yet scoped into stories**. Not a coherence gap; tracked as follow-up.

**Functional Requirements Coverage:**
- All 67 PRD FRs bucketed in PRD §6.13: 22 Existing-and-verified, 1 Existing-but-incomplete, 10 Required-for-initial-release, 34 Required-for-later-release.
- Of the 34 Required-for-later-release FRs, all live in capability-level §6.10–§6.12 with PRD phase tags.
- FR-126 (platform-wide audit) reclassified to Reliability/Audit epic; architectural home defined.
- FR-030 `[ASSUMPTION-CONFIRM]` resolved-by-shipping 2026-06-23.

**Non-Functional Requirements Coverage:**
- NFR-001 (Tenant isolation): `BelongsToTenant` + 404-not-403 + ADR-0005 row-level. ✅
- NFR-002 (Identity integrity): ADR-0004 + project-context § Identity & email. ✅
- NFR-003 (Immutability / versioning): existing rule kernel + `Stages` module; FR-031 snapshot pattern. ✅
- NFR-004 (Decimal): brick/math everywhere; project-context § Decimal & money; custom PHPStan rule pending. ⚠ (rule story not yet filed)
- NFR-005 (No arbitrary code in rules): project-context § Deserialization & template safety. ✅
- NFR-006 (Consent-aware reads): pending SP-4 — gap on the *local* consent side.
- NFR-007 (Payment integrity): P1b — out of scope for this architecture pass.
- NFR-008 (Data-respecting limits): FR-062 UX banner pending; enforcement deferred to P1b. ⚠
- NFR-009 (Security baseline): project-context § Logging + Timing-safe + Rate limiting; rotation policy TBD via OQ8.
- NFR-010 (Availability / DR): RPO/RTO still `[Proposed]`; ratification = OQ8.
- NFR-011 (Localization): addendum A3 ("MENA-native, not MENA-translated") flags qualitative gap.
- NFR-012 (Observability): audit enforcement moves to `app/Reliability/Audit/` per AD-1 implication; story pending.
- NFR-013 (Data governance): residency = OQ4; retention = OQ8 (per (q) split).
- NFR-014 (Performance): still `[Proposed]` per OQ8.
- NFR-015 (Database topology, new): acceptance test commitment in ADR-0005; story pending in Reliability/Audit epic.

### Implementation Readiness Validation

**Decision Completeness:**
- Critical decisions documented with versions: PHP ^8.3, Laravel ^13.8, React 19, TS 6, Vite 8, vitest 4, Playwright 1.61 (project-context § Tech stack). ✅
- 5 architecture decisions in `adr/` (ADR-0001 modular monolith; ADR-0002 superseded; ADR-0003 mocked OIDC; ADR-0004 identity inversion; ADR-0005 DB topology). 3 auto-memory decisions still lack ADRs (cohort naming § 1; 24-module scope § 2; repo layout § 3). ⚠ (audit F-005 partial)

**Structure Completeness:**
- Brownfield structure documented (20 modules + 3 cross-cutting); canonical skeleton defined; 5 new directories named for follow-up creation (4 absent modules + Reliability home). ✅
- Module-to-FR/Epic mapping complete for P1a + Epic 3 + Epic 4; P2/P3/P4 capability-level mapping at FR-cluster granularity.

**Pattern Completeness:**
- Step 5 P1–P8 + project-context.md 18 subsections = pattern canon. ✅
- Frontend patterns deferred to `bmad-ux` / `frontend-design` pass. ⚠ (acknowledged gap)
- OpenAPI → zod codegen decided (generate, not hand-author); pipeline story pending. ⚠

### Gap Analysis Results

**Critical Gaps (block implementation of named work):** *None.* No critical blocker for continuing Epic 3 + Epic 4 implementation. Reliability/Audit epic blocked on its own scoping story (expected).

**Important Gaps:**
- **IG1** — 3 auto-memory decisions (cohort naming, 24-module scope, repo layout) still without ADRs (F-005 partial). Story: Epic 0 hygiene.
- **IG2** — Cat 3 (API design beyond patterns), Cat 4 (Frontend), Cat 5 (Infrastructure / Deployment) explicitly deferred from Step 4. Future `bmad-create-architecture` passes or `bmad-ux` pass.
- **IG3** — Reliability/Audit epic scoping story not yet filed. Carries Opt 2 home + the 4 sub-areas + NFR-015 arch test + Step 5 P7/P8 enforcement tests.
- **IG4** — 4 absent modules (FinalEvaluation / Notifications / Search / Administration) named but not scaffolded.
- **IG5** — OQ4 (residency region), OQ6 (signed design partner — Phase 1a entry gate), OQ8 (NFR ratification) open. Strategy / commercial, not engineering, but affect architecture readiness for Phase 1a.

**Nice-to-Have Gaps:**
- **NH1** — Step 5 patterns could ship more concrete code examples per rule.
- **NH2** — Frontend pattern subsection (when reached) should commit to the react-query default direction tentatively named.
- **NH3** — Status doc refresh (F-003 — non-architecture but adjacent).

### Validation Issues Addressed in This Session

- **F-001** ADR-0002 supersession — *resolved* (ADR-0004 written; ADR-0002 flipped to Superseded).
- **F-002** `docs/project-context.md` missing — *resolved* (file created with PHP/Laravel rules + Database Topology section).
- **F-005** (2 of 4 items) — *resolved* (ADR-0004 + ADR-0005 written; auto-memory § 1/§ 2/§ 3 ADRs still pending).
- **F-007** (4 absent modules) — *named* in Step 6 deltas; *not created*.
- **F-009 + F-010** (Reliability/Audit epic carve-out) — *named* via PRD §7 R/A row + Step 6 cross-cutting home; *not scoped*.

### Architecture Completeness Checklist

**Requirements Analysis**
- [x] Project context thoroughly analyzed
- [x] Scale and complexity assessed
- [x] Technical constraints identified
- [x] Cross-cutting concerns mapped

**Architectural Decisions**
- [x] Critical decisions documented with versions
- [x] Technology stack fully specified
- [x] Integration patterns defined
- [ ] Performance considerations addressed *(NFR-014 still `[Proposed]`; load model pending OQ8)*

**Implementation Patterns**
- [x] Naming conventions established
- [x] Structure patterns defined
- [x] Communication patterns specified
- [x] Process patterns documented

**Project Structure**
- [x] Complete directory structure defined
- [x] Component boundaries established
- [x] Integration points mapped
- [x] Requirements to structure mapping complete

**Tally: 15 of 16 checked.** The unchecked item (Performance) traces to OQ8, not an architectural omission.

### Architecture Readiness Assessment

**Overall Status:** **READY WITH MINOR GAPS** — 15/16 checklist items checked; remaining gap is OQ8 NFR ratification (PM-owned, tracked); no Critical Gaps; 5 Important Gaps tracked with story homes. Suitable for continuing Epic 3 + Epic 4 implementation; Reliability/Audit epic and 4 absent modules require their own scoping stories before their implementation can begin.

**Confidence Level:** **High** for the slice covered (brownfield + identity + DB topology + patterns + structure). **Medium** for the deferred slices (Cat 3 API beyond patterns, Cat 4 Frontend, Cat 5 Infrastructure / Deployment) — they have known homes and clear deferral conditions.

**Key Strengths:**
- AD-1 + AD-2 reinforce each other; ADR record now coherent.
- `docs/project-context.md` + Step 5 + Step 6 form a complete-enough pattern + structure canon for AI agents implementing the next stories.
- Cross-cutting Reliability home (Opt 2) preserves modules-as-domains discipline and matches existing precedent — discoverability + boundary integrity simultaneously high.
- Reviewer-validated decision discipline (decision-log audit trail; retroactive silent-edit log already closed).

**Areas for Future Enhancement:**
- ADRs for auto-memory § 1/§ 2/§ 3 (cohort naming, 24-module scope, repo layout) — Epic 0 hygiene.
- Step-4 Cat 3/4/5 architectural decisions — follow-up `bmad-create-architecture` passes or domain-specific skills (`bmad-ux` for frontend).
- Reliability/Audit epic scoping — owns IG3 + part of IG4 + NFR-015 test.

### Implementation Handoff

**AI Agent Guidelines:**
- Follow `docs/project-context.md` § PHP/Laravel rules (18 subsections) as the canonical pattern reference for backend code.
- Follow ADR-0004 + ADR-0005 for identity and database topology decisions.
- Follow Step 6 module skeleton + cross-cutting boundaries for new code locations.
- New tenant-owned tables MUST use `BelongsToTenant` + `organization_id` + ULID PK; cross-module access MUST go via `App\Modules\<X>\Contracts\`.
- For ambiguous situations not covered here: cite the specific document + section; if no document covers it, surface the gap as a `[NEEDS USER CALL]` rather than silently picking.

**First Implementation Priority:**
- Continue **Epic 3 (Assessment / Scoring / Decision)** — Stories 3.1, 3.2, 3.3 → FR-040, FR-041, FR-042, FR-043, FR-081. All architecturally supported; assessment module scaffold ready; decimal rules + immutability invariants in place.
- Continue **Epic 4 / SP-2, SP-3, SP-4** — FR-008 (SG link), FR-009 (consented import), 7 role-profile types. ADR-0004 carries the shape; project-context § Identity & email carries the rules.
- File **Reliability/Audit epic scoping story** before any of its IG3 implementation begins.
