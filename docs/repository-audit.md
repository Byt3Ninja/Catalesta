# Repository Currency Audit

> Owner: Engineering · Created: 2026-06-23 · Type: Drift / gap report against
> the existing baseline. **Not** a new BMAD baseline — points back at the
> canonical artifacts and flags where they disagree.

## Purpose

This document is a one-shot audit answering a single question: where do the
repository's **canonical artifacts** disagree with each other or with the
verified application behaviour as of 2026-06-23?

It does not establish new product/architecture authority. The canonical
sources remain:

| Layer | Canonical home | Notes |
| --- | --- | --- |
| Invariants | `CLAUDE.md` + `.claude/rules/` | Authority order defined in `CLAUDE.md`. |
| Architecture decisions | `adr/` | Three ADRs accepted; one superseded — see F-001. |
| BMAD planning | `_bmad-output/planning-artifacts/` | BMM v6 install (`_bmad/`). |
| BMAD implementation artifacts | `_bmad-output/implementation-artifacts/` | Epic and story outputs. |
| Long-form product / architecture / SaaS / UX docs | `docs/{product,architecture,saas,quality,ux}/` | Pre-BMM hand-authored. |
| As-built status | `docs/status/implementation-status.md` | Single source for what is *built*. |
| Project context (intended) | `docs/project-context.md` (referenced by `CLAUDE.md`) | **Does not exist on disk.** See F-008. |
| Architecture graph | `graphify-out/GRAPH_REPORT.md` (+ `graph.json`) | Snapshot dated 2026-06-22. |
| Resolved doc-contradiction decisions | Auto-memory `architecture-decisions.md` (6 items) | Living register, not yet promoted to ADRs. |

Findings reference these by path. Severity uses Critical / High / Medium / Low
in the standard sense (Critical = blocks safe implementation today; High =
blocks work in an active epic; Medium = will block soon; Low = hygiene).

---

## Findings

### F-001 — ADR-0002 contradicts the 2026-06-21 identity-ownership inversion

- **Severity:** Critical
- **Affected modules:** Identity, Profiles, Organizations, Notifications, Audit, Integrations
- **Evidence:**
  - `adr/0002-startup-gate-system-of-record.md`: "Startup Gate owns global
    identity, profiles, startup memberships, consent, and shared
    achievements." — Status: Accepted.
  - Auto-memory `architecture-decisions.md` item 6 (2026-06-21): "Identity
    ownership inverted — Catalesta is the system of record … Supersedes the
    earlier 'Startup Gate owns identity' stance baked into the original PRD
    §9 / CLAUDE.md rules 2/4/5/11."
  - `CLAUDE.md` § "Identity Invariants": "The primary user identifier is the
    local `Account` ULID." / "Local registration and authentication must work
    independently of Startup Gate."
  - Recent commits: `ea2830d docs(epics): add Epic 4 story breakdown
    (SP-1..SP-4)`, `faaf258 docs(ir): implementation readiness report
    2026-06-23 (post-Epic 1+2, post-SP-1)` — SP-1 is the carrier of the
    inversion (migrates `external_users` → `accounts` + `linked_identities`,
    repoints `organization_memberships.account_id`).
