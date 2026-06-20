---
stepsCompleted: [1, 2, 3, 4, 5]
date: 2026-06-20
project_name: Catalesta
documentsUnderAssessment:
  - prds/prd-Catalesta-2026-06-20/prd.md
  - architecture.md
  - epics.md
  - ux-designs/ux-Catalesta-2026-06-20/DESIGN.md
  - ux-designs/ux-Catalesta-2026-06-20/EXPERIENCE.md
supportingEvidence:
  - prds/prd-Catalesta-2026-06-20/validation-report.md
  - prds/prd-Catalesta-2026-06-20/review-adversarial.md
  - ux-designs/ux-Catalesta-2026-06-20/review-accessibility.md
  - ux-designs/ux-Catalesta-2026-06-20/review-rubric.md
  - docs/status/implementation-status.md (as-built code state)
scope: Phase 1a (Selection MVP)
---

# Implementation Readiness Assessment Report

**Date:** 2026-06-20
**Project:** Catalesta
**Scope:** Phase 1a (Selection MVP) — brownfield on 5 built modules

## Step 1 — Document Inventory

All four required artifact types present; one canonical version of each; no whole-vs-sharded duplicate conflicts; nothing missing.

| Type | Canonical file | Format | Notes |
|---|---|---|---|
| PRD | `prds/prd-Catalesta-2026-06-20/prd.md` | whole (in PRD folder) | finalized, validation gate passed |
| Architecture | `architecture.md` | whole | P1a foundation + brownfield stress-test, ADR-1..5 |
| Epics & Stories | `epics.md` | whole (455 lines) | 3 epics, 17 stories, marked dev-ready |
| UX | `ux-designs/ux-Catalesta-2026-06-20/DESIGN.md` + `EXPERIENCE.md` | two-spine | DESIGN=visual, EXPERIENCE=behavioral; P1a mocks rendered |

**Supporting evidence (not under assessment):** PRD `validation-report.md`, `review-adversarial.md`; UX `review-accessibility.md`, `review-rubric.md`; `docs/status/implementation-status.md` (as-built).

**Duplicates:** none. **Missing:** none. **Discovery result:** ✅ clear to proceed.

## Step 2 — PRD Analysis

PRD read in full (224 lines). Requirements are **phased**; Phase 1a is the scope under assessment. Inventory below; P1a FR/NFR set is what epics must cover.

### Functional Requirements — Phase 1a (scope under assessment)

**6.1 Identity, Tenancy & Access**
- FR-001 OIDC auth via Startup Gate `sub` (mock in P1a; provider-agnostic adapter); email never an identifier
- FR-002 Signup creates an Organization; creator becomes admin
- FR-003 Every tenant row carries server-set `organization_id` (never client-supplied)
- FR-004 Fail-closed tenant isolation: unresolved tenant → no rows; cross-tenant → 404
- FR-005 RBAC scopes permissions per organization
- FR-006 Consent-aware profile reads via `ConsentProvider` seam (mock in P1a)

**6.2 Program & Cohort Configuration**
- FR-010 Operator creates + publishes a Program
- FR-011 Operator opens a Cohort with an enrollment (open/close) window
- FR-012 Published artifacts immutable + versioned; edits create new versions
- FR-013 Programs clonable / saveable as templates (clones copy published versions)

**6.3 Application Form (minimal)**
- FR-020 Attach one published immutable form; enumerated 8 field types
- FR-021 Form reachable at a public mobile-web URL per cohort
- FR-022 No arbitrary code in form logic; declarative field defs only

**6.4 Application Management**
- FR-030 Authenticated applicant submits, bound to a Cohort
- FR-031 Submission captures an immutable snapshot (answers + content-addressed files + version IDs)
- FR-032 Idempotent submit via `Idempotency-Key`
- FR-033 Submit to unpublished/closed cohort → 422
- FR-034 Operator views tenant-scoped submission list

