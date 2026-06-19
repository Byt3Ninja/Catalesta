# Scope & Plan Validation Review — Catalesta

**Date:** 2026-06-19
**Reviewer:** Claude (multi-agent: code-state audit + plan-coherence + scope-coherence)
**Type:** Review only — no scope or planning artifacts were modified.

This review validates the **product scope** and the **product plan** against the
actual codebase. Sources: `docs/` (36 numbered docs + brief + master-scope),
`prompts/` (68 build specs), `docs/superpowers/{plans,specs}`, and the
implemented `backend/`.

---

## 1. Code reality vs. stated scope

Business-logic file counts under `backend/app/Modules/*`:

| Status | Modules |
|---|---|
| **Real logic** | Identity (21 files), Programs (22), Stages (30), Organizations (16), Cohorts (7) |
| **Empty scaffold** | Profiles, Startups, Forms, Applications, Documents, Assessments, Workflows, RoleAssignments, Tasks, Mentorship, Training, FinalEvaluation, Graduation, Reporting, Integrations, Audit (16 modules, 0 files) |
| **SaaS/commercial layer** | 0 code anywhere (Plans, Entitlements, Subscriptions, Geidea, Domains, Branding — docs only) |
| **Frontend** | ~8 TS files — scaffold, not feature pages |

Totals: 12 Application actions, 26 domain models, 11 controllers, 26 migrations,
28 feature tests.

**Headline:** What's built is the *configuration kernel* (programs / cohorts /
stages) on top of *foundation* (identity / org). The product's stated **core
value — the participant lifecycle** (Application → Eligibility → Evaluation →
Mentorship → Training → Final Evaluation → Graduation, `docs/00` line 7) — has
**zero code**, as does the entire SaaS commercial plane. Roughly **5 of ~24
modules + 0% SaaS** carry logic.

---

## 2. Plan coverage vs. scope

Four plans exist and cover **prompts ~00–07 of 68**:

| Plan | Covers | Quality |
|---|---|---|
| Phase 1 — identity/tenancy | prompts 00–04 | exemplary; **no rollback/risk section** |
| Phase 2 — programs/stages | prompts 05–07 | exemplary; **no rollback/risk section** |
| Tenancy hardening | cross-cutting (scope-validation C1/C3) | full protocol incl. rollback |
| Phase 2 completion (9 tasks) | tracks/dependencies/archival | **best-specified of the set** |

**~60 of 68 prompts (9 of 11 sub-projects) have no spec or plan**, including the
entire participant lifecycle (Forms, Applications, Assessments, Mentorship,
Training, Graduation), Notifications/Reporting/Search/Admin/Audit-enforcement,
**all** SaaS/billing, and the two cross-cutting substrates flagged as
"build now, expensive to retrofit": the **reliability backbone**
(transactional outbox + idempotency) and the **entitlement-enforcement seam**.

### Phase 2 completion plan — specific flags
- `DeleteTrack` is **unsafe between Task 3 and Task 5** (cascade logic is appended
  in Tasks 4–5). Treat Tasks 3–5 as one atomic unit.
- Task 6 changes `AdvanceParticipantStage`'s constructor — verify every
  construction site (hidden-coupling risk).
- Task 7 (archived guard) must be wired into ~10 mutating paths across three
  modules — most likely task to miss a path.

---

## 3. Plan-coherence defects (the real risks — around the plans, not in them)

1. **Roadmap contradicts the approved plan-of-record.** `docs/07-delivery-roadmap.md`
   is a 5-line stub ("execute prompts in numeric order"); the approved
   `scope-validation-design.md` says to retire that for **11 parallel
   sub-projects**. Never reconciled. *Single biggest planning defect.*
2. **`ProgramStatus` enum-vs-string ambiguity** threads through three artifacts;
   the completion plan papers over it at execution time.
3. **No plan for the two load-bearing substrates** (outbox/idempotency,
   entitlement seam) — cheapest now, most expensive later.
4. **Consent enforcement (CLAUDE rule 11) is partial/unenforced**; **Audit module
   is empty** (drift against rules 7/14).
5. **Stale knowledge graph** — `graphify-out/` references renamed/dead doc names,
   yet the repo *mandates* graph-first navigation.
6. **DR/backup and federated SSO** are confirmed flagship-blocking gaps, no plan.

---

## 4. Scope-definition defects (governance, not architecture)

Decomposition is **strong** (`docs/09-dependency-graph.md` hard deps + parallel
bands; `docs/12-release-gates.md` 6 sellable-increment gates). The problems are
upstream:

- **No single source of truth.** Scope is *triplicated* — `00-master-scope.md` ↔
  `product-brief.md` (68-item catalog) ↔ `prompts/INDEX.md` — plus 36 topic docs.
  The three lists are **not identical**.
- **Three numbering schemes** for the same 68 units (`prompts/INDEX.md`,
  `docs/09`, brief catalog) — a bare "prompt 12" is ambiguous.
- **Module count unsettled:** 24 (CLAUDE.md / `docs/02`) vs 20 (saved architecture
  decision).
- **Extended-scope membership disagrees:** waitlists, personalized tracks,
  print/formal docs, support-case management are in `00-master-scope` but
  **missing from the brief catalog**.
- **Undefined scope items:** *personalized tracks* (vaguest), *service
  marketplace*, *"trusted publication"* of achievements (the only tenant→Startup-
  Gate flow), *white-label/branding levels*, *"restricted"* subscription status.
- Correction: *configuration validation* is well-defined in `docs/27`; it's
  *program simulation* next to it that's under-specified.

### Production-SaaS gaps the scope omits entirely
Admin/staff **impersonation + its audit** (high security/audit risk, unmentioned);
**platform availability SLA** (exists only as a plan dimension); **full tenant
offboarding** (domain release, cert revocation, billing closeout, Startup-Gate
de-linking); **data-residency** (unratified open question — material for MENA-
first); **secrets rotation/vault**; **email deliverability/DKIM-SPF** for branded
senders; **trial/payment-fraud controls**.

---

## 5. Bottom line

The **engineering is sound and the seams are clean**; the four written plans are
high quality. The risk is **governance, not architecture**:

1. **No ratified plan-of-record** — roadmap stub vs approved 11-sub-project spec,
   never reconciled.
2. **No single authoritative scope doc** and **no ratified MVP cut line** — "full
   68-unit scope" remains an all-or-nothing commitment.
3. **~75% of modules and 100% of the SaaS plane are unbuilt**, including the
   participant lifecycle that *is* the product.

---

## 6. Recommendations (highest leverage first)

1. **Reconcile `07-delivery-roadmap.md` with the scope-validation spec** into one
   ratified plan-of-record (the 11 sub-projects) with an explicit
   **first-sellable-slice MVP boundary** (brief Open Question #2: signup → publish
   program → applications → selection → billing). Everything sequences off this.
2. **Designate one authoritative scope register** (likely `00-master-scope.md`);
   make the brief catalog and `prompts/INDEX.md` reference it, not restate it.
   Reconcile module count (20 vs 24) and extended-scope membership.
3. **Resolve `ProgramStatus` enum-vs-string** before continuing phase2-completion;
   treat its Tasks 3–5 as one atomic unit.
4. **Plan the two cross-cutting substrates** (outbox/idempotency, entitlement
   seam) before more feature modules.
5. **Then build the participant lifecycle** (Applications/Forms/Assessments);
   defer SaaS billing + extended capabilities.
6. **Regenerate the graphify graph; close consent + audit enforcement.**
7. **Define the undefined items** and close the production-SaaS omissions
   (impersonation+audit, SLA, offboarding, data-residency, retention values).
