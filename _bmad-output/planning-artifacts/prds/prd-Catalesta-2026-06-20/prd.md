---
title: Catalesta — Product Requirements Document (Full Scope, Phased)
status: update-in-progress
created: 2026-06-20
updated: 2026-06-23
last_finalized: 2026-06-20
last_finalized_grade: Good
update_grade_pending: true
open_items: [OQ1, OQ2, OQ3, OQ4, OQ5, OQ6, OQ7, OQ8, OQ9]
update_signal_2026-06-23: 'Six-bucket scope overlay (§6.13); Database Topology subsection (§9.1, pending ADR-0005); §7 readiness-report note; retroactive log of silent post-2026-06-20 edits (identity inversion already applied in §6.1/§9/FR-157)'
---

# Catalesta PRD

> Scope source of truth: `docs/product/scope-register.md` · Sequence source of truth: `docs/plan/roadmap.md`.
> Full functional surface as requirements, sequenced into phases. **Phase 1 = "Selection MVP + billing seam,"** split here into **1a (Selection MVP, instrument-first)** and **1b (billing seam, gated on the World-A result)** — see §7 and the PM note there. Tech mechanism choices live in `addendum.md`.
>
> **FR ID scheme:** IDs are block-allocated per capability area (e.g. 001–006, 010–013) with reserved gaps for insertion; a gap is not a missing FR. Metrics M#, counter-metrics C#, goals G#, journeys UJ-#, NFRs NFR-###, open questions OQ#.

---

## 0. Glossary

| Term | Meaning |
|---|---|
| **Program** | A configurable offering (e.g. an accelerator) owned by a tenant; has stages, policies, role requirements. |
| **Cohort** | A dated **intake/run** of a program (enrollment window). Applications bind to a cohort. |
| **Stage** | A configurable lifecycle step within a program (Application, Evaluation, …). In Phase 1 the only stage exercised is **Application/Selection**; later stages are P3+. |
| **Form** | A published, immutable, versioned set of fields attached to a cohort for application intake. |
| **Application** | A submission by an applicant to a cohort; frozen as an immutable **snapshot** on submit. |
| **Rubric** | A published, versioned scoring scheme; scored with decimal arithmetic. |
| **Snapshot** | An immutable copy of the exact answers + referenced form/program/rubric **versions** at submit time. |
| **Entitlement** | A tenant capability/limit resolved only via `EntitlementService` (never by plan-name checks). |
| **World A / World B** | The core hypothesis: A = trustworthy *selection* is the acute, recurring, renewable job; B = selection was merely the first buildable slice and the renewable job lives later in the lifecycle. **Decision rule: §3.** |

---

## 1. Overview

**Catalesta** is a multi-tenant SaaS that lets accelerators and incubators run the full program lifecycle — application → eligibility → evaluation → mentorship → training → final evaluation → graduation → alumni follow-up — in one configurable, auditable, tenant-isolated system, replacing the spreadsheet + form-builder + email patchwork. MENA-first (bilingual Arabic/English + RTL, Geidea billing). Laravel modular monolith. **Catalesta owns identity** — native registration, authentication, and locally-owned multi-role profiles; the Account id (ULID) is the immutable user key and email is a local login credential only. Startup Gate is an **optional** linked identity provider (SSO) and a consented profile-import source, never the system of record — the platform is fully operational without it.

**Form factor:** responsive web application (operator console + **mobile-web public application pages**) + REST API + webhooks. [ASSUMPTION] no native mobile app in any phase here. The public applicant flow (UJ-2) is designed mobile-first because applicant traffic in MENA is expected to be mobile-dominant.

## 2. Problem & Goals

**Problem.** Programs run on fragmented tooling: form builder for applications, spreadsheet for scoring, email for coordination, separate tools for mentorship and reporting. Result: lost applications, inconsistent/disputable evaluation, no single source of truth, heavy manual reporting, zero reuse across cohorts.

**Product goals.**
- G1 — Run an entire program in one configurable system.
- G2 — Make selection trustworthy and defensible (versioned rubrics, decimal scoring, immutable snapshots, audit trail).
- G3 — Prove the program worked (outcomes/reporting to funders).
- G4 — Monetize via tenant subscriptions without ever holding tenant data hostage (commitment realized by **FR-062** and **NFR-008**).

## 3. Success Metrics & Counter-Metrics

> Targets below are **provisional** (set now so each metric is falsifiable) and are **re-ratified after first-partner data**. The Phase-1a learning metric is **M3**; the North Star **M1** is the lagging outcome it is the leading indicator for (see M1 note).

| # | Metric | Type | Provisional target |
|---|---|---|---|
| M1 | **Cohorts run to decision** per active tenant per quarter | North Star | ≥ 2 within 2 quarters of activation |
| M2 | Time signup → first **published** program | Activation | ≤ 1 working day (median) |
| M3 | **Median time-to-decision** per applicant (submission → accept/reject) | Value (selection) | ≥ 30% faster than the tenant's prior baseline |
| M4 | Applications processed per published cohort | Engagement | ≥ 50 (pilot floor) |
| M5 | Trial → paid; net revenue retention | Commercial | **Measurable only once charging is live (Phase 1b/2); target TBR** |