**6.5 Assessment / Selection**
- FR-040 Score against published rubric, decimal `DECIMAL(6,2)` half-up, declared tie-break
- FR-041 Scores + rubric version recorded immutably with scorer `sub`
- FR-042 Accept/reject decision per applicant, audited; reopen is an audited event
- FR-043 Export decision list (CSV)

**6.6 Reliability Substrate**
- FR-050 Transactional outbox (same-tx write, at-least-once relay, idempotent consumer, backoff, dead-letter; 1 consumer in P1a)
- FR-051 Idempotency on application submit + payment-callback endpoints (primitive built P1a)
- FR-052 Enumerated audit set: program.published, cohort.opened/closed, application.submitted, submission.scored, decision.recorded, decision.reopened, decisions.exported

**6.7 Entitlement Seam**
- FR-060 `[P1a]` All entitlement checks via `EntitlementService` (socket = allow-all); 3 enumerated call sites (program.publish, cohort.open, application.submit); no plan-name references
- FR-062 Limit never deletes/hides data; banner + block-write, reads/exports stay live *(note: limit-blocking effectively P1b — no live trigger in P1a)*

**6.8 Billing Seam (P1a foundational part)**
- FR-070 `[P1a]` `PaymentProvider` interface + provider-agnostic verified idempotent callback primitive

**6.9 Instrumentation**
- FR-080 Event taxonomy (application.viewed/started/abandoned{step}/submitted, submission.scored{elapsed}, rubric.edited, decision.recorded{time_to_decision}, decisions.exported, export-then-leave)
- FR-081 Dispute/reopen is a first-class audited event (→ C2)

**Total P1a FRs: 30** (FR-001…006, 010…013, 020…022, 030…034, 040…043, 050…052, 060, 062, 070, 080…081)

### Functional Requirements — Deferred (later phases, capability-level)
- `[P1b]` FR-061 (active_programs counter), FR-071–073 (Geidea sandbox e2e, verified idempotent callbacks, no card/CVV)
- `[P2]` FR-100–108 (documents, workflow, role-eligibility, tasks, mentorship, training, final eval, graduation, tracks) + substrate generalization
- `[P3]` FR-120–127 (notifications, calendar, reporting, search, admin, API/webhooks, audit platform-wide, full form builder), FR-130–133 (production billing, metering, domains, branding)
- `[P4]` FR-150–159 (interviews, partners/finance, marketplace, surveys/hackathons, simulation/outcomes/risk, bulk ops, NFR-156 hardening split, FR-157 real Startup Gate, FR-158 impersonation, FR-159 offboarding/DR)

### Non-Functional Requirements (cross-cutting, all phases)
- NFR-001 Fail-closed tenant isolation (`BelongsToTenant` on every tenant model; C1=0)
- NFR-002 Identity integrity (`sub` only cross-system key)
- NFR-003 Immutability & versioning (published artifacts + FR-031 snapshots)
- NFR-004 Decimal arithmetic (no floats in score/money paths)
- NFR-005 No arbitrary code in rules (declarative; validator rejects code)
- NFR-006 Consent-aware access (`ConsentProvider` seam)
- NFR-007 Payment integrity (provider isolation, verified idempotent callbacks, no card/CVV)
- NFR-008 Data-respecting limits (FR-062)
- NFR-009 Security baseline (secrets, key rotation, least-privilege, rate limits)
- NFR-010 Availability & DR (RPO≤15m / RTO≤4h [Proposed])
- NFR-011 Localization & accessibility (P1a renders AR + RTL; full AA = P4)
- NFR-012 Observability (structured logs, correlation IDs, enforced audit)
- NFR-013 Data governance (PDPL + GDPR DSR; residency decided before first pilot)
- NFR-014 Performance (p95<500ms core reads/score/form-load [ASSUMPTION load model])

**Total NFRs: 14** (P1a-active: 001–009, 011, 012, 014; 010/013 ratify before 1a exit)

