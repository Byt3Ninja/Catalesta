# Design — Documentation Restructure & Single Source of Truth

**Date:** 2026-06-19
**Status:** Approved design (pending user spec review → writing-plans)
**Phase:** Planning / documentation only — **no code is written or changed.**
**Driver:** `docs/superpowers/specs/2026-06-19-scope-plan-review.md` (scope + plan validation).

---

## 1. Problem

The project has **no single source of truth**. Scope is *triplicated* across
`00-master-scope.md`, `product-brief.md` (restates the full 68-item catalog), and
`prompts/INDEX.md` (68 prompts in a different order), with the 36 numbered docs as
a fourth expansion. They have already drifted:

- **Three numbering schemes** for the same 68 build units (`prompts/INDEX.md`,
  `09-dependency-graph.md`, brief catalog) — a bare "prompt 12" is ambiguous.
- **Module count** is 24 in `CLAUDE.md` / `02-domain-boundaries.md` but 20 in the
  saved architecture decision.
- **Extended-scope membership disagrees:** waitlists, personalized tracks,
  print/formal docs, support cases are in `00-master-scope` but absent from the
  brief catalog.
- `07-delivery-roadmap.md` (5-line stub, "execute prompts in numeric order")
  contradicts the approved scope-validation spec ("11 parallel sub-projects").
- Undefined scope items (personalized tracks, service marketplace, trusted
  publication, white-label levels, restricted status) and missing production-SaaS
  docs (DR/RPO-RTO, tenant offboarding, admin impersonation + audit,
  data-residency, secrets rotation, retention values, email deliverability).

A reader cannot trust any single doc as authoritative.

## 2. Goals / Non-goals

**Goals**
- One **canonical scope register** — the only place the functional surface is *defined*.
- One **canonical plan-of-record** — the only place the build sequence is *decided*.
- Every other doc **references** those two and restates nothing.
- A clean, semantic documentation tree that is **easy to follow and update**.
- **All required product docs exist** (gaps filled, no TBD).
- **Intent separated from as-built status** (status was leaking into scope docs).
- The graphify knowledge graph regenerated against the new tree.

**Non-goals**
- No code, migrations, or implementation. (Treat the codebase as if it does not exist.)
- Not redesigning features beyond *defining* the currently-undefined scope items at
  register level.
- Not changing the actual product scope — only how it is recorded and governed.

## 3. Single-source-of-truth model

Two non-overlapping authorities, each owning one question:

| Authority | File | Owns | Everyone else… |
|---|---|---|---|
| **Scope register** | `docs/product/scope-register.md` | *What* exists — canonical module list, full functional surface, one ID scheme | links to it; never re-defines scope |
| **Plan-of-record** | `docs/plan/roadmap.md` | *When / in what order* — phases, MVP cut line, deferred backlog | links to it; never re-decides sequence |

**Rules that keep it true (in `docs/README.md`):**
1. Changing scope → edit the **register first**; downstream docs reference it.
2. Changing sequence → edit the **roadmap first**.
3. Scope/plan docs carry **no implementation status** — that lives only in `status/`.
4. **No global filename numbers.** Semantic folders + descriptive kebab-case names.
   Ordering lives in the roadmap; build-spec IDs derive from the register.
5. Every doc has a header block: **Owner · Last-updated · Source-of-truth link**.
6. `docs/README.md` lists every doc exactly once (the map).

## 4. Target tree

