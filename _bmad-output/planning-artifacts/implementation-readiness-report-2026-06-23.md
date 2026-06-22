---
stepsCompleted:
  - step-01-document-discovery
  - step-02-prd-analysis
  - step-03-epic-coverage-validation
  - step-04-ux-alignment
  - step-05-epic-quality-review
  - step-06-final-assessment
assessor: John (BMM Product Manager) via bmad-check-implementation-readiness
groundTruthCommit: b57d478 (main, prior to session edits; +6 PRs landed during session: 30, 31, 32, 33, 34, 35, 36, 37)
documentsUnderAssessment:
  prd: _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
  architecture: _bmad-output/planning-artifacts/architecture.md
  epics: _bmad-output/planning-artifacts/epics.md
  ux:
    - _bmad-output/planning-artifacts/ux-designs/ux-Catalesta-2026-06-20/DESIGN.md
    - _bmad-output/planning-artifacts/ux-designs/ux-Catalesta-2026-06-20/EXPERIENCE.md
  priorReport: _bmad-output/planning-artifacts/implementation-readiness-report-2026-06-20.md
duplicatesFound: false
missingDocuments: []
---

# Implementation Readiness Assessment Report

**Date:** 2026-06-23
**Project:** Catalesta

## Document Inventory

### PRD (sharded)
**Folder:** `_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/`
- `prd.md` (30 KB, modified 2026-06-22) — primary PRD
- `.decision-log.md` (7 KB) — supporting governance
- `review-adversarial.md`, `review-rubric.md`, `validation-report.md`, `validation-report.html` — review artifacts

### Architecture (whole)
- `_bmad-output/planning-artifacts/architecture.md` (7 KB, modified 2026-06-22)

### Epics & Stories (whole)
- `_bmad-output/planning-artifacts/epics.md` (40 KB, modified 2026-06-22)

### UX Design (sharded)
**Folder:** `_bmad-output/planning-artifacts/ux-designs/ux-Catalesta-2026-06-20/`
- `DESIGN.md` (9 KB) — primary design doc
- `EXPERIENCE.md` (16 KB) — experience flows
- `.decision-log.md` (3 KB)
- `review-accessibility.md`, `review-rubric.md` — review artifacts
- `mockups/`, `imports/`, `.working/` — supporting assets

### Prior Reports
- `implementation-readiness-report-2026-06-20.md` (24 KB) — earlier IR run, for delta reference

## Discovery Outcomes

- **Duplicates found:** None. Each doc type has exactly one canonical source (PRD sharded, Architecture whole, Epics whole, UX sharded).
- **Missing documents:** None of PRD / Architecture / Epics / UX is missing.
- **Critical issues blocking assessment:** None.

## PRD Analysis

PRD source: `prds/prd-Catalesta-2026-06-20/prd.md` (status: final · grade: Good · open items: OQ1–OQ9). Read in full. Requirements extracted verbatim by ID; full text lives in the PRD section noted in parentheses.

### Functional Requirements

**§6.1 Identity, Tenancy & Access — [P1a]**