### Additional Requirements / Constraints
- Non-negotiables (CLAUDE.md): `organization_id` on every tenant row; `sub` not email; no raw card/CVV; published artifacts immutable; decimal scoring; no code-in-rules; consent-aware reads; integrations behind interfaces.
- Phase gating: 1b entry gated on World-A (OQ1) + OQ3 packaging ratification.
- "Slice depth" (§7) defines exactly how deep each P1a substrate primitive is built.

### PRD Completeness Assessment
**Strong.** FRs/NFRs are numbered, phased, and acceptance-oriented; assumptions are tagged inline; "slice depth" makes P1a substrate scope unambiguous. **Open items that affect readiness:**
- **OQ9 (SSOT integrity):** roadmap.md still says Phase 1 = "Selection MVP + billing" as one unit; PRD splits 1a/1b. Two SSOT docs disagree — must reconcile.
- **OQ8:** NFR-010/013/014 + M3 baseline gate 1a-exit while still `[Proposed]`/`[ASSUMPTION]` — ratify or demote before exit (not a blocker to *start*).
- **FR-030 cohort-binding** and **FR-040 precision** flagged for confirmation before the 1a schema freeze.
- **FR-062** phase-tag: limit-blocking has no live trigger until P1b — epics must not build a 1a block that can never fire.

## Step 3 — Epic Coverage Validation

Epics doc read in full. It carries an explicit **FR Coverage Map** (epics.md §"FR Coverage Map") plus per-epic "FRs covered" lines — strong traceability discipline. Cross-referenced every P1a FR below.

### Coverage Matrix (Phase 1a FRs)

| FR | Requirement (short) | Epic coverage | Status |
|---|---|---|---|
| FR-001 | OIDC `sub` auth | Reused foundation (Identity) + AR-6 tests | ✓ Covered |
| FR-002 | Signup → org, creator admin | Epic 1 (S1.1) | ✓ Covered |
| FR-003 | `organization_id` server-set | Reused foundation | ✓ Covered |
| FR-004 | Fail-closed isolation / 404 | Reused foundation + AR-6 | ✓ Covered |
| FR-005 | RBAC per org | Epic 1 | ✓ Covered |
| FR-006 | Consent-aware reads | Epic 1 (S1.5) | ✓ Covered |
| FR-010 | Create + publish program | Epic 1 (S1.2) | ✓ Covered |
| FR-011 | Open cohort + window | Epic 1 (S1.4) | ✓ Covered |
| FR-012 | Published immutable + versioned | Epic 1 | ✓ Covered |
| FR-013 | Clone / templates | Epic 1 | ✓ Covered |
| FR-020 | Attach published form (8 types) | Epic 1 (S1.3) | ✓ Covered |
| FR-021 | Public mobile-web URL | Epic 1 (S1.4) | ✓ Covered |
| FR-022 | No code in form logic | Epic 1 (S1.3) | ✓ Covered |
| FR-030 | Applicant submit, cohort-bound | Epic 2 (S2.6/2.7) | ✓ Covered |
| FR-031 | Immutable snapshot | Epic 2 (S2.6, AR-4) | ✓ Covered |
| FR-032 | Idempotent submit | Epic 2 (S2.2/2.7, AR-2) | ✓ Covered |
| FR-033 | Closed cohort → 422 | Epic 2 (S2.7) | ✓ Covered |
| FR-034 | Submission list | Epic 2 (S2.8) | ✓ Covered |
| FR-040 | Decimal scoring vs rubric | Epic 3 (S3.1) | ✓ Covered |
| FR-041 | Scores immutable + scorer `sub` | Epic 3 (S3.1) | ✓ Covered |
| FR-042 | Accept/reject decision, reopen | Epic 3 (S3.2) | ✓ Covered |
| FR-043 | CSV export | Epic 3 (S3.3) | ✓ Covered |
| FR-050 | Transactional outbox | Epic 2 E2.0 (S2.3/2.4, AR-3) | ✓ Covered |
| FR-051 | Idempotency primitive | Epic 2 E2.0 (S2.2, AR-2) | ✓ Covered |
| FR-052 | Enumerated audit | Epic 2 E2.0 (S2.5) | ✓ Covered |
| FR-060 | EntitlementService socket | Epic 1 | ✓ Covered |
| FR-062 | Limit banner / data-respecting | UX-DR3 surface only (designed, wired P1b) | ⚠ Deferred-by-design, **unmapped in FR coverage map** |
| FR-070 | PaymentProvider iface + callback primitive | Primitive → Epic 2 (consumer-agnostic FR-051); interface → **deferred to P1b** | ⚠ Split / PRD↔epics tag divergence |
| FR-080 | Event taxonomy | Epics 2+3 (Learning Telemetry, gated DoD) | ✓ Covered |
| FR-081 | Reopen audited event | Epic 3 (S3.2) | ✓ Covered |

