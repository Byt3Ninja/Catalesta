---
stepsCompleted: [1, 2, 3]
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
- Reuse (built): Identity/`sub` auth (mock), Organizations/tenancy/RBAC, Programs, Cohorts, Stages, versioning/immutability, decimal rule kernel.
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
- External integrations behind interfaces only: Startup Gate OIDC (mock in P1a → real FR-157), Geidea (sandbox, no real charge in P1a).
- Content-addressed file storage introduced by FR-031 (blob store dependency).
- Non-negotiables (CLAUDE.md): organization_id on every tenant row; `sub` not email; no raw card/CVV; published artifacts immutable.

### Cross-Cutting Concerns Identified
Tenant isolation · immutability/versioning · enforced audit · transactional outbox + idempotency · entitlement seam · consent-aware access · i18n/RTL + a11y floor · observability/correlation IDs · payment-provider isolation.

## Foundation (brownfield — established stack)

Full-stack; **brownfield, no starter selected**. Versions pinned by repo.
- **Backend:** PHP 8.3.31, Laravel 13.8, Sanctum 4, `firebase/php-jwt` 7 (JWKS), `brick/math` 0.17 (decimal). Modular monolith `app/Modules/*` + `app/Shared/*`.
- **Frontend:** React 19.2, TS 6 strict, Vite 8, React Query 5, Zod 4; Vitest 4 + Playwright 1.61.
- **Infra (docker-compose, provisioned):** postgres, redis, queue-worker, scheduler, minio (S3), startup-gate-mock, mailpit, nginx.
- **Already fixed:** fail-closed `BelongsToTenant`; decimal kernel; versioning/immutability kernel; Sanctum SPA + JWKS-OIDC. No init story needed.

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