```
docs/
  README.md                         # doc map, conventions, update rules
  product/                          # WHAT we build (intent)
    scope-register.md               # ★ canonical source of truth
    product-brief.md                # positioning; references register
    lifecycle.md                    # core participant lifecycle
    data-residency-retention.md     # NEW: residency decision + retention values
    features/                       # one file per extended capability
  architecture/                     # HOW it's built
    overview.md  domain-boundaries.md  data-ownership.md
    security-baseline.md            # + NEW secrets-rotation/vault section
    shared-contracts.md  integration-strategy.md
    tenancy-isolation.md  data-privacy-rights.md
    devops-observability.md
    resilience-dr.md                # NEW: RPO/RTO, backup/restore, tenant offboarding
    admin-impersonation-audit.md    # NEW
  saas/
    commercial-architecture.md  plans-entitlements-usage.md
    subscriptions-billing.md        # + NEW restricted-status definition
    geidea-payments.md
    domains-branding.md             # + NEW email-deliverability (DKIM/SPF)
    white-label-levels.md           # NEW
    security-testing.md
  ux/
    strategy.md  design-system.md  navigation.md  onboarding.md
    forms-application.md  dashboard.md  responsive-mobile.md
    usability-analytics.md  saas-billing-ux.md
  quality/
    testing-strategy.md  integration-testing.md
  plan/                             # WHEN / in what order
    roadmap.md                      # ★ canonical plan-of-record
    dependency-graph.md  release-gates.md
    build-specs/                    # the 68 prompts, re-keyed to the register
      README.md
    phases/                         # engineering plans + specs (today's superpowers/)
  status/                           # AS-BUILT (separated from intent)
    implementation-status.md        # NEW: consolidated as-built index
    phase-2-notes.md  bootstrap.md
```

## 5. File mapping (move / rename / merge)

Moves use `git mv` to preserve history. All cross-references rewritten.

| Today | New home |
|---|---|
| `00-master-scope.md` | `product/scope-register.md` (upgraded to canonical) |
| `product-brief.md` | `product/product-brief.md` (dedup → references register) |
| `01-architecture.md` | `architecture/overview.md` |
| `02-domain-boundaries.md` | `architecture/domain-boundaries.md` |
| `03-data-ownership.md` | `architecture/data-ownership.md` |
| `04-security-baseline.md` | `architecture/security-baseline.md` (+ secrets rotation) |
| `05-testing-strategy.md` | `quality/testing-strategy.md` |
| `06-devops.md` | `architecture/devops-observability.md` |
| `07-delivery-roadmap.md` | `plan/roadmap.md` (rewritten as plan-of-record) |
| `08-integration-strategy.md` | `architecture/integration-strategy.md` |
| `09-dependency-graph.md` | `plan/dependency-graph.md` |
| `10-shared-contracts.md` | `architecture/shared-contracts.md` |
| `11-integration-testing.md` | `quality/integration-testing.md` |
| `12-release-gates.md` | `plan/release-gates.md` |
| `13`–`20` | `ux/{strategy,design-system,navigation,onboarding,forms-application,dashboard,responsive-mobile,usability-analytics}.md` |
| `21-interviews-public-programs.md` | `product/features/interviews-public-programs.md` |
| `22-program-operations-finance.md` | `product/features/program-operations-finance.md` |
| `23-service-requests-collaboration.md` | `product/features/service-requests-collaboration.md` |
| `24-surveys-hackathons-knowledge.md` | `product/features/surveys-hackathons-knowledge.md` |
| `25-outcomes-risk-privacy.md` | split → `product/features/outcomes-impact.md`, `product/features/risk-intervention.md`, `architecture/data-privacy-rights.md` |
| `26-bulk-quality-versioning.md` | `product/features/bulk-operations-data-quality.md` (versioning concept → architecture ref) |
| `27-simulation-validation.md` | `product/features/simulation-validation.md` |
| `28-resilience-support-guidance.md` | split → `architecture/resilience-dr.md` (+ DR targets, offboarding), `product/features/support-cases.md` |
| `29-printing-formal-documents.md` | `product/features/formal-documents.md` |
| `30`–`34`, `36` | `saas/*` (see tree) |
| `35-saas-ux-billing-domains.md` | `ux/saas-billing-ux.md` |
| `phase-2-notes.md` | `status/phase-2-notes.md` |
| `tenancy.md` | `architecture/tenancy-isolation.md` |
| `prompts/INDEX.md` + `prompts/00–68` | `plan/build-specs/` (INDEX → `README.md`, re-keyed to register IDs) |
| `superpowers/plans/*`, `superpowers/specs/*` | `plan/phases/{plans,specs}/*` |
| `BOOTSTRAP.md` (root) | `status/bootstrap.md` (fix stale refs to `14-delivery-roadmap.md` etc.) |
| root `README.md`, `CLAUDE.md` | stay at root (CLAUDE.md path refs updated) |