**FRs in epics but not in PRD:** none. **Architecture-derived stories (AR-1..8)** properly added as substrate work — not orphan scope.

### Missing / Divergent Requirements

**No critical missing FRs.** Two deliberate phase-deferrals need a one-line reconciliation so they aren't silent:

1. **FR-062 (medium — traceability gap).** PRD tags it `[P1a]`, but it's absent from the epic FR Coverage Map. Reality matches the PRD's own note: limit-blocking has **no live trigger in P1a** (entitlement is allow-all), so only the *banner surface* is built (UX-DR3, "designed, wired P1b"). **Recommendation:** add FR-062 to the coverage map as "UX surface in Epic 1; enforcement deferred to P1b" so the map is exhaustive. Not a build blocker.
2. **FR-070 (low — tag divergence).** PRD §6.8 tags it `[P1a] foundational`; epics explicitly move it to the P1b billing epic (party-mode rationale: no live exerciser in a no-billing P1a), **retaining** the design constraint that Epic 2's idempotency primitive (FR-051) is consumer-agnostic so the Geidea callback adopts it for free. The *primitive* is therefore covered; only the empty `PaymentProvider` interface stub defers. **Recommendation:** annotate the PRD FR-070 tag to read "primitive in 1a via FR-051; interface stub 1b" — reconcile the two SSOT statements.

### NFR Coverage (woven, not orphaned)
All 14 NFRs are inventoried in epics §Requirements Inventory and tied to stories/ARs: NFR-001→AR-6 per-table isolation tests; NFR-003→AR-4 snapshot + versioning kernel; NFR-004→Epic 3 decimal; NFR-005→FR-022 validator; NFR-011→UX-DR5/6 RTL + a11y floor; NFR-012→FR-052 enforced audit. NFR-010/013/014 remain `[Proposed]` (OQ8 — ratify before 1a-exit, not before start).

### Coverage Statistics
- **Total P1a FRs:** 30
- **Fully covered / traceable:** 28 (incl. 4 reused-foundation FRs)
- **Deferred-by-design (need coverage-map note):** 2 (FR-062 enforcement, FR-070 interface)
- **Critical missing:** 0
- **Effective P1a "buildable now" coverage:** 100% — the 2 exceptions are intentional P1b deferrals with retained design constraints.

## Step 4 — UX Alignment

### UX Document Status
**Found** — two-spine: `DESIGN.md` (visual: tokens, color/type/spacing, light+dark) + `EXPERIENCE.md` (behavioral: IA, states, interactions, a11y, flows, instrumentation). Both `status: final`, P1a-scoped, mocks rendered (`mockups/p1a-key-screens.html`). Conflict rule defined (spines win; DESIGN=visual, EXPERIENCE=behavioral).

