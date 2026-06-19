# Catalesta — Scope Validation

**Status:** Validated design (rev. 2 — architecture review folded in) · **Date:** 2026-06-18 · **Owner:** Product
**Validation target:** Approach **A** — literal full scope (build all 68 prompts before launch)
**Binding constraint:** Flagship completeness (a marquee design partner / tender must see a complete platform at launch)
**Inputs:** `prompts/INDEX.md` (68 prompts), `docs/00-master-scope.md`, `docs/02-domain-boundaries.md`, `docs/30-saas-commercial-architecture.md`, `docs/product-brief.md`, direct reads of prompts `01`, `02`, `03`, `10`, `18`, `27`, and an independent senior **software-architecture review** (source/migration/test-verified, 2026-06-18).

> This document records the outcome of a full scope-validation pass across four lenses — the bet, completeness, redundancy, and decomposition — under the explicit decision to ship the full 68-prompt scope as v1. Gap claims were verified against the actual prompt files, not inferred. **Rev. 2** folds in an independent architecture review that verified findings against source, migrations, and tests; its corrections (build-state reality, a fifth substrate, the fail-open tenancy risk, and the `docs/07`-vs-`docs/09` reframing) are integrated below and marked _[arch review]_.

---

## 1. Bottom-line verdict

**Full scope as v1 is a valid bet under the flagship-completeness constraint — APPROVED — contingent on the corrections below.** The architecture review confirms the bet but resets the starting line: see §1a.