## 6. Net-new docs (so "all docs exist")

- `product/scope-register.md` — canonical (reconciles module count + numbering)
- `plan/roadmap.md` — plan-of-record (phases, Selection-MVP cut line, deferred backlog)
- `docs/README.md` — doc map + conventions + update rules
- `status/implementation-status.md` — single as-built index
- `product/features/personalized-tracks.md`, `service-marketplace.md`,
  `achievements-trusted-publication.md` — define the undefined items
- `architecture/resilience-dr.md` (RPO/RTO, backup/restore, tenant offboarding),
  `architecture/admin-impersonation-audit.md`, secrets-rotation section in
  `security-baseline.md`
- `saas/white-label-levels.md`, restricted-status def in `subscriptions-billing.md`,
  email-deliverability section in `domains-branding.md`
- `product/data-residency-retention.md` — residency decision + concrete retention values

## 7. MVP boundary recorded in the plan-of-record

First sellable slice = **Selection MVP + billing**:
`signup → publish program → applications → selection/scoring → Geidea billing`.

**In scope:** Foundation + Programs/Cohorts/Stages (already specced) → Forms →
Applications → Assessments/scoring → SaaS plans/entitlements/usage → subscriptions →
Geidea billing; plus the load-bearing substrates (outbox/idempotency,
entitlement-enforcement seam).

**Deferred backlog (documented, not dropped):** mentorship, training, graduation/
alumni, reporting, extended capabilities, custom domains/branding, federated SSO,
full DR targets beyond baseline.

## 8. Remediation phases (all docs-only)

1. **Scaffold + map** — create the tree; write `README.md` (conventions, update
   rules); publish the §5 mapping table as the working checklist.
2. **Register** — build `scope-register.md` as canonical: reconcile module count,
   single ID scheme, full surface, cross-links.
3. **Plan-of-record** — rewrite `roadmap.md` (phases + MVP cut line + deferred
   backlog); reconcile with `dependency-graph.md`; retire "numeric order."
4. **Move & dedup** — `git mv` every existing doc to its new home; rewrite
   cross-references; strip restated scope so each topic is defined once.
5. **Gap-fill** — write all §6 net-new docs (no TBD/placeholder).
6. **Integrate & verify** — wire the `README.md` map; separate status from intent;
   run a consistency check (no orphan links; register ↔ plan ↔ build-specs aligned;
   module count consistent everywhere); update `CLAUDE.md` graphify-workflow paths;
   regenerate the graphify graph.

## 9. Acceptance criteria

- Exactly **one** register and **one** plan-of-record; no scope defined in two places.
- Module count stated once, identically everywhere it appears.
- **One** ID/numbering scheme (register IDs); no competing numbered lists.
- Every existing doc relocated per §5; **zero orphan/broken cross-links**.
- All §6 net-new docs exist and contain real content (no TBD).
- `docs/README.md` maps 100% of docs; every doc has the Owner/Last-updated/SoT header.
- graphify graph regenerated and oriented against the new tree.

## 10. Risks & rollback

- **Content loss during moves** → use `git mv` (history preserved); the §5 table is
  a tick-list; diff-review each move.
- **Broken references** → grep for old paths/numbers after each move; fix before commit.
- **Scope creep into implementation** → hard non-goal; this phase ships docs only.
- **Rollback** — all work on a branch in git; revert the branch. No runtime impact
  (documentation only).