### UX ↔ PRD Alignment — strong
- **Journeys match exactly.** EXPERIENCE "Key Flows" Flow 1 (Layla intake) = PRD UJ-1; Flow 2 (Omar applies, mobile-web AR/RTL) = PRD UJ-2. Same actors, same climax moments (decision commit / irreversible submit).
- **Every surface maps to an FR.** IA section ties each surface to FRs (002, 010/012, 011/020, 034, 040, 042/081, 043, 062, 021, 032, 030, 031) and asserts **IA closure**: "no orphan surfaces; no stated P1a need without a home."
- **Scope agrees with PRD §11.** EXPERIENCE explicitly defers dashboard/Action Center, billing workspace, mentorship/training, custom domains/branding — matches the PRD Phase-1a exclusions. No scope creep.
- **Instrumentation reconciled.** EXPERIENCE pins the **stepped** application form (Next/Back + progress + per-section autosave) specifically so `application.started/abandoned{step}` are real — directly serving FR-080. Rubric-edit and decision-time surfaces match the taxonomy.

### UX ↔ Architecture Alignment — supported
- **File upload** (content-addressed, dedup) ← AR-5/ADR-5 (`sha256` + refcount over MinIO). ✓
- **Idempotent submit** (duplicate → "already applied") ← AR-2/ADR-2 (`idempotency_keys`). ✓
- **Immutable-after-submit snapshot** ← AR-4/ADR-1 (`submission_snapshot` jsonb). ✓
- **Performance** (public form load, submission list, score write) ← NFR-014 p95<500ms budget. ✓
- **RTL/i18n + a11y floor** ← UX-DR5/DR6; minimal a11y CI gate is a real story (Story 1.0). ✓

### Alignment Issues / Warnings (all minor)
1. **FR-062 limit banner** — EXPERIENCE designs the surface but states "no live trigger until P1b." Consistent with the Step-3 coverage finding; not a conflict, but the same reconciliation note applies (mark FR-062 surface-only in P1a).
2. **Export large / partial-failure state** — EXPERIENCE "Carried (medium)" flags this state is unspecified. Story 3.3 (CSV export) must define the large-export / partial-failure behavior, or it's an untested edge at build. **Action:** ensure S3.3 AC covers it.
3. **RTL test scope** — EXPERIENCE's "4 render targets" (2 modes × 2 directions) is currently named against the **2 critical screens** only. **Action:** either extend the check to all operator screens or explicitly state "rendered RTL, spot-checked on 2" in the test plan (UX-DR5). Pin before 1a-exit.
4. **`{inputBorder}` contrast** — carried UX item: confirm ≥3:1 (WCAG 1.4.11) with a tool during Story 1.0. Low risk.

### Warnings
None blocking. UX is the *strongest-aligned* artifact in the set — it was built against the PRD FRs and the P1a architecture, and the epics carry it forward as first-class UX-DR1..8 requirements.

## Step 5 — Epic Quality Review

Validated 3 epics / 17 stories against create-epics-and-stories standards: user value, epic independence, story sizing, forward-dependency ban, DB-timing, AC quality, brownfield fit.

### A. Epic structure — user value
| Epic | User-centric? | Verdict |
|---|---|---|
| 1 — Stand Up an Intake | Operator opens applications | ✓ value epic |
| 2 — Receive Applications | Applicants apply; operator sees funnel | ✓ value epic (substrate folded *inside*, not a technical epic) |
| 3 — Score & Decide | Operator scores, decides, exports | ✓ value epic |

No "technical milestone" epics. The E2.0 reliability gate (Stories 2.1–2.5) is correctly sequenced **inside** a value epic rather than spun out as a forbidden "infrastructure epic."

### B. Epic independence — clean, no forward references
- Epic 1 stands alone. Epic 2 consumes **only** Epic 1's explicit exit contract (the content-addressed form version id). Epic 3 reads Epic 2's snapshot (Story 2.6) and is gated on Epic 2 evidence.
- **No Epic N → N+1 dependency. No circular dependencies.** Backward-only. ✓