- **FR-001** A user authenticates with a native Catalesta account (email + password) or, optionally, via a linked identity provider; the immutable Account id (ULID) is the user key and email is a local login credential, never a cross-system identifier. Sessions use Sanctum SPA cookie-session transport. (Epic 4 / SP-1–SP-2; Epic-1 SG-OIDC-mock path superseded.)
- **FR-002** Signing up creates an Organization (tenant); the creating user becomes its admin.
- **FR-003** Every tenant-owned record carries `organization_id`, server-set (never client-supplied/mass-assignable).
- **FR-004** Every tenant query is isolation-enforced fail-closed: an unresolved tenant returns no rows; cross-tenant access returns 404.
- **FR-005** RBAC scopes permissions per organization (operator, evaluator, applicant, …).
- **FR-006** Profile reads are consent-aware, **including locally-owned profiles**; the `ConsentProvider` interface is enforced at every profile-read call site. (CLAUDE #11.)
- **FR-007** A user can register a native account (email + password), verify email, reset a forgotten password, and manage their session — no Startup Gate dependency. (Epic 4 / SP-1.)
- **FR-008** A user can link an optional Startup Gate identity to their Catalesta account and sign in with it, or unlink it; account remains usable after unlink. `sub` stored on the link, not the account. (Epic 4 / SP-2.)
- **FR-009** A user can import selected profile fields from Startup Gate after explicit field-level consent; imported data is a local editable copy with per-field source tracking, import history, conflict preview, and never auto-overwrites locally modified fields; consent is revocable. (Epic 4 / SP-4.)

**§6.2 Program & Cohort Configuration — [P1a]**

- **FR-010** An operator creates a Program and publishes it.
- **FR-011** An operator opens a Cohort on a program with an enrollment window; submissions accepted only while open (FR-033). The enrollment window is a property of the cohort.
- **FR-012** Published stage/version artifacts are immutable and versioned; edits create new versions.
- **FR-013** Programs can be cloned and saved/instantiated as templates. A clone copies the published version of each artifact and starts a fresh draft.

**§6.3 Application Form (minimal) — [P1a]**

- **FR-020** An operator attaches one published, immutable application form to a cohort. **Phase-1a field set (enumerated):** short text, long text, single-select, multi-select, number, date, file upload, consent/acknowledgement checkbox. Conditional logic, calculated fields, full builder = P3 (FR-127).
- **FR-021** The published form is reachable at a public, mobile-web application URL for the cohort.
- **FR-022** No arbitrary code executes in form logic; field definitions are declarative data only.

**§6.4 Application Management — [P1a]**

- **FR-030** An authenticated applicant submits an application bound to a Cohort. [ASSUMPTION-CONFIRM: cohort-binding.]
- **FR-031** On submission the system captures an immutable snapshot containing: submitted answer values, uploaded file references (content-addressed), and the version IDs of the form, program, and rubric in effect.
- **FR-032** Submission is idempotent: a duplicate submit with the same `Idempotency-Key` returns the original result.
- **FR-033** Submission against an unpublished or closed cohort is rejected (422).
- **FR-034** An operator views the tenant-scoped list of submissions for a cohort.

**§6.5 Assessment / Selection — [P1a]**

- **FR-040** An operator scores a submission against the cohort's published rubric using **decimal arithmetic**: scores `DECIMAL(6,2)` [ASSUMPTION precision], half-up rounding, ties broken by rubric's declared tie-break order (else earliest submission time). No floats in any score path (NFR-004).
- **FR-041** Scores and the rubric version are recorded immutably with the scorer's `sub` (auditable).
- **FR-042** An operator records an accept/reject decision per applicant; decisions audited (FR-052). Reopening a recorded decision is itself audited (FR-081 / C2).
- **FR-043** An operator exports the decision list (CSV) for a cohort.

**§6.6 Reliability Substrate — [P1a]**

- **FR-050** A transactional outbox: domain events written in the same DB transaction as the state change; a relay delivers them at-least-once to consumers (must be idempotent); failed deliveries retry with exponential backoff (cap 6 attempts [ASSUMPTION]) and land in a dead-letter store. P1a wires one consumer ("application submitted → notification", log transport). Ordering is per-aggregate, not global.
- **FR-051** Idempotency enforced on application submission and the payment-callback endpoint (callback handler ships P1b; primitive is payment-agnostic and shipped P1a).
- **FR-052** Audit records written for the enumerated P1a set: `program.published`, `cohort.opened/closed`, `application.submitted`, `submission.scored`, `decision.recorded`, `decision.reopened`, `decisions.exported`. Audit completeness for this set is acceptance-testable.

**§6.7 Entitlement Seam — [P1a] socket / [P1b] counter**

- **FR-060** [P1a] Domain modules check entitlements only via `EntitlementService` — never by inspecting plan names. P1a ships the interface (socket) returning allow-all; enforced call sites: `program.publish`, `cohort.open`, `application.submit`. Architecture-test asserted.
- **FR-061** [P1b] Metering counter — the real limit policy. [ASSUMPTION + OQ3] one real counter = `active_programs` per organization, plus boolean feature flags. Not built until OQ3 ratified.
- **FR-062** Reaching a limit never deletes or hides existing tenant data: in-product banner; write actions exceeding the limit are blocked; reads and exports remain available. (Note: PRD §10 carries a finding that limit-blocking is properly P1b, not P1a as the §6.7 tag implies.)

**§6.8 Billing Seam — [P1b]** (gated on World-A; §7)

- **FR-070** Signature-verified, idempotent callback **primitive** is built provider-agnostically in P1a as part of FR-051. The `PaymentProvider` interface stub itself is deferred to P1b.
- **FR-071** [P1b] Integrate Geidea sandbox end-to-end behind `PaymentProvider`. No real money charged in P1a/P1b sandbox; first partners run free.
- **FR-072** [P1b] Payment callbacks signature-verified and processed idempotently; browser returns never authoritative.
- **FR-073** [P1b] No raw card numbers or CVV are ever stored.

**§6.9 Instrumentation (learning) — [P1a]**

- **FR-080** System records a defined event taxonomy: `application.viewed`, `application.started`, `application.abandoned{step}` (→ C3), `application.submitted`, `submission.scored{elapsed}`, `rubric.edited{cohort,phase}`, `decision.recorded{time_to_decision}` (→ M3), `decisions.exported`, plus a session signal for export-then-leave (export followed by no further in-product action within 24 h [ASSUMPTION]). Events tenant-scoped and queryable for the World-A/B band.
- **FR-081** Dispute/reopen is a first-class audited event feeding C2 (see FR-042/FR-052).

**§6.10 Substrate Generalization + Delivery Core — [P2]** (capability-level)

- **FR-100** Documents · **FR-101** Workflow engine (declarative) · **FR-102** Role eligibility & assignments · **FR-103** Tasks & milestones · **FR-104** Mentorship · **FR-105** Training · **FR-106** Final evaluation · **FR-107** Graduation, alumni & follow-up · **FR-108** Personalized tracks. Plus: generalize the P1a substrate — multi-consumer outbox ordering/replay, idempotency across new write paths, audited-action set extension.

**§6.11 Platform Services & Production Commercial Plane — [P3]** (capability-level)

- **FR-120** Notifications · **FR-121** Calendar integrations · **FR-122** Reporting/dashboards · **FR-123** Search/directories · **FR-124** Admin/config · **FR-125** Public API/webhooks · **FR-126** Audit enforced platform-wide · **FR-127** Full form builder (conditional logic, calculated fields).
- **FR-130** Production subscription billing: versioned immutable plans, trials, dunning, upgrades/downgrades/add-ons, Geidea recurring + Hosted Payment Page (real charging). **FR-131** Usage metering across the full dimension set. **FR-132** Subdomains + verified custom domains with automatic TLS. **FR-133** Branding/white-label (controlled tokens only; no arbitrary CSS/JS).

**§6.12 Extended Capabilities & Production Cutover — [P4]** (capability-level)

- **FR-150** Interviews/public pages/waitlists · **FR-151** Partners/finance/timesheets · **FR-152** Service marketplace/messaging (non-transactional at MVP) · **FR-153** Surveys/hackathons/knowledge · **FR-154** Simulation/outcomes/risk · **FR-155** Bulk ops/version migration · **FR-156** *(splits before P4 planning into:)* localization hardening, security hardening, observability, data migration/import-export, performance/production-readiness — each becomes its own FR · **FR-157** Startup Gate as optional linked SSO provider + consented profile import (no authority cutover) · **FR-158** Admin impersonation with full audit · **FR-159** Tenant offboarding end-to-end + DR.

**Total FRs:** 67 enumerated IDs across §6.1–§6.12.
- Phase-1a detailed (with full text): **25** (FR-001..FR-013, FR-020..FR-022, FR-030..FR-034, FR-040..FR-043, FR-050..FR-052, FR-060, FR-070 primitive, FR-080..FR-081)
- Phase-1b detailed: **4** (FR-061, FR-070 interface, FR-071..FR-073)  *(FR-062 carries a phase-tag-ambiguity finding — see OQ-carry below.)*
- Phase-2/3/4 capability-level: **38** (FR-100..FR-108, FR-120..FR-133, FR-150..FR-159)

### Non-Functional Requirements

- **NFR-001 Tenant isolation** — fail-closed; `BelongsToTenant`; architecture test asserts the trait on every tenant-owned model. (C1 = 0 incidents.)
- **NFR-002 Identity integrity** — Account id (ULID) is the primary user identifier; an SG `sub`, when linked, is the immutable key of that external identity only. Email never identifies across systems.
- **NFR-003 Immutability & versioning** — published forms/stages/assessments/workflows cannot mutate; new versions only; formal submissions capture immutable snapshots (FR-031).
- **NFR-004 Decimal arithmetic** — all scoring uses `DECIMAL` math with the precision/rounding in FR-040; no floats in money/score paths.
- **NFR-005 No arbitrary code in rules** — rule/form/expression definitions are declarative data. Acceptance test: validator rejects any definition whose nodes are not in the allowed field/operator set.
- **NFR-006 Consent-aware access** — all profile reads (including locally-owned profiles) enforce consent state via `ConsentProvider`; importing any field from Startup Gate requires explicit field-level consent.
- **NFR-007 Payment integrity** — provider-interface isolation; verified, idempotent callbacks; no raw card/CVV; browser returns non-authoritative.
- **NFR-008 Data-respecting limits** — hitting a usage limit never deletes/hides tenant data (FR-062).
- **NFR-009 Security baseline** — secrets never committed; signing-key rotation ≤ 90 days, provider-API-key rotation ≤ 180 days or on incident; least-privilege; rate limiting: ≤ 60 req/min/IP on public application endpoint, ≤ 600 req/min/tenant on authenticated APIs. [ASSUMPTION ceilings.]
- **NFR-010 Availability & DR** — RPO ≤ 15 min, RTO ≤ 4 h [Proposed]; automated daily full + continuous WAL backup; tested restore runbook. Single-region at P1.
- **NFR-011 Localization & accessibility** — Phase 1a renders Arabic + RTL for UJ-2 and the core operator screens; full bilingual coverage + WCAG 2.2 AA hardening is P4 (FR-156). "Renders RTL," not "does not preclude."
- **NFR-012 Observability** — structured logging, metrics, correlation IDs; audit is enforced (FR-052), not opt-in.
- **NFR-013 Data governance** — Egypt PDPL baseline + GDPR-grade DSR rights. Residency region must be decided before the first pilot (OQ4), not at P3. Retention values per `product/data-residency-retention.md` [Proposed].
- **NFR-014 Performance** — p95 < 500 ms for core operator reads (FR-034, FR-040) and public form load (FR-021), measured at 1,000 active cohorts / 100k applications / 50 concurrent operators [ASSUMPTION load model]; budget ratified before P1a exit.

**Total NFRs:** 14.

### Additional Requirements & Constraints

**Phased delivery (§7):**

| Phase | Theme | FRs | Gate |
|---|---|---|---|
| 1a | Selection MVP — instrument-first, no billing | FR-001..052, 060 (socket), 070 (primitive), 080–081 | One operator runs intake end-to-end; substrate primitives real; World-A/B band evaluable |
| 1b | Billing seam | FR-061, 071–073 | **Entry gate: World-A confirmed (§3 band) AND OQ3 ratified.** Geidea sandbox e2e; `active_programs` counter; no real charge |
| 2 | Substrate generalization + delivery core | FR-100..108 | Participant lifecycle (mentorship→graduation) runnable |
| 3 | Platform services + production commercial plane | FR-120..133 | Tenant pays via production Geidea; reporting, notifications, full forms |
| 4 | Extended capabilities + production cutover | FR-150..159 | Extended modules; optional SG SSO + import; DR/offboarding/impersonation production-ready |

**Slice depth (P1a):** outbox = table + transactional write + at-least-once relay + one idempotent consumer + dead-letter (FR-050); idempotency = key table + middleware on the two named endpoints (FR-051); audit = enumerated set (FR-052); entitlement = interface + three call sites, allow-all (FR-060).

**Open questions (PRD §10):**

| # | Question | Owner | Resolution gate |
|---|---|---|---|
| OQ1 | World A vs B | PM | After ≥2 cohorts/≥2 operators (§3 band) — **gates Phase 1b** |
| OQ2 | Ratify M1–M5 targets | PM | First-partner data — **gates GTM** |
| OQ3 | Metering dimensions beyond `active_programs` + pricing tiers | PM/Founder | **Gates Phase 1b (FR-061), P3 (FR-130/131)** |
| OQ4 | Data-residency region + retention values | Founder/Legal | Before first pilot — **gates onboarding** |
| OQ5 | Beachhead ICP | Founder | Before Phase-1a design-partner outreach |
| OQ6 | Named design partner(s) + acquisition plan | Founder | This week; blocks OQ1/OQ2 chain |
| OQ7 | World-B monetization fallback (High) | Founder/PM | At World-A/B decision (post-P1a) |
| OQ8 | Ratify NFR-010 / NFR-013 / NFR-014 + M3 baseline (High) | PM (+ Founder/Legal for residency) | Before P1a exit review |
| OQ9 | Roadmap reconciliation — `docs/plan/roadmap.md` still says Phase 1 = "Selection MVP + billing" as one unit (Medium, SSOT) | PM | Next roadmap edit |

**Carried mediums/lows (from validation-report.md):** phase-tag FR-062 (limit-blocking is 1b); add test predicates for NFR-011 RTL / §7 "band evaluable" / FR-080 completeness / 1a payment-callback contract; right-size 1a NFRs (drop 100k-app perf + provider-key rotation until partner/key exists); confirm FR-030 cohort-binding + FR-040 precision before 1a schema freeze.

**Out of scope (all phases):** native mobile apps; offline mode; AI/LLM features; marketplace payments/settlement; multi-region active-active.

### PRD Completeness Assessment

**Strengths:**
- Every FR is numbered, phase-tagged, and traceable. ID block-allocation scheme (with reserved gaps) is documented (header note).
- Each NFR has an acceptance hook (an explicit test predicate or a referenced FR).
- Phase gates are concrete, not aspirational (World-A/B band §3 is falsifiable; OQ3 ratification block on Phase 1b).
- Open questions carry owner + resolution gate, not just "TBD".
- Glossary at §0 + Data-ownership map at §9 reduces interpretation drift in downstream docs.

**Gaps / risks for coverage validation in later steps:**
- **OQ9 (SSOT divergence):** PRD says Phase 1 = "1a then 1b"; roadmap may still say "Selection MVP + billing." Step 3 (epic coverage) will need to compare epic→FR mapping against the PRD's phasing, not the roadmap's, until OQ9 is resolved.
- **FR-062 phase-tag** ambiguity: §6.7 tags `FR-062` as P1a; §10 carries a finding that limit-blocking is properly P1b. Epic coverage check should accept either tagging but flag the ambiguity if epics treat FR-062 as in-scope for P1a.
- **Capability-level FRs (P2–P4):** FR-100..159 are deliberately capability-level — full detail in `docs/product/scope-register.md`, not the PRD. Step 3 coverage cannot demand line-item epics for these; the bar is "at least one epic per FR cluster with a deeper-detail pointer."
- **NFR acceptance evidence:** several NFRs (NFR-010 RPO/RTO, NFR-014 perf load model) are `[Proposed]` or `[ASSUMPTION]` — OQ8 owns ratifying them before P1a exit. Epic coverage should not enforce these as exit gates while OQ8 is open.

## Epic Coverage Validation

Epics source: `_bmad-output/planning-artifacts/epics.md` (frontmatter: `status: complete · scope: Phase 1a (Selection MVP)`). Full file (465 lines) read.

### Epic structure

- **Epic 1: Stand Up an Intake** — stories 1.0..1.5 (1.0 = frontend foundation, 1.1..1.5 = features)
- **Epic 2: Receive Applications** — stories 2.1..2.5 (E2.0 reliability gate) + 2.6..2.8 (user-facing flow)
- **Epic 3: Score & Decide** — stories 3.1..3.3 (gated on Epic 2 evidence; sequenced AFTER Epic 4)
- **Epic 4: Standalone Identity, Accounts & Profiles** — sub-projects SP-1..SP-4 (no story-level breakdown in epics.md; tracked separately under `docs/superpowers/specs/`)
- **Cross-cutting deliverable: Learning Telemetry** — DoD-gated; not a separate build epic

The epics doc explicitly declares its scope as Phase 1a, so P1b / P2 / P3 / P4 FRs are absent **by design** and counted as "correctly deferred," not "missing."

### Epic FR coverage extracted (from epics.md §FR Coverage Map + per-epic claims)

- **FR-001, FR-003, FR-004** → Reused foundation (Identity/tenancy), verified per-table via AR-6 isolation tests. *(SG-OIDC-mock framing of FR-001 is superseded by Epic 4 / SP-1; impact ledger noted in epics.md lines 145.)*
- **FR-002, FR-005, FR-006, FR-010, FR-011, FR-012, FR-013, FR-020, FR-021, FR-022, FR-060** → Epic 1
- **FR-030, FR-031, FR-032, FR-033, FR-034, FR-050, FR-051, FR-052** → Epic 2
- **FR-040, FR-041, FR-042, FR-043, FR-081** → Epic 3
- **FR-080** → Cross-cutting Learning Telemetry (Epics 2 + 3)
- **FR-007, FR-008, FR-009** → Epic 4 (SP-1, SP-2, SP-4)
- **FR-062** → UX surface (limit banner) designed in Epic 1 via UX-DR3; enforcement deferred to P1b
- **FR-070** → Primitive built consumer-agnostic in Epic 2 / Story 2.2; `PaymentProvider` interface stub deferred to P1b billing epic

### Story-level FR mapping (per-story claims in epics.md)

| Story | Primary FRs | Notes |
|---|---|---|
| 1.0 Frontend foundation | UX-DR1, UX-DR2, UX-DR6 | No direct FR; substrate for all later stories |
| 1.1 Sign up + create org | FR-002, FR-005 | + AR-6 isolation; FR-001 reused (auth) |
| 1.2 Create + publish program | FR-010, FR-012, FR-013, FR-060 | publish gated through EntitlementService |
| 1.3 Build + publish form | FR-020, FR-012, FR-022 | + NFR-005 declarative-only acceptance |
| 1.4 Open/close cohort + URL | FR-011, FR-021, FR-060 | cohort.open gated; closed-422 asserted in Epic 2 |
| 1.5 Operator Home + consent | FR-006 | + NFR-006; consent against the SG mock (superseded by SP-4) |
| 2.1 Content-addressed blobs | AR-5 | substrate; no FR (enables FR-031 file refs) |
| 2.2 Idempotency primitive | FR-051, AR-2 | consumer-agnostic; satisfies P1a piece of FR-070 |
| 2.3 Outbox table + producer | FR-050, AR-3, AR-7 | in-transaction insert; no relay yet |
| 2.4 Outbox relay worker | FR-050, AR-3 | E2.0 "survives concurrency + crash" gate |
| 2.5 Enumerated audit | FR-052 | + NFR-012; the enumerated P1a set |
| 2.6 Submission record + snapshot | FR-030, FR-031, AR-4, AR-6 | binds resolved form/program version id |
| 2.7 Public idempotent submit + receipt | FR-032, FR-033, FR-080 | + UX-DR4/8; Learning Telemetry DoD-gated |
| 2.8 Operator list + funnel | FR-034, FR-080 | + UX-DR2/6; funnel is the operator-facing telemetry view |
| 3.1 Score against rubric | FR-040, FR-041, FR-080 | + NFR-004 decimal; emits submission.scored + rubric.edited |
| 3.2 Decision (auditable, reopenable) | FR-042, FR-080, FR-081 | decision.recorded + decision.reopened |
| 3.3 Export decision list | FR-043, FR-080 | + export-then-leave World-A/B signal |
| Epic 4 / SP-1 | FR-007 (+ supersedes 001/006) | Stories live in `docs/superpowers/specs/2026-06-22-sp1*` |
| Epic 4 / SP-2 | FR-008 | Future spec (SG-as-linked-provider) |
| Epic 4 / SP-3 | — | 7 role-profile types; **no FR explicitly assigned** (see findings) |
| Epic 4 / SP-4 | FR-009 | Consented SG import |

### Coverage matrix — PRD FRs vs Epic claim

P1a in-scope FRs (the bar the epics doc commits to):

| FR | PRD claim (paraphrase) | Epic coverage | Status |
|---|---|---|---|
| FR-001 | Native auth or linked provider; Account ULID is user key | Reused foundation; SP-1 reworks the auth provider | ✓ Covered (with supersession ledger) |
| FR-002 | Sign-up creates Organization (creator = admin) | Epic 1 / Story 1.1 | ✓ Covered |
| FR-003 | Every tenant record carries server-set `organization_id` | Reused foundation + AR-6 | ✓ Covered |
| FR-004 | Tenant queries fail-closed; cross-tenant → 404 | Reused foundation + AR-6 | ✓ Covered |
| FR-005 | RBAC per organization | Epic 1 / Story 1.1 | ✓ Covered |
| FR-006 | Consent-aware profile reads via `ConsentProvider` | Epic 1 / Story 1.5 (mock); SP-4 makes consent local | ✓ Covered |
| FR-007 | Native registration + verify + reset + session | Epic 4 / SP-1 | ✓ Covered |
| FR-008 | Optional SG link/unlink + sign-in via SG | Epic 4 / SP-2 | ✓ Covered |
| FR-009 | Consented field-level SG profile import | Epic 4 / SP-4 | ✓ Covered |
| FR-010 | Operator creates and publishes a Program | Epic 1 / Story 1.2 | ✓ Covered |
| FR-011 | Operator opens a Cohort with enrollment window | Epic 1 / Story 1.4 | ✓ Covered |
| FR-012 | Published artifacts immutable + versioned | Epic 1 / Stories 1.2, 1.3 | ✓ Covered |
| FR-013 | Programs cloneable + templatable | Epic 1 / Story 1.2 | ✓ Covered |
| FR-020 | Attach one published immutable form (enumerated field set) | Epic 1 / Story 1.3 | ✓ Covered |
| FR-021 | Form reachable at public mobile-web URL | Epic 1 / Story 1.4 | ✓ Covered |
| FR-022 | Declarative-only form logic (no arbitrary code) | Epic 1 / Story 1.3 | ✓ Covered |
| FR-030 | Authenticated applicant submits bound to Cohort | Epic 2 / Story 2.6 | ✓ Covered |
| FR-031 | Immutable submission snapshot | Epic 2 / Story 2.6 | ✓ Covered |
| FR-032 | Submission is idempotent via Idempotency-Key | Epic 2 / Story 2.7 | ✓ Covered |
| FR-033 | Closed/unpublished cohort → 422 | Epic 2 / Story 2.7 | ✓ Covered |
| FR-034 | Operator views tenant-scoped submission list | Epic 2 / Story 2.8 | ✓ Covered |
| FR-040 | Score with DECIMAL(6,2) half-up, declared tie-break | Epic 3 / Story 3.1 | ✓ Covered |
| FR-041 | Scores + rubric version immutable, scorer `sub` | Epic 3 / Story 3.1 | ✓ Covered |
| FR-042 | Accept/reject decision audited; reopen audited | Epic 3 / Story 3.2 | ✓ Covered |
| FR-043 | Export decision list CSV | Epic 3 / Story 3.3 | ✓ Covered |
| FR-050 | Transactional outbox + at-least-once relay | Epic 2 / Stories 2.3, 2.4 | ✓ Covered |
| FR-051 | Idempotency on submit + payment-callback endpoint | Epic 2 / Story 2.2 (payment piece deferred) | ✓ Covered |
| FR-052 | Enumerated P1a audit set | Epic 2 / Story 2.5 | ✓ Covered |
| FR-060 | EntitlementService allow-all (3 enumerated call sites) | Epic 1 / Stories 1.2, 1.4 | ✓ Covered |
| FR-070 (primitive only in P1a) | Consumer-agnostic callback idempotency primitive | Epic 2 / Story 2.2 (built as design constraint) | ✓ Covered |
| FR-080 | Event taxonomy (viewed/started/abandoned/submitted/scored/rubric.edited/decision.recorded/exported/export-then-leave) | Cross-cutting: Epic 2 (2.7, 2.8) + Epic 3 (3.1, 3.2, 3.3); DoD-gated | ✓ Covered |
| FR-081 | Dispute/reopen first-class audited event | Epic 3 / Story 3.2 | ✓ Covered |

P1b / P2 / P3 / P4 FRs (correctly deferred — out of epics scope by design):

| FR | Phase | Status |
|---|---|---|
| FR-061 | P1b | ⏸ Deferred (gated on OQ3 + World-A) |
| FR-062 | mixed (UX in P1a, enforcement P1b) | ⏸ UX surface in P1a (banner only); enforcement deferred to P1b |
| FR-070 interface stub | P1b | ⏸ Deferred (primitive in P1a covered) |
| FR-071, FR-072, FR-073 | P1b | ⏸ Deferred |
| FR-100..FR-108 | P2 | ⏸ Deferred (capability-level) |
| FR-120..FR-127, FR-130..FR-133 | P3 | ⏸ Deferred (capability-level) |
| FR-150..FR-159 | P4 | ⏸ Deferred (capability-level) |

### Missing Requirements

**No P1a FRs are missing.** All 32 P1a FRs (25 in §6.1–§6.9 detailed + FR-007/008/009 routed via Epic 4 + FR-070 P1a primitive) are accounted for in Epics 1–4 or the cross-cutting Learning Telemetry deliverable.

**Minor traceability gaps (not blocking, worth tightening):**

- **SP-3 has no FR assignment.** Epic 4 / SP-3 ("7 local role-profile types" — Founder, Startup, Mentor, Service Provider, Investor, Trainer, Judge) is mentioned in epics.md line 142 with no FR pointer. PRD §9 (Data Ownership) enumerates the 7 types but **does not assign an FR ID** to "role profile types as system of record." There is no FR currently traceable to SP-3 as a distinct deliverable; it sits adjacent to FR-006 (consent-aware reads) but doesn't satisfy it. **Recommendation:** add an FR in PRD §6.1 (e.g. `FR-009.5` or `FR-014`) for "the platform owns the 7 enumerated role-profile types as system of record," then route SP-3 to it.
- **Epic 4 has no story-level breakdown inside epics.md.** SP-1..SP-4 are named as sub-projects but their stories live in `docs/superpowers/specs/2026-06-21..06-22-*` (the pre-BMAD-canonization specs). Per the project's BMAD-only decision, Epic 4 stories should eventually migrate into epics.md (or a sibling `epics-epic-4.md`) so dev agents using `bmad-dev-story` can implement against a single canonical story file.
- **FR-070 dual-phase claim.** epics.md line 66 says FR-070 is "DEFERRED out of P1a epics." This is half right: the **interface stub** is P1b, but the **idempotency primitive** is built in P1a per PRD §6.8 / FR-070, and that primitive is exactly what Story 2.2 delivers (consumer-agnostic). The deferral statement should be tightened to "FR-070 *interface stub* deferred to P1b; FR-070 *primitive* shipped via Story 2.2's consumer-agnostic design constraint."

**FRs in epics but NOT in PRD:** None. The epics doc adds no new FR IDs; all FR references are traceable to PRD §6.1–§6.9.

### Coverage Statistics

- **Total PRD FRs:** 67 enumerated IDs across §6.1–§6.12
- **In-scope for P1a epics:** 32 (P1a detailed + the FR-070 P1a primitive)
- **Out-of-scope for P1a epics (deferred by phase):** 35 (P1b: 5; P2: 9; P3: 12; P4: 10) — *(FR-062 counted in P1b: enforcement piece; UX-surface piece IS in P1a as design-only)*
- **P1a FRs covered in epics:** 32 of 32
- **P1a coverage percentage:** **100%**
- **Deferred-but-noted percentage:** 100% (every P1b/P2/P3/P4 FR is explicitly tagged as deferred in epics.md, not silently dropped)
- **Traceability defects:** 3 minor (SP-3 has no FR; Epic 4 stories not in epics.md; FR-070 deferral statement is imprecise)

## UX Alignment Assessment

UX sources read in full: `DESIGN.md` (161 lines — visual identity, tokens, components, contrast/a11y rules) and `EXPERIENCE.md` (134 lines — IA, voice/tone, behavior, state patterns, RTL/bilingual, a11y floor, instrumentation surfaces, flows). Both scoped to **Phase 1a (Selection MVP)** per their frontmatter.

### UX Document Status

**Found.** Both UX spines are present, dated (2026-06-20), and explicitly cross-reference each other ("DESIGN governs the visual layer and EXPERIENCE the behavioral layer") and the PRD. EXPERIENCE.md references DESIGN.md tokens by `{name}`; DESIGN.md tokens carry verified contrast notes (WCAG 1.4.11 cited on `{inputBorder}`).

### UX ↔ PRD Alignment

Mapping checked for every PRD P1a FR and the operator/applicant journeys:

| PRD FR / NFR / UJ | UX coverage |
|---|---|
| **FR-001** Native auth or linked provider (Sanctum SPA) | EXPERIENCE.md → Auth state pattern *(SG-mock framing; superseded by Epic 4 — see misalignment #1)* |
| **FR-002** Sign-up creates Organization | EXPERIENCE.md → Signup/Org-create state pattern |
| **FR-004** Tenant queries fail-closed → 404 | EXPERIENCE.md → Permission/not-found 404 ("Not found or you don't have access" — neutral, never reveals another tenant) |
| **FR-006** Consent-aware reads | EXPERIENCE.md inherits the ConsentProvider seam framing (the surfaces don't expose it, but the IA respects it) |
| **FR-010, FR-012** Create/publish program | EXPERIENCE.md IA → Program → create / publish |
| **FR-011** Open cohort with enrollment window | EXPERIENCE.md IA → Cohort → open + attach form |
| **FR-013** Programs cloneable / templates | UX-side coverage: not explicitly enumerated in EXPERIENCE.md (epic-only) |
| **FR-020** Attach published immutable form (8 field types) | EXPERIENCE.md "attach-only" framing matches PRD; full builder explicitly P3 |
| **FR-021** Public mobile-web URL | EXPERIENCE.md → Public application page (mobile-first, single column) |
| **FR-022** No arbitrary code in form logic | Implicit (declarative tokens only); enforcement is acceptance test, not UX |
| **FR-030, FR-031** Applicant submits + immutable snapshot | EXPERIENCE.md → Immutable-after-submit state ("You can't edit after submitting") |
| **FR-032** Idempotent submit | EXPERIENCE.md → Already-submitted state ("You've already applied to this cohort") |
| **FR-033** Closed cohort → 422 | EXPERIENCE.md → Closed-cohort state ("This cohort closed on {date}. Applications are no longer accepted.") |
| **FR-034** Operator submission list | EXPERIENCE.md → Submissions IA + table row pattern + empty-state copy ("No applications yet. Share your cohort link.") |
| **FR-040** Decimal scoring with rubric max | DESIGN.md → Score input (mono, `dir=ltr`, `value/max`, decimal-only); EXPERIENCE.md → autosave + commit; NFR-004 enforced |
| **FR-041** Immutable scores | EXPERIENCE.md → autosave-draft + explicit "Submit score" commit boundary |
| **FR-042** Accept/reject decision | EXPERIENCE.md → Confirmation modal (destructive/irreversible); audit at commit (FR-052) |
| **FR-043** Export decision list | EXPERIENCE.md → Bulk selection + export flow (with carried follow-up on "large/partial-failure state") |
| **FR-052** Enumerated audit | EXPERIENCE.md → Autosave vs commit framing makes "explicit commits are audited" first-class |
| **FR-060** EntitlementService allow-all | EXPERIENCE.md → Limit banner has explicit P1a/P1b phase note ("no live trigger until P1b") |
| **FR-062** Limit-reached UX | EXPERIENCE.md → Limit-reached state (create blocked; reads/exports stay live); banner spec'd |
| **FR-080** Event taxonomy | EXPERIENCE.md → Instrumentation Surfaces section explicitly reconciles to PRD FR-080 (stepped form, visible rubric edit, time-to-decision, export-then-leave) |
| **FR-081** Dispute/reopen audited | EXPERIENCE.md → Reopen decision uses confirmation modal + audit at commit |
| **NFR-011** RTL/Arabic + a11y floor | DESIGN.md → Layout/Components RTL via logical properties; EXPERIENCE.md → RTL & Bilingual Behavior section, `bdi` on every interpolated value, Western numerals pinned (`ar-u-nu-latn`); Accessibility Floor section codifies contrast + keyboard + screen-reader + modal semantics + touch targets |
| **UJ-1** Operator runs intake | EXPERIENCE.md → Flow 1 ("Layla runs an intake") |
| **UJ-2** Applicant applies | EXPERIENCE.md → Flow 2 ("Omar applies", mobile-web Arabic/RTL) |

**Coverage outcome:** every PRD P1a FR with a UX-facing aspect is reflected in either DESIGN.md (visual) or EXPERIENCE.md (behavior). No P1a FR is silently omitted from UX.

### UX ↔ Architecture Alignment

| UX requirement | Architecture ADR / decision |
|---|---|
| Immutable-after-submit applicant snapshot (FR-031) | **ADR-1** — separate `submission_snapshot` jsonb, distinct lifecycle from published config |
| Idempotent submit ("already applied" replay) (FR-032) | **ADR-2** — `idempotency_keys` with `request_fingerprint`, fresh build (not via versioning kernel) |
| Tenant-scoped 404 / "Not found or you don't have access" (FR-004) | **ADR-3** — `BelongsToTenant` opt-in per new table, explicit cross-tenant test per table |
| Stepped form telemetry events delivered at-least-once (FR-080) | **ADR-4** — outbox + relay worker with atomic claim; consumer-side `event_id` dedupe (note: telemetry retention/table NOT enumerated in ADRs — see gap #3) |
| Content-addressed file upload, dedupe of identical files (FR-031 file refs) | **ADR-5** — sha256 key + refcount over MinIO; GC deferred (ticketed debt) |
| Score input decimal, value/max (FR-040, NFR-004) | Reuses pre-existing `brick/math` 0.17 decimal kernel — listed in Foundation, no new ADR |
| Audit on commit (FR-052) | Reuses pre-existing Audit shared kernel (line 61) — no new ADR; covered via per-action invocation in feature stories |
| Confirmation modal (destructive actions) | UI primitive, not an architectural decision |
| Mobile-web RTL public flow | Frontend stack (React 19/TS/Vite) listed in Foundation; no separate ADR |
| Limit banner (FR-062) | Architecture acknowledges EntitlementService socket (FR-060) and counter (FR-061) deferral — banner has no live trigger until P1b |

**Coverage outcome:** every UX-driven persistence/concurrency invariant has an architecture decision behind it. The 5 ADRs cover exactly the new substrate (snapshot, idempotency, isolation, outbox, blob store).

### Alignment Issues

**1. EXPERIENCE.md identity model note is stale (S2-Drift).**
EXPERIENCE.md line 21 reads: *"all users authenticate via Startup Gate `sub` (mock in P1a). Roles in P1a: Operator/Admin and Applicant."* This was the pre-SP-1a framing. The actual shipped model: native Catalesta accounts (Account ULID = primary user key), with SG demoted to an optional linked provider (FR-001/FR-007/FR-008 + Epic 4 / SP-1, SP-2). The epics.md doc carries an explicit supersession ledger (lines 145); EXPERIENCE.md does not.
**Suggested fix (Phase 2 edit, if you authorize):** add a one-line supersession note at the top of the "Identity model" bullet, mirroring epics.md's "impact ledger" pattern.

**2. EXPERIENCE.md has no UX entries for shipped Epic 4 / SP-1b-ii flows.**
SP-1b-ii (frontend native auth) shipped the following user-facing screens, all live on `main`: native registration, login (native + SG), email-verification interstitial, "email verified" landing, forgot password, reset password. EXPERIENCE.md only documents the SG-OIDC auth handoff state pattern. The screens exist in code (with stories, tests, and a11y coverage) but have no IA entry, voice/tone guidance, or state-pattern enumeration in EXPERIENCE.md.
**Severity:** Drift — the screens shipped per the SP-1b-ii spec (`docs/superpowers/specs/2026-06-22-sp1b-ii-native-auth-frontend-design.md`), so behavior is documented *somewhere*, but EXPERIENCE.md (now the canonical UX spine post-BMAD-switch) doesn't reflect them.
**Suggested fix:** add a "Native auth surfaces" section to EXPERIENCE.md mirroring the SP-1b-ii design doc's IA + states, then mark the superpowers spec as historical.

**3. Architecture.md has no ADR for the FR-080 learning-telemetry substrate.**
Architecture line 32 mentions "instrumentation events FR-080/081" in the requirements overview, but the 5 ADRs (snapshot, idempotency, isolation, outbox, blob) do not cover the `learning_events` table that actually shipped in Story 2.8 (per the SP-1b-ii merge cluster, the table exists with `BelongsToTenant`, append-only triggers, explicit organization_id stamping). The persistence + retention decision was made in code without an architecture-doc ADR.
**Severity:** Drift — the decision exists in the codebase and in Story 2.8's spec; it just isn't captured in `_bmad-output/planning-artifacts/architecture.md`.
**Suggested fix:** add **ADR-6** to architecture.md: "learning_events table — outbox-shape + explicit organization_id (audit-style) + append-only triggers; LearningTelemetry recorder with explicit-org-wins for public events (no PII)."

**4. Architecture.md acknowledges itself as partial (structural, not a misalignment).**
Line 74: *"this architecture doc is scoped to the P1a foundation + substrate decisions needed for story creation; remaining architecture steps (full data models, API contracts) can be completed later via bmad-create-architecture."* The remaining steps (data models, API contracts) are deferred but real. Not blocking the assessment; flag for follow-up.

**5. Several PRD NFRs lack architecture-side commitments.**
- **NFR-010** (RPO ≤ 15 min, RTO ≤ 4 h) — mentioned in requirements overview line 35 but no ADR for backup strategy (full + WAL + retention), no restore runbook reference. OQ8 owns ratifying these before P1a exit.
- **NFR-014** (p95 < 500 ms at the stated load model) — no ADR on caching, indexing strategy, query plan, or load-test approach. OQ8.
**Severity:** these are Open Questions on the PRD side (OQ8) so it's correct that architecture doesn't pin them yet — but if OQ8 ratifies them, architecture.md needs ADRs to match.

### Warnings

- **EXPERIENCE.md voice-tone and microcopy are EN/AR but the bilingual copy deck artifact is referenced only.** EXPERIENCE.md line 51 says "Maintain a bilingual copy deck (P1a strings)" — neither DESIGN.md nor EXPERIENCE.md is that deck, and no `*copy-deck*.md` was found in `_bmad-output/planning-artifacts/`. If the deck lives elsewhere (`docs/ux/`?), it should be referenced from EXPERIENCE.md; if it doesn't exist yet, it's a content debt the upcoming Phase-2 stories should produce.
- **Carried-medium items from the UX validation gate (EXPERIENCE.md line 132)** — exact `{inputBorder}` ≥ 3:1 confirmation, RTL test scope extension, operator-console touch-target floor (deferred to P4), table/grid header-association detail, export large/partial-failure state. These are tracked-not-fixed; review-rubric.md and review-accessibility.md hold the canonical follow-up list.

## Epic Quality Review

Standards applied: the `create-epics-and-stories` workflow checklist — user value, epic independence, story sizing, Given/When/Then acceptance criteria, no forward dependencies, just-in-time table creation, brownfield integration vs greenfield setup, traceability to FRs.

### Per-Epic Compliance Check

| Epic | User value | Independence | Story sizing | Forward deps | Table timing | ACs format | FR traceability |
|---|---|---|---|---|---|---|---|
| **Epic 1: Stand Up an Intake** | ✓ ("operator opens applications") | ✓ no dep on E2/E3/E4 | ⚠ Story 1.0 is substrate (justified, see #2) | ✓ none | ✓ no new tables (brownfield reuse) | ✓ G/W/T | ✓ 11 P1a FRs covered |
| **Epic 2: Receive Applications** | ✓ ("applicants apply; operator sees them") | ✓ consumes Epic 1's content-addressed version id as INPUT (not forward) | ⚠ Stories 2.1–2.5 are substrate (justified, see #1) | ✓ none (intra-epic `blocked-by: GATE-E2.0` is correct) | ✓ each story builds the table it needs | ✓ G/W/T + ★ Edge-Case Hardening | ✓ 8 P1a FRs + FR-080 cross-cut |
| **Epic 3: Score & Decide** | ✓ ("score, decide, export") | ✓ epic-gated on Epic 2 evidence (not a forward dep — Epic 3 sequences AFTER) | ✓ user-facing throughout | ✓ none | ✓ each story builds the table it needs | ✓ G/W/T | ✓ 5 P1a FRs |
| **Epic 4: Standalone Identity** | ✓ ("Catalesta as system of record; native account, no SG dependency") | ✓ sequenced after Epic 2 review, before Epic 3 | ⚠ no story-level breakdown in epics.md (defect, see #3) | n/a (stories live in legacy superpowers specs) | ✓ migrations real (per SP-1a) | n/a in epics.md | ⚠ SP-3 has no FR (see step-03) |

### Brownfield Indicators (PRD + epics.md confirm)

- ✓ **No project-init story** (AR-1: brownfield reuse — tenancy + decimal + versioning kernels are already shipped).
- ✓ **Integration / migration stories** present where needed (Epic 4 / SP-1 migrates `ExternalUser` → `accounts` + `linked_identities`; `organization_memberships` repointed to `account_id`).
- ✓ **External adapter isolation** (AR-8): Startup Gate behind an OIDC adapter; Geidea behind `PaymentProvider`. Confirms the architecture's "behind interfaces only" rule.

### Edge-Case Hardening + Dev-Story Handoff Contract (epics.md §"Edge-Case Hardening" and §"Dev-Story Handoff Contract")

Beyond the create-epics-and-stories baseline, the epic doc layers two sophisticated patterns:

- **Edge-Case Hardening** folds additional ACs (★ = must-fix-before-green) into the named stories that own them. The "per-story dev-context assembly rule" makes these ACs *normative parts of the story*, not optional. This is a strength — dev agents using `bmad-dev-story` see the full normative AC set in one read.
- **GATE-E2.0** is the single explicit cross-story gate (stories 2.6–2.8 declare `blocked-by: GATE-E2.0`). Combined with the per-story Definition of Done (unit + feature + authorization + tenant-isolation + a11y + telemetry), this is a healthy quality bar — not a structural defect.

The 3 ★ must-fix-before-green ACs (2.7 close-boundary race, 2.6 GC-vs-snapshot refcount, 2.2 in-flight/crash semantics) are all addressed in the shipped Epic 2 code (per the green-suite baseline at session start: 384 tests passing).

### Findings — by severity

#### 🔴 Critical Violations
None.

#### 🟠 Major Issues

**1. Stories 2.1–2.5 are technical substrate, not user stories** (best-practice deviation, but **justified by domain**).

The five "E2.0 reliability gate" stories deliver substrate (content-addressed blobs, idempotency primitive, transactional outbox, relay worker, audit table) with no user-facing surface. Pure `create-epics-and-stories` doctrine flags these as technical milestones.

The epic doc explicitly justifies this with the FMA tripwires (AR-7) and the framing *"chaos/concurrency-tested 'a submission survives a double-click and a mid-write crash' before any user-facing flow."* For a system-of-record platform whose core promise is auditable, defensible selection, building the substrate first and chaos-testing it before features is a well-established pattern (it's the same logic that drives a sea-floor check before a bridge). Each substrate story has:
- A clear deliverable (table + service + tests).
- Throwaway-consumer tests so the story can be validated without depending on Applications.
- An explicit concurrency / crash test (Story 2.4 = "the E2.0 'survives concurrency + crash' gate").

**Verdict:** documented deviation, accepted; the justification meets the bar for "domain-specific exception to the user-value-per-story rule." Future epics should be challenged on this pattern if it generalizes beyond reliability substrates.

**2. Epic 4 has no story-level breakdown in epics.md** (already flagged in step-03; raised to major here because of the BMAD-everywhere project decision).

Epic 4 names sub-projects (SP-1 native accounts → SP-2 SG-as-linked-provider → SP-3 role profiles → SP-4 consented import) but does not enumerate stories with Given/When/Then ACs inside epics.md. The story-level content lives in `docs/superpowers/specs/2026-06-21-standalone-identity-design.md`, `2026-06-22-sp1a-identity-model-inversion-design.md`, `2026-06-22-sp1b-i-native-auth-backend-design.md`, and `2026-06-22-sp1b-ii-native-auth-frontend-design.md` — the legacy superpowers track that the project has now retired.

A dev agent invoked via `bmad-dev-story` against Epic 4 would have no canonical story context — only the high-level paragraph at epics.md lines 141–145. SP-1 already shipped (sprint-status confirms 4-1 = done), but SP-2/SP-3/SP-4 still need to be implementable, and that requires story-level entries in epics.md.

**Suggested fix:** migrate the SP-1..SP-4 spec content into epics.md as Stories 4.1, 4.2 (a/b/c if needed for the SP-1 cluster), 4.3 (SP-2), 4.4 (SP-3), 4.5 (SP-4). Mark the superpowers specs historical. Done as part of Phase-2 doc edits, not now.

#### 🟡 Minor Concerns

**3. Story 1.0 (frontend foundation) is substrate, not user-facing alone.** Same domain-justified-deviation pattern as Stories 2.1–2.5; called out separately because it sits in Epic 1 (a user-value epic) rather than a substrate-only cluster. The Definition of Done line (epics.md line 172) explicitly constrains later stories to consume it, so it's defensibly within scope. No structural fix recommended.

**4. SP-3 has no FR pointer** (echoes the step-03 traceability defect). PRD assigns no FR to "the platform owns the 7 role-profile types as system of record." Epic 4 / SP-3 implements this but maps to no FR.
**Suggested fix:** add an FR in PRD §6.1 (`FR-009.5` or `FR-014`) for "the platform owns the 7 enumerated role-profile types as system of record," then route SP-3 to it.

**5. FR-070 deferral statement in epics.md is imprecise** (echoes step-03). epics.md line 66 says FR-070 is "DEFERRED out of P1a epics," but the PRD splits it: the *primitive* ships P1a (via Story 2.2's consumer-agnostic idempotency), only the *interface stub* defers to P1b.
**Suggested fix:** tighten the deferral statement to *"FR-070 interface stub deferred to P1b; FR-070 primitive shipped via Story 2.2's consumer-agnostic design constraint."*

**6. Story 1.5's "N submissions to score" next-action was text-not-link initially** until Epic 2 / Story 2.8 shipped (the link handoff is now real; sprint-status confirms 2-8 = done). The intermediate state — a link target that did not yet exist — was handled via UX rendering the action as `<strong>` until the route landed. Story 1.5's ACs don't explicitly document this intermediate state; Story 2.8 does. Not a structural defect; just under-documented.

#### Best Practices Compliance Checklist

| Check | Pass / Fail |
|---|---|
| Epic delivers user value | ✓ all 4 epics |
| Epic can function independently | ✓ Epic 2 consumes Epic 1 contract; Epic 3 gated on Epic 2 evidence; Epic 4 sequenced |
| Stories appropriately sized | ⚠ 6 substrate stories (1.0, 2.1–2.5) deviate from "user value per story" with documented domain justification |
| No forward dependencies | ✓ none found |
| Database tables created when needed | ✓ each story builds the table it needs |
| Clear acceptance criteria (Given/When/Then) | ✓ + Edge-Case Hardening + Glossary + GATE-E2.0 + per-story DoD |
| Traceability to FRs maintained | ⚠ 32/32 P1a FRs covered; SP-3 has no FR; Epic 4 missing canonical story breakdown |

**Overall verdict:** epics doc is **high quality** for Phase 1a. Two major issues (substrate-stories deviation, Epic 4 missing story breakdown) — first is justified domain pattern, second is a real artifact of the BMAD-vs-superpowers transition and should be resolved before SP-2 starts.

## Summary and Recommendations

### Overall Readiness Status

**Split verdict:**

| Scope | Status |
|---|---|
| **Phase 1a as-shipped** (Epic 1 + Epic 2 + SP-1) | ✅ **READY** — already delivered; 384 backend tests passing on `main`; sprint-status confirms all stories `done` |
| **Phase 1a final polish** (residual planning fixes — flagged below) | ⚠️ **NEEDS WORK** — non-blocking but worth resolving before SP-2 |
| **Phase 1b entry** (billing seam) | 🛑 **NOT READY** — gated on OQ1 (World A/B band), OQ3 (metering dimensions), OQ8 (NFR ratification). PRD section 7 is explicit on these gates. |
| **Epic 3 entry** (Score & Decide) | ⚠️ **NEEDS WORK** — gated on Epic 2 evidence ("applicants actually submit; funnel has real data"). Code is shipped; gate is recruiting a first-partner cohort that runs to decision. |
| **Epic 4 continuation** (SP-2, SP-3, SP-4) | ⚠️ **NEEDS WORK** — story-level entries missing from `epics.md` (currently only in legacy `docs/superpowers/specs/`). Dev agents using `bmad-dev-story` against Epic 4 have no canonical content. |

### Critical Issues Requiring Immediate Action

The assessment found **zero critical defects** in shipped Phase-1a work — the planning artifacts and the merged code line up.

For the next slice of work, the following are real blockers (in priority order):

1. **OQ1 — World A/B decision band needs to be run.** Phase 1b billing is explicitly gated on it (PRD §7); Phase 1a is the instrumented hypothesis that resolves it. Action: collect telemetry data from ≥2 cohorts across ≥2 operators (OQ6 = recruit a design partner).
2. **OQ6 — Named design partner.** Founder-owned, "secure ≥1 operator call this week." Without it the entire validation chain (OQ1 → OQ2) cannot resolve. PRD §10 calls this "the top non-engineering action."
3. **OQ9 — Roadmap reconciliation.** `docs/plan/roadmap.md` still bundles Phase 1 = "Selection MVP + billing"; PRD splits to 1a / 1b. Two SSOT-level docs disagree. PM-owned, "next roadmap edit."

### Recommended Next Steps (sequenced)

1. **Resolve OQ9 (15-min PM edit):** update `docs/plan/roadmap.md` to match the PRD's 1a / 1b split. Removes a real SSOT divergence.
2. **Add story-level breakdown for Epic 4 into `epics.md`:** migrate SP-1 (done — captures the as-shipped reality), SP-2 (next up), SP-3, SP-4. Use the same Given/When/Then format as Epics 1–3. Mark the legacy `docs/superpowers/specs/2026-06-2*` files as historical. This closes the BMAD-everywhere transition's biggest documentation gap.
3. **Assign an FR to SP-3 in the PRD:** "the platform owns the 7 enumerated role-profile types as system of record." Currently SP-3 maps to no FR (traceability defect from steps 3 and 5).
4. **Add ADR-6 to `architecture.md` for FR-080 learning telemetry:** `learning_events` table shape (outbox-shape + explicit `organization_id` + append-only triggers); `LearningTelemetry` recorder with explicit-org-wins for public events (no PII). The decision exists in code; it just isn't captured in the architecture doc.
5. **Tighten the FR-070 deferral note in `epics.md`:** "FR-070 *interface stub* deferred to P1b; FR-070 *primitive* shipped via Story 2.2's consumer-agnostic design constraint." One-line precision fix.
6. **Update `EXPERIENCE.md`:**
   - Add supersession note on the identity model bullet ("Sub mock superseded by Epic 4 / SP-1; Account ULID is now primary").
   - Add a "Native auth surfaces" section mirroring the SP-1b-ii design doc (registration, email-verification interstitial, verified landing, forgot password, reset password, native + SG login).
7. **OQ8 ratification — schedule before P1a exit review:** NFR-010 RPO/RTO values, NFR-013 residency region, NFR-014 perf load model, M3 baseline. Without these, the "P1a complete" claim has soft underpinnings.
8. **OQ1 / OQ6:** founder-owned recruitment + telemetry collection, not planning work.

### Findings inventory

| Severity | Count | Detail |
|---|---|---|
| 🔴 Critical | 0 | — |
| 🟠 Major | 2 | E2.0 substrate stories deviation (justified domain pattern); Epic 4 missing story-level breakdown in epics.md (artifact of BMAD transition) |
| 🟡 Minor | 6 | Story 1.0 substrate (justified); SP-3 has no FR; FR-070 deferral imprecise; Story 1.5 intermediate state under-documented; EXPERIENCE.md identity model stale; EXPERIENCE.md missing Epic 4 / SP-1b-ii surfaces |
| ℹ️ Warnings | 3 | architecture.md partial (acknowledged); bilingual copy deck not located; carried-medium UX-validation items in EXPERIENCE.md line 132 |
| 📂 Architectural ADR gaps | 3 | ADR-6 needed (FR-080 telemetry); NFR-010 backup/restore ADR pending OQ8; NFR-014 perf budget ADR pending OQ8 |
| 🌍 Open questions (PRD-side, unchanged) | 9 | OQ1–OQ9 |

### Final Note

This assessment identified **2 major + 6 minor + 3 warnings + 3 architecture gaps + 9 open questions** across the 10 canonical state docs. **Zero critical defects** for the work already shipped. The most actionable next moves are operational (resolve OQ9, migrate Epic 4 into epics.md, write ADR-6) — none block what's currently in production. The phase-gate-critical items (OQ1 World A/B band, OQ6 design partner) are founder-owned and outside the planning-doc scope of this review.

You may proceed to SP-2 / Epic 3 / Phase 1b as the open questions clear. The plan is sound; the documentation is close to currency; the codebase matches the plan.

---

**Assessor:** John (BMM Product Manager) via `bmad-check-implementation-readiness`
**Ground truth:** `main` HEAD `b57d478` + 6 session-PRs merged (#30 auth-401, #31 idempotency-savepoint, #32 audit-savepoint-test, #33 snapshot-assertEquals, #34 password-throttle, #35 OIDC-dead-test-delete, #36 form_version_id ULID, #37 ProfileApi-dead-test-delete; tests green at 384 passing)
**Method:** sequential micro-step workflow (`bmad-check-implementation-readiness/steps/step-01..06`)