1. **Replace the single numeric waterfall with ~11 independently-spec'd sub-projects** plus named demo-able checkpoints (Lens 4). The size of scope is not the risk; `docs/07`'s *"execute each prompt in numeric order"* as one continuous chain is — and it ignores the parallel tracks `docs/09` already documents _[arch review]_.
2. **Close the verified completeness gaps** (Lens 2): disaster recovery and federated tenant SSO (confirmed — SSO severity raised to a flagship go/no-go gate), plus a minor COI-recusal / score-normalization wording fix.
3. **Stand up five shared substrates** (Lens 3) — the original three (comms, bulk-data, reporting) plus the two highest-leverage seams the first review missed: the **outbox/idempotency reliability backbone** and the **entitlement-enforcement seam** _[arch review]_. (The versioning kernel is already built — consume it, don't re-define it.)
4. **Fix the fail-open tenant-isolation model before building more modules** (§1a / §6) _[arch review]_ — this is an architectural release-gate item, not a scope item, but it gates everything built after it.

Without these, the scope is *achievable but fragile*. With them, it is *defensible and inspection-ready*.

---

## 1a. Build-state reality (the starting line) _[arch review]_

The scope bet is being judged against a *plan*; the architecture review verified the *code*. The gap is material and must frame all sequencing:

- **~6 of 24 modules exist** in code (Identity, Organizations, Programs, Cohorts, plus Shared Tenancy / Versioning / Audit). Migrations stop at `cohorts`.
- **Stages is not built** — no model, no migration — despite the branch being named `phase-2-programs-stages`. Phase 2 delivered Programs + Cohorts only.
- **0% of the SaaS control plane** exists (Plans, Subscriptions, Entitlements, Usage, Geidea, Domains, Branding are docs-only / `.gitkeep`).
- Therefore **every sub-project past #2 in §5 is greenfield**, and the flagship-completeness clock is much further from zero than "Phase 1 done, Phase 2 in progress" implies.

Implication: the cheap window to fix cross-cutting structure (tenancy, reliability/entitlement seams) is **now**, while there are 6 modules to migrate rather than 24.

---

## 2. Lens 1 — The bet (accept, but reframe the unit of work)

Under flagship completeness with scope fixed, building all 68 is defensible: a marquee tender rewards looking complete, and the org has chosen to pay for it. The bet is sound; **the framing is the risk.**

- The hazard is not scope *size* — it is treating the 68 as one continuous waterfall (`docs/07`). A slip anywhere cascades to the launch date, and there is no live signal until the very end.
- **Sharpened by the architecture review:** the parallelism is not *missing* — `docs/09` already contains a "Parallelizable After Core Foundation" section identifying parallel tracks. The defect is that `docs/07` (the *governing* instruction — literally "execute each prompt in numeric order") **ignores the parallel structure `docs/09` already describes.** The fix is to make `docs/07` honor `docs/09`, not to invent parallelism from scratch.
- Sub-project boundaries must be drawn on **contract seams** — and those seams (typed domain events, outbox) do not yet exist in code (§6, I2). The decomposition is only as real as the seams underneath it.
- **Correction:** keep full scope; execute as sub-projects (§5) with internal *demo-able* checkpoints. Even if nothing is sold early, proving the lifecycle spine works months before the extended modules land de-risks the flagship date.

---

## 3. Lens 2 — Completeness (verified against prompt files)

The 68 cover the accelerator/incubator lifecycle well. Gap claims were checked against the actual prompts:

| Candidate gap | Verified in | Verdict |
|---|---|---|
| **Disaster recovery / backup / restore / RPO-RTO / recovery testing** | `prompts/27` goal lists logs, metrics, tracing, health/readiness checks, queue + failed-job tooling, alerts, dashboards, runbooks — **observability only** | ✅ **CONFIRMED GAP** — standard government/corporate tender line item; absent from the catalog |
| **Federated tenant-staff SSO (SAML / external IdP / Azure AD)** | `prompts/01-03` + source: no IdP-brokering seam exists in code; identity flows **exclusively** through Startup Gate OIDC | 🔴 **CONFIRMED GAP — severity RAISED to flagship go/no-go** _[arch review]_. A government/corporate accelerator that must log in via Azure AD/SAML cannot. "Startup Gate might broker it upstream" is an **external dependency the team does not control** — decide *before* sub-project sequencing, because if it lands in platform scope it changes the identity architecture |
| **Evaluator conflict-of-interest recusal + cross-evaluator score normalization** | `prompts/10` goal already names **blind review, aggregation, disqualification, audit** | ⚠️ **MINOR** — blind review & aggregation are covered; only COI-recusal (assignment-time exclusion) and normalization (adjusting for harsh/lenient scorers) are unstated. One-line prompt addition, not a missing subsystem |
| **Native mobile app** | `prompts/40` is responsive/mobile **web** | ➖ **CONSCIOUS EXCLUSION** — confirm and prepare the tender answer; not a true gap |
| **WhatsApp / SMS comms channels** | `prompts/18` goal explicitly lists "in-app, email, push, **SMS, WhatsApp** adapters" | ❌ **RETRACTED** — in scope; original inference was wrong |

**Net:** the catalog is in better shape than first estimated. Two real gaps to close — **federated SSO (go/no-go for the flagship)** and DR — one minor wording fix (COI/normalization), one exclusion to confirm (mobile app). Note these are *catalog* gaps; the more serious gaps are *architectural* (§6).

---

## 4. Lens 3 — Redundancy (unify substrates, do not cut modules)

Scope is fixed, so nothing is removed — but ~12 of the 68 prompts build overlapping machinery. Building them independently yields divergent stacks (three feedback systems, two messaging layers). **Fix by standing up shared substrates once, then having modules ride them.** The first review named four; the architecture review corrected the list — the versioning kernel is **already built** (a substrate to *consume*, not define), and the two most load-bearing seams were missing. The corrected set is **five**:

1. **Communications substrate** — Notifications/Comms (`18`) + Messaging/Collaboration (`48`) + Survey/NPS delivery (`49`) share one channel layer (in-app/email/push/SMS/WhatsApp), not three.
2. **Bulk-data engine** — Data Migration/Import-Export (`28`) + Bulk Operations/Data Quality (`56`) + Version Migration (`57`) are one engine in three roles.
3. **Reporting/analytics substrate** — Reporting/Dashboards (`20`) + Outcomes/Impact (`53`) + Usability Analytics (`41`) + Surveys/NPS (`49`) share one query/aggregation layer.
4. **Reliability backbone — transactional outbox + inbound idempotency keys** _[arch review, NEW]_. This is the substrate that makes the modular monolith decoupled and makes Geidea callbacks idempotent (SaaS rule 7). It is **docs-only today** — no `Shared/Outbox`, no `Shared/Idempotency`, no relay. Every module's write path touches it; retrofitting across 20+ modules later is the single most expensive cross-cutting rework. Define it now (6 modules to wire, not 24).
5. **Entitlement-enforcement seam — `EntitlementService` / `UsageMeter` / `SubscriptionGuard`** _[arch review, NEW]_. Must be consulted server-side by *every* tenant-owned write path. Stub the interfaces + middleware/policy hook points now — exactly as the Identity provider and tenancy seams were established up front — even if the billing backend (sub-project 11) lands last.

**Already built — consume, don't re-define:** the **versioning kernel** (`backend/app/Shared/Versioning/`, 5 files, fully unit-tested). Its risk is the *opposite* of a redundancy risk — it exists with **zero production consumers** (only a test-only `FakeVersion` model). Wiring it to the first real artifact (Stages) is a §6 action, not a substrate to create.

**Doc-of-record overlap (from the PM review, still open):** `docs/13–29` (UX) restates prompts `34–41`; `docs/30–36` (SaaS) restates prompts `58–68`. Designate one as canonical per topic to prevent drift. The architecture review found this drift is already real — the knowledge graph references pre-re-baseline doc names.

This remains the highest-leverage finding: it reduces build effort and divergence risk while preserving the full scope. The reliability + entitlement seams (4, 5) outrank the reporting substrate in priority because every module touches them.

---

## 5. Lens 4 — Decomposition (the 68 are ~11 sub-projects, not one spec)

A project this size cannot be a single spec/plan. The prompts already cluster into independently spec-able, interface-bounded sub-projects; cross-project contracts already exist in the `docs/10` event catalog. Each sub-project gets its own spec → plan → implementation cycle.

| # | Sub-project | Prompts | Status |
|---|-------------|---------|--------|
| 1 | Platform Foundation (identity, tenancy, RBAC, startups) | 00–04 | ✅ Phase 1 done |
| 2 | Program Configuration Kernel (+ versioning kernel) | 05–07 | 🔄 Phase 2 in progress |
| 3 | Application & Selection (+ COI-recusal / normalization fix) | 08–12 | next |
| 4 | Program Delivery (tasks, mentorship, training, final eval, graduation/alumni) | 13–17 | |
| 5 | Platform Services & Comms (comms substrate, calendar, reporting substrate, search) | 18–21 | |
| 6 | Governance & Integration (admin, API, audit, security, observability; **+ DR gap**) | 22–24, 26–27 | |
| 7 | Data & Production Readiness (unified bulk-data engine; performance) | 28–29, 56–57 | |
| 8 | Startup Gate Cutover + Integration / Release gates | 30–33 | |
| 9 | Experience Layer (parallelizable) | 34–41 | |
| 10 | Extended Capabilities (each a flagged mini-project, sequenced last) | 42–55 | |
| 11 | SaaS Commercial Plane (partly parallel) | 58–68 | |

**Flagship demo checkpoint** = sub-projects 1–6 + 9 + the minimal SaaS slice of 11 — the "demonstrably complete" spine a tender inspects. Sub-projects 7, 8, 10, and the remainder of 11 complete the full bet.

**Localization/accessibility note:** `25-localization-accessibility` (Arabic/English + RTL, WCAG 2.2 AA) is cross-cutting and must be a standing acceptance criterion in every sub-project, not a late single prompt.

---

## 6. Architectural reality & risks _[arch review]_

The four-lens pass validated the *plan*; the architecture review verified the *code* and surfaced risks the scope pass could not. Verdict from that review: **sound patterns and a trustworthy built slice, but the most load-bearing invariant is structurally fail-open, and the seams that make the modular monolith work do not exist yet.** These are architectural, not scope, but they gate the scope.

**CRITICAL — fix before building more modules:**
- **C1. Tenant isolation is opt-in and fails OPEN.** ✅ **RESOLVED (2026-06-19).** Implemented fail-closed isolation: `BelongsToTenant` trait enforces global scope always-on (queries without resolved tenant return no rows; writes throw `TenantContextMissingException`). Architecture test `tests/Architecture/TenantIsolationArchTest.php` enforces the trait on every tenant-owned model. Cross-tenant access explicit via `TenantContext::runAsSystem(callable)`. See `docs/tenancy.md`.
- **C2. Hostname tenant resolution / unknown-host rejection is absent.** `ResolveTenant.php:27` resolves only from the `X-Organization-Id` header; `config/tenancy.php` defines nothing else. Rule 10 makes "reject unknown hosts" non-negotiable — treat as a release-gate **security** item, not a late custom-domain prompt.
- **C3. `$guarded = []` (full mass-assignment) on `Program` and `Cohort`** ✅ **RESOLVED (2026-06-19).** Explicit `$fillable` lists implemented; `organization_id` never mass-assignable. Assigned server-side from `app(\App\Shared\Tenancy\TenantContext::class)->organizationId()` on model create. See `docs/tenancy.md`.

**IMPORTANT:**
- **Reliability + entitlement seams are docs-only** — see Lens 3 substrates 4 & 5. Highest cross-cutting rework risk.
- **Modules are coupled by direct model access, not contracts** — e.g. `ResolveTenant.php:36` queries `OrganizationMembership` directly; tests/controllers reach across `App\Modules\*\Domain\Models\*`. No internal typed/versioned domain events despite the `docs/10` catalog. At 6 modules this is fine; at 24 with no enforced seam, the boundaries the docs promise won't exist. **The sub-project decomposition assumes contract isolation that isn't enforced yet.**
- **Consent is fetchable but not enforced as a gate** (`MeController.php:57-95` doesn't inject `ConsentProvider`). Defensible now (`/me/*` is self-read); invariant #11 unmet for the cross-user reads (mentor↔founder, evaluator↔applicant) that don't exist yet. Make it a standing acceptance criterion.
- **Audit is opt-in per call, not enforced** (manual `AuditLogger` invocation; `Modules/Audit` empty). Coverage will drift as modules grow.
- **Versioning kernel & decimal scoring have zero production consumers** — `brick/math` is a dependency with no code use; invariant #9 (decimal scoring) is entirely unproven. Wire to Stages to prove #8/#9 end-to-end before replicating 4×.

**OPERATIONAL:**
- **The knowledge graph is stale and violates project rule 7** — built after the "re-baseline to Full Platform Kit" commit but still referencing pre-re-baseline names (`docs/05-modules.md`, `docs/14-delivery-roadmap.md`). Anyone following the mandated graph-first protocol is oriented against a structure that no longer exists. Regenerate it.

---

## 7. Required corrections (actionable)

Ordered by leverage. The first three are architectural and **must precede accelerated module-building** _[arch review]_.

1. **Harden the tenant-isolation model (C1/C2/C3)** _[arch review]_ — base `TenantModel`; architecture test enforcing the trait on every tenant-owned model; fail-closed scoping outside an audited platform-admin/system allowlist for `withoutGlobalScope('tenant')`; hostname resolution + unknown-host rejection in the tenancy layer. **Cheapest now (6 modules), very expensive later (24).**
2. **Stand up the reliability + entitlement seams now** (Lens 3 substrates 4 & 5) _[arch review]_ — transactional outbox + inbound idempotency keys, and `EntitlementService`/`UsageMeter`/`SubscriptionGuard` interfaces + middleware/policy hook points, even though the billing backend lands last.
3. **Decide the inter-module contract style** _[arch review]_ — typed domain events via the outbox vs. direct service calls with published interfaces — and draw sub-project boundaries on those seams. This decides whether the "modular monolith" is real.
4. **Wire the versioning kernel + decimal scoring to Stages** _[arch review]_ — prove invariants #8/#9 end-to-end on this branch's namesake artifact before replicating across forms/assessments/workflows.
5. **Make tenant-isolation, consent-on-cross-user-read, audit-on-write, and localization/RTL standing acceptance criteria** in every sub-project spec — enforced by structure, not discipline.
6. **Scope/process:** restate `docs/07-delivery-roadmap.md` as the ~11 sub-projects above honoring `docs/09`'s existing parallel tracks, each with a spec→plan cycle and demo-able checkpoint; retire "execute in numeric order" as the governing instruction.
7. **Add DR scope:** backup/restore/DR requirement (RPO/RTO targets, recovery testing) — extend `prompts/27` or add a dedicated prompt in sub-project 6.
8. **Resolve federated SSO (go/no-go):** decide whether tenant-staff external IdP (SAML/OIDC) is (a) brokered by Startup Gate upstream, or (b) added to platform scope — *before* sequencing, as it can change the identity architecture.
9. **Tighten `prompts/10`:** add COI-recusal at assignment time and cross-evaluator score normalization to the goal line.
10. **Confirm mobile-app exclusion** and prepare the tender answer (responsive web only).
11. **Designate doc-of-record** for the UX (`13–29` vs `34–41`) and SaaS (`30–36` vs `58–68`) overlaps; **regenerate the stale knowledge graph** to match the re-baselined doc pack (project rule 7).

---

## 8. Open questions for stakeholders
1. **Fail-open vs fail-closed tenancy** in non-request contexts (queue/console): what is the sanctioned pattern for system jobs that legitimately span tenants? _[arch review]_
2. **Federated SSO ownership:** Startup Gate upstream, or platform scope? (Gates corporate/government flagships.)
3. **Inter-module contract style:** in-process typed domain events via the outbox, or direct service calls with published interfaces? Decides whether the modular monolith is real. _[arch review]_
4. **Where the entitlement check lives:** middleware, policy layer, or service-level guard — and how it composes with the existing `TenantContext::can()`. _[arch review]_
5. **DR targets:** what RPO/RTO does the flagship/tender require?
6. **Doc-of-record:** which file wins when UX/SaaS docs and prompts disagree? (Drift is already real — see the stale graph.)
7. **Demo checkpoint date:** when must the "demonstrably complete" spine (sub-projects 1–6 + 9 + minimal SaaS) be live for the flagship?
8. **Mobile app:** is responsive web acceptable to the target flagship, or is a native app a tender requirement?

---

### Verification note
Gap findings in §3 were verified by reading `prompts/01`, `02`, `03`, `10`, `18`, `27` directly. Two original gap claims (WhatsApp/SMS; COI/blind-review/aggregation) were corrected after verification. **Rev. 2** integrates an independent senior software-architecture review whose findings (§1a, §4 substrates 4–5, §6) were verified against source, migrations, and tests — key files cited inline (`BelongsToTenant.php`, `ResolveTenant.php`, `config/tenancy.php`, `Program.php`, `Cohort.php`, `MeController.php`, `Shared/Versioning/*`). No source files were modified during this validation or its revision.