### C. Story quality
- **AC format:** every story uses Given/When/Then BDD; criteria are concrete and testable (sha256, 422/409, `DECIMAL(6,2)` half-up, atomic-claim SQL, refcount). Well above typical.
- **Forward dependencies:** none. The substrate stories (2.1–2.5) are each tested against a **throwaway consumer** with "no dependency on Applications" — the standard trap (substrate needing the feature to test) is explicitly avoided.
- **DB timing:** each story creates only the tables it needs (`idempotency_keys`@2.2, `outbox_events`@2.3, `audit_events`@2.5, `application_submissions`@2.6) — **not** all-upfront. ✓
- **Edge-case sweep:** a dedicated section adds boundary ACs per story with ★ = must-fix-before-green (close-boundary race @2.7, GC-vs-snapshot refcount @2.6, in-flight idempotency @2.2). This is unusually rigorous and closes real correctness holes.

### D. Brownfield fit
- Architecture says brownfield, no starter → epics correctly have **no project-init story**; Story 1.0 is frontend foundation, not scaffolding. ✓
- Integration with existing modules is explicit; AR-6 per-table isolation tests are the compatibility bridge for the opt-in `BelongsToTenant`. ✓

### E. Dev-readiness mechanism (notable strength)
The **Dev-Story Handoff Contract** (§ epics.md) makes one-story-at-a-time execution real: a normative union rule (story body + its `N.M` hardening ACs + glossary + DoD + GATE-E2.0), a glossary resolving definite-article nouns, the GATE-E2.0 checklist with `blocked-by` markers, and a per-story DoD that bakes in CLAUDE.md's test mandate (unit + feature + authorization + tenant-isolation). This is what elevates the stories from "written" to genuinely **dev-ready**.

### Findings by severity
**🔴 Critical:** none.
**🟠 Major:** none.
**🟡 Minor / watch-items:**
1. **Substrate story personas (2.1–2.5)** are "platform engineer / compliance-conscious operator" — technical by necessity. Correctly folded into a value epic and independently testable; just note their value only *realizes* when 2.6–2.8 ship on top. No change needed.
2. **Story 1.0 (frontend foundation)** is the largest single story. Sprawl risk is mitigated by the explicit "ONLY this set" component constraint + deferral list, but watch its size at sprint planning — consider splitting token-layer vs component-set if it overflows one sprint.
3. **RTL 4-target test scope** (2 modes × 2 directions) is named against the 2 critical screens only — pin "all operator screens" vs "spot-checked on 2" in the sprint test plan (carries from UX Step 4).
4. **Reconciliation (improves on Step-4 finding):** the export large/empty/partial state IS covered — Story 3.3 AC ("empty/loading/large-export states") + Edge-Case 3.3 (headers-only empty CSV, "as of [time]" stamp). Step-4 concern #2 is effectively **resolved** in the epics.

### Best-practices compliance — all epics
- [x] Delivers user value · [x] Functions independently · [x] Stories sized · [x] No forward dependencies · [x] DB tables created when needed · [x] Clear testable ACs · [x] FR traceability maintained

**Verdict:** epic/story quality is **strong — dev-ready**. No structural defects; only sizing-watch and test-scope-pinning items remain, none blocking.

## Summary and Recommendations

### Overall Readiness Status

**✅ READY** (Phase 1a) — proceed to sprint planning / implementation, with **4 light pre-flight fixes** (none blocking the start).

The four artifacts are present, internally consistent, and three-way aligned (PRD ↔ UX ↔ Architecture ↔ Epics). FR coverage is effectively 100% for buildable P1a scope; epic/story structure has **no critical or major defects**; ACs and the edge-case sweep are unusually rigorous; the Dev-Story Handoff Contract makes one-story-at-a-time execution genuinely viable. This is a well-above-average planning package.

### Critical Issues Requiring Immediate Action

**None.** No 🔴 critical and no 🟠 major findings across any step.

