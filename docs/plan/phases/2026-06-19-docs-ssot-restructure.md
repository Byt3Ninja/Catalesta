# Documentation Restructure & Single Source of Truth — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reorganize all product documentation into a clean semantic tree with one canonical scope register and one canonical plan-of-record, so the project has a single, integrated, easy-to-update source of truth.

**Architecture:** Pure documentation work — no code. Existing docs are relocated with `git mv` (history preserved) into `docs/{product,architecture,saas,ux,quality,plan,status}/`. Two files become the only authorities: `product/scope-register.md` (defines *what*) and `plan/roadmap.md` (decides *when/order*). Every other doc references them and restates nothing. Net-new docs fill the gaps the scope review found.

**Tech Stack:** Markdown, git, ripgrep (`rg`) for link/consistency checks, graphify CLI for graph regeneration.

## Global Constraints

- **Planning phase only — NO code, migrations, or implementation.** Treat the codebase as if it does not exist. (spec §2)
- **Single source of truth:** scope is *defined* only in `product/scope-register.md`; sequence is *decided* only in `plan/roadmap.md`. No other doc re-defines scope or re-decides order. (spec §3)
- **Canonical module count = 24** — the `CLAUDE.md` "Required Modules" list (Identity, Organizations, Profiles, Startups, Programs, Cohorts, Stages, Forms, Applications, Documents, Assessments, Workflows, RoleAssignments, Tasks, Mentorship, Training, FinalEvaluation, Graduation, Notifications, Integrations, Reporting, Search, Administration, Audit). Stated identically everywhere it appears.
- **Canonical numbering = build-spec IDs `00`–`68`** (today's `prompts/INDEX.md` numbers). The register, dependency-graph, and brief reference these IDs; no competing numbered list is introduced.
- **No global filename numbers** in the new tree — semantic folders + descriptive kebab-case names. (spec §3 rule 4)
- **Intent vs status separation:** scope/plan docs carry no implementation status; status lives only in `status/`. (spec §3 rule 3)
- **Every doc gets a header block:** `Owner · Last-updated · Source-of-truth: <link>`. (spec §3 rule 5)
- **Moves use `git mv`**; rewrite every cross-reference; **zero broken links** at the end. (spec §10)
- **No placeholders / TBD** in any delivered doc. (spec §2, §9)
- Reference docs: design spec `docs/superpowers/specs/2026-06-19-docs-ssot-restructure-design.md`; review `docs/superpowers/specs/2026-06-19-scope-plan-review.md`.

---

### Task 1: Scaffold the tree and write the README map + conventions

**Files:**
- Create: `docs/product/`, `docs/product/features/`, `docs/architecture/`, `docs/saas/`, `docs/ux/`, `docs/quality/`, `docs/plan/`, `docs/plan/build-specs/`, `docs/plan/phases/`, `docs/status/` (via `.gitkeep` until populated)
- Create: `docs/README.md`

**Interfaces:**
- Produces: the folder skeleton every later task moves files into; `docs/README.md` containing the conventions (the "update rules" §3) and a doc-map table later tasks append rows to.

- [ ] **Step 1: Create the folder skeleton**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta
mkdir -p docs/product/features docs/architecture docs/saas docs/ux docs/quality docs/plan/build-specs docs/plan/phases docs/status
```

- [ ] **Step 2: Write `docs/README.md`**

Content must include, verbatim, these sections:
- **Purpose:** "This is the documentation map. Two files are authoritative: `product/scope-register.md` defines *what* we build; `plan/roadmap.md` decides *when and in what order*. Every other doc references them and restates neither scope nor sequence."
- **Folder semantics table:** product = WHAT (intent) · architecture = HOW · saas = commercial plane · ux = experience · quality = testing & verification · plan = WHEN/order · status = AS-BUILT.
- **Conventions:** no global filename numbers; kebab-case names; every doc starts with `Owner · Last-updated · Source-of-truth: <link>`; canonical module count = 24; canonical numbering = build-spec IDs 00–68.
- **Update rules:** (1) change scope → edit the register first; (2) change order → edit the roadmap first; (3) never put implementation status in scope/plan docs — use `status/`; (4) add every new doc to the map below.
- **Doc map:** a table with columns `Path | Purpose | References`. Seed it with `product/scope-register.md` and `plan/roadmap.md`; later tasks append their files.

- [ ] **Step 3: Verify skeleton and README exist**

Run: `ls -d docs/product docs/architecture docs/saas docs/ux docs/quality docs/plan/build-specs docs/plan/phases docs/status && rg -c "Source-of-truth" docs/README.md`
Expected: all directories list; `rg` count ≥ 1.

- [ ] **Step 4: Commit**

```bash
git add docs/README.md docs/product docs/architecture docs/saas docs/ux docs/quality docs/plan docs/status
git commit -m "docs: scaffold semantic docs tree + README map and conventions"
```

---

### Task 2: Build the canonical scope register

**Files:**
- Create: `docs/product/scope-register.md` (sourced from `docs/00-master-scope.md` content + the brief's catalog, reconciled)
- Modify: `docs/README.md` (mark register row authoritative)

**Interfaces:**
- Consumes: Task 1 tree; `docs/00-master-scope.md`, `docs/product-brief.md`, `prompts/INDEX.md`, `CLAUDE.md` Required Modules.
- Produces: `scope-register.md` — the canonical functional surface with stable IDs that Tasks 3, 7, 8, 10, 11 reference.

- [ ] **Step 1: Write the failing consistency check**

Create a temporary check that the register asserts the canonical module count and a single numbering scheme.
Run: `test -f docs/product/scope-register.md && rg -q "24 modules" docs/product/scope-register.md`
Expected: FAIL (file does not exist yet).

- [ ] **Step 2: Author `docs/product/scope-register.md`**

Required structure:
- Header block (`Owner · Last-updated · Source-of-truth: this file`).
- **§ Modules (canonical):** the **24** modules from `CLAUDE.md` Required Modules, as a numbered list, each with a one-line responsibility and a link to its deep-spec doc's *new* path (use the §5 mapping target paths even though files move in later tasks). State "Canonical module count: 24."
- **§ Core lifecycle:** copy the lifecycle line from `00-master-scope.md` (Application → … → Alumni Follow-Up); link to `product/lifecycle.md`.
- **§ Extended scope:** the full master-scope extended list **reconciled with** the brief catalog — explicitly include the items the brief dropped (waitlists/conditional admission, personalized tracks, print/formal documents, support case management). Each item → its `product/features/*.md` file.
- **§ SaaS commercial scope:** the master-scope SaaS list → `saas/*.md` files.
- **§ Build-spec index:** a table `ID (00–68) | Capability | Module | Build-spec file (plan/build-specs/…)`. This is the ONE numbering scheme; note "Numbering authority: build-spec IDs 00–68; all other docs reference these."
- **§ Reconciliation notes:** record that the saved architecture-decision memory said "20 modules" and that this register reconciles to 24 per `CLAUDE.md`; flag for owner confirmation.

- [ ] **Step 3: Run the consistency check**

Run: `rg -q "Canonical module count: 24" docs/product/scope-register.md && rg -q "Numbering authority: build-spec IDs" docs/product/scope-register.md && echo OK`
Expected: prints `OK`.

- [ ] **Step 4: Count the module list to confirm 24 entries**

Run: `awk '/## .*Modules \(canonical\)/{f=1;next} /^## /{f=0} f && /^[0-9]+\./' docs/product/scope-register.md | wc -l`
Expected: `24`.

- [ ] **Step 5: Update README map row + commit**

Add the register to the README doc-map table (mark "AUTHORITATIVE — scope").

```bash
git add docs/product/scope-register.md docs/README.md
git commit -m "docs: canonical scope register (single source of truth for scope; module count=24, one numbering scheme)"
```

---

### Task 3: Build the canonical plan-of-record (roadmap)

**Files:**
- Create: `docs/plan/roadmap.md` (replaces the `07-delivery-roadmap.md` stub)
- Modify: `docs/README.md` (mark roadmap row authoritative)

**Interfaces:**
- Consumes: `scope-register.md` (Task 2) build-spec IDs; the approved review's phase table; `docs/09-dependency-graph.md`.
- Produces: `roadmap.md` — the only doc that decides sequence; referenced by build-specs and status.

- [ ] **Step 1: Failing check**

Run: `test -f docs/plan/roadmap.md && rg -q "MVP cut line" docs/plan/roadmap.md`
Expected: FAIL.

- [ ] **Step 2: Author `docs/plan/roadmap.md`**

Required structure:
- Header block.
- **§ Authority statement:** "This is the plan-of-record. Sequence is decided only here. The prior instruction 'execute prompts in numeric order' is retired."
- **§ MVP cut line:** Selection MVP + billing — `signup → publish program → applications → selection/scoring → Geidea billing` (spec §7). List the in-scope build-spec IDs by referencing the register.
- **§ Phases:** the table below, each phase listing its build-spec IDs (from the register), entry/exit criteria, and a link to `plan/release-gates.md`:

| Phase | Theme | Build-spec IDs (ref register) |
|---|---|---|
| 0 | Foundation (identity/tenancy) — done | 00–04 |
| 1 | Program config kernel (programs/cohorts/stages) — done/in-progress | 05–07 |
| 2 | Cross-cutting substrates (outbox/idempotency, entitlement seam) | (substrate specs) |
| 3 | Selection MVP (forms, applications, assessments/scoring) | 07–10 |
| 4 | Commercial plane (plans/entitlements/usage, subscriptions, Geidea) | 58–62 |
| 5 | Sale-readiness (offboarding, secrets, data-residency, impersonation+audit) | (from gap docs) |

- **§ Deferred backlog (documented, not dropped):** mentorship, training, graduation/alumni, reporting, extended capabilities, custom domains/branding, federated SSO, full DR targets — each with its register ID/feature link.
- **§ Dependency reference:** link to `plan/dependency-graph.md`; do not restate dependencies.

- [ ] **Step 3: Run check**

Run: `rg -q "plan-of-record" docs/plan/roadmap.md && rg -q "is retired" docs/plan/roadmap.md && rg -q "Deferred backlog" docs/plan/roadmap.md && echo OK`
Expected: `OK`.

- [ ] **Step 4: Update README map row + commit**

```bash
git add docs/plan/roadmap.md docs/README.md
git commit -m "docs: canonical plan-of-record (phases + Selection-MVP cut line + deferred backlog; retires numeric-order)"
```

---

### Task 4: Relocate architecture docs and rewrite references

**Files (git mv):**
- `docs/01-architecture.md` → `docs/architecture/overview.md`
- `docs/02-domain-boundaries.md` → `docs/architecture/domain-boundaries.md`
- `docs/03-data-ownership.md` → `docs/architecture/data-ownership.md`
- `docs/04-security-baseline.md` → `docs/architecture/security-baseline.md`
- `docs/08-integration-strategy.md` → `docs/architecture/integration-strategy.md`
- `docs/10-shared-contracts.md` → `docs/architecture/shared-contracts.md`
- `docs/06-devops.md` → `docs/architecture/devops-observability.md`
- `docs/tenancy.md` → `docs/architecture/tenancy-isolation.md`

**Interfaces:**
- Consumes: Task 1 tree.
- Produces: stable new paths used by register/roadmap links and Task 12's link check.

- [ ] **Step 1: Record current inbound links (baseline)**

Run: `rg -l "01-architecture|02-domain-boundaries|03-data-ownership|04-security-baseline|08-integration-strategy|10-shared-contracts|06-devops|docs/tenancy\.md|tenancy\.md" docs prompts CLAUDE.md BOOTSTRAP.md`
Expected: a list of files referencing the old names (note it for Step 4).

- [ ] **Step 2: Move the files**

```bash
cd /Users/byteninja/Downloads/GrowthLabs/Catalesta
git mv docs/01-architecture.md docs/architecture/overview.md
git mv docs/02-domain-boundaries.md docs/architecture/domain-boundaries.md
git mv docs/03-data-ownership.md docs/architecture/data-ownership.md
git mv docs/04-security-baseline.md docs/architecture/security-baseline.md
git mv docs/08-integration-strategy.md docs/architecture/integration-strategy.md
git mv docs/10-shared-contracts.md docs/architecture/shared-contracts.md
git mv docs/06-devops.md docs/architecture/devops-observability.md
git mv docs/tenancy.md docs/architecture/tenancy-isolation.md
```

- [ ] **Step 3: Add header blocks**

To each moved file, prepend the `Owner · Last-updated · Source-of-truth` header (Source-of-truth points to `product/scope-register.md` for scope claims, else the file itself).

- [ ] **Step 4: Rewrite references** in every file found in Step 1 (and inside the moved files themselves) from old paths/numbers to the new `architecture/*` paths.

- [ ] **Step 5: Verify no references to old names remain**

Run: `rg -n "01-architecture|02-domain-boundaries|03-data-ownership|04-security-baseline|08-integration-strategy|10-shared-contracts|06-devops\.md|docs/tenancy\.md" docs prompts CLAUDE.md BOOTSTRAP.md`
Expected: no matches.

- [ ] **Step 6: Append moved files to README map + commit**

```bash
git add docs CLAUDE.md BOOTSTRAP.md
git commit -m "docs: relocate architecture docs to architecture/ and rewrite references"
```

---

### Task 5: Relocate SaaS docs and rewrite references

**Files (git mv):**
- `docs/30-saas-commercial-architecture.md` → `docs/saas/commercial-architecture.md`
- `docs/31-plans-entitlements-usage.md` → `docs/saas/plans-entitlements-usage.md`
- `docs/32-subscriptions-billing-lifecycle.md` → `docs/saas/subscriptions-billing.md`
- `docs/33-geidea-payment-integration.md` → `docs/saas/geidea-payments.md`
- `docs/34-custom-domains-branding.md` → `docs/saas/domains-branding.md`
- `docs/36-saas-security-testing.md` → `docs/saas/security-testing.md`

**Interfaces:**
- Produces: `saas/*` paths referenced by register and Task 11 gap-fills.

- [ ] **Step 1: Baseline inbound links**

Run: `rg -l "30-saas-commercial|31-plans-entitlements|32-subscriptions-billing|33-geidea|34-custom-domains|36-saas-security" docs prompts CLAUDE.md`
Expected: list noted.

- [ ] **Step 2: Move the files**

```bash
git mv docs/30-saas-commercial-architecture.md docs/saas/commercial-architecture.md
git mv docs/31-plans-entitlements-usage.md docs/saas/plans-entitlements-usage.md
git mv docs/32-subscriptions-billing-lifecycle.md docs/saas/subscriptions-billing.md
git mv docs/33-geidea-payment-integration.md docs/saas/geidea-payments.md
git mv docs/34-custom-domains-branding.md docs/saas/domains-branding.md
git mv docs/36-saas-security-testing.md docs/saas/security-testing.md
```

- [ ] **Step 3: Add header blocks; rewrite references** in noted files and inside moved files.

- [ ] **Step 4: Verify**

Run: `rg -n "30-saas-commercial|31-plans-entitlements|32-subscriptions-billing|33-geidea|34-custom-domains|36-saas-security" docs prompts CLAUDE.md`
Expected: no matches.

- [ ] **Step 5: Append to README map + commit**

```bash
git add docs CLAUDE.md
git commit -m "docs: relocate SaaS docs to saas/ and rewrite references"
```

---

### Task 6: Relocate UX and quality docs and rewrite references

**Files (git mv):**
- `docs/13-ux-strategy.md` → `docs/ux/strategy.md`
- `docs/14-design-system.md` → `docs/ux/design-system.md`
- `docs/15-role-based-navigation.md` → `docs/ux/navigation.md`
- `docs/16-onboarding-progressive-disclosure.md` → `docs/ux/onboarding.md`
- `docs/17-form-application-ux.md` → `docs/ux/forms-application.md`
- `docs/18-dashboard-action-center.md` → `docs/ux/dashboard.md`
- `docs/19-responsive-mobile-ux.md` → `docs/ux/responsive-mobile.md`
- `docs/20-usability-analytics.md` → `docs/ux/usability-analytics.md`
- `docs/35-saas-ux-billing-domains.md` → `docs/ux/saas-billing-ux.md`
- `docs/05-testing-strategy.md` → `docs/quality/testing-strategy.md`
- `docs/11-integration-testing.md` → `docs/quality/integration-testing.md`
- `docs/12-release-gates.md` → `docs/plan/release-gates.md`

**Interfaces:**
- Produces: `ux/*`, `quality/*`, `plan/release-gates.md` paths (roadmap links to release-gates).

- [ ] **Step 1: Baseline inbound links**

Run: `rg -l "13-ux-strategy|14-design-system|15-role-based|16-onboarding|17-form-application|18-dashboard|19-responsive|20-usability|35-saas-ux|05-testing-strategy|11-integration-testing|12-release-gates" docs prompts CLAUDE.md`
Expected: list noted.

- [ ] **Step 2: Move the files** (one `git mv` per pair above).

- [ ] **Step 3: Add header blocks; rewrite references** (including the roadmap's link to `plan/release-gates.md`).

- [ ] **Step 4: Verify**

Run: `rg -n "13-ux-strategy|14-design-system|15-role-based|16-onboarding|17-form-application|18-dashboard|19-responsive|20-usability|35-saas-ux|05-testing-strategy|11-integration-testing|12-release-gates" docs prompts CLAUDE.md`
Expected: no matches.

- [ ] **Step 5: Append to README map + commit**

```bash
git add docs CLAUDE.md
git commit -m "docs: relocate UX/quality docs and release-gates; rewrite references"
```

---

### Task 7: Relocate product/feature docs, split combined docs, and dedup the brief

**Files (git mv + split):**
- `docs/21-interviews-public-programs.md` → `docs/product/features/interviews-public-programs.md`
- `docs/22-program-operations-finance.md` → `docs/product/features/program-operations-finance.md`
- `docs/23-service-requests-collaboration.md` → `docs/product/features/service-requests-collaboration.md`
- `docs/24-surveys-hackathons-knowledge.md` → `docs/product/features/surveys-hackathons-knowledge.md`
- `docs/26-bulk-quality-versioning.md` → `docs/product/features/bulk-operations-data-quality.md`
- `docs/27-simulation-validation.md` → `docs/product/features/simulation-validation.md`
- `docs/29-printing-formal-documents.md` → `docs/product/features/formal-documents.md`
- **Split** `docs/25-outcomes-risk-privacy.md` → `docs/product/features/outcomes-impact.md` + `docs/product/features/risk-intervention.md` + `docs/architecture/data-privacy-rights.md`
- **Split** `docs/28-resilience-support-guidance.md` → `docs/architecture/resilience-dr.md` (resilience portion) + `docs/product/features/support-cases.md`
- `docs/product-brief.md` → `docs/product/product-brief.md`
- Create: `docs/product/lifecycle.md`

**Interfaces:**
- Consumes: register (Task 2) for the canonical feature list/IDs.
- Produces: `product/features/*` files the register links to; a brief that references the register.

- [ ] **Step 1: Baseline inbound links**

Run: `rg -l "21-interviews|22-program-operations|23-service-requests|24-surveys|25-outcomes|26-bulk|27-simulation|28-resilience|29-printing|product-brief" docs prompts CLAUDE.md`
Expected: list noted.

- [ ] **Step 2: Move the simple ones**

```bash
git mv docs/21-interviews-public-programs.md docs/product/features/interviews-public-programs.md
git mv docs/22-program-operations-finance.md docs/product/features/program-operations-finance.md
git mv docs/23-service-requests-collaboration.md docs/product/features/service-requests-collaboration.md
git mv docs/24-surveys-hackathons-knowledge.md docs/product/features/surveys-hackathons-knowledge.md
git mv docs/26-bulk-quality-versioning.md docs/product/features/bulk-operations-data-quality.md
git mv docs/27-simulation-validation.md docs/product/features/simulation-validation.md
git mv docs/29-printing-formal-documents.md docs/product/features/formal-documents.md
git mv docs/product-brief.md docs/product/product-brief.md
```

- [ ] **Step 3: Split `25-outcomes-risk-privacy.md`** — copy its outcomes/impact content into `product/features/outcomes-impact.md`, its risk/intervention content into `product/features/risk-intervention.md`, and its privacy/DSR content into `architecture/data-privacy-rights.md`; then `git rm docs/25-outcomes-risk-privacy.md`. Add header blocks to all three. (resilience-dr.md created in Step 4 of Task 11 if not here — create the file here with the support split.)

- [ ] **Step 4: Split `28-resilience-support-guidance.md`** — copy the resilience/support-process portions into `architecture/resilience-dr.md` (DR-target content added in Task 11) and the support-case product behavior into `product/features/support-cases.md`; then `git rm docs/28-resilience-support-guidance.md`. Add header blocks.

- [ ] **Step 5: Dedup the brief** — in `product/product-brief.md`, replace the restated 68-item catalog with a one-line pointer: "Full functional surface is defined in `product/scope-register.md`; this brief covers positioning and value only." Keep positioning/value content; delete the duplicated scope enumeration. Add header block.

- [ ] **Step 6: Author `product/lifecycle.md`** — the core lifecycle (Application → Eligibility → Initial Evaluation → Mentorship → Training → Final Evaluation → Graduation → Alumni Follow-Up) with one paragraph per stage, linking each stage to its module in the register. Header block.

- [ ] **Step 7: Rewrite references** in noted files to the new paths (note the three/two split targets).

- [ ] **Step 8: Verify old names gone + brief no longer restates scope**

Run: `rg -n "21-interviews|22-program-operations|23-service-requests|24-surveys|25-outcomes|26-bulk|27-simulation|28-resilience|29-printing|docs/product-brief\.md" docs prompts CLAUDE.md`
Expected: no matches.
Run: `rg -q "defined in .*scope-register" docs/product/product-brief.md && echo OK`
Expected: `OK`.

- [ ] **Step 9: Append to README map + commit**

```bash
git add docs CLAUDE.md
git commit -m "docs: relocate feature docs, split outcomes/resilience, dedup brief to reference register"
```

---

### Task 8: Relocate plan artifacts and re-key build specs

**Files (git mv):**
- `docs/09-dependency-graph.md` → `docs/plan/dependency-graph.md`
- `prompts/*.md` (00–68 + INDEX) → `docs/plan/build-specs/` (`INDEX.md` → `docs/plan/build-specs/README.md`)
- `docs/superpowers/plans/*` → `docs/plan/phases/plans/*`
- `docs/superpowers/specs/*` → `docs/plan/phases/specs/*` (this plan + the two specs move too)

**Interfaces:**
- Consumes: register build-spec IDs (Task 2), roadmap (Task 3).
- Produces: `plan/build-specs/*` and `plan/phases/*`; the dependency-graph at its final path.

- [ ] **Step 1: Baseline inbound links**

Run: `rg -l "09-dependency-graph|prompts/|superpowers/plans|superpowers/specs" docs prompts CLAUDE.md README.md`
Expected: list noted.

- [ ] **Step 2: Move dependency graph + build specs**

```bash
git mv docs/09-dependency-graph.md docs/plan/dependency-graph.md
git mv prompts/INDEX.md docs/plan/build-specs/README.md
for f in prompts/*.md; do git mv "$f" "docs/plan/build-specs/$(basename "$f")"; done
rmdir prompts 2>/dev/null || true
```

- [ ] **Step 3: Move engineering plans/specs**

```bash
mkdir -p docs/plan/phases/plans docs/plan/phases/specs
for f in docs/superpowers/plans/*.md; do git mv "$f" "docs/plan/phases/plans/$(basename "$f")"; done
for f in docs/superpowers/specs/*.md; do git mv "$f" "docs/plan/phases/specs/$(basename "$f")"; done
rmdir docs/superpowers/plans docs/superpowers/specs docs/superpowers 2>/dev/null || true
```

- [ ] **Step 4: Re-key build-specs to register IDs** — in `docs/plan/build-specs/README.md`, present the 00–68 list as the canonical numbering and add a note: "These IDs are the single numbering authority (referenced by `product/scope-register.md` and `plan/roadmap.md`)." Confirm each build-spec filename keeps its `NN-` prefix (these prefixes ARE the canonical IDs — they are the one allowed numbering, per Global Constraints).

- [ ] **Step 5: Rewrite references** (CLAUDE.md graphify-workflow path note, README map, roadmap/register links, and the BOOTSTRAP reference to `prompts/00-bootstrap.md`).

- [ ] **Step 6: Verify old paths gone**

Run: `rg -n "docs/09-dependency-graph|(^|[^-])prompts/|superpowers/" docs CLAUDE.md README.md`
Expected: no matches (the `prompts/` directory no longer exists).
Run: `test ! -d prompts && test ! -d docs/superpowers && echo OK`
Expected: `OK`.

- [ ] **Step 7: Append to README map + commit**

```bash
git add -A docs CLAUDE.md README.md
git commit -m "docs: relocate dependency-graph, build-specs, and engineering phases into plan/"
```

---

### Task 9: Relocate as-built status docs and consolidate the status index

**Files:**
- `docs/phase-2-notes.md` → `docs/status/phase-2-notes.md`
- `BOOTSTRAP.md` (root) → `docs/status/bootstrap.md`
- Create: `docs/status/implementation-status.md`

**Interfaces:**
- Produces: `status/*` — the only place implementation status lives.

- [ ] **Step 1: Move status docs**

```bash
git mv docs/phase-2-notes.md docs/status/phase-2-notes.md
git mv BOOTSTRAP.md docs/status/bootstrap.md
```

- [ ] **Step 2: Fix stale refs in `bootstrap.md`** — replace `prompts/00-bootstrap.md` → `docs/plan/build-specs/00-repository-bootstrap.md` and `docs/14-delivery-roadmap.md` → `docs/plan/roadmap.md`. Add header block.

- [ ] **Step 3: Author `docs/status/implementation-status.md`** — a single as-built index: a table `Module | Status (Implemented/Partial/Scaffold/Absent) | Notes`, seeded from the scope review's findings (Identity/Organizations/Programs/Stages/Cohorts = Implemented; the rest = Scaffold/Absent; SaaS = Absent). Header block. State clearly: "This is the ONLY place implementation status is tracked; scope and plan docs must not carry status."

- [ ] **Step 4: Verify intent/status separation**

Run: `rg -ni "implementation status|as-built|scaffold-only|0 files" docs/product docs/plan/roadmap.md docs/product/scope-register.md`
Expected: no matches (status language must not appear in scope/plan docs).

- [ ] **Step 5: Append to README map + commit**

```bash
git add docs README.md
git commit -m "docs: move as-built notes to status/; add consolidated implementation-status index"
```

---

### Task 10: Gap-fill — define the undefined scope items

**Files (create):**
- `docs/product/features/personalized-tracks.md`
- `docs/product/features/service-marketplace.md`
- `docs/product/features/achievements-trusted-publication.md`

**Interfaces:**
- Consumes: register (links these from Extended scope).
- Produces: definitions for items previously named-but-undefined (review §4).

- [ ] **Step 1: Failing check**

Run: `ls docs/product/features/personalized-tracks.md docs/product/features/service-marketplace.md docs/product/features/achievements-trusted-publication.md`
Expected: FAIL (missing).

- [ ] **Step 2: Author `personalized-tracks.md`** — define: what a track is vs a stage path, who assigns participants to a track, how tracks interact with the stage engine (applicability), and lifecycle. Header block; link from register.

- [ ] **Step 3: Author `service-marketplace.md`** — define: provider model, listing/pricing, request→fulfilment→settlement flow, and what is explicitly out of MVP. Header block.

- [ ] **Step 4: Author `achievements-trusted-publication.md`** — define "trusted publication": the verification/trust mechanism for the only tenant→Startup-Gate data flow (who attests, what is checked, idempotency). Header block.

- [ ] **Step 5: Verify + link from register**

Run: `for f in personalized-tracks service-marketplace achievements-trusted-publication; do rg -q "Source-of-truth" docs/product/features/$f.md || echo "MISSING $f"; done`
Expected: no `MISSING` output. Confirm register links each.

- [ ] **Step 6: Commit**

```bash
git add docs/product/features docs/product/scope-register.md docs/README.md
git commit -m "docs: define personalized tracks, service marketplace, trusted publication"
```

---

### Task 11: Gap-fill — production-SaaS docs

**Files (create / extend):**
- Create: `docs/architecture/resilience-dr.md` (extend the Task 7 split with DR targets)
- Create: `docs/architecture/admin-impersonation-audit.md`
- Create: `docs/product/data-residency-retention.md`
- Create: `docs/saas/white-label-levels.md`
- Modify: `docs/architecture/security-baseline.md` (secrets-rotation section)
- Modify: `docs/saas/subscriptions-billing.md` (restricted-status definition)
- Modify: `docs/saas/domains-branding.md` (email-deliverability section)

**Interfaces:**
- Consumes: register/roadmap (Phase 5 sale-readiness references these).
- Produces: the production-readiness docs the review found missing (review §5/§7).

- [ ] **Step 1: Failing check**

Run: `ls docs/architecture/resilience-dr.md docs/architecture/admin-impersonation-audit.md docs/product/data-residency-retention.md docs/saas/white-label-levels.md`
Expected: FAIL on at least the not-yet-created ones.

- [ ] **Step 2: `resilience-dr.md`** — add concrete **RPO/RTO** targets, backup/restore procedure, and **tenant offboarding** end-to-end (domain release, cert revocation, billing closeout, Startup-Gate de-linking). Header block.

- [ ] **Step 3: `admin-impersonation-audit.md`** — define staff "log in as tenant user": authorization, scope limits, banner/consent, and the **audit trail** every impersonated action writes. Header block.

- [ ] **Step 4: `data-residency-retention.md`** — record the **data-residency decision** (resolve the open question) and **concrete retention values** per data category. Header block.

- [ ] **Step 5: `white-label-levels.md`** — define each branding/white-label tier and exactly what it unlocks vs the "no arbitrary CSS" rule. Header block.

- [ ] **Step 6: Extend existing SaaS/security docs** — add a secrets-rotation/vault section to `security-baseline.md`; a "restricted" subscription-status definition (vs grace/suspended) to `subscriptions-billing.md`; an email-deliverability (DKIM/SPF) section to `domains-branding.md`.

- [ ] **Step 7: Verify all gap docs exist and are non-empty**

Run: `for f in architecture/resilience-dr architecture/admin-impersonation-audit product/data-residency-retention saas/white-label-levels; do test -s docs/$f.md || echo "EMPTY $f"; done; rg -q "RPO" docs/architecture/resilience-dr.md && rg -q "restricted" docs/saas/subscriptions-billing.md && echo OK`
Expected: no `EMPTY`; prints `OK`.

- [ ] **Step 8: Link from register/roadmap + commit**

```bash
git add docs/architecture docs/product docs/saas docs/plan/roadmap.md docs/product/scope-register.md docs/README.md
git commit -m "docs: production-SaaS gap docs (DR/RPO-RTO, offboarding, impersonation+audit, residency/retention, white-label, secrets, restricted status, email deliverability)"
```

---

### Task 12: Integrate and verify the whole tree

**Files:**
- Modify: `docs/README.md` (final complete map), `CLAUDE.md` (graphify-workflow paths)
- Regenerate: graphify graph

**Interfaces:**
- Consumes: every prior task's outputs.
- Produces: a verified, link-clean, single-source-of-truth doc set + fresh graph.

- [ ] **Step 1: Complete the README doc-map** — ensure every file under `docs/` appears exactly once with Purpose + References columns; register and roadmap marked AUTHORITATIVE.

- [ ] **Step 2: Orphan/dangling-link check**

Run: `rg -o "\]\(([^)]+\.md)\)" -r '$1' docs --no-filename | sort -u | while read p; do :; done` then for each relative link confirm the target exists. Practical check:
Run: `rg -no "\]\((\.{0,2}/?[A-Za-z0-9_./-]+\.md)" docs -r '$1' | sort -u`
Manually confirm each path resolves; fix any that don't.

- [ ] **Step 3: No-old-numbering check**

Run: `rg -n "docs/[0-9][0-9]-" docs CLAUDE.md README.md`
Expected: no matches (no references to old numbered docs anywhere).

- [ ] **Step 4: Single-count consistency check**

Run: `rg -no "2[04] modules" docs CLAUDE.md | sort | uniq -c`
Expected: only "24 modules" appears; if any "20 modules" remains outside the register's reconciliation note, fix it.

- [ ] **Step 5: Intent/status separation check (repeat)**

Run: `rg -ni "scaffold|not yet implemented|as-built|0 php files" docs/product docs/plan/roadmap.md docs/product/scope-register.md`
Expected: no matches.

- [ ] **Step 6: Update `CLAUDE.md` graphify-workflow paths** — replace `graphify-out/GRAPH_REPORT.md` workflow references only if path changed; ensure the "## Graphify Knowledge Graph" section points at the new tree. Verify no doc path in CLAUDE.md references an old location.

- [ ] **Step 7: Regenerate the graphify graph**

Run: `graphify` (regenerate per repo tooling) so the graph is oriented against the new tree.
Expected: graph regenerated without referencing dead doc names.

- [ ] **Step 8: Final commit**

```bash
git add -A
git commit -m "docs: integrate doc tree — complete README map, link/consistency checks, regenerate graph"
```

---

## Self-Review

**Spec coverage (spec §§2–10):**
- §3 SSoT model → Tasks 2 (register) + 3 (roadmap) + README rules (Task 1). ✓
- §4 target tree → Tasks 1, 4–9. ✓
- §5 file mapping → Tasks 4–9 cover every row (architecture, saas, ux/quality, product/features+splits+brief, plan/build-specs/phases, status/bootstrap). ✓
- §6 net-new docs → register/roadmap/README (T1–3), undefined items (T10), production-SaaS (T11), implementation-status (T9), lifecycle (T7). ✓
- §7 MVP boundary → Task 3. ✓
- §8 remediation phases → map 1:1 to Tasks 1–2/3/4–9/10–11/12. ✓
- §9 acceptance criteria → Task 12 checks (orphan links, no old numbering, single module count, intent/status, graph). ✓
- §10 risks/rollback → `git mv` throughout; baseline-link steps; branch-revert rollback. ✓

**Placeholder scan:** net-new docs specify required sections + the exact decision to record (module count 24, numbering 00–68, MVP slice, RPO/RTO, residency) rather than "TBD." Content authoring is the deliverable; structure is fully specified. ✓

**Type/name consistency:** paths in later tasks match the tree in Task 1 and the §5 mapping; build-spec `NN-` prefixes are explicitly the canonical numbering; register/roadmap filenames consistent across all references. ✓

**Open confirmation:** module count reconciliation to 24 vs the saved "20" memory is recorded in the register (Task 2 §Reconciliation notes) and flagged for owner confirmation rather than silently overriding.