**M1 ↔ M3 linkage:** M3 (fast, defensible decisions) is the leading indicator; M1 (repeated cohorts run to decision) is the lagging outcome. The bet is that a tenant who can decide faster and defensibly runs *more* cohorts here. If Phase-1a moves M3 but **not** repeat-cohort intent, that is itself the World-B signal.

**Counter-metrics (guardrails).**
- C1 — Cross-tenant data exposure incidents = **0** (hard gate).
- C2 — Scoring **disputes / reopened decisions** per cohort. *Instrumented by **FR-081** (dispute/reopen is a first-class audited event).*
- C3 — Application drop-off rate.
- C4 — Tenant data-loss on plan-limit / lapse = **0**.

**Validation status & World A/B decision rule.** No design partner is yet recruited; per the 2026-06-23 OQ6 strengthening (option II — aligned with the rubric-walker's original recommendation), **Phase 1a start is contingent on ≥1 signed design partner**. Recruiting one is no longer a "this week" action item but a **hard entry gate**. Owner = founder; gate = §10 OQ6. Phase 1a is an **instrumented hypothesis** that — once entered — resolves the World A/B question via:

> **[Proposed decision band]** After ≥ 2 cohorts across ≥ 2 operators: **World A** if (a) M3 improves ≥ 30% vs baseline AND (b) rubric-edit events occur mid-cohort (selection is a live, contested job) AND (c) ≤ 20% export-then-leave (operators decide *inside* the product). **World B** if operators decide in seconds with untouched rubrics and immediately export to work elsewhere. World B → re-sequence: the renewable job is later in the lifecycle, and Phase 1b billing is **not** built on selection.

`[NOTE FOR PM]` This decision rule is the gate on Phase 1b. Phase 1b (billing) must not start until the band returns World A.

## 4. Users & Stakeholders

- **Program Operator / Admin** (primary buyer + daily user) — configures and runs cohorts, scores, decides, reports.
- **Applicant / Startup** (end participant) — discovers a program, applies, tracks status. Authenticates with a native Catalesta account, or optionally a linked Startup Gate identity.
- **Evaluator / Mentor / Trainer** (delivery roles) — score applications, run sessions, mark progress.
- **Funder / Sponsor** (stakeholder, not daily user) — consumes outcomes & reporting.
- **Platform Admin** (Catalesta staff) — tenant administration, support, impersonation (audited, P4 FR-158).

## 5. Key User Journeys

**UJ-1 — Operator runs an intake (Phase 1a happy path).** Layla, ops lead at a Cairo accelerator, signs up → her organization is created → she configures one program with one published application form and opens a cohort (intake window) → she shares the public application URL → applicants submit (each submission frozen as an immutable snapshot) → Layla reviews the submission list, scores each against the published rubric (decimal), and marks accept/reject → she exports the decision list (CSV). Every step is tenant-isolated and audited; time-to-decision and rubric edits are recorded.

**UJ-2 — Applicant applies (Phase 1a, mobile-web).** Omar, a founder on his phone, opens the public program page, authenticates, completes the published form in Arabic (RTL), submits once (a duplicate submit is idempotent), and sees his status. His submitted data cannot be silently altered afterward.

> Later-phase journeys (mentorship matching, training delivery, graduation, funder reporting) are captured at capability level in §6 phases 3–5 and detailed when those phases are planned; one capability-journey per delivery role is added at that point.

## 6. Scope — Functional Requirements (phased)

Phase tags: `[P1a]` Selection MVP (instrument-first, no billing) · `[P1b]` Billing seam (gated on World-A) · `[P2]` Substrate generalization + delivery core · `[P3]` Platform services + production commercial plane · `[P4]` Extended capabilities + production cutover. Phase 1a/1b are specified to build depth; later phases are capability-level (full detail in `docs/product/scope-register.md`; P1-critical detail is inlined here).

### 6.1 Identity, Tenancy & Access — `[P1a]`
- **FR-001** A user authenticates with a **native Catalesta account** (email + password) or, optionally, via a linked identity provider; the immutable **Account id (ULID)** is the user key and email is a local login credential, never a cross-system identifier. Sessions use the existing Sanctum SPA cookie-session transport. *(Native accounts + the linked-provider model are delivered by Epic 4 / SP-1–SP-2; the shipped Epic-1 SG-OIDC-mock path is superseded — see the Epic 1 impact ledger in `epics.md`.)*
- **FR-002** Signing up creates an Organization (tenant); the creating user becomes its admin.
- **FR-003** Every tenant-owned record carries `organization_id`, server-set (never client-supplied/mass-assignable).
- **FR-004** Every tenant query is isolation-enforced fail-closed: an unresolved tenant returns no rows; cross-tenant access returns 404.
- **FR-005** RBAC scopes permissions per organization (operator, evaluator, applicant, …).
- **FR-006** Profile reads are consent-aware, **including locally-owned profiles**; the `ConsentProvider` interface is enforced at every profile-read call site. (CLAUDE #11.)
- **FR-007** A user can **register a native account** (email + password), verify their email, reset a forgotten password, and manage their session — with no Startup Gate dependency. *(Epic 4 / SP-1.)*
- **FR-008** A user can **link** an optional Startup Gate identity to their Catalesta account and sign in with it, or **unlink** it; the account remains usable after unlink. `sub` is stored on the link, not the account. *(Epic 4 / SP-2.)*
- **FR-009** A user can **import selected profile fields from Startup Gate after explicit, field-level consent**; imported data is a local editable copy with per-field source tracking, import history, and a conflict preview, and **never auto-overwrites locally modified fields**; consent is revocable. *(Epic 4 / SP-4.)*

### 6.2 Program & Cohort Configuration — `[P1a]`
- **FR-010** An operator creates a Program and publishes it.
- **FR-011** An operator opens a **Cohort** on a program with an enrollment window. A cohort has an open/close datetime; submissions are accepted only while open (FR-033). The enrollment window is a property *of the cohort* (no separate overlapping window).
- **FR-012** Published stage/version artifacts are immutable and versioned; edits create new versions (publishing never mutates a published version).
- **FR-013** Programs can be cloned and saved/instantiated as templates. A clone copies the **published** version of each artifact and starts a fresh draft (it never references a draft as if published).

### 6.3 Application Form (minimal) — `[P1a]`
- **FR-020** An operator attaches one **published, immutable** application form to a cohort. **Phase-1a field set (enumerated):** short text, long text, single-select, multi-select, number, date, file upload, and a consent/acknowledgement checkbox. Conditional logic, calculated fields, and the full builder are **P3 (FR-127)**.
- **FR-021** The published form is reachable at a public, mobile-web application URL for the cohort.
- **FR-022** No arbitrary code executes in form logic; field definitions are declarative data only (acceptance: a form definition cannot reference code/expressions outside the enumerated field types — see NFR-005 test).

### 6.4 Application Management — `[P1a]`
- **FR-030** An authenticated applicant submits an application **bound to a Cohort**. *(Resolved-by-shipping 2026-06-23: Epic 2 / Story 2.6 implements this binding; the original `[ASSUMPTION-CONFIRM]` tag is closed — see decision log.)*
- **FR-031** On submission the system captures an **immutable snapshot** containing: the submitted answer values, uploaded file references (content-addressed), and the **version IDs** of the form, program, and rubric in effect. Later edits to any source artifact never alter a stored snapshot. The snapshot is what is scored and audited.
- **FR-032** Submission is **idempotent**: a duplicate submit with the same `Idempotency-Key` returns the original result, not a second record.
- **FR-033** Submission against an unpublished or closed cohort is rejected (422).
- **FR-034** An operator views the tenant-scoped list of submissions for a cohort.

### 6.5 Assessment / Selection — `[P1a]`
- **FR-040** An operator scores a submission against the cohort's **published rubric** using **decimal arithmetic**: scores are stored as `DECIMAL(6,2)` [ASSUMPTION precision], **half-up rounding**, and ties are broken by the rubric's declared tie-break order (else by earliest submission time). No floating-point appears in any score path (NFR-004).
- **FR-041** Scores and the rubric version are recorded immutably with the scorer's `sub` (auditable).
- **FR-042** An operator records an accept/reject **decision** per applicant; decisions are audited (FR-052). Reopening a recorded decision is itself an audited event (FR-081 / C2).
- **FR-043** An operator **exports** the decision list (CSV) for a cohort.

### 6.6 Reliability Substrate (payment-agnostic primitives) — `[P1a]`
- **FR-050** A **transactional outbox**: domain events are written in the same DB transaction as the state change; a relay delivers them **at-least-once** to consumers, which must be idempotent; failed deliveries retry with exponential backoff (cap [ASSUMPTION] 6 attempts) and land in a dead-letter store. Phase 1a wires **one** consumer ("application submitted → notification", log transport). Ordering is per-aggregate, not global.
- **FR-051** **Idempotency** is enforced on the two endpoints where a double-fire is costly: application submission and the payment-callback endpoint (the latter's handler ships in P1b but the idempotency primitive is built here, payment-agnostic).
- **FR-052** The following actions write **audit** records (enumerated, the P1a audited set): `program.published`, `cohort.opened/closed`, `application.submitted`, `submission.scored`, `decision.recorded`, `decision.reopened`, `decisions.exported`. Audit completeness for this set is acceptance-testable.

### 6.7 Entitlement Seam (socket only in P1a) — `[P1a]` / counter `[P1b]`
- **FR-060** `[P1a]` Domain modules check entitlements **only** via `EntitlementService` — never by inspecting plan names. Phase 1a ships the **interface (socket) returning allow-all**; the enforced call sites are enumerated: `program.publish`, `cohort.open`, `application.submit`. "Invoked everywhere" is acceptance-tested by an architecture test asserting these gated actions call `EntitlementService` and that no module references a plan name.
- **FR-061** `[P1b]` Metering counter — the **real** limit policy. [ASSUMPTION + OQ3] the one real counter is **`active_programs` per organization**, plus boolean feature flags. **Not built until packaging dimensions (OQ3) are at least provisionally ratified** — building the counter blind is the rejected path.
- **FR-062** Reaching a limit never deletes or hides existing tenant data: at the threshold the operator sees an in-product banner on the affected create action and on the org billing page; **write actions that would exceed the limit are blocked**, while all **reads and exports remain available**.

### 6.8 Billing Seam — `[P1b]` (gated on World-A; see §7)
- **FR-070** `[P1a` primitive `/ P1b` interface`]` (foundational, payment-agnostic) The **signature-verified, idempotent callback primitive** is built provider-agnostically in **P1a as part of FR-051** (consumer-agnostic by design, so the Geidea callback adopts it for free). The `PaymentProvider` **interface stub itself is deferred to P1b** (the billing epic) — it has no live exerciser in a no-billing P1a. *(Reconciles the epics' FR-070 placement; the primitive ships 1a, the interface 1b.)*
- **FR-071** `[P1b]` Integrate the **Geidea sandbox** end-to-end behind `PaymentProvider`. **No real money is charged in Phase 1a/1b sandbox**; first partners run free until a production charging decision.
- **FR-072** `[P1b]` Payment callbacks are signature-verified and processed idempotently; browser returns are never authoritative.
- **FR-073** `[P1b]` No raw card numbers or CVV are ever stored.

### 6.9 Instrumentation (learning) — `[P1a]`
- **FR-080** The system records a defined event taxonomy: `application.viewed` (public page), `application.started`, `application.abandoned{step}` (→ C3 drop-off), `application.submitted`, `submission.scored{elapsed}`, `rubric.edited{cohort,phase}`, `decision.recorded{time_to_decision}` (→ M3), `decisions.exported`, and a session signal for **export-then-leave** (export followed by no further in-product action within [ASSUMPTION] 24h). Events are tenant-scoped and queryable for the World-A/B band (§3).
- **FR-081** Dispute/reopen is a first-class audited event feeding C2 (see FR-042/FR-052).

### 6.10 Substrate generalization + Delivery Core — `[P2]` (capability-level)
- **FR-100** Documents; **FR-101** Workflow engine (declarative); **FR-102** Role eligibility & assignments; **FR-103** Tasks & milestones; **FR-104** Mentorship; **FR-105** Training; **FR-106** Final evaluation; **FR-107** Graduation, alumni & follow-up; **FR-108** Personalized tracks. **Plus:** generalize the P1a substrate — multi-consumer outbox ordering/replay guarantees, idempotency across new write paths, audited-action set extension — defined here so the "generalize later" cost is explicit, not free.

### 6.11 Platform Services & Production Commercial Plane — `[P3]` (capability-level)
- **FR-120** Notifications; **FR-121** Calendar integrations; **FR-122** Reporting/dashboards; **FR-123** Search/directories; **FR-124** Admin/config; **FR-125** Public API/webhooks; **FR-126** Audit enforced platform-wide; **FR-127** Full form builder (conditional logic, calculated fields).
- **FR-130** Production subscription billing: versioned immutable plans, trials, dunning, upgrades/downgrades/add-ons, Geidea recurring + Hosted Payment Page (real charging). **FR-131** Usage metering across the full dimension set. **FR-132** Subdomains + verified custom domains with automatic TLS. **FR-133** Branding/white-label (controlled tokens only; no arbitrary CSS/JS).

### 6.12 Extended Capabilities & Production Cutover — `[P4]` (capability-level)
- **FR-150** Interviews/public pages/waitlists; **FR-151** Partners/finance/timesheets; **FR-152** Service marketplace/messaging; **FR-153** Surveys/hackathons/knowledge; **FR-154** Simulation/outcomes/risk; **FR-155** Bulk ops/version migration; **FR-156** *(splits before P4 planning into:)* localization hardening, security hardening, observability, data migration/import-export, performance/production-readiness — each becomes its own FR; **FR-157** Startup Gate as an **optional** linked SSO provider + consented profile import (no authority cutover — SG never becomes the system of record); **FR-158** Admin impersonation with full audit; **FR-159** Tenant offboarding end-to-end + DR.

### 6.13 Six-Bucket Scope Classification — overlay (added 2026-06-23)

> Overlay on the FRs above. Six buckets requested in the 2026-06-23 PRD update: **Existing and verified**, **Existing but incomplete**, **Required for initial release** (sub-tags for in-flight Epic 3 / Epic 4), **Required for later release**, **Optional**, **Out of scope**. Grounded in `_bmad-output/planning-artifacts/implementation-readiness-report-2026-06-23.md` § Coverage Matrix and `docs/status/implementation-status.md` (status doc dated 2026-06-19 — refresh tracked as `docs/repository-audit.md` F-003; readiness report is authoritative where they disagree). Bucket totals: **22** Existing/verified · **1** Existing/incomplete · **7** Required-initial Epic-3 · **2** Required-initial Epic-4 · **1** Required-initial (FR-062 UX banner) · **34** Required-later · §11 enumerates OOS.
>
> **Framing note (added 2026-06-23 — adversarial review F-Δ1).** "Existing and verified" here counts code that shipped via Epics 1, 2, and SP-1 in service of the original Phase 1a scope. Per the 2026-06-23 §7 NOTE FOR PM (OQ6 strengthening), that code is — under the strengthened framing — formally "instrument-build" until Phase 1a's entry gate (≥1 signed design partner) is satisfied. The two framings reconcile when OQ6 closes: at that point "Existing and verified" maps cleanly to Phase 1a delivery. Until then, both readings are intentional — traceability against the FR taxonomy uses the bucket name; phase-status reporting uses §7's instrument-build framing.

| FR | Bucket | Source signal |
|---|---|---|
| FR-001 | Existing and verified | Reused foundation; SP-1 landed reworks auth provider |
| FR-002 | Existing and verified | Epic 1 / S1.1; `Organizations` Implemented |
| FR-003 | Existing and verified | Foundation + AR-6; `BelongsToTenant` Implemented |
| FR-004 | Existing and verified | Foundation + AR-6; 404-not-403 landed in S1.1 (auto-memory §5) |
| FR-005 | Existing and verified | Epic 1 / S1.1; `Organizations` RBAC |
| FR-006 | Existing but incomplete | S1.5 seam against SG mock; SP-4 makes consent local (pending) |
| FR-007 | Existing and verified | Epic 4 / SP-1 (landed per readiness 2026-06-23) |
| FR-008 | Required for initial release — Epic 4 in-flight | Epic 4 / SP-2 (next-up, not yet landed) |
| FR-009 | Required for initial release — Epic 4 in-flight | Epic 4 / SP-4 (next-up, not yet landed) |
| FR-010 | Existing and verified | Epic 1 / S1.2; `Programs` Implemented |
| FR-011 | Existing and verified | Epic 1 / S1.4; `Cohorts` Implemented |
| FR-012 | Existing and verified | Epic 1 / S1.2, S1.3; `Stages` + `Programs` Implemented |
| FR-013 | Existing and verified | Epic 1 / S1.2; `Programs` clone + templates Implemented |
| FR-020 | Existing and verified | Epic 1 / S1.3 (status doc stale on Forms — F-003) |
| FR-021 | Existing and verified | Epic 1 / S1.4 (status doc stale on Forms — F-003) |
| FR-022 | Existing and verified | Epic 1 / S1.3 + NFR-005 acceptance test |
| FR-030 | Existing and verified | Epic 2 / S2.6; `[ASSUMPTION-CONFIRM]` resolved-by-shipping 2026-06-23 — see decision log |
| FR-031 | Existing and verified | Epic 2 / S2.6 |
| FR-032 | Existing and verified | Epic 2 / S2.7 + idempotency primitive S2.2 |
| FR-033 | Existing and verified | Epic 2 / S2.7 |
| FR-034 | Existing and verified | Epic 2 / S2.8 |
| FR-040 | Required for initial release — Epic 3 in-flight | Epic 3 / S3.1 (epic claim only; Epic 3 not yet landed) |
| FR-041 | Required for initial release — Epic 3 in-flight | Epic 3 / S3.1 |
| FR-042 | Required for initial release — Epic 3 in-flight | Epic 3 / S3.2 |
| FR-043 | Required for initial release — Epic 3 in-flight | Epic 3 / S3.3 |
| FR-050 | Existing and verified | Epic 2 / S2.3, S2.4; survives-concurrency+crash gate |
| FR-051 | Existing and verified | Epic 2 / S2.2 primitive; submit-endpoint via S2.7. Payment-callback *endpoint* itself ships P1b (FR-072) |
| FR-052 | Required for initial release — Epic 3 in-flight | Substrate + Epic-1/2 events shipped via S2.5; 4 of 7 enumerated events (`submission.scored`, `decision.recorded`, `decision.reopened`, `decisions.exported`) wire in with Epic 3 |
| FR-060 | Existing and verified | Epic 1 / S1.2, S1.4 + Epic 2 / S2.7; architecture test asserts wiring |
| FR-061 | Required for later release | P1b ⏸ Deferred (gated on OQ3 + World-A) |
| FR-062 | Required for initial release (UX banner pending) | UX banner is P1a per PRD; not yet story-claimed. Enforcement piece naturally Required-later (P1b) |
| FR-070 | Existing and verified | P1a callback-idempotency primitive shipped via Epic 2 / S2.2 (consumer-agnostic by design). P1b `PaymentProvider` interface stub tracked alongside FR-071…073 |
| FR-071 | Required for later release | P1b ⏸ Deferred |
| FR-072 | Required for later release | P1b ⏸ Deferred |
| FR-073 | Required for later release | P1b ⏸ Deferred (PCI hygiene — standing NFR-class invariant once billing ships) |
| FR-080 | Required for initial release — Epic 3 in-flight | Cross-cutting: Epic 2 events shipped (S2.7, S2.8); Epic 3 events (S3.1, S3.2, S3.3) in-flight; full taxonomy gates on Epic 3 |
| FR-081 | Required for initial release — Epic 3 in-flight | Epic 3 / S3.2 |
| FR-100…108 | Required for later release | P2 capability-level ⏸ Deferred. Status doc: Documents / Workflows / Mentorship / Training / Graduation = Scaffold; FinalEvaluation = Absent (F-007) |
| FR-120…125, FR-127 | Required for later release | P3 capability-level ⏸ Deferred. Status doc: Notifications + Search + Administration = Absent (F-007) |
| FR-126 | Required for initial release — Reliability/Audit epic carve-out | **Reclassified 2026-06-23** (from P3 → Reliability/Audit epic). Decision routes via `docs/repository-audit.md` F-009 + F-010 carve-out (audit-enforced + outbox + idempotency + signed webhooks as one epic, inserted before P2). Aligns CLAUDE.md "audit-bearing events" baseline with implementation timing. See decision log 2026-06-23 |
| FR-130…133 | Required for later release | P3 production commercial plane ⏸ Deferred (subscription billing, full metering, custom domains, branding) |
| FR-150…155 | Required for later release | P4 ⏸ Deferred |
| FR-156 | Required for later release — pre-split pending | P4 ⏸. PRD already notes this FR splits into 5 sub-FRs (localization hardening, security hardening, observability, data migration / import-export, performance / production-readiness) before P4 planning |
| FR-157 | Required for later release ✓ | P4 ⏸. Text already aligned with 2026-06-21 inversion: "SG never becomes the system of record" |
| FR-158 | Required for later release | P4 ⏸ |
| FR-159 | Required for later release | P4 ⏸. NFR-010 (RPO/RTO) ratification is P1a-exit-gating (OQ8) — see §10 |

## 7. Implementation Phases (roadmap)

> Authoritative sequence: `docs/plan/roadmap.md`. `[NOTE FOR PM]` The roadmap's Phase-1 entry currently reads "Selection MVP + billing" as one unit. This PRD **splits it into 1a then 1b, with 1b gated on the World-A result**. Update the roadmap entry to match, or reconcile the divergence — do not leave the two SSOT-level statements disagreeing.
>
> `[NOTE FOR PM 2026-06-23]` `_bmad-output/planning-artifacts/implementation-readiness-report-2026-06-23.md` supersedes any prior phase-progress framing in this section. As of 2026-06-23: **Epics 1 + 2 are landed; SP-1 is landed.** Epic 3 (Stories 3.1–3.3 → FRs 040–043, 081) and Epic 4 SP-2 / SP-3 / SP-4 (→ FRs 008, 009) are in-flight or next-up. Phase 1a is not yet complete. Phase 1b remains gated on the World-A result + OQ3 ratification, unchanged.
>
> `[NOTE FOR PM 2026-06-23 — Phase 1a *entry* gate]` **Phase 1a start is contingent on OQ6 — ≥1 signed design partner.** Strengthened from "≥1 operator call this week" to close the PRD-vs-rubric divergence (rubric-walker's original recommendation). Engineering work that lands before OQ6 is satisfied is treated as instrument-build, not Phase 1a delivery. Update `docs/plan/roadmap.md` Phase 1a entry to match.
>
> `[NOTE FOR PM 2026-06-23 — Reliability/Audit epic carve-out]` **FR-126 (platform-wide audit enforced) is reclassified from P3 to a new Reliability/Audit epic** inserted before P2. The epic owns outbox + idempotency + audit-enforced + signed-webhooks (per `docs/repository-audit.md` F-009 + F-010). Phase-table row for this epic to be added once the epic is scoped; FR-126 source signal in §6.13 reflects the new home.

| Phase | Theme | FRs | Gate / exit criterion |
|---|---|---|---|
| **1a** | Selection MVP — instrument-first, **no billing** | FR-001…052, 060 (socket), 070 (primitive), 080–081 | One operator runs a real intake end-to-end (publish→receive→score→decide→export); substrate primitives real (see "slice depth"); instrumentation live; World-A/B band (§3) can be evaluated |
| **1b** | Billing seam | FR-061, 071–073 | **Entry gate: World-A confirmed (§3 band) AND OQ3 packaging dimensions provisionally ratified.** Exit: Geidea sandbox e2e verified; `active_programs` counter enforced; no real charge |
| **R/A** | Reliability / Audit epic *(carve-out — added 2026-06-23, before P2)* | FR-126 (moved from P3); signed-webhook substrate (new, not yet FR'd); generalization of FR-050 / 051 / 052 substrate from P1a's enumerated set to platform-wide | Audit-enforced platform-wide (move from opt-in → enforced for every authorization decision, identity link/unlink, consent grant, profile import, stage outcome); signed-webhook substrate live for all external integrations; outbox + idempotency primitives generalized beyond the P1a enumerated call sites. Closes `docs/repository-audit.md` F-009 + F-010 |
| **2** | Substrate generalization + delivery core | FR-100…108 | Participant lifecycle (mentorship→graduation) runnable; substrate generalized to multi-consumer |
| **3** | Platform services + production commercial plane | FR-120…125, FR-127, FR-130…133 *(FR-126 moved to R/A — see above)* | Tenant subscribes + pays (production Geidea); reporting, notifications, full forms |
| **4** | Extended capabilities + production cutover | FR-150…159 | Extended modules; optional Startup Gate SSO + import (FR-157); DR/offboarding/impersonation production-ready |

**"Slice depth" defined.** For Phase 1a, each substrate primitive is built to exactly this depth: **outbox** = table + transactional write + at-least-once relay + one idempotent consumer + dead-letter (FR-050); **idempotency** = key table + middleware on the two named endpoints (FR-051); **audit** = the enumerated action set (FR-052); **entitlement** = interface + three enumerated call sites, allow-all policy (FR-060). "Generalized in P2" = the FR-100 generalization scope above. The substrate is built once at this depth and *extended* (not rewritten) in P2.

**Phase rule.** The substrate primitives (FR-050/051/052) and the entitlement *socket* (FR-060) are payment- and policy-agnostic and are built in 1a because retrofitting them after features ship is far costlier — especially for fail-closed isolation and audit. The **policy** (FR-061 counter) and **provider specifics** (FR-071–073) are deferred to 1b precisely because they depend on decisions (World-A, OQ3) not yet made.

## 8. Cross-cutting Non-Functional Requirements

- **NFR-001 Tenant isolation** — fail-closed; `BelongsToTenant`; architecture test asserts the trait on every tenant-owned model. (C1 = 0 incidents.)
- **NFR-002 Identity integrity** — the **Account id (ULID)** is the primary user identifier; a Startup Gate `sub`, when linked, is the immutable key of that external identity only. Email never identifies across systems.
- **NFR-003 Immutability & versioning** — published forms, stages, assessments, workflows cannot mutate; new versions only; formal submissions capture immutable snapshots (FR-031).
- **NFR-004 Decimal arithmetic** — all scoring uses `DECIMAL` math with the precision/rounding in FR-040; no floats in money/score paths.
- **NFR-005 No arbitrary code in rules** — rule/form/expression definitions are declarative data. **Acceptance test:** a validator rejects any definition whose nodes are not in the allowed field/operator set; an attempt to embed PHP/SQL/JS/shell fails validation and is covered by a test.
- **NFR-006 Consent-aware access** — all profile reads (including locally-owned profiles) enforce consent state via the `ConsentProvider` seam; importing any field from Startup Gate requires explicit field-level consent.
- **NFR-007 Payment integrity** — provider-interface isolation; verified, idempotent callbacks; no raw card/CVV; browser returns non-authoritative.
- **NFR-008 Data-respecting limits** — hitting a usage limit never deletes/hides tenant data (FR-062).
- **NFR-009 Security baseline** — secrets never committed; **signing-key rotation ≤ 90 days, provider-API-key rotation ≤ 180 days or on incident**; least-privilege; **rate limiting: ≤ 60 req/min/IP on the public application endpoint, ≤ 600 req/min/tenant on authenticated APIs** [ASSUMPTION ceilings].
- **NFR-010 Availability & DR** — **RPO ≤ 15 min, RTO ≤ 4 h** [Proposed — ratify with first enterprise/procurement requirement]; automated daily full + continuous WAL backup; tested restore runbook. Single-region at P1 (see NFR-013).
- **NFR-011 Localization & accessibility** — **Phase 1a renders Arabic + RTL** for the public applicant flow (UJ-2) and the core operator screens; full bilingual coverage + WCAG 2.2 AA hardening is P4 (FR-156). "Renders RTL," not "does not preclude."
- **NFR-012 Observability** — structured logging, metrics, correlation IDs; audit is enforced (FR-052), not opt-in.
- **NFR-013 Data governance** — Egypt PDPL baseline + GDPR-grade DSR rights. **Residency region commitment must be decided before the first pilot (OQ4 — region only, per 2026-06-23 (q) split), not at P3** — for MENA gov/quasi-gov accelerators it is frequently a contractual precondition, and "single-region" (§11) must be a *named, compliant* region before onboarding a partner. **Retention values** per `product/data-residency-retention.md` [Proposed] — **ratified at Phase 1a exit via OQ8** (per 2026-06-23 (q) split).
- **NFR-014 Performance** — **p95 < 500 ms** for the core operator reads (submission list FR-034, score write FR-040) and public form load (FR-021), measured at **1,000 active cohorts / 100k applications / 50 concurrent operators** [ASSUMPTION load model]; budget ratified before P1a exit.
- **NFR-015 Database topology** *(added 2026-06-23, pending ADR-0005 — anchors §9.1)*. Single product database (one logical Postgres or MySQL); multi-tenancy is row-level via `organization_id`; per-tenant DB / schema-per-tenant **forbidden**. Strongly-consistent reads (post-write reads, authorization checks, idempotency lookups, OIDC callback verification) target the writer; replicas allowed for non-consistency-sensitive reads. Analytics warehouse / data lake **never** a product-code read path. **Acceptance test:** an architecture test asserts (1) all Eloquent connections resolve to a single configured product DB (no `connection` overrides outside reporting/admin scopes); (2) any controller, service, job, or Policy importing an analytics-store client class fails the test. Canonical rule + enforcement detail: `docs/project-context.md` § Database Topology.

## 9. Data Ownership & Domain Boundaries

- **Catalesta (system of record):** accounts & identity, general + role profiles, memberships, consent, verification. Owns all user, role, program, operational, assessment, document, and reporting data. The 7 role-profile types are Founder, Startup, Mentor, Service Provider, Investor, Trainer, Judge.
- **Startup Gate (optional external identity domain):** an optional linked SSO provider and a consented, field-level profile-import source. No direct DB sharing; the platform integrates via adapter interfaces. Imported data is a local editable copy and never auto-overwrites local edits.
- **Program Platform (tenant domain):** organizations, programs, cohorts, stages, forms, applications, documents, assessments, workflows, role assignments, tasks, mentorship, training, final evaluation, graduation, reporting. Join key is the **Account id**.
- **Achievements** flow tenant → Startup Gate only via **trusted publication** (attested, snapshot-backed, consent-gated, idempotent — `features/achievements-trusted-publication.md`).

### 9.1 Database Topology (added 2026-06-23 — pending ADR-0005)

- **Single product database** — one logical PostgreSQL or MySQL. Multi-tenancy is row-level via `organization_id`, **never** database-per-tenant or schema-per-tenant.
- **Read replicas allowed** via Laravel's `read` / `write` connection split. Strongly-consistent reads — post-write reads, authorization checks, idempotency lookups, OIDC callback verification — target the writer.
- **Per-tenant database is forbidden.** Any proposal to shard, partition by tenant, or move a single tenant to its own DB requires a superseding ADR.
- **Out-of-band analytics warehouse / data lake** allowed for Reporting exports, **never as a product-code read path** (controllers, services, jobs, Policies never read from it).
- **Redis** (cache / queue / session) and **S3** (via Flysystem) are not "the product database" — this rule does not constrain them.

**Decision recorded:** 2026-06-23 (this update). **Pending:** ADR-0005 — Single-Database Topology with Row-Level Tenancy (target: Epic 0 hygiene, alongside ADR-0004 identity-ownership inversion). Canonical rule + enforcement details live in `docs/project-context.md` § Database Topology.

## 10. Assumptions & Open Questions

**Assumptions (tagged inline):** responsive web + mobile-web public flow, no native app; applicant auth via native Catalesta account (optional linked SG); P1a no billing (seam primitives only); metering = `active_programs` counter + flags (P1b, pending OQ3); applications bind to cohort (FR-030 — ✓ resolved-by-shipping 2026-06-23); P1a form field set as enumerated (FR-020); decimal `DECIMAL(6,2)` half-up (FR-040); outbox retry cap 6; export-then-leave window 24h; rate-limit/perf ceilings as stated.

**Open questions (owner + when):**
- OQ1 — World A vs B? **Owner:** PM. **Resolved by** the §3 decision band after Phase 1a (≥2 cohorts/≥2 operators). **Gates Phase 1b.**
- OQ2 — Ratify success-metric targets M1–M5. **Owner:** PM. **By** first-partner data. **Gates GTM.**
- OQ3 — Metering **dimensions** beyond `active_programs` + pricing/packaging tiers. **Owner:** PM/Founder. **Gates Phase 1b (FR-061) and P3 (FR-130/131).**
- OQ4 — Data-**residency region commitment** (Egypt / GCC / EU / multi-region…). **Owner:** Founder/Legal. **Severity:** High (2026-06-23 — tightened in light of OQ6 strengthening: signed-partner contract requires a residency commitment). **By** first pilot (procurement gate). **Gates onboarding any partner.** *(Retention values moved to OQ8 per the 2026-06-23 (q) split — OQ4 = region only; OQ8 = ratification of retention values + the other Phase-1a-exit numbers.)*
- OQ5 — Beachhead **ICP** (which accelerator segment first). **Owner:** Founder. **By** before Phase 1a design-partner outreach.
- OQ6 — Named **design partner(s)** + an acquisition plan. **Owner:** Founder. **Gate (2026-06-23 strengthening — aligned with the rubric-walker's original recommendation):** **Phase 1a *start* is contingent on ≥1 signed design partner.** Closes the prior PRD-vs-rubric divergence (PRD had weakened this to "≥1 operator call this week"). Phase 1a cannot begin until this is met; the entire validation chain (OQ1, OQ2) routes through this. See §7 Phase 1a entry-gate note.

### Tracked open items from validation (revision 2) — carried at finalize

Unresolved findings from the validate gate (Grade: Good), **deferred with owner + revisit condition** — finalization does not pretend they are resolved.

- **OQ7 — World-B monetization path *(High, strategic)*.** If the §3 band returns World B, the PRD currently deletes the revenue mechanism with no replacement (G4 stranded). **Owner:** Founder/PM. **Revisit:** at the World-A/B decision (post-Phase-1a). **Decide:** a World-B monetization fallback, or an explicit "thesis fails → pivot" stance.
- **OQ8 — Ratify the values that gate Phase-1a exit *(High)*.** NFR-010 (RPO/RTO), NFR-013 (**retention values only** — region commitment lives in OQ4 per the 2026-06-23 (q) split), NFR-014 (perf targets + load model), and the **M3 baseline** currently gate 1a exit while still `[Proposed]`/`[ASSUMPTION]`. **Owner:** PM (+ Founder/Legal for retention). **Revisit:** before Phase-1a exit review. **Decide:** ratify each, or demote it from an exit gate. *(FR-159 P4 implementation depends on the NFR-010 numbers ratified here; no separate FR-159 OQ is opened — the dependency is captured by OQ8 → FR-159.)*
- **OQ9 — Roadmap reconciliation *(Medium → bumped to High 2026-06-23 staleness, SSOT integrity)*.** `docs/plan/roadmap.md` still states Phase 1 = "Selection MVP + billing" as one unit; this PRD splits it into 1a/1b with 1b gated. **Owner:** PM. **Revisit:** next roadmap edit. **Decide:** update the roadmap entry (the two SSOT-level docs must not disagree). **Staleness note (2026-06-23):** `_bmad-output/planning-artifacts/implementation-readiness-report-2026-06-23.md` now treats roadmap.md as "source-of-truth sequence" — every passing day creates additional authoritative artifacts depending on a roadmap entry that contradicts this PRD. Severity bumped from Medium to High.
- **Carried mediums/lows (detail in `validation-report.md`):** phase-tag FR-062 (limit-blocking is 1b); add test predicates for NFR-011 RTL / §7 "band evaluable" / FR-080 completeness / the 1a payment-callback contract; right-size 1a NFRs (drop the 100k-app perf bar + provider-key rotation until a partner/real key exists); confirm FR-030 cohort-binding + FR-040 precision before the 1a schema freeze.

## 11. Out of Scope

**All phases:** native mobile apps; offline mode; AI/LLM features; marketplace payments/settlement (the service marketplace is non-transactional at MVP); multi-region active-active.

**Phase 1a explicitly excludes (deferred, not dropped):** all billing/charging and the metering counter (P1b/P3); the workflow engine, documents, mentorship/training/graduation and the rest of the lifecycle (P2); notifications beyond the single log-transport outbox consumer (P3); the full form builder (P3); custom domains/branding (P3); optional Startup Gate SSO + import (P4).
