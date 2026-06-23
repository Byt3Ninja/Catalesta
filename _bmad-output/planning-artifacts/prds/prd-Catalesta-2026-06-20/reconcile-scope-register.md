---
created: 2026-06-23
source: docs/product/scope-register.md
target: prd.md (2026-06-23 update)
purpose: Surface gaps between scope-register (canonical 24-module scope) and the updated PRD. Surface only — no fixes proposed.
---

# Scope-Register ⇄ PRD Reconciliation Extract

## 0. Inputs

- **Canonical scope source:** `docs/product/scope-register.md` (Owner: Product · Last-updated 2026-06-19). Declares 24-module canonical surface + extended scope + SaaS commercial scope + canonical build-spec IDs `00`–`68`.
- **Target document:** `prd.md` (updated 2026-06-23): six-bucket overlay §6.13, §9.1 Database Topology, OQ8 split, FR-126 reclassification, OQ4 severity bump, OQ6 strengthening.

## 1. Coverage map — scope-register modules → PRD FR coverage

| # | Scope-register module | PRD FR coverage | Phase claim in PRD | Notes |
|---|---|---|---|---|
| 1 | Identity | FR-001, FR-007, FR-008, FR-009, FR-157 | P1a + P4 | Aligned with 2026-06-21 identity-inversion (SG optional) |
| 2 | Organizations | FR-002, FR-003, FR-004 | P1a | Aligned |
| 3 | Profiles | FR-006, FR-009, NFR-006 | P1a + P4 | Local-owned profile model aligned |
| 4 | Startups | **No direct FR** | — | **GAP: scope-register lists Startups (memberships, delegation) as canonical module #4; PRD has no FR addressing startup entities, memberships, or delegation as a distinct surface.** Implicit via "applicant" in UJ-2 only. |
| 5 | Programs | FR-010, FR-012, FR-013 | P1a | Aligned |
| 6 | Cohorts | FR-011 | P1a | Aligned (cohort = dated intake) |
| 7 | Stages | FR-012 (versioned artifacts); PRD §0 glossary defers stage-engine to P3+ | P3+ | Scope-register positions Stages as a canonical module (#7, build-spec `06`); PRD treats stages narrowly in P1a as published-artifact versioning and defers the configurable stage engine implicitly to later phases. **Possible divergence: stage engine has no explicit later-phase FR (not in FR-100…108).** |
| 8 | Forms | FR-020, FR-021, FR-022, FR-127 | P1a (minimal) + P3 (full builder) | Aligned |
| 9 | Applications | FR-030…034 | P1a | Aligned |
| 10 | Documents | FR-100 | P2 | Aligned |
| 11 | Assessments | FR-040, FR-041 | P1a | Aligned |
| 12 | Workflows | FR-101 | P2 | Aligned (declarative engine) |
| 13 | RoleAssignments | FR-102 | P2 | Aligned |
| 14 | Tasks | FR-103 | P2 | Aligned |
| 15 | Mentorship | FR-104 | P2 | Aligned |
| 16 | Training | FR-105 | P2 | Aligned |
| 17 | FinalEvaluation | FR-106 | P2 | Aligned |
| 18 | Graduation | FR-107 | P2 | Aligned |
| 19 | Notifications | FR-120 | P3 | Aligned (with P1a single log-transport consumer per FR-050) |
| 20 | Integrations | FR-121 (calendar) | P3 | Aligned |
| 21 | Reporting | FR-122 | P3 | Aligned |
| 22 | Search | FR-123 | P3 | Aligned |
| 23 | Administration | FR-124, FR-125 (public API/webhooks), FR-158 (impersonation) | P3 + P4 | Aligned |
| 24 | Audit | FR-052 (P1a enumerated set), FR-081 (dispute/reopen), FR-126 (platform-wide) | P1a + Reliability/Audit epic carve-out | FR-126 reclassified 2026-06-23 — now Required-initial via carve-out epic, not P3 |

## 2. Extended-scope coverage map (scope-register §Extended)

| Capability | Build-spec | PRD coverage | Phase |
|---|---|---|---|
| Interviews & live screening | `42` | FR-150 (bundled) | P4 |
| Public program pages & discovery | `43` | FR-150 (bundled) | P4 |
| Waitlists & conditional admission † | `43` | FR-150 (bundled) | P4 |
| Personalized tracks † | `06` | FR-108 | P2 |
| Partners, sponsors, funders | `44` | FR-151 (bundled) | P4 |
| Program finance & grants | `45` | FR-151 (bundled) | P4 |
| Timesheets & resource utilization | `46` | FR-151 (bundled) | P4 |
| Service requests & marketplace | `47` | FR-152 (bundled) | P4 |
| Messaging & collaboration | `48` | FR-152 (bundled) | P4 |
| Surveys & feedback (NPS) | `49` | FR-153 (bundled) | P4 |
| Hackathons & challenges | `50` | FR-153 (bundled) | P4 |
| Knowledge base | `51` | FR-153 (bundled) | P4 |
| Program simulation | `52` | FR-154 (bundled) | P4 |
| Configuration validation | `52` | FR-154 (bundled) | P4 |
| Outcomes & impact framework | `53` | FR-154 (bundled) | P4 |
| Risk & intervention | `54` | FR-154 (bundled) | P4 |
| Data lifecycle & privacy rights | `55` | **Partial — NFR-013 mentions PDPL + GDPR DSR; no explicit FR for data-lifecycle / privacy-rights surface** | — |
| Bulk operations & data quality | `56` | FR-155 (bundled) | P4 |
| Version migration | `57` | FR-155 (bundled) | P4 |
| Print & formal documents † | `29`-era | **No direct FR** | — |
| Support case management † | `28`-era | **No direct FR** | — |
| Achievements (trusted publication) | `02` | §9 Data Ownership footnote only ("Achievements flow tenant → Startup Gate via trusted publication"); no FR | — |

## 3. SaaS commercial scope coverage map

| Capability | Build-spec | PRD coverage | Phase |
|---|---|---|---|
| Versioned immutable plans | `58` | FR-130 | P3 |
| Feature entitlements (`EntitlementService`) | `58` | FR-060 (socket), FR-130 (production) | P1a + P3 |
| Usage metering & limits (server-side) | `60` | FR-061, FR-131, FR-062 | P1b + P3 |
| Subscription lifecycle (trials, dunning) | `59` | FR-130 (bundled) | P3 |
| Upgrades / downgrades / add-ons | `62` | FR-130 (bundled) | P3 |
| Geidea recurring billing + Hosted Payment Page | `61` | FR-071, FR-072, FR-073, FR-130 | P1b + P3 |
| SaaS administration | `63` | FR-124 (general admin) | P3 |
| SaaS security & testing | `64` | NFR-007, NFR-009 | cross-cutting |
| Billing & usage UX | `65` | FR-062 (banner), FR-130 (bundled) | P1a + P3 |
| Tenant subdomains & verified custom domains (TLS) | `66` | FR-132 | P3 |
| Tenant branding / white-label | `67` | FR-133 | P3 |

## 4. Cross-cutting build-spec coverage (scope-register §Build-spec index 25–33)

| ID | Capability | PRD coverage |
|---|---|---|
| 25 | Localization & Accessibility | NFR-011 + FR-156 (pre-split sub-FR) |
| 26 | Security Hardening | NFR-009 + FR-156 (pre-split sub-FR) |
| 27 | Observability & Operations | NFR-012 + FR-156 (pre-split sub-FR) |
| 28 | Data Migration, Import, Export | FR-156 (pre-split sub-FR) — and historically "support cases" lived here (scope-register marks `28`-era for Support); PRD does **not** carry support cases |
| 29 | Performance & Production Readiness | NFR-014 + FR-156 (pre-split sub-FR) — and historically "print/formal documents" lived here (scope-register marks `29`-era for Formal Documents); PRD does **not** carry formal documents |
| 30 | Real Startup Gate Cutover | **CONFLICT — see §7** |
| 31 | Integration Orchestration | Implicit in FR-121/FR-125; no explicit FR |
| 32 | Full System Integration Test | Not represented as an FR (test-level, not product surface) |
| 33 | Release Readiness Review | Not represented (process, not product) |

UX build-specs `34`–`41` are not represented as FRs in the PRD — they are UX/design tickets, expected to live in the UX spec, not the PRD.

## 5. Modules in scope-register with no FR coverage in PRD (silently dropped or implicit-only)

1. **Startups (canonical module #4, build-spec `04` — Startups, Memberships, Delegation).** The scope-register lists this as a top-line canonical module. The PRD never names "startup" as a first-class entity in §6 FRs, never defines startup membership, and never defines delegation. Implicit via the UJ-2 "applicant" role only.
2. **Achievements (trusted publication) (scope-register Extended, build-spec `02`).** Present only as a single sentence in PRD §9 Data Ownership ("Achievements flow tenant → Startup Gate via trusted publication"). No FR, no phase tag, no §6.13 bucket row.
3. **Data Lifecycle & Privacy Rights (scope-register Extended, build-spec `55`).** Partially covered by NFR-013 (PDPL + GDPR DSR baseline) but no FR for DSR endpoints, retention enforcement, deletion lifecycle, or privacy-rights surface.
4. **Print & Formal Documents † (scope-register Extended, `29`-era, marked brief-dropped-and-restored).** Scope-register explicitly restored this as in-scope; PRD has no FR.
5. **Support Case Management † (scope-register Extended, `28`-era, marked brief-dropped-and-restored).** Same — scope-register explicitly restored; PRD has no FR.
6. **Stages — configurable, versioned stage engine with entry/exit rules (canonical module #7, build-spec `06`).** PRD covers "stages are versioned published artifacts" via FR-012 and §0 glossary, but defers the engine without an explicit FR-100-class entry; FR-100…108 enumerate Documents/Workflow/RoleAssignments/Tasks/Mentorship/Training/FinalEvaluation/Graduation/PersonalizedTracks but not Stage Engine.

## 6. FRs in PRD that claim modules not in scope-register

None found. Every FR in PRD §6 maps to a module that exists in scope-register's canonical 24, Extended, SaaS, or cross-cutting list. Items like the **Reliability/Audit epic carve-out** (FR-126 reclassification) introduce a *delivery vehicle* not in scope-register, but the underlying capability (audit, outbox, idempotency, signed webhooks) is grounded in Audit (#24) + cross-cutting `26` + `27`.

## 7. Material disagreements between scope-register description and PRD FR

### [CONFLICT-1] Identity / Startup Gate authority

- **Scope-register (line 23):** "Identity — **Global identity via Startup Gate; `sub` as immutable user id**; auth." Build-specs `01` (Mock SG OIDC) and `30` (Real Startup Gate Cutover) reinforce SG as the identity authority and explicitly schedule a "cutover."
- **PRD §1, §6.1 FR-001/007/008/009, §9, FR-157, NFR-002:** "**Catalesta owns identity** — native registration, authentication, and locally-owned multi-role profiles; the Account id (ULID) is the immutable user key … Startup Gate is an **optional** linked identity provider … never the system of record." FR-157 explicitly says "no authority cutover — SG never becomes the system of record."
- **Status:** Auto-memory architecture-decisions §6 records the inversion (Catalesta system of record / SG optional SSO+import). **Scope-register has not been updated** to reflect that inversion. The scope-register's module #1 description and build-spec `30` ("Real Startup Gate Cutover") are stale relative to the ratified inversion.

### [CONFLICT-2] Stage Engine deferral phase

- **Scope-register (#7, build-spec `06`):** "Configurable, versioned stage engine; entry/exit rules" — listed as a canonical module on par with Programs/Cohorts/Forms.
- **PRD §6 + §0 glossary:** Phase 1a only exercises the Application/Selection stage. The configurable stage engine with entry/exit rules has **no enumerated FR** in P2 (FR-100…108) or P3 (FR-120…127). FR-101 covers Workflow engine; stage engine per se is not enumerated.
- **Status:** Possible silent deferral with no FR home. PRD does not bucket "stage engine" in §6.13.

### [CONFLICT-3] Personalized Tracks build-spec assignment

- **Scope-register (Extended row, line 68):** "Personalized tracks † → build-spec `06`" (i.e. delivered alongside the stage engine).
- **PRD FR-108:** Personalized tracks listed under P2 alongside Mentorship/Training/Graduation, not tied to the stage-engine build-spec.
- **Status:** Minor sequencing divergence — scope-register couples Personalized Tracks to the stage engine; PRD couples it to delivery-core capability cluster.

### [CONFLICT-4] Phase placement — Audit (FR-126)

- **Scope-register:** Audit is canonical module #24 (build-spec `24`); scope-register does not assign phase placement (that is roadmap's role).
- **PRD §6.13 + §7 notes:** FR-126 (platform-wide audit enforced) **reclassified 2026-06-23 from P3 to a new Reliability/Audit epic** inserted before P2. The new epic owns outbox + idempotency + audit-enforced + signed-webhooks.
- **Status:** Not a scope-register-vs-PRD conflict per se (scope-register is silent on phase), but the new Reliability/Audit epic name does not yet appear in scope-register, the build-spec index `00`–`68`, or `docs/plan/roadmap.md`. Cross-doc SSOT integrity risk.

## 8. Phase-placement divergences

- **Scope-register** has no phase assignments — it is explicit ("does not decide build order — that is roadmap.md"). Phase comparison therefore goes PRD ⇄ roadmap, not PRD ⇄ scope-register. **OQ9 in PRD §10** already tracks the PRD-vs-roadmap divergence (Phase 1 = "Selection MVP + billing" in roadmap vs split 1a/1b in PRD) and bumps it to High severity 2026-06-23. Out of scope for this reconciliation extract.

## 9. Items the scope-register treats as in-scope that the PRD silently demotes

| Item | Scope-register signal | PRD signal |
|---|---|---|
| Startups module (memberships, delegation) | Canonical #4 | No FR; not in §6.13 buckets |
| Achievements (trusted publication) | Extended scope row + flagged on canonical Profiles module via build-spec `02` | Single sentence in §9; no FR, not bucketed in §6.13 |
| Print & Formal Documents † | Extended scope row, marked brief-dropped-restored | No FR |
| Support Case Management † | Extended scope row, marked brief-dropped-restored | No FR |
| Data Lifecycle & Privacy Rights | Extended scope row, build-spec `55` | Only NFR-013 baseline; no FR |
| Configurable Stage Engine (entry/exit rules) | Canonical #7 | No FR for the *engine*; FR-012 covers versioning of published stage artifacts only |

## 10. Items the PRD treats as in-scope that the scope-register does not call out

- **Reliability Substrate primitives** (FR-050 outbox, FR-051 idempotency, FR-052 audited-action set, FR-060 entitlement socket) are PRD-level invariants framed as Phase-1a-substrate. Scope-register has no equivalent "reliability substrate" row — outbox/idempotency/audit are implicit under Audit (#24) + cross-cutting build-specs `26`/`27`. Not necessarily a gap (these are NFR-class engineering substrate), but they have no scope-register home.
- **Six-Bucket Scope Classification (§6.13)** is a PRD overlay, not in scope-register. Expected — overlay is the PRD's own framing.
- **Database Topology (§9.1, pending ADR-0005)** is architecture, not scope. Out of scope-register's mandate.

---

## 11. Summary roll-up

- **Modules silently dropped from PRD vs scope-register:** 6 (Startups, Achievements, Data-Lifecycle/Privacy-Rights, Formal Documents, Support Cases, Stage Engine).
- **FRs claiming modules not in scope-register:** 0.
- **Material name divergences:** 1 known-and-resolved (cohorts vs program_cycles, resolved to "cohorts" per auto-memory §1).
- **Material description disagreements:** 1 unresolved [CONFLICT-1] (Identity authority — scope-register stale on the SG-inversion), 1 possible [CONFLICT-2] (Stage Engine has no FR home), 1 minor [CONFLICT-3] (Personalized Tracks build-spec coupling).
- **Phase divergences:** routed via OQ9 (PRD ⇄ roadmap), not in scope here.
- **Cross-doc SSOT risk:** [CONFLICT-4] (Reliability/Audit epic carve-out not yet in scope-register / build-spec index / roadmap).