> **Update 2026-06-20 (post-assessment):** findings 1–3 (the three doc one-liners) **applied** — `roadmap.md` MVP cut-line reconciled to the 1a/1b split (OQ9); `epics.md` coverage map now maps FR-062 (surface-only/P1b enforcement); `prd.md` FR-070 tag annotated (primitive 1a via FR-051, interface 1b). Only finding 4 (RTL test-scope, pin at sprint planning) and the OQ8 exit-gate ratifications remain — neither blocks the start.

### Findings ledger (all minor / 🟡)

| # | Finding | Source step | Action | Blocks start? |
|---|---|---|---|---|
| 1 | **OQ9 SSOT divergence** — roadmap.md says Phase 1 = "Selection MVP + billing" as one unit; PRD splits 1a/1b | PRD | One-line edit to `docs/plan/roadmap.md` Phase-1 row to match the 1a/1b split | No |
| 2 | **FR-062 unmapped** in epic FR Coverage Map (surface-only in P1a) | Coverage | Add "FR-062 → UX surface (Epic 1), enforcement P1b" to the coverage map | No |
| 3 | **FR-070 tag divergence** — PRD `[P1a]`, epics defer interface to P1b (primitive kept) | Coverage | Annotate PRD FR-070: "primitive via FR-051 in 1a; PaymentProvider interface 1b" | No |
| 4 | **RTL test scope** — "4-target" check named on 2 screens only | UX/Quality | Pin in sprint test plan: all operator screens, or state "spot-checked on 2" | No |
| — | OQ8 — NFR-010/013/014 + M3 baseline still `[Proposed]` and gate **1a-exit** | PRD | Ratify or demote **before the 1a-exit review** (not before start) | No (exit gate) |
| — | Story 1.0 sizing watch; FR-030 cohort-binding + FR-040 precision confirm before schema freeze | Quality/PRD | Handle at sprint planning / first-story kickoff | No |

*Note: the Step-4 "export large/partial-failure state" concern is **resolved** — Story 3.3 + Edge-Case 3.3 already cover empty/large/"as of [time]" export states.*

### Recommended Next Steps

1. **Fix the 3 doc one-liners now** (findings 1–3): reconcile roadmap Phase-1 row (OQ9), add FR-062 + FR-070 notes to the coverage map / PRD. ~15 minutes; removes the only SSOT inconsistencies.
2. **Shelf `phase2-completion`** (confirmed) — it's Phase-1 polish off the MVP critical path; park it so it doesn't compete with substrate work.
3. **Go to sprint planning** with the two-track order:
   - **Track A (backend critical path):** Epic 2 E2.0 gate — 2.1 blob → 2.2 idempotency → 2.3 outbox+producer → 2.4 relay → 2.5 audit (GATE-E2.0). The ★ must-fix ACs (2.7 close-race, 2.6 GC-vs-snapshot, 2.2 in-flight) are the highest-risk; schedule deliberate review there.
   - **Track B (frontend, parallel):** Story 1.0 frontend foundation (no backend dep), then Epic 1 (1.1–1.5) wiring UI to the already-built modules + thin Forms.
   - Then Epic 2 feature stories (2.6–2.8, `blocked-by: GATE-E2.0`) → Epic 3 (3.1–3.3).
4. **Before the 1a-exit review (not now):** ratify OQ8 values (RPO/RTO, residency, perf load model, M3 baseline) or demote them from exit gates; confirm FR-030/FR-040 before the schema freeze.
5. **Non-engineering, parallel (OQ6):** secure ≥1 design-partner operator call — the entire World-A/B validation chain (which gates P1b) routes through it.

### Final Note

This assessment reviewed **4 artifacts across 5 validation dimensions** and found **0 critical, 0 major, and ~6 minor items** — all documentation reconciliations or sprint-planning watch-items, none blocking the start of implementation. **Phase 1a is READY to build.** Recommend fixing the three doc one-liners, then proceeding to sprint planning on the two-track order above.

*Assessed by John (Product Manager) · 2026-06-20 · BMad Implementation Readiness workflow.*