- **Current behaviour:** The accepted ADR record states the opposite of the
  invariant now being implemented. Any reader applying authority order in
  `CLAUDE.md` ( #3 ADR > #4 CLAUDE.md ) will hit a direct contradiction.
- **Expected behaviour:** ADR-0002 marked Superseded-by a new ADR (e.g.
  ADR-0004) stating: Catalesta owns identity, profiles, memberships, consent;
  Startup Gate is an optional linked SSO provider + consented import source;
  primary key is local Account ULID; SG `sub` lives on `linked_identities`,
  not on the account; email is a local credential only.
- **Business / technical / security impact:** Authority ambiguity in the
  identity domain; risk that downstream stories or integrations re-introduce
  SG-keyed assumptions; SG outage no longer authoritatively required to be a
  non-blocker.
- **Tenant-isolation impact:** None directly, but profile-import consent
  records and cross-tenant identity linkage assumptions hinge on the new
  ownership model.
- **Recommended remediation:** Add ADR-0004
  ("Catalesta-as-Identity-System-of-Record"), set ADR-0002 status to
  "Superseded by ADR-0004", reference auto-memory decision 6 and SP-1 design
  doc (`docs/superpowers/specs/2026-06-21-standalone-identity-design.md`).
  No code change.
- **Dependency:** None (doc-only).
- **Proposed epic:** Epic 0 — Repository Stabilization (see § Proposed
  Remediation Epics).

### F-002 — `docs/project-context.md` referenced by CLAUDE.md does not exist

- **Severity:** Critical
- **Affected modules:** All (it is the cited project-wide context).
- **Evidence:**
  - `CLAUDE.md` authority order item 5: `docs/project-context.md`.
  - `.claude/rules/16-documentation.md`: "Use exact domain terminology from
    `docs/project-context.md`."
  - `ls docs/`: no `project-context.md` file present
    (`architecture/`, `plan/`, `product/`, `quality/`, `saas/`, `status/`,
    `superpowers/`, `ux/`, `README.md`).
- **Current behaviour:** Every reader is told to consult a file that is not
  there.
- **Expected behaviour:** Either (a) the file exists and consolidates canonical
  context, or (b) `CLAUDE.md` + `16-documentation.md` are amended to point at
  the actual canonical homes (`docs/product/product-brief.md`,
  `docs/architecture/overview.md`, `docs/architecture/domain-boundaries.md`,
  etc.).
- **Impact:** Implementers cannot satisfy the "read project-context.md before
  changes" gate in `01-task-protocol.md`; risk of duplicate baselines being
  authored to fill the vacuum (precisely the failure mode this audit is
  preventing).
- **Recommended remediation:** Pick (a) or (b). Recommendation: option (b) —
  amend `CLAUDE.md` and `16-documentation.md` to reference the existing
  canonical docs, since the long-form content already exists under
  `docs/{product,architecture,saas}`. If (a) is preferred, the new file
  should be a thin index, not a copy.
- **Dependency:** None.
- **Proposed epic:** Epic 0 — Repository Stabilization.

### F-003 — Implementation-status doc is stale relative to Epic 4 / SP-1

- **Severity:** High
- **Affected modules:** Identity, Organizations, Profiles
- **Evidence:**
  - `docs/status/implementation-status.md` header: "Last-updated: 2026-06-19".
  - Identity row: "Implemented — `app/Modules/Identity` (21 files) — OIDC,
    profiles projection" (assumes `ExternalUser` / SG-keyed model).
  - Recent commits `ea2830d`, `faaf258` indicate Epic 4 (SP-1..SP-4) is
    underway; auto-memory item 6 records SP-1 migrating `external_users` →
    `accounts` + `linked_identities` and repointing
    `organization_memberships.account_id`.
- **Current behaviour:** The single source-of-truth status file does not
  reflect SP-1's data-model changes or Epic 4's existence.
- **Expected behaviour:** Status table refreshed: Identity row reflects new
  `accounts` + `linked_identities` model; an Epic 4 row or footnote added;
  any new tests/migrations counted.
- **Impact:** New work cannot reliably classify modules as
  "implemented/scaffold/absent". `CLAUDE.md` authority order #7 ("existing
  verified application behaviour") cannot be cross-checked without a current
  status doc.
- **Recommended remediation:** Refresh after SP-1 lands, on the same commit
  that lands SP-1 (per `16-documentation.md` "Update documentation in the
  same change as architecture, API, schema, environment, integration,
  operational, or security-sensitive changes."). Going forward, gate Epic
  completion on a status refresh.
- **Dependency:** SP-1 merge state must be known (in-flight vs. merged).
- **Proposed epic:** Epic 0 — Repository Stabilization.

### F-004 — Four open doc contradictions flagged in auto-memory remain unresolved

- **Severity:** High
- **Affected modules:** Workflows, Mentorship, Applications, FinalEvaluation, Operations
- **Evidence:** Auto-memory `architecture-decisions.md` § "How to apply"
  lists four still-open contradictions:
  1. Workflow table set: `docs/04` vs `docs/07` (two competing schemas).
  2. Mutual-feedback one-vs-two tables.
  3. `application_decisions` vs `evaluation_decisions` (which table is
     authoritative for stage outcomes).
  4. `.env.example` not aligned with Redis / S3 / OIDC config used in the
     actual `backend/` Laravel app.
- **Current behaviour:** Phase-affected stories will start with two valid
  references and pick arbitrarily.
- **Expected behaviour:** Each item has a written decision (auto-memory entry
  promoted to ADR or to a canonical doc edit) before the affected story
  enters Ready.
- **Impact:** Workflows + FinalEvaluation epics cannot enter readiness; .env
  drift breaks fresh-clone bring-up.
- **Recommended remediation:** Spawn four small decisions (one per
  contradiction). Land each as a canonical-doc edit + auto-memory note. The
  `.env.example` item is the cheapest — pure file sync against
  `backend/config/*.php`.
- **Dependency:** None (4 independent).
- **Proposed epic:** Epic 0 — Repository Stabilization (sub-tasks per item).

### F-005 — Three of six confirmed architecture decisions have no ADR

- **Severity:** Medium
- **Affected modules:** Programs, Cohorts, Organizations, repo layout, scope register
- **Evidence:** Auto-memory `architecture-decisions.md` lists 6 confirmed
  resolutions. Of those, only the inversion in #6 corresponds to an ADR slot
  (and that ADR is still missing — F-001). The following have no ADR record:
  - Item 1 — Cohort naming canonical; `program_cycles` → `cohorts` rename.
  - Item 2 — 24-module canonical scope, with 4 not yet scaffolded.
  - Item 3 — Repo layout: `backend/` + `frontend/` + `services/` as
    siblings.
  - Item 5 — Cross-tenant org access returns 404 not 403 (decided
    2026-06-20, affects 6 existing test assertions).
- **Current behaviour:** Decisions live only in auto-memory and (where lucky)
  in `_bmad-output/planning-artifacts/`. Auto-memory is not a CLAUDE.md
  authority tier.
- **Expected behaviour:** Each decision either (a) has a short ADR, or (b)
  is captured in the affected canonical doc with explicit "Decision:" framing.
- **Impact:** New work or reviewers cannot cite the decision authoritatively;
  the 404-vs-403 decision in particular has a concrete code-test contract
  and should be discoverable from `adr/`.
- **Recommended remediation:** Add minimal ADRs (one each for items 1, 2, 3,
  5) or, for item 2, point to `docs/product/scope-register.md` as the
  canonical 24-module register and add a one-line note in ADR-0001.
- **Dependency:** None.
- **Proposed epic:** Epic 0 — Repository Stabilization.

### F-006 — Authority order between `_bmad-output/`, `docs/`, and `_bmad/` is unstated

- **Severity:** Medium
- **Affected modules:** All
- **Evidence:**
  - `_bmad/` — BMM v6 install (`config.toml`, custom workflows).
  - `_bmad-output/planning-artifacts/`, `_bmad-output/implementation-artifacts/`
    — BMAD outputs.
  - `docs/architecture/`, `docs/product/`, `docs/saas/`, `docs/quality/`,
    `docs/ux/` — long-form hand-authored docs (11 architecture files; product
    brief; lifecycle; flows; scope register; data residency; SaaS; quality).
  - `CLAUDE.md` authority order #6: "Approved BMAD product, UX, architecture,
    and release documents." Does not specify which tree.
  - `MANIFEST.md` exists at root (not yet inspected for this purpose).
- **Current behaviour:** Reader cannot tell whether `_bmad-output/` or
  `docs/` wins when they disagree — and where they overlap, both exist.
- **Expected behaviour:** A one-paragraph note in either `CLAUDE.md` or
  `MANIFEST.md` declaring which tree is authoritative for each artifact
  type, or a redirect note in the loser pointing at the winner.
- **Impact:** Recurrent "which doc is right?" friction; risk of stale `docs/`
  pages being treated as canonical when BMAD outputs supersede them.
- **Recommended remediation:** Add a short "Doc authority map" section to
  `MANIFEST.md` (already a likely host). Reference it from
  `.claude/rules/16-documentation.md`.
- **Dependency:** None.
- **Proposed epic:** Epic 0 — Repository Stabilization.

### F-007 — Module-count vs as-built drift

- **Severity:** Medium
- **Affected modules:** FinalEvaluation, Notifications, Search, Administration
- **Evidence:** `docs/status/implementation-status.md` (2026-06-19): four
  modules marked Absent — FinalEvaluation, Notifications, Search,
  Administration. `CLAUDE.md` "Required Modules" lists 24 (matches
  auto-memory item 2).
- **Current behaviour:** Four required modules have no folder. Status doc
  acknowledges this, but no epic ordering decision is recorded for when each
  is brought in.
- **Expected behaviour:** Each absent module mapped to a target release in
  the existing roadmap (`docs/plan/roadmap.md` — not inspected here; confirm
  before raising a story). At minimum, "Notifications" needs a target
  release because audit/observability/integration epics depend on it.
- **Impact:** Phase-affected stories will trip on missing modules; e.g.
  any "send a notification" story has no module to land in.
- **Recommended remediation:** Roadmap-edit pass mapping each Absent module
  to a phase. Possibly fold into Epic 0 along with F-005.
- **Dependency:** Decision on which existing epic owns each module.
- **Proposed epic:** Epic 0 — Repository Stabilization.

### F-008 — Frontend status under-specified

- **Severity:** Low
- **Affected modules:** Frontend
- **Evidence:** `docs/status/implementation-status.md`: "Frontend (`frontend/`):
  Scaffold (~8 TS files)". 25 entries in `frontend/` at root — actual file
  count not re-verified.
- **Current behaviour:** Status of frontend rollout is not granular; UX
  spec sits in `docs/ux/` (not inspected here) but isn't tied back to
  per-module frontend readiness.
- **Expected behaviour:** Frontend readiness tracked per module column in
  the status doc, mirroring backend.
- **Impact:** UI epics cannot be sequenced confidently.
- **Recommended remediation:** Add a frontend column to the status table on
  next refresh (folds into F-003).
- **Dependency:** F-003.
- **Proposed epic:** Epic 0 — Repository Stabilization.

### F-009 — Reliability substrates flagged Absent in status are CLAUDE.md-implied requirements

- **Severity:** Medium
- **Affected modules:** Cross-cutting (Integrations, Notifications, Audit, Workflows)
- **Evidence:**
  - `docs/status/implementation-status.md`: "Reliability substrates
    (transactional outbox, idempotency) — Absent".
  - Auto-memory item 4: "hoist outbox + idempotency + audit + signed-webhook
    scaffolding into Phase 1" — decided 2026-06-18.
  - `CLAUDE.md` "Versioning and Historical Integrity" + integration rules
    imply outbox / idempotency are required for safe webhook + workflow
    behaviour.
- **Current behaviour:** Decision exists, substrates not built. Stories in
  Workflows / Mentorship / FinalEvaluation that emit events will lack the
  substrate they were promised.
- **Expected behaviour:** Phase 1.5 (per auto-memory) explicitly tracked in
  the roadmap with an owning epic.
- **Impact:** Any later story that needs outbox/idempotency will either
  block, retrofit, or silently skip the substrate.
- **Recommended remediation:** Confirm in roadmap that an Infra/Reliability
  epic carries outbox + idempotency + audit-enforced-not-opt-in + signed
  webhooks. If not present, add it before the first event-emitting story
  enters Ready.
- **Dependency:** Roadmap inspection.
- **Proposed epic:** Epic 0 — Repository Stabilization (sub: infra carve-out).

### F-010 — Audit module is opt-in; CLAUDE.md treats audit as a baseline requirement

- **Severity:** Medium
- **Affected modules:** Audit (and every consumer)
- **Evidence:** `docs/status/implementation-status.md`: "Audit — Scaffold —
  folder only; **audit currently opt-in, not enforced**." `CLAUDE.md`
  "Authorization and Privacy" treats authorization decisions, linking, and
  consent changes as audit-bearing events; `01-task-protocol.md` requires
  audit-impact analysis before changes.
- **Current behaviour:** Audit invariants in CLAUDE.md are not backed by an
  enforced mechanism in code.
- **Expected behaviour:** Audit enforcement moved from opt-in to required
  for authorization decisions, identity-link/unlink, consent grants, profile
  imports, and stage outcomes.
- **Impact:** Compliance-relevant events may not be captured.
- **Recommended remediation:** Pair with F-009; one Reliability/Audit epic
  covers both. Define the minimal enforced event set as part of that epic's
  readiness.
- **Dependency:** F-009.
- **Proposed epic:** Epic 0 — Repository Stabilization (audit carve-out) or
  a new Reliability epic if F-009 lands as a separate stream.

### F-011 — ADR-0001 is a stub (one line) and lacks the standard ADR sections

- **Severity:** Low
- **Affected modules:** Architecture
- **Evidence:** `adr/0001-modular-monolith.md`: status + a single decision
  sentence; no context, no alternatives, no consequences.
  `.claude/rules/16-documentation.md`: "ADRs must include context, decision,
  alternatives, consequences, and status."
- **Current behaviour:** ADR-0001 does not meet the standard the rule
  requires. Same applies to ADR-0002 (now superseded — see F-001) and
  ADR-0003.
- **Expected behaviour:** Each ADR expanded with context, alternatives,
  consequences.
- **Impact:** Reviewers cannot reconstruct *why* a decision was taken.
- **Recommended remediation:** Backfill ADR sections in a single doc PR.
- **Dependency:** None.
- **Proposed epic:** Epic 0 — Repository Stabilization (hygiene).

### F-012 — `graphify-out/` snapshot dir is older than the report file

- **Severity:** Low
- **Affected modules:** All (navigation tool)
- **Evidence:** `graphify-out/GRAPH_REPORT.md` snapshot dated 2026-06-22;
  internal community structure references 696 communities; `2026-06-19`
  directory remains under `graphify-out/`. Recent SP-1 work likely touched
  Identity module structure (new `linked_identities`).
- **Current behaviour:** Graphify is current as of 2026-06-22; SP-1 may or
  may not have landed after that.
- **Expected behaviour:** Regenerate after SP-1 lands (per rule 14
  "Regenerate Graphify after substantial structural changes").
- **Impact:** Architecture queries may slightly mis-locate the Identity
  module.
- **Recommended remediation:** Run graphify on the merge commit of SP-1 (if
  not already done) and confirm `GRAPH_REPORT.md` snapshot timestamp moves
  forward.
- **Dependency:** SP-1 merge.
- **Proposed epic:** Epic 0 — Repository Stabilization (hygiene).

---

## Out-of-scope for this audit

The following were **not** inspected, and are not represented as either
verified or broken by this document. Each is flagged so a future audit can
pick them up:

- Per-route authorization correctness (would require route-by-route
  inspection of `backend/routes/` + policies).
- Tenant-isolation correctness inside `BelongsToTenant` (covered by
  `Phase2TenantIsolationTest`; not re-executed here).
- API contracts vs OpenAPI (`backend/openapi/` not diffed against
  controllers).
- Migration safety / rollback for any specific migration in
  `backend/database/migrations/`.
- SaaS billing / Geidea / domain / branding implementation state (status
  doc says "Absent — documented only"; not re-verified).
- Test coverage of any specific scenario.
- Frontend per-page state.
- `.env.example` vs `backend/config/*.php` field-by-field comparison (F-004
  notes the divergence; the diff itself is for the remediation story).
- The full BMAD planning tree in `_bmad-output/` (read only at directory
  level).

If any of these need an evidence-graded answer, raise a follow-up audit
scoped to that slice.

---

## Proposed Remediation Epics

All findings collapse into a single proposed pre-epic. None require new
production code in this audit.

### Epic 0 — Repository Stabilization (doc + ADR hygiene)

Inserted before continuing Epic 4 work. All stories doc-only; none touch
runtime behaviour. Order is loose — most can be done in parallel.

| Story | Carries | Severity covered | Dependency |
| --- | --- | --- | --- |
| 0.1 — Issue ADR-0004 inverting identity ownership; mark ADR-0002 Superseded | F-001 | Critical | — |
| 0.2 — Resolve `docs/project-context.md` reference: amend CLAUDE.md to point at canonical homes (or create a thin index) | F-002 | Critical | — |
| 0.3 — Refresh `docs/status/implementation-status.md` post-SP-1 (Identity row, frontend column, Epic 4 footnote) | F-003, F-008 | High | SP-1 merge |
| 0.4 — Resolve the four open doc contradictions (workflow tables, mutual feedback, application vs evaluation decisions, `.env.example`) | F-004 | High | — (4 sub-tasks) |
| 0.5 — Add ADRs (or canonical-doc edits) for items 1, 2, 3, 5 in auto-memory | F-005 | Medium | — |
| 0.6 — Add "Doc authority map" to `MANIFEST.md`; reference from rule 16 | F-006 | Medium | — |
| 0.7 — Roadmap pass for the four Absent modules; assign each to a target phase | F-007 | Medium | — |
| 0.8 — Reliability/Audit epic carve-out: confirm or add an epic owning outbox + idempotency + enforced audit + signed webhooks | F-009, F-010 | Medium | — |
| 0.9 — Expand ADR-0001 and ADR-0003 to the standard ADR schema | F-011 | Low | — |
| 0.10 — Regenerate graphify on SP-1 merge commit | F-012 | Low | SP-1 merge |

Acceptance for Epic 0 as a whole: every Critical and High finding has either
a merged remediation or an explicit deferral note in the affected
canonical doc.

### Pre-existing epics — no impact

Existing Epics 1–4 are unaffected by this audit. The 2026-06-23
implementation-readiness report (`faaf258`) is the authoritative status of
those.

---

## Verification of this audit

- All evidence cited above was read in this session (`CLAUDE.md`,
  `.claude/rules/16-documentation.md`, `adr/0001..0003`,
  `docs/status/implementation-status.md`, auto-memory
  `architecture-decisions.md`, the `graphify-out/GRAPH_REPORT.md` outline,
  and directory listings of `docs/`, `_bmad/`, `_bmad-output/`, `adr/`,
  `backend/`, `graphify-out/`).
- No source files in `backend/` or `frontend/` were opened beyond what the
  status doc already records. Findings about source behaviour cite the
  status doc and recent commits, not first-hand re-inspection.
- No production code, migrations, ADR text, or canonical doc content was
  modified by this audit.
- Items marked "Not verified" in the *Out-of-scope* section explicitly were
  not checked.

## Reviewer checklist

- [ ] F-001: confirm the 2026-06-21 inversion is the canonical decision and
      ADR-0004 wording matches it.
- [ ] F-002: confirm whether `docs/project-context.md` should exist or
      `CLAUDE.md` should be amended.
- [ ] F-003: confirm SP-1 merge state before refreshing the status doc.
- [ ] F-004: nominate an owner per contradiction.
- [ ] F-006: confirm where the doc-authority map should live (`MANIFEST.md`
      vs new `docs/README.md`).
- [ ] F-009/F-010: confirm whether a Reliability/Audit epic already exists in
      `_bmad-output/` (not inspected in this pass).
