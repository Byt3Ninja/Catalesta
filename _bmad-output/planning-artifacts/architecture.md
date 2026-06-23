---
stepsCompleted: [1, 2, 3, 4, 5]
lastStep: 5
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
