---
stepsCompleted: [1, 2, 3, 4]
status: complete
augmented: '2026-06-23'
augmentation_notes: 'Phase 1: 14-field augmentation of 23 existing stories via appendix (preserves existing story bodies). Phase 2: added Epic 0 (Repository Stabilization — 10 stories: 3 Done in this session, 7 planning candidates) and Epic R/A (Reliability and Audit Substrate — 9 planning candidates). Phase 3: dependency order placement in Epic List. All new stories = planning candidates; NEVER marked Approved for Implementation.'
inputDocuments:
  - _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
  - _bmad-output/planning-artifacts/ux-designs/ux-Catalesta-2026-06-20/DESIGN.md
  - _bmad-output/planning-artifacts/ux-designs/ux-Catalesta-2026-06-20/EXPERIENCE.md
  - _bmad-output/planning-artifacts/architecture.md
  - docs/product/scope-register.md
  - docs/plan/roadmap.md
scope: Phase 1a (Selection MVP)
---

# Catalesta - Epic Breakdown

## Overview

Complete epic and story breakdown for **Catalesta — Phase 1a (Selection MVP)**, decomposing the PRD, UX spines, and the (P1a-scoped) Architecture into implementable stories. Brownfield: builds on Identity/Organizations/Programs/Cohorts/Stages + the tenancy/decimal/versioning kernels.

## Requirements Inventory

### Functional Requirements

**Identity, Tenancy & Access (reuse-heavy):**
- FR-001: Authenticate with a native Catalesta account (email + password) or an optional linked provider; immutable Account id (ULID) is the user key. (SG OIDC demotes to an optional linked provider — Epic 4.)
- FR-002: Signing up creates an Organization; creator becomes admin.
- FR-003: Every tenant-owned record carries server-set `organization_id` (never mass-assignable).
- FR-004: Tenant queries fail-closed — unresolved tenant returns no rows; cross-tenant returns 404.
- FR-005: RBAC scopes permissions per organization.
- FR-006: Profile reads are consent-aware via the `ConsentProvider` seam, including locally-owned profiles.
- FR-007: Native account registration + email verification + password reset + session (Epic 4 / SP-1).
- FR-008: Link/unlink an optional Startup Gate identity; sign in with SG; `sub` stored on the link (Epic 4 / SP-2).
- FR-009: Consented field-level profile import from SG — source tracking, import history, conflict preview, never auto-overwrite local edits, revocable consent (Epic 4 / SP-4).

**Program & Cohort (reuse):**
- FR-010: Operator creates and publishes a Program.
- FR-011: Operator opens a Cohort with an open/close enrollment window; submissions accepted only while open.
- FR-012: Published stage/version artifacts are immutable and versioned.
- FR-013: Programs can be cloned / saved & instantiated as templates.

**Forms (new, thin / attach-only):**
- FR-020: Attach one published, immutable form to a cohort; P1a field set = short text, long text, single-select, multi-select, number, date, file upload, consent checkbox.
- FR-021: Published form reachable at a public mobile-web application URL.
- FR-022: No arbitrary code in form logic (declarative field definitions only).

**Applications (new):**
- FR-030: Authenticated applicant submits an application bound to a Cohort.
- FR-031: On submission, capture an immutable `submission_snapshot` (answer values + content-addressed file refs + form/program/rubric version IDs); source edits never alter it.
- FR-032: Submission is idempotent (`Idempotency-Key`; duplicate returns original).
- FR-033: Submission to an unpublished/closed cohort is rejected (422).
- FR-034: Operator views the tenant-scoped submission list for a cohort.

**Assessment / Selection (new; decimal kernel reuse):**
- FR-040: Score a submission against the published rubric using decimal arithmetic (`DECIMAL(6,2)`, half-up, declared tie-break).
- FR-041: Scores + rubric version recorded immutably with the scorer's `sub`.
- FR-042: Record an accept/reject Decision (audited); reopening a decision is an audited event.
- FR-043: Export the decision list (CSV) for a cohort.

**Reliability Substrate (new — the seams):**
- FR-050: Transactional outbox — domain events written in the same DB transaction as the state change; relay delivers at-least-once; one consumer in P1a.
- FR-051: Idempotency enforced on application submission and the payment-callback endpoint.
- FR-052: Enumerated audit on program.published, cohort.opened/closed, application.submitted, submission.scored, decision.recorded, decision.reopened, decisions.exported.

**Entitlement seam (socket only in P1a):**
- FR-060: Modules check entitlements only via `EntitlementService` (allow-all in P1a; enforced call sites: program.publish, cohort.open, application.submit).
- FR-070: `PaymentProvider` interface + callback-idempotency — **DEFERRED out of P1a epics** (party-mode, Winston+Amelia): stranded with no live exerciser in a no-billing P1a → moves to the billing epic (P1b). **Design constraint retained:** the idempotency primitive built in Epic 2 must be *consumer-agnostic* so the Geidea callback adopts it later for free.

**Instrumentation (new):**
- FR-080: Record the event taxonomy: application.viewed/started/abandoned{step}, submitted, submission.scored{elapsed}, rubric.edited{cohort,phase}, decision.recorded{time_to_decision}, decisions.exported, export-then-leave.
- FR-081: Dispute/reopen is a first-class audited event (feeds counter-metric C2).

*(Out of these epics — deferred per roadmap: FR-061 metering counter, FR-071..073 Geidea charging → P1b; FR-100+ delivery/platform/extended → P2–P4.)*

### NonFunctional Requirements

- NFR-001: Tenant isolation fail-closed; arch test asserts the trait on every tenant-owned model (C1 = 0 cross-tenant incidents).
- NFR-002: Identity integrity — Account id (ULID) is the primary user key; `sub` is the SG-link key only; email never identifies across systems.
- NFR-003: Immutability & versioning — published artifacts immutable; submissions captured as immutable snapshots.
- NFR-004: Decimal arithmetic — `DECIMAL` math, no floats in score paths.
- NFR-005: No arbitrary code in rules — declarative; validator rejects non-allowed nodes (acceptance-tested).
- NFR-006: Consent-aware access via `ConsentProvider` seam, including locally-owned profiles; SG import requires field-level consent.
- NFR-007: Payment integrity — provider-interface isolation, verified idempotent callbacks, no raw card/CVV, browser return non-authoritative.
- NFR-008: Data-respecting limits — reaching a limit never deletes/hides tenant data.
- NFR-009: Security baseline — secrets not committed; rotation policy; rate limiting; least-privilege.
- NFR-010: Availability & DR — RPO ≤ 15 min, RTO ≤ 4 h [Proposed]; backup/restore.
- NFR-011: Localization & accessibility — P1a renders Arabic + RTL for public flow + core operator screens; P1a a11y floor (full WCAG 2.2 AA hardening deferred to P4).
- NFR-012: Observability — structured logging, metrics, correlation IDs; audit enforced.
- NFR-013: Data governance — Egypt PDPL + GDPR DSR; residency region decided before first pilot.
- NFR-014: Performance — p95 < 500 ms for core operator reads + public form load at the stated load model [Proposed].

### Additional Requirements

**(from Architecture — brownfield + stress-test ADRs; these become substrate stories)**
- AR-1: Brownfield — reuse `BelongsToTenant`, the decimal kernel, the versioning/immutability kernel, the existing module layout. No project-init story.
- AR-2: **Build `idempotency_keys`** (postgres): `UNIQUE(scope, key)` + stored `request_fingerprint` + stored response; same key + different fingerprint → 422; same+same → replay (durable replay for payment callbacks). [ADR-2]
- AR-3: **Build `outbox_events`** + relay worker on the existing queue-worker: write event inside the domain transaction; relay claims rows atomically (`UPDATE … SET dispatched_at WHERE dispatched_at IS NULL RETURNING`); consumer idempotency on `event_id`. [ADR-4]
- AR-4: **Build `submission_snapshot` (jsonb)** for user-submitted payloads — reuse the immutability primitive underneath, separate from published-config snapshots. [ADR-1]
- AR-5: **Build content-addressing** over MinIO: `sha256` key + refcount; GC deferred to a manual command (ticketed debt). [ADR-5]
- AR-6: Per new tenant-owned table, build an explicit cross-tenant isolation test (`BelongsToTenant` is opt-in, not inherited). [ADR-3]
- AR-7: FMA tripwires (code review): outbox insert inside the domain DB transaction; idempotency claims the key before work; Geidea callback signature-verified before any state change; browser return only reads status.
- AR-8: External integrations behind interfaces only — Startup Gate OIDC as an optional linked SSO/import provider (FR-157), Geidea (sandbox, no charge in P1a).

### UX Design Requirements

**(from DESIGN.md + EXPERIENCE.md — first-class inputs)**
- UX-DR1: Implement the design-token system — colors (light + dark, incl. `accentBtn`, `inputBorder`), typography (Space Grotesk display / Inter body / Tajawal Arabic), spacing scale, radii, elevation — per DESIGN.md frontmatter.
- UX-DR2: Build the P1a component set with behavior — primary button (`accentBtn`), input (`inputBorder`, error association), score input (`dir=ltr`, value/max), table row (focusable open-detail distinct from bulk checkbox), status badge (icon+text), limit banner, file upload, empty state, confirm modal (focus contained, Esc, restore).
- UX-DR3: Build the P1a surfaces — signup/org-create, auth transitions, program publish, cohort open, form attach, public application page (mobile-web RTL, **stepped** with Next/Back + progress + per-section autosave), applicant status, submission list, scoring, decision/reopen, CSV export, limit banner (designed, wired P1b), operator home (minimal, not Action Center).
- UX-DR4: Implement the state patterns for every surface — empty / loading / error / permission-404 / closed-cohort-422 / already-submitted-idempotent / immutable-after-submit / limit-reached / auth (pending/fail/IdP-unavailable) / signup-org-create.
- UX-DR5: RTL & bilingual behavior — logical-property mirroring (every screen in `dir=rtl`); `bdi`/isolation on **every interpolated value** in copy; Western numerals everywhere (`-nu-latn` for Arabic dates); `dir=auto` on text fields; verify both modes × both directions on the 2 critical screens.
- UX-DR6: P1a accessibility floor — verified-contrast tokens; **minimal automated a11y CI gate** (contrast + missing-label + lang/dir); full keyboard + visible focus ring; `aria-invalid` + `aria-describedby` error association; modal focus semantics; status not color-alone; 44px mobile targets; `prefers-reduced-motion`.
- UX-DR7: Instrumentation surfaces — stepped form emitting started/abandoned{step}; visible/loggable rubric-edit action; recorded decision time; export-then-leave signal — matching the FR-080 taxonomy exactly.
- UX-DR8: Bilingual microcopy/voice — authored EN/AR copy deck (not machine-translated); plain/exact/reassuring; confirm-before-irreversible-submit copy.

### FR Coverage Map

- FR-001, 003, 004 → **Reused foundation** (Identity/tenancy) — verified within each epic via per-table isolation tests (AR-6).
- FR-002, 005, 006, 010, 011, 012, 013, 020, 021, 022, 060 → **Epic 1** (Stand Up an Intake).
- FR-030, 031, 032, 033, 034, 050, 051, 052 → **Epic 2** (Receive Applications; substrate in the E2.0 gate).
- FR-040, 041, 042, 043, 081 → **Epic 3** (Score & Decide).
- FR-080 → **Epics 2 + 3** via the cross-cutting **Learning Telemetry** deliverable (events emitted by their surfaces).
- FR-062 → **UX surface only in P1a** (limit banner designed in Epic 1 via UX-DR3); **enforcement deferred to P1b** — entitlement is allow-all in 1a (FR-060 socket), so the block has no live trigger until the FR-061 counter lands. Surface built now, wired in P1b.
- FR-070 → **Deferred to the billing epic (P1b)** — not in these P1a epics (design constraint kept in Epic 2).

## Epic List

### Epic 1: Stand Up an Intake *(the precondition)*
An operator signs up, creates an organization, publishes a program, opens a cohort with an enrollment window **(and can close it)**, and attaches a public application form — i.e. opens applications. Includes day-one **zero/empty states**. **Exit criterion (contract for Epic 2):** a published form exposes an **immutable, content-addressed version id**.
**FRs covered:** 002, 005, 006, 010, 011, 012, 013, 020, 021, 022, 060. *(FR-001/003/004 reused; per-table isolation tests per AR-6.)*

### Epic 2: Receive Applications *(largest — opens with the reliability gate)*
Applicants apply on mobile-web (RTL), submit once (immutable snapshot, idempotent, file upload), get an **acknowledgment/receipt** and a status view; the operator sees the tenant-scoped **submission list + a funnel** ("40 viewed, 8 submitted").
- **E2.0 — Reliability gate (named milestone; chaos/concurrency-tested, "submission survives concurrency + crash"), built BEFORE any user-facing flow:** content-addressing (sha256 + refcount) → `idempotency_keys` + `IdempotencyService` (**consumer-agnostic**) → `outbox_events` split into **S3a** (table + transactional producer + `published_at`) and **S3b** (relay worker: poll, atomic-claim, dispatch, retry, dead-letter) → enumerated audit. FMA tripwires per AR-7.
- Then the user-facing flow: `submission_snapshot` (captures the form **version id** from Epic 1's contract) → submit (FR-030/031/032/033) → file upload → receipt → status → operator list + funnel.
**FRs covered:** 030, 031, 032, 033, 034, 050, 051, 052. **ARs:** 2, 3, 4, 5, 7. *(FR-070 removed; design constraint kept.)*

### Epic 3: Score & Decide *(gated on Epic 2 evidence; sequenced AFTER Epic 4)*
The operator scores submissions against the published rubric (decimal), records defensible accept/reject decisions (audited, reopenable), and exports the decision list. **Entry assumption (contract):** scoring reads the **immutable snapshot, never the live form**. **Gate:** do not start until Epic 2 shows applicants actually submit.
**FRs covered:** 040, 041, 042, 043, 081.

### Epic 4: Standalone Identity, Accounts & Profiles *(foundational — runs after Epic 2 review closes, BEFORE Epic 3)*
Catalesta becomes the system of record for accounts and identity. Native registration/auth/account-management and locally-owned multi-role profiles; Startup Gate demotes to an optional linked SSO provider + consented import source. Delivered as four dependency-ordered sub-projects, each with its own brainstorm→spec→plan cycle: **SP-1** native accounts & auth (local `accounts` + N `linked_identities`; migrate existing `ExternalUser` rows; memberships repoint to Account) → **SP-2** SG OIDC as an optional linked provider (link/unlink) → **SP-3** the 7 local role-profile types (Founder, Startup, Mentor, Service Provider, Investor, Trainer, Judge; system of record) → **SP-4** consented SG import pipeline (field-level consent, source tracking, history, conflict preview, never-overwrite-local, revocation).
**FRs covered:** 007, 008, 009 (+ supersedes the SG-mock framing of 001/006). **Spec:** `docs/superpowers/specs/2026-06-21-standalone-identity-design.md`.

**Epic 1 impact ledger (forward note — do NOT edit closed story files):** Story 1.1 shipped sign-up as "first SG-OIDC-mock login → create org," and the auth provider is `ExternalUser` keyed on `sub`. Epic 4 / SP-1 supersedes this: sign-up becomes native account registration, `ExternalUser` rows migrate to `accounts` + `linked_identities`, and `organization_memberships` repoint to `account_id`. Epic 2's in-review stories keep working across the migration and adopt the account model when SP-1 lands. **Identity supersession (Epic 1–3):** the `sub`-as-actor-key references in already-written ACs — e.g. the `audit_events` actor in Story 2.5, the scorer's identity in FR-041 and Stories 3.1–3.2, the operator in Story 3.2 — and the "consent against the mock" framing in Story 1.5 (FR-006) are left intact to reflect what shipped/is in review, and are superseded by Epic 4: SP-1 repoints the audit/actor key to the **Account id** and SP-4 makes consent local. Do not rewrite those ACs piecemeal; the Epic 4 migration resolves them together.

### Cross-cutting deliverable: Learning Telemetry *(named, acceptance-gated — not a separate build epic)*
The World-A/B learning data (FR-080) — `application.viewed/started/abandoned{step}` (Epic 2), `submission.scored{elapsed}` / `decision.recorded{time_to_decision}` / `decisions.exported` + export-then-leave (Epic 3). **DoD rule:** no Epic 2/3 story closes until its events emit **and are verified in a dashboard a human has looked at.** Surfaced to the operator (the funnel) as well as to the team.
**FRs covered:** 080 (+ 081 dispute/reopen event from Epic 3).

### Recent additions (2026-06-23 — planning candidates only)

#### Epic 0: Repository Stabilization *(doc-only, parallel to all engineering work)*
Closes `docs/repository-audit.md` findings F-001 through F-012. 10 stories total — **3 Done in this session** (0.1 ADR-0004 + ADR-0002 supersession; 0.2 `docs/project-context.md`; 0.8 Reliability/Audit epic carve-out architectural slot), **7 planning candidates** (0.3 status doc refresh; 0.4 four open doc contradictions; 0.5 ADRs for auto-memory §1/§2/§3; 0.6 doc authority map; 0.7 roadmap pass for 4 absent modules; 0.9 ADR-0001 + ADR-0003 schema expansion; 0.10 graphify regeneration). **Dependency:** none — runs parallel to all engineering work.

#### Epic R/A: Reliability and Audit Substrate *(carve-out before P2)*
Cross-cutting home `backend/app/Reliability/` per architecture.md §6 Step 6 (Opt 2: sibling of `app/Tenancy/`, `app/Storage/`). Owns FR-126 (reclassified from P3 → R/A), signed-webhook substrate (new), substrate generalization (outbox + idempotency + audit-set extension from P1a's enumerated set), NFR-015 architecture test (single-DB topology), Step 5 P3/P7/P8 enforcement tests, and OpenAPI→zod codegen pipeline. **9 planning candidates** (RA.1 skeleton; RA.2 audit-enforced middleware; RA.3 signed-webhook substrate; RA.4 generalized outbox; RA.5 generalized idempotency; RA.6 NFR-015 arch test; RA.7 event naming + versioning arch tests; RA.8 API versioning arch test; RA.9 OpenAPI→zod codegen pipeline). **Dependency:** enters Ready after Epic 2 substrate is stable + SP-1 merged + Epic 0.8 done (last is done this session). **Blocks:** no P2 capability epic (FR-100..108) should ship before Epic R/A's generalization stories land — current P1a substrate works for P1a but is a liability once P2's multi-consumer outbox + cross-module idempotency need it.

#### Dependency order (2026-06-23 update)

```
Epic 0 (Repository Stabilization, doc-only)  ←  runs parallel, blocks nothing
  ↓
Epic 1 (Stand Up an Intake)                   ←  DONE
Epic 2 (Receive Applications)                 ←  DONE
SP-1 (Native accounts + auth)                 ←  DONE
  ↓
Epic 3 (Score & Decide)                       ←  IN FLIGHT
Epic 4 / SP-2, SP-3, SP-4 (Identity completion) ←  IN FLIGHT
  ↓
Epic R/A (Reliability and Audit Substrate)    ←  carve-out before P2
  ↓
P2 capability epics (FR-100..108)             ←  deferred; out of scope this turn
P3 / P4 capability epics                      ←  deferred
```

---

## Epic 1: Stand Up an Intake

An operator can sign up, create an organization, publish a program and an immutable application form, and open (and close) a cohort that exposes a public application URL. **Exit contract for Epic 2:** the published form carries an immutable, content-addressed version id. Builds on the existing Identity/Organizations/Programs/Cohorts/Stages modules.

### Story 1.0: Frontend foundation (design tokens, component set, a11y gate)

As a **frontend developer**,
I want the DESIGN.md token system, the minimal P1a component set, and the accessibility CI gate in place,
So that every feature story builds on consistent, accessible, RTL-ready primitives instead of re-inventing them.

**Acceptance Criteria:**

**Given** the DESIGN.md tokens (UX-DR1) and EXPERIENCE.md behavior (UX-DR2/6)
**When** the foundation is implemented
**Then** the token layer exposes colors (light + dark, incl. `accentBtn`, `inputBorder`), typography (Space Grotesk / Inter / Tajawal), spacing, radii, elevation as the single source consumed by all components
**And** the **minimum component set** ships (and ONLY this set — defer modal/table/dropdown/toast/date-picker/file-chrome to the first feature that needs them): **Button** (primary `accentBtn`/secondary/disabled/loading), **TextInput + field wrapper** (label/`inputBorder`/error/helper, RTL-aware with `aria-invalid`+`aria-describedby`), **Form layout primitive** (field group + inline validation slot), **Banner/inline alert** (info/error/success), **Spinner + skeleton**, **Empty/error/offline state block** (one component, three messages), **App shell + direction provider** (live LTR↔RTL switch, font wiring, light/dark token surface), **Link/text styles**
**And** the **minimal a11y CI gate** runs on every build: contrast (verified token pairs), missing-form-label, and `lang`/`dir` presence — failing the build on regression (UX-DR6)
**And** RTL/bidi is owned **here** (the direction provider + `dir="auto"` field behavior), not re-decided per feature story
**And** the P1a dark-mode decision is explicit (tokens ship for both; whether a toggle is in P1a scope is stated, not assumed)
**Definition of Done:** later stories MUST consume these primitives and MUST NOT re-implement buttons/inputs/RTL/state-blocks.

### Story 1.1: Sign up and create an organization

As a **prospective program operator**,
I want to sign in and create my organization,
So that I have a tenant workspace where I become the admin.

**Acceptance Criteria:**

**Given** a user authenticated via the Startup Gate OIDC mock with no organization
**When** they complete the create-organization form (organization name)
**Then** an Organization is created with a server-set `organization_id`, the creator is assigned the admin role (FR-002, FR-005), and they land on the operator Home
**And** the org-create form is not skippable — a user with no org cannot reach any console surface
**And** a second org with a duplicate name within scope is rejected with a clear validation message (entered name preserved)
**And** a cross-tenant isolation test asserts the new `organizations` access path returns 404 across tenants (AR-6).

### Story 1.2: Create and publish a program

As an **operator**,
I want to create a program and publish it,
So that I have a published, immutable basis to run cohorts from.

**Acceptance Criteria:**

**Given** an operator in their organization
**When** they create a program and publish it
**Then** a published, immutable program version is recorded (FR-010, FR-012); editing creates a new version, never mutating the published one
**And** `program.publish` is gated through `EntitlementService` (allow-all in P1a) at the call site (FR-060)
**And** an existing program can be cloned or instantiated from a template into a new draft (FR-013)
**And** publishing is audited (`program.published`).

### Story 1.3: Build and publish an application form (content-addressed version)

As an **operator**,
I want to assemble and publish an application form from the supported field types,
So that applicants have a stable, immutable form to fill in.

**Acceptance Criteria:**

**Given** a program in the operator's organization
**When** they add fields (short text, long text, single-select, multi-select, number, date, file upload, consent checkbox) and publish the form
**Then** the form is published immutably and exposes an **immutable, content-addressed version id** (sha256 of the canonical definition) — the Epic 2 snapshot contract (FR-020, FR-012)
**And** the form definition is declarative only; an attempt to embed code/expressions outside the allowed field types fails validation with a test (FR-022, NFR-005)
**And** editing a published form produces a new version with a new version id; the prior version id remains resolvable.

### Story 1.4: Open and close a cohort with a public application URL

As an **operator**,
I want to open a cohort with an enrollment window and get a public application URL,
So that applicants can apply during the window, and I can stop accepting when done.

**Acceptance Criteria:**

**Given** a published program with a published form
**When** the operator opens a cohort with an open/close datetime and attaches the published form
**Then** a public, mobile-web application URL is produced for the cohort (FR-011, FR-021), and `cohort.open` is gated via `EntitlementService` (FR-060)
**And** the operator can **close** the cohort manually before its close datetime
**And** while the window is open the public URL serves the form; once closed or before open it does not accept submissions (the 422 behavior is asserted in Epic 2)
**And** opening/closing are audited (`cohort.opened`, `cohort.closed`).

### Story 1.5: Operator Home and consent-aware reads (day-one states)

As an **operator**,
I want a Home that shows my cohorts and the one next action, with sensible day-one empty states,
So that a brand-new organization is not a blank screen.

**Acceptance Criteria:**

**Given** an operator with zero or some cohorts
**When** they open Home
**Then** Home shows current cohorts and the single next action ("open a cohort" / "N submissions to score"), and day-one shows a zero/empty state that explains the first action (not the deferred Action Center)
**And** any profile read is consent-aware via the `ConsentProvider` seam against the mock (FR-006, NFR-006)
**And** Home renders correctly in both light/dark and LTR/RTL (UX-DR5/6).

---

## Epic 2: Receive Applications

Applicants apply on mobile-web (RTL), submit once (immutable snapshot, idempotent, file upload), get a receipt + status; the operator sees the submission list and funnel. **Stories 2.1–2.5 are the E2.0 reliability gate** — built and chaos/concurrency-tested ("a submission survives a double-click and a mid-write crash") *before* any story builds user-facing flow on top. Each substrate story is tested against a throwaway consumer (no dependency on Applications).

### Story 2.1: Content-addressed blob storage  *(E2.0 gate)*

As a **platform engineer**,
I want files stored and identified by content hash over MinIO,
So that uploads are deduped and immutably referenceable by snapshots.

**Acceptance Criteria:**

**Given** the MinIO store
**When** a file is stored
**Then** its key is the `sha256` digest of its content and a refcount is recorded (AR-5); storing identical content twice dedupes to one blob with refcount 2
**And** a stored blob is retrievable by digest and is immutable
**And** garbage collection is a separate manual command (not automatic) — orphan handling is ticketed debt, documented
**And** this story has no dependency on Applications (tested directly).

### Story 2.2: Idempotency primitive  *(E2.0 gate)*

As a **platform engineer**,
I want a consumer-agnostic idempotency service,
So that any retried operation produces exactly one effect.

**Acceptance Criteria:**

**Given** the `idempotency_keys` table (`scope, key, request_fingerprint, response_snapshot, locked_at, expires_at`, `UNIQUE(scope,key)`)
**When** `IdempotencyService::remember(scope, key, fingerprint, fn)` is called twice with the same key+fingerprint
**Then** the work runs once and the second call replays the stored response (FR-051, AR-2)
**And** the same key with a *different* fingerprint returns **422**, never a wrong-cached replay
**And** two concurrent first-calls (two DB connections) resolve to one writer via the key claim/row-lock; the loser does not double-write
**And** it is tested via a throwaway closure — no HTTP, no Applications. Design constraint: nothing in the signature is submission-specific (the Geidea callback can adopt it later).

### Story 2.3: Transactional outbox — table and producer  *(E2.0 gate)*

As a **platform engineer**,
I want domain events written in the same transaction as the state change,
So that an event is never lost or orphaned relative to its data.

**Acceptance Criteria:**

**Given** the `outbox_events` table (`id, event_type, payload, dispatched_at`)
**When** a domain write occurs
**Then** the outbox row is inserted **inside the same DB transaction** as the domain write (FR-050, AR-3, AR-7); if the transaction rolls back, neither the data nor the event exists
**And** request handlers never dispatch to redis directly — only the producer writes the row (code-review tripwire)
**And** tested with a hand-inserted/rolled-back row; no relay or real consumer required yet.

### Story 2.4: Outbox relay worker  *(E2.0 gate — completes the gate)*

As a **platform engineer**,
I want a relay that reliably drains the outbox to one consumer,
So that events are delivered at-least-once even under concurrency and crashes.

**Acceptance Criteria:**

**Given** undispatched `outbox_events` rows
**When** the relay runs on the existing queue-worker
**Then** it claims rows atomically (`UPDATE … SET dispatched_at = now() WHERE dispatched_at IS NULL RETURNING …`, never SELECT-then-UPDATE), dispatches to the single P1a consumer (log/dev transport), and marks them dispatched (AR-3)
**And** the consumer is idempotent on `event_id` (a redelivered event has no second effect)
**And** failed dispatch retries with bounded exponential backoff and lands in a dead-letter store after the cap
**And** a concurrency test (two relay instances) shows no row is double-claimed; a crash-mid-dispatch test shows the row redelivers, not vanishes — **this is the E2.0 "survives concurrency + crash" gate.**

### Story 2.5: Enumerated audit trail  *(E2.0 gate)*

As a **compliance-conscious operator**,
I want significant actions recorded in an append-only audit trail,
So that selection activity is defensible.

**Acceptance Criteria:**

**Given** the enumerated P1a action set
**When** any of `program.published, cohort.opened, cohort.closed, application.submitted, submission.scored, decision.recorded, decision.reopened, decisions.exported` occurs
**Then** an immutable `audit_events` row is written with actor `sub`, organization, and timestamp (FR-052, NFR-012)
**And** audit completeness for the enumerated set is acceptance-tested (a missing action fails the test)
**And** the audit store is separate from the versioning store (it records "who did what", not "what the value was").

### Story 2.6: Application submission record + immutable snapshot

As an **applicant**,
I want my submitted answers frozen at submit time,
So that what I submitted cannot be silently altered later.

**Acceptance Criteria:**

**Given** a published form (Epic 1's content-addressed version id) and an open cohort
**When** an application is created
**Then** an `application_submissions` row is stored bound to the cohort, with an immutable `submission_snapshot` (jsonb) capturing answer values, content-addressed file refs (Story 2.1), and the **form/program version ids** (FR-030, FR-031, AR-4)
**And** later edits to the source form/program never alter the stored snapshot (asserted by mutating the source and re-reading the snapshot)
**And** a cross-tenant isolation test covers the new tables (AR-6).

### Story 2.7: Public idempotent submit flow + receipt  *(Learning Telemetry: gated DoD)*

As an **applicant**,
I want to complete and submit the application once on my phone and get a confirmation,
So that I know it was received and I can't accidentally double-submit.

**Acceptance Criteria:**

**Given** the public mobile-web cohort URL (RTL/Arabic capable)
**When** the applicant completes the **stepped** form and submits
**Then** the submit is wrapped in the idempotency service (Story 2.2) and a duplicate `Idempotency-Key` returns the original result, not a second record (FR-032); `ApplicationSubmitted` is emitted to the outbox (Story 2.3)
**And** submission to a closed/unpublished cohort is rejected with **422** and a clear message (FR-033)
**And** the applicant sees an **acknowledgment/receipt** and a read-only status; before final submit a confirm step warns "you can't edit after submitting" (UX-DR4/8)
**And** the stepped form emits Learning Telemetry events `application.viewed/started/abandoned{step}/submitted` (FR-080) — **and this story does not close until those events are verified in a dashboard a human has looked at.**

### Story 2.8: Operator submission list + funnel

As an **operator**,
I want to see submissions and a simple funnel for my cohort,
So that I can tell whether my intake is working.

**Acceptance Criteria:**

**Given** a cohort with applications
**When** the operator opens Submissions
**Then** they see the tenant-scoped submission list (FR-034) with empty/loading states, and a **funnel** ("N viewed, M started, K submitted") sourced from the Learning Telemetry events
**And** the list renders in light/dark and LTR/RTL, with the row exposing a focusable "open detail" control distinct from any bulk control (UX-DR2/6)
**And** the funnel is the operator-facing view of the same telemetry used for the World-A/B band.

---

## Epic 3: Score & Decide

The operator scores submissions against the published rubric (decimal), records defensible accept/reject decisions (audited, reopenable), and exports. **Entry contract:** scoring always reads the **immutable snapshot (Story 2.6), never the live form**. **Epic gate:** do not start until Epic 2 shows applicants actually submit (the funnel has real data).

### Story 3.1: Score a submission against the rubric

As an **evaluator/operator**,
I want to score a submission against the published rubric with exact decimals,
So that scores are reproducible and defensible.

**Acceptance Criteria:**

**Given** a submission's immutable snapshot and the cohort's published rubric
**When** the operator enters per-criterion scores
**Then** scoring uses decimal arithmetic (`DECIMAL(6,2)`, half-up, declared tie-break order) with no floating point in any score path (FR-040, NFR-004); the total is computed and displayed as `value / max`
**And** scores are recorded immutably with the rubric version and the scorer's `sub` (FR-041)
**And** scoring reads the **snapshot**, never re-reading the current form (entry contract; asserted by mutating the source form and confirming the score basis is unchanged)
**And** the score commit emits `submission.scored{elapsed}` telemetry; if the operator edits rubric criteria during an open cohort, `rubric.edited{cohort,phase}` is emitted (FR-080 — both are World-A signals).

### Story 3.2: Record an accept/reject decision (auditable, reopenable)

As an **operator**,
I want to record and, if needed, reopen accept/reject decisions,
So that selection outcomes are deliberate and correctable with a trail.

**Acceptance Criteria:**

**Given** a scored submission
**When** the operator records an accept or reject decision
**Then** the decision is stored and **audited** (`decision.recorded`) with the operator's `sub` and time, and `decision.recorded{time_to_decision}` telemetry is emitted (FR-042, FR-080)
**And** reopening a recorded decision is itself a first-class **audited** event (`decision.reopened`) that feeds the dispute/reopen counter C2 (FR-081)
**And** the decision commit uses a confirm step (the irreversible-action modal with focus containment) before writing (UX-DR2/6).

### Story 3.3: Export the decision list (CSV)

As an **operator**,
I want to export the cohort's decisions as CSV,
So that I can act on the selection outside the tool and prove it later.

**Acceptance Criteria:**

**Given** a cohort with recorded decisions
**When** the operator exports
**Then** a CSV of the decision list is produced (FR-043) and the export is **audited** (`decisions.exported`)
**And** an `export-then-leave` signal is captured (export followed by no further in-product action within the window) — the World-A/B retention signal (FR-080)
**And** the export action is tenant-scoped (only this org's cohort) and shows empty/loading/large-export states.

---

## Epic 4: Standalone Identity, Accounts & Profiles

Catalesta becomes the system of record for accounts, identity, and consent. Native registration / auth / account-management plus locally-owned multi-role profiles; Startup Gate demotes to an optional linked SSO provider + consented profile-import source — never authoritative. **Entry contract (from Epic 2):** the in-flight OIDC-mock-based stories keep working across the migration via §3.1 backfill. **Exit contract (for Epic 3):** scoring reads the **Account id (ULID)** as the canonical actor key, not `sub`.

Sequenced **after Epic 2 review closes, BEFORE Epic 3**. Spec: `docs/superpowers/specs/2026-06-21-standalone-identity-design.md`.

### Story 4.1: Native accounts & identity-model inversion (SP-1a — ✅ DONE)

As a **platform engineer**,
I want existing `external_users` rows migrated to `accounts` + `linked_identities` without changing behavior,
So that future native-auth and SG-link work builds on the new model while in-flight OIDC-mock stories keep running.

**Acceptance Criteria:**

**Given** the shipped `external_users` table keyed on `startup_gate_subject_id`
**When** the SP-1a migration runs
**Then** every `external_users` row becomes one `accounts` row (`id` ULID, email from claims, `password_hash` null) + one `linked_identities` row (`provider=startup_gate, subject_id=<old sub>`)
**And** `organization_memberships` repoints from `external_user_id` to `account_id` (column renamed/redefined; a membership belongs to an Account)
**And** the existing OIDC-mock auth path keeps working unchanged (Sanctum SPA cookie-session transport preserved)
**And** the test seam `TestCase::makeExternalUser` is replaced with an Account factory; `actingAs('web')` resolves to an Account
**And** `profile_snapshots` and `participant_stage_statuses` no longer reference the dropped `external_user_id` columns (assertion test).

*Shipped: commit `ecd9fa8`; verified via `IdentityModelInversionTest` + `AccountProjectionTest`.*

### Story 4.2: Native auth backend (SP-1b-i — ✅ DONE)

As an **applicant or operator**,
I want to register a Catalesta account with email + password, verify my email, recover a forgotten password, and stay signed in via session,
So that the platform is fully operational with zero Startup Gate dependency.

**Acceptance Criteria:**

**Given** the SP-1a Account + LinkedIdentity model
**When** native-auth endpoints are exposed under `/api/v1/auth/*`
**Then** registration creates an Account (lowercase email, hashed password, `email_verified_at` null, `MustVerifyEmail` behaviour), throttled per IP, audited as `auth.register`
**And** password login validates credentials **enumeration-safely** (login never reveals user existence; same response for wrong-password and unknown-email; throttled per `email|ip`)
**And** SSO-only accounts (`password_hash` null) cannot native-login
**And** password reset uses Laravel's broker pattern with queued mail, no enumeration leak
**And** email verification uses signed routes with throttling
**And** `GET /api/v1/auth/session` returns the AccountSessionResource (email lowercased, verified state, role-profiles stub)
**And** `EnsureEmailVerified` middleware blocks console / onboarding for unverified accounts (gates org create + join)
**And** every state-changing endpoint runs through CSRF preflight (Sanctum `statefulApi`).

*Shipped: see `NativeAuthController`, `auth.*` route group, `auth_logs` enumerated actions; verified via `PasswordLoginTest`, `PasswordResetTest`, `EmailVerificationTest`, `EmailVerifiedGateTest`, `RegistrationTest`, `NativeAuthFoundationTest`.*

### Story 4.3: Native auth frontend (SP-1b-ii — ✅ DONE)

As an **applicant or operator using the UI**,
I want screens for register, login (native + SG), email verification, forgot password, and reset password,
So that native auth works end-to-end in the browser, light/dark + LTR/RTL, with the P1a a11y floor met.

**Acceptance Criteria:**

**Given** the SP-1b-i native-auth API + the SP-1a Account model
**When** the frontend ships under `/api/v1/auth/*`
**Then** the session schema is evolved to carry native-account identity alongside SG-projected fields
**And** a CSRF-preflight `csrfFetch` wrapper auto-fetches `/sanctum/csrf-cookie` when absent and sets `X-XSRF-TOKEN` on every mutation (preflight failures surface as `CsrfPreflightError`, not silent 419s)
**And** `RegisterPage`, `ForgotPasswordPage`, `ResetPasswordPage`, `VerifyEmailNotice`, `EmailVerifiedPage` exist with Storybook stories + Vitest tests + axe coverage
**And** `LoginPage` exposes a native-credential form alongside the SG OIDC path
**And** a safe post-login redirect helper rejects open-redirects (`//host`, `/\host`, absolute URLs) and falls back to `/`
**And** all surfaces render light/dark × LTR/RTL with `bdi` on interpolated values and `dir="auto"` on text fields.

*Shipped: see PR #26; verified via `RegisterPage.test.tsx`, `ForgotPasswordPage.test.tsx`, `ResetPasswordPage.test.tsx`, `VerifyEmailNotice.test.tsx`, `EmailVerifiedPage.test.tsx`, `LoginPage.test.tsx`, `postLoginRedirect.test.ts`, `csrf.test.ts`, `auth.test.ts`.*

### Story 4.4: Optional Startup Gate linked provider (SP-2 — backlog)

As an **applicant with an existing SG identity**,
I want to link my SG account to my Catalesta account, sign in with SG, and unlink later if I want,
So that I can keep using my SG identity without it being authoritative for my Catalesta data.

**Acceptance Criteria:**

**Given** a logged-in Catalesta account
**When** the user initiates "Link Startup Gate" and completes the SG OIDC flow
**Then** a `linked_identities` row is created `(provider=startup_gate, subject_id=<sub>)` bound to the account
**And** `last_login_at` and encrypted token material live on the link row (never on `accounts`)
**And** "Sign in with Startup Gate" resolves an SG `sub` to its linked account; if no link exists, the flow prompts to link to an existing account or create a new one
**And** unlinking removes the `linked_identities` row but leaves the account fully usable (password and/or other links remain)
**And** an account with no password and only one SG link **cannot unlink** without first setting a password (no lockout)
**And** every link / unlink is audited (`auth.sg.linked`, `auth.sg.unlinked`).

*FR covered: FR-008. Spec to be authored when picked up.*

### Story 4.5: Multi-role local profiles (SP-3 — backlog, epic-sized)

As a **platform user with one or more roles**,
I want a base profile plus role-profile entries (Founder, Startup, Mentor, Service Provider, Investor, Trainer, Judge) that Catalesta owns as system of record,
So that role-aware features (delivery, evaluation, mentorship) read structured local data instead of depending on Startup Gate.

**Acceptance Criteria:**

**Given** the Account model from SP-1a
**When** profiles are introduced
**Then** a `profiles(account_id)` base row exists for every account
**And** `role_profiles(account_id, role_type)` rows hold per-role structured data, completion state, and **per-field source tracking** (locally-entered vs SG-imported)
**And** legacy names map: Evaluator → Judge, Funder → Investor
**And** Operator/Admin and Platform Admin remain RBAC role assignments, NOT profile types
**And** profile reads enforce consent via the `ConsentProvider` seam (including locally-owned profiles per CLAUDE.md rule 11)
**And** completion state per role is queryable (drives onboarding next-actions).

*FR coverage gap (flagged in IR report 2026-06-23): PRD currently has no FR for "platform owns the 7 enumerated role-profile types as system of record." Recommend adding `FR-014` (or similar; gaps reserved) in PRD §6.1 before SP-3 starts. Spec to be authored when picked up.*

### Story 4.6: Consented Startup Gate import pipeline (SP-4 — backlog)

As a **user with a linked Startup Gate identity**,
I want to import selected profile fields from Startup Gate after granting explicit, field-level consent, with full control over what's imported and when,
So that my local profile is enriched from SG without ever losing my local edits or being silently overwritten.

**Acceptance Criteria:**

**Given** an account with a linked SG identity (from SP-2)
**When** the user opens "Import from Startup Gate"
**Then** a field-level consent UI lists every importable field with a granted/denied toggle per field
**And** on import, each consented field writes to the local profile with `source=startup_gate` and `imported_at`; non-consented fields are not fetched
**And** fields with a locally-edited source (`source=local`) are **NEVER** auto-overwritten — instead a **conflict-preview** step shows local vs incoming and asks the user to keep, replace, or merge
**And** a `profile_imports` history row records each import (granted scopes, count of fields written, count deferred-to-conflict)
**And** consent is revocable per-field; revoking a field does NOT delete already-imported local data (it just blocks future re-imports)
**And** unlinking SG (SP-2) does NOT delete imported data (it stays in the local profile with `source=startup_gate` as historical attribution)
**And** never-overwrite + import-history + revocation are acceptance-tested.

*FR covered: FR-009. Spec to be authored when picked up.*

---

## Edge-Case Hardening (folded from the Boundary & Edge Case Sweep — Amelia + Sally)

Additional acceptance criteria on the named stories. **★ = correctness hole, must-fix-before-green.**

- **1.1** — dup org-name collision is unicode/case/whitespace-normalized; first-admin assignment is **atomic** with org create (never an org with no admin); not-skippable enforced at the **API**, not just the UI.
- **1.3** — form version id = `sha256` of the canonical definition and is **org-scoped** (a tenant cannot enumerate another tenant's form by content hash); identical content republished is idempotent (no dup row; defined refcount/owner).
- **2.2 ★** — uniqueness is `(scope,key)` with a negative cross-scope test; **fingerprint includes the actor** (no cross-actor replay leak); in-flight (locked, no response yet) duplicate → **409**; `fn` failure semantics defined and tested (cached failure-replay vs key release — pick one); response has a **max-size cap** (oversize → fail-closed, never truncate-and-replay); crash between `fn` success and response-write is **recoverable** (lock reclaim/expiry), not locked-forever; no TTL → declare "never expires" or define eviction + hit-after-evict behavior.
- **2.3** — prove the **rollback** case: domain txn aborts → no orphan outbox row (the test that proves the in-txn invariant).
- **2.4** — poison message bounded by **max-attempts AND max-age** → dead-letter; `dispatched_at` set **DB-side `now()`**, never app clock; claimed-but-undispatched rows **reclaimed after a visibility timeout** (relay crash mid-batch); `event_id` dedupe has a defined retention window; per-aggregate ordering stated (or explicitly "no ordering guarantee").
- **2.5** — UPDATE/DELETE on `audit_events` **denied at the DB layer** (revoked grants/trigger), not only app-layer.
- **2.6 ★** — **GC must refuse to collect any blob referenced by a `submission_snapshot`** (refcount pinned before the snapshot is durable); a referenced blob must be **finalized + sha256-verified** before referenceable (no half-upload); snapshot binds the **resolved** version id at submit time (race-safe vs republish); null/empty submission + max-payload boundaries defined.
- **2.7 ★** — the FR-033 **close-check and the snapshot write are one transaction** (re-check close inside the write); Idempotency-Key replay after a mid-attempt close replays the original receipt, not a re-evaluation. **UX states:** per-step **local autosave** + reconnect banner; **closed-mid-session** full-screen state (distinct from arrived-after-close) with cohort name + close time; pre-upload client guard (size/type) + mid-upload retry; submit button **optimistic lock** ("Submitting…") and the deduped second tap resolves to the **same calm receipt** (never a duplicate error); language switch EN↔AR preserves the draft + flips direction; `dir="auto"` per field for mixed Arabic/Latin input; a **keepable receipt** = on-screen **reference number** + an emailed/keepable artifact (support can find an accountless applicant by reference).
- **2.8** — zero-day funnel shows an **empty state + copyable share link** (not "0/0/0"); `viewed` clamped ≥ `started` with "views are approximate" microcopy (beacon-loss undercount).
- **3.1** — score boundary inclusive at max (`<=`); below-min/negative **rejected**; over-scale input **rejected** (half-up applies to computed totals, not to truncating user input); tie-break uses a **total ordering** (e.g. submission_id), deterministic across re-runs; aggregate sum cannot overflow `DECIMAL(6,2)`; each score row stores the form/rubric version it scored.
- **3.2** — after reopen, the UI shows the state change + the **blast radius** ("Reopened. The applicant has not been notified" / or "was already emailed a decision").
- **3.3** — empty-cohort export → **headers-only CSV** (or disabled with "Nothing to export yet"); exports while the cohort is open carry an **"as of [time]"** stamp.

**Top-3 must-fix-before-green:** 2.7 close-boundary race · 2.6 GC-vs-snapshot refcount invariant · 2.2 in-flight/crash semantics.

---

## Epic 0: Repository Stabilization (added 2026-06-23 — doc-only)

Originated from `docs/repository-audit.md` (12 findings: 2 Critical, 2 High, 5 Medium, 3 Low). Closes audit findings F-001 through F-012 across 10 stories. **All stories are doc-only — no production code, no migrations, no API surface.** Three stories already landed in this session and are recorded for audit trail; seven remain as planning candidates.

**Epic dependency:** none. Epic 0 has no engineering dependency, blocks nothing, and is blocked by nothing — runs parallel to Epic 3 + Epic 4 implementation.

### Story 0.1: ADR-0004 (identity inversion) + ADR-0002 supersession

**Status:** **Done 2026-06-23** (commit `1c41a9a` on branch `docs/prd-update-2026-06-23`).
**Carries:** audit F-001.
**Outcome:** `adr/0004-catalesta-identity-system-of-record.md` created (Accepted); `adr/0002-startup-gate-system-of-record.md` Status flipped to "Superseded by ADR-0004"; both follow the standard 5-section ADR schema (Status / Context / Decision / Alternatives / Consequences / References).

### Story 0.2: Create `docs/project-context.md`

**Status:** **Done 2026-06-23** (commit `8834bb0` on branch `docs/prd-update-2026-06-23`).
**Carries:** audit F-002.
**Outcome:** `docs/project-context.md` created with PHP/Laravel rules (18 subsections) + Database Topology section. CLAUDE.md authority-order #5 reference now resolves.

### Story 0.3: Refresh `docs/status/implementation-status.md` post-SP-1

**Status:** **Done 2026-06-23** — status doc rewritten to current truth (post-SP-1 identity, Epic 1+2 delivered, 384 tests, Frontend per-module column added, Epic 4 footnote, Audit opt-in/reliability-substrate cross-cutting updated, Last-updated bumped).
**Type:** Planning candidate (not Approved for Implementation)
**Business objective:** Restore canonical as-built status to current truth so reviewers can distinguish "shipped" from "claimed."
**Actor:** Engineering owner of the status doc.
**Business rules:** Status doc is the single source for what is built; refresh on every Epic completion + on SP-N completion. Per `.claude/rules/16-documentation.md`, update documentation in the same change as the change being recorded.
**Acceptance criteria:**
- Identity row reflects the post-SP-1 model (Account ULID + linked_identities, not `ExternalUser`)
- Forms / Applications / Cohorts rows updated to current state (readiness 2026-06-23 marks Epic 1 + Epic 2 complete)
- New "Frontend per-module" column added (audit F-008)
- Epic 4 row added or footnoted, with SP-1 status
- Cross-cutting section updated: Audit module stays "opt-in" until Epic R/A lands; reliability substrates "Absent" → "in Epic R/A scope" after R/A scoping
- Header `Last-updated` field bumped
**Authorization:** N/A — doc; PR review controls
**Tenant isolation:** N/A — doc
**Data + migration impact:** N/A — doc
**API impact:** N/A — doc
**UI states:** N/A — doc
**Failure behaviour:** PR review catches inaccuracy; no runtime failure mode
**Audit requirements:** N/A — commit history is the audit
**Test expectations:** Reviewer sign-off; no automated test
**Dependencies:** SP-1 merged to main; Epic 1 + Epic 2 verified per readiness 2026-06-23
**Rollback:** `git revert` the refresh commit

### Story 0.4: Resolve four open doc contradictions

**Type:** Planning candidate (4 sub-tasks; may split before implementation)
**Business objective:** Eliminate ambiguities in canonical docs that bias affected-story implementation.
**Actor:** Engineering + PM per contradiction.
**Business rules:** Each contradiction listed in auto-memory `architecture-decisions.md` § How-to-apply must have a written decision before the affected story enters Ready.
**Acceptance criteria:**
- Workflow table set: docs/04 vs docs/07 — choose canonical, redirect losing doc
- Mutual-feedback one-vs-two tables — choose one schema, record decision
- application_decisions vs evaluation_decisions — choose authoritative table for stage outcomes
- `.env.example` aligned with current `backend/config/*.php` (Redis / S3 / OIDC)
- Each decision logged in `_bmad-output/.../.decision-log.md` or auto-memory
**Authorization:** N/A
**Tenant isolation:** N/A
**Data + migration impact:** Only `.env.example` sub-task touches an environment artifact; no schema or data change
**API impact:** N/A
**UI states:** N/A
**Failure behaviour:** Reviewers may reject a direction; story splits as needed
**Audit requirements:** N/A
**Test expectations:** Reviewer confirms losing doc's old text no longer appears outside archive comment
**Dependencies:** None (4 independent sub-tasks)
**Rollback:** `git revert` per sub-task

### Story 0.5: Add ADRs for auto-memory §1, §2, §3 (cohort naming, 24-module scope, repo layout)

**Status:** **Done 2026-06-23** — auto-memory items 1/2/3 (ADR-0006/0007/0008) and item 5 (ADR-0009, cross-tenant 404-vs-403, auto-memory §5) all surfaced as ADRs. F-005 fully closed.
**Type:** Planning candidate
**Business objective:** Surface decisions currently only in auto-memory as canonical ADRs reviewers can cite by path.
**Actor:** Architect (PM consult for cohort-naming impact).
**Business rules:** Each decision in auto-memory items §1, §2, §3 becomes its own ADR using the 5-section schema; references the canonical doc that holds the rule.
**Acceptance criteria:**
- `adr/0006-cohort-naming-and-program-cycles-rename.md` (cohort canonical; `program_cycles` → `cohorts` rename already implemented; ADR documents the decision)
- `adr/0007-24-module-canonical-scope.md` (24 modules as scope source of truth; references `docs/product/scope-register.md`)
- `adr/0008-repo-layout-backend-frontend-services-siblings.md` (sibling layout; references `docs/project-context.md` § Repo layout)
- Each ADR uses the 5-section schema (Status / Context / Decision / Alternatives / Consequences / References)
**Authorization:** N/A
**Tenant isolation:** N/A
**Data + migration impact:** N/A — cohort rename already implemented
**API impact:** N/A — existing API uses `cohorts` already
**UI states:** N/A
**Failure behaviour:** Reviewers may push back on framing; iterate
**Audit requirements:** N/A
**Test expectations:** Reviewer confirms each ADR uses the schema
**Dependencies:** None
**Rollback:** Delete the ADR files; references in other docs become orphan

### Story 0.6: Add "Doc authority map" to `MANIFEST.md`

**Status:** **Done 2026-06-23** — MANIFEST.md gains a Doc authority map (artifact type → canonical home) + `_bmad-output`/`docs`/`_bmad` split + conflict-resolution rule, and `.claude/rules/16-documentation.md` now references it. The rule-16 half landed once **F-013** was resolved (option (a): `.claude/rules/` un-ignored and version-controlled; local settings/skills stay ignored).
**Type:** Planning candidate
**Business objective:** Resolve the ambiguity between `_bmad-output/`, `docs/`, and `_bmad/` as competing canonical homes (audit F-006).
**Actor:** Tech writer / architect.
**Business rules:** A single section in MANIFEST.md declares which tree is authoritative for each artifact type; `.claude/rules/16-documentation.md` references it.
**Acceptance criteria:**
- MANIFEST.md gains a "Doc authority map" section: PRD, UX spec, architecture, ADRs, status, BMAD planning artifacts each map to a canonical home
- `.claude/rules/16-documentation.md` updated to reference the map
- Conflict-resolution rule stated ("when two homes disagree, the home named in the map wins; the loser must redirect")
**Authorization:** N/A
**Tenant isolation:** N/A
**Data + migration impact:** N/A
**API impact:** N/A
**UI states:** N/A
**Failure behaviour:** Reviewer may push back on canonical assignment; iterate
**Audit requirements:** N/A
**Test expectations:** Reviewer confirms MANIFEST.md section + rule 16 reference
**Dependencies:** None
**Rollback:** `git revert`

### Story 0.7: Roadmap pass for 4 absent modules

**Type:** Planning candidate
**Business objective:** Assign each currently-absent module (FinalEvaluation / Notifications / Search / Administration) a target phase so phase-affected stories have a place to land.
**Actor:** PM with architect consult.
**Business rules:** Per audit F-007, the 4 absent modules need explicit phase placement; Notifications likely earlier than the others (audit + observability + integrations depend on it).
**Acceptance criteria:**
- `docs/plan/roadmap.md` updated with explicit phase placement for each of FinalEvaluation / Notifications / Search / Administration
- Notifications placed at Phase 2 (or in Epic R/A if signed-webhook substrate needs it earlier)
- FinalEvaluation, Search, Administration each placed at P2 or P3 per scope-register intent
- Each placement cross-referenced to the FRs it carries
**Authorization:** N/A
**Tenant isolation:** N/A
**Data + migration impact:** N/A — doc; module scaffold lives in later epics
**API impact:** N/A
**UI states:** N/A
**Failure behaviour:** PM may revisit phase placement after Phase 1a learning data
**Audit requirements:** N/A
**Test expectations:** Reviewer confirms each absent module has a placement
**Dependencies:** Story 0.4 (workflow-tables sub-task may inform Notifications timing)
**Rollback:** `git revert` roadmap edit

### Story 0.8: Reliability/Audit epic carve-out (architectural slot)

**Status:** **Done 2026-06-23** (commits `8834bb0` + `1c41a9a` on branch `docs/prd-update-2026-06-23`).
**Carries:** audit F-009 + F-010 (architectural carve-out).
**Outcome:** PRD §7 gains "R/A" phase-table row; architecture.md Step 6 names `app/Reliability/` as the cross-cutting home; FR-126 reclassified P3 → R/A. Epic R/A scoping (below) defines the constituent stories.

### Story 0.9: Expand ADR-0001 + ADR-0003 to the standard 5-section schema

**Status:** **Done 2026-06-23** — both ADRs expanded to the Status / Context / Decision / Alternatives / Consequences / References schema; Accepted status retained.
**Type:** Planning candidate
**Business objective:** Make existing 1-line ADRs reviewable per `.claude/rules/16-documentation.md` schema requirement (audit F-011).
**Actor:** Architect.
**Business rules:** Every ADR must have Context, Decision, Alternatives, Consequences, References (in addition to Status).
**Acceptance criteria:**
- `adr/0001-modular-monolith.md` expanded with the 5 sections
- `adr/0003-mocked-oidc-first.md` expanded with the 5 sections
- Both ADRs retain their Accepted status
**Authorization:** N/A
**Tenant isolation:** N/A
**Data + migration impact:** N/A
**API impact:** N/A
**UI states:** N/A
**Failure behaviour:** Reviewer may push back on rationale framing; iterate
**Audit requirements:** N/A
**Test expectations:** Reviewer confirms each ADR carries the 5 sections
**Dependencies:** None
**Rollback:** `git revert`

### Story 0.10: Regenerate graphify on next SP-* merge

**Status:** **Satisfied by verification 2026-06-23** — `graphify-out/GRAPH_REPORT.md` is already dated 2026-06-22 (post-SP-1) and reflects the inverted-identity shape (`accounts` + `linked_identities` present, `external_users` absent). No regeneration needed; a fresh ~641k-token pass (per `cost.json`) would not change the conclusion. Minor deviation: no dated snapshot dir for 2026-06-22 (only `2026-06-19/`); the report file itself carries the current date and content. Next structural change still triggers a regen per rule 14.
**Type:** Planning candidate
**Business objective:** Keep the knowledge-graph snapshot current with the inverted-identity codebase so AI agents using graphify orient correctly.
**Actor:** Engineering (whoever lands the next SP-* merge).
**Business rules:** Per `.claude/rules/14-graphify-impact-analysis.md`, regenerate after substantial structural changes; SP-1's `external_users → accounts + linked_identities` migration qualifies.
**Acceptance criteria:**
- `graphify-out/GRAPH_REPORT.md` snapshot timestamp moves forward to the SP-1 merge commit
- New snapshot directory reflects the Identity module's post-SP-1 shape
- Regeneration runs as part of the merge commit, not a follow-up
**Authorization:** N/A
**Tenant isolation:** N/A
**Data + migration impact:** N/A — knowledge graph only
**API impact:** N/A
**UI states:** N/A
**Failure behaviour:** Regeneration failure → capture error in the PR, file as follow-up; do not block the merge
**Audit requirements:** N/A
**Test expectations:** Reviewer confirms snapshot timestamp + directory match the SP-1 merge commit
**Dependencies:** SP-1 merge
**Rollback:** Restore prior snapshot from `graphify-out/2026-06-19/`; rerun on a later commit

---

## Epic R/A: Reliability and Audit Substrate (added 2026-06-23 — planning-candidate scope)

Originated from PRD §7 R/A row (added this session) + architecture.md Step 6 Reliability boundary (Opt 2: cross-cutting `backend/app/Reliability/`). Owns FR-126 (platform-wide audit, reclassified P3 → R/A), signed-webhook substrate, substrate generalization (outbox + idempotency + audit-set extension from P1a's enumerated set), NFR-015 architecture-test acceptance (single-DB topology from ADR-0005), Step 5 P7/P8 enforcement (event naming + versioning), Step 5 P3 enforcement (API versioning), and OpenAPI → zod codegen pipeline.

**Epic dependency:** enters Ready after (a) Epic 2 substrate is stable (FR-050/051/052 production), (b) Epic 0.8 done (this session), (c) SP-1 inversion is merged (so `Account ULID` is the audit actor key). **Epic blocks:** no P2 capability epic (FR-100..108) ships before Epic R/A lands its generalization stories — current P1a substrate works for P1a's enumerated set but becomes a liability when P2's multi-consumer outbox + cross-module idempotency need it.

**FRs covered:** FR-126 (platform-wide audit); generalization of FR-050 / FR-051 / FR-052. **NFRs anchored:** NFR-012 (observability — audit enforced), NFR-015 (DB topology arch test).

### Story RA.1: `app/Reliability/` skeleton (Audit / Outbox / Idempotency / Webhooks)

**Type:** Planning candidate (not Approved for Implementation)
**Business objective:** Establish the cross-cutting home for the Reliability/Audit substrate so subsequent stories have a place to land.
**Actor:** Backend engineer.
**Business rules:** Skeleton matches architecture.md Step 6 § Reliability home; four sub-areas (Audit / Outbox / Idempotency / Webhooks); each carries `Contracts/` + `Services/` + `Middleware/` per canonical skeleton.
**Acceptance criteria:**
- `backend/app/Reliability/` created at sibling level to `app/Tenancy/` and `app/Storage/` (NOT under `app/Modules/`)
- Four sub-directories created: `Audit/`, `Outbox/`, `Idempotency/`, `Webhooks/`
- Each sub-directory has empty `Contracts/`, `Services/`, optionally `Middleware/`, optionally `Workers/` (Outbox)
- `ReliabilityServiceProvider` registered
- deptrac (Epic 0 hygiene) treats `App\Reliability\*` as cross-cutting (no inbound dependency from `App\Modules\*` except via Contracts)
**Authorization:** N/A — substrate; controllers continue to use Policy classes
**Tenant isolation:** Substrate emits tenant-bound side effects but is not tenant-scoped itself
**Data + migration impact:** None for the skeleton; subsequent RA stories add tables
**API impact:** N/A — no controllers
**UI states:** N/A — no UI
**Failure behaviour:** ServiceProvider binding failure surfaces at boot; CI catches via Laravel `php artisan optimize`
**Audit requirements:** N/A — skeleton emits no events
**Test expectations:** Architecture test asserts `App\Reliability\*` cross-cutting placement; `ReliabilityServiceProvider` registered
**Dependencies:** None (deptrac config from Epic 0 becomes blocking once it lands)
**Rollback:** Delete `backend/app/Reliability/`; remove ServiceProvider binding

### Story RA.2: Audit-enforced middleware (FR-126; closes F-010 + CLAUDE.md baseline)

**Type:** Planning candidate
**Business objective:** Move audit from opt-in (current `app/Modules/Audit/` scaffold) to enforced for every audit-bearing event named in CLAUDE.md.
**Actor:** Backend engineer + security reviewer.
**Business rules:** Per CLAUDE.md "Authorization and Privacy" + "Versioning and Historical Integrity," audit is a baseline invariant. PRD FR-052 enumerated set covers Epics 1+2; this story extends to platform-wide.
**Acceptance criteria:**
- Middleware `App\Reliability\Audit\Middleware\RecordAuthDecision` records audit row for every `Gate::authorize` / `$this->authorize` call (decision + actor + resource + outcome)
- Identity link, unlink, consent grant / revoke emit canonical events (`auth.linked_identity.{linked,unlinked}`, `auth.consent.{granted,revoked}`) by default
- Profile import emits `profile.field.imported` per imported field with source attribution
- Stage outcome (publish / open / close / accept / reject) emits canonical event names per Step 5 P7
- Reads of audit rows continue through `app/Modules/Audit/` (read API stays in the domain module)
- Architecture test: every controller using `$this->authorize` triggers the middleware
**Authorization:** This story changes the substrate; call-site authorization unchanged
**Tenant isolation:** Audit rows carry `organization_id` (server-set, never client-supplied); `BelongsToTenant` on `audit_events`
**Data + migration impact:** May add `auth_decision_id` foreign key on `audit_events`; migration must be additive (expand pattern)
**API impact:** Audit-read endpoints unchanged; new event types appear in audit feed
**UI states:** N/A — substrate; operator-facing audit views are P3 in `Modules/Audit`
**Failure behaviour:** Audit-write failure must NOT block user action (queued via outbox); failures emit high-severity log + circuit-break after N consecutive failures
**Audit requirements:** This story IS the audit substrate; itself audited via build-time test that the middleware runs
**Test expectations:** Feature tests for: authz decision recorded; identity link/unlink recorded; consent grant/revoke recorded; profile import field-level recorded; architecture test asserts middleware is wired on every controller using `$this->authorize`
**Dependencies:** RA.1 (skeleton); E2 / Story 2.5 (audit substrate data model)
**Rollback:** Remove middleware from kernel registration; revert migration; existing FR-052 enumerated audit continues via opt-in path

### Story RA.3: Signed-webhook substrate (inbound verifier + outbound signer)

**Type:** Planning candidate
**Business objective:** Provide an HMAC-signed-webhook substrate for all current and future external integrations (SG trusted-publication outbound, Geidea callbacks inbound at P1b, future webhook subscribers).
**Actor:** Backend engineer + security reviewer.
**Business rules:** Per project-context § Identity & email + § Timing-safe equality, all comparisons use `hash_equals`; key rotation ≤ 90 days (NFR-009); replay protection via timestamp + nonce.
**Acceptance criteria:**
- Inbound verifier: `App\Reliability\Webhooks\Contracts\WebhookVerifier` + middleware `VerifySignature` (HMAC-SHA256; clock-skew tolerance per config; replay rejection via nonce store with TTL)
- Outbound signer: `App\Reliability\Webhooks\Contracts\WebhookSigner` (HMAC-SHA256; current-key + previous-key dual-sign during rotation window)
- HMAC key store + rotation command (artisan; previous key honored 24h overlap)
- Architecture test: any controller declared as "webhook ingress" carries `VerifySignature` middleware
- All comparisons use `hash_equals`
**Authorization:** Webhook ingress runs before standard Sanctum auth; callers authenticated by signature
**Tenant isolation:** Webhook payload carries `organization_id` candidate; substrate validates against the signing key's tenant binding before tenant context is set
**Data + migration impact:** New table `webhook_signing_keys (id, organization_id, key_id, secret_hash, status, rotated_at)`; new table `webhook_nonces (nonce, expires_at)` + sweeper job
**API impact:** Standard route group (`Route::middleware('webhook')->group(...)`); existing endpoints unaffected
**UI states:** Admin UI for key rotation out of scope (lives in `Modules/Administration` per Story 0.7)
**Failure behaviour:** Signature failure → 401 with no payload echo; replay collision → 409; clock-skew over tolerance → 401 (`clock_skew` reason); all failures audited per RA.2
**Audit requirements:** Each webhook receipt audited (verified signature, sender key id, replay status); rotation events audited
**Test expectations:** Feature tests for: valid sig + valid nonce → accepted; valid sig + reused nonce → 409; expired timestamp → 401; rotated-out key → 401; dual-sign rotation window works
**Dependencies:** RA.1; RA.2 (audit middleware for receipt audit)
**Rollback:** Remove route group middleware; webhook endpoints fall back to per-integration handling; revert migrations after data export

### Story RA.4: Generalized outbox (multi-consumer + replay)

**Type:** Planning candidate
**Business objective:** Extend the P1a outbox (FR-050; single consumer per Epic 2 / Story 2.4) to multi-consumer + replay so Reporting, Notifications, and future P2/P3 features can subscribe without rebuilding substrate.
**Actor:** Backend engineer.
**Business rules:** Per project-context § Background jobs + Step 5 P7/P8 — every event carries `event_name`, `event_version`, `event_id`, `organization_id`, `created_at`. Per-aggregate ordering preserved; cross-aggregate ordering NOT guaranteed.
**Acceptance criteria:**
- `outbox_consumers` table tracks each consumer's last-seen `event_id` per aggregate
- New consumer can replay from a stated `event_id` (or beginning) with no impact on others
- Consumer-side idempotency: duplicate `event_id` rejected
- Dead-letter queue per consumer (not shared)
- Architecture test: events emitted from any module carry the 5 required fields
- Existing Epic 2 single-consumer continues to work without code change
**Authorization:** N/A — substrate
**Tenant isolation:** All consumer queries filter `organization_id` explicitly (defense in depth per project-context § Tenant isolation)
**Data + migration impact:** Add `outbox_consumers (id, name, last_seen_event_id, last_seen_at, status)`; add `dead_letter_events (id, consumer_id, event_id, payload, failed_at, error)`; existing `outbox_events` unchanged
**API impact:** Internal — consumers register via DI not HTTP
**UI states:** N/A
**Failure behaviour:** Consumer crash → relay continues other consumers; circuit breaker per consumer after N consecutive failures; dead-letter does not delete; manual replay command in artisan
**Audit requirements:** Consumer add / remove + dead-letter ops audited via RA.2
**Test expectations:** Feature tests: two consumers receive same event independently; replay from event_id works without affecting others; dead-letter receives failed events; consumer A failure doesn't block consumer B
**Dependencies:** RA.1; E2 / Stories 2.3, 2.4 (existing outbox); RA.2 (audit middleware)
**Rollback:** Drop `outbox_consumers` + `dead_letter_events` tables; revert to single-consumer code path; existing Epic 2 consumer keeps working

### Story RA.5: Generalized idempotency (platform-wide middleware)

**Type:** Planning candidate
**Business objective:** Extend the P1a idempotency primitive (FR-051; two endpoints per Epic 2 / Story 2.2) to a platform-wide middleware any controller can opt into (or any POST that wants default protection).
**Actor:** Backend engineer.
**Business rules:** Per Step 5 P5 envelope contract + project-context § Mass-assignment.
**Acceptance criteria:**
- `App\Reliability\Idempotency\Middleware\IdempotencyMiddleware` reads `Idempotency-Key` header; required on opt-in routes, optional on default-protected POSTs
- Cache TTL configurable per route (default 24h)
- Replay returns cached HTTP status + body + headers; original timestamp preserved in `X-Idempotent-Replay` header
- Request-body hash + idempotency-key collision → 409
- Architecture test: opt-in routes carry the middleware; configured default-protected POSTs carry it
- Existing FR-032 + FR-051 endpoints continue without code change
**Authorization:** N/A — substrate; runs after auth, before controller
**Tenant isolation:** Cache key includes `organization_id`; cross-tenant replay impossible
**Data + migration impact:** Existing `idempotency_keys` table unchanged; substrate may add `idempotency_route_config` table or file config
**API impact:** New optional header standardized; existing endpoints unaffected
**UI states:** N/A
**Failure behaviour:** Missing key on required route → 400 with structured error per Step 5 P6; cache write failure → request still processed (best-effort + alerting)
**Audit requirements:** Replay served events audited via RA.2 with original + replay timestamps
**Test expectations:** Feature tests: missing-key 400; valid-key first-call processed + cached; valid-key replay returns cached response; body-hash collision 409; cross-tenant replay rejected
**Dependencies:** RA.1; E2 / Story 2.2 (existing primitive); RA.2 (audit middleware)
**Rollback:** Remove middleware from route groups; opt-in routes fall back to per-controller idempotency or none

### Story RA.6: NFR-015 architecture test (single-DB topology + analytics-read prohibition)

**Type:** Planning candidate
**Business objective:** Make ADR-0005's single-DB topology automatically enforceable so future contributors can't accidentally introduce a per-tenant DB or product-code reads from an analytics warehouse.
**Actor:** Backend engineer.
**Business rules:** Per ADR-0005 + PRD NFR-015.
**Acceptance criteria:**
- `backend/tests/Architecture/DatabaseTopologyTest.php` asserts every Eloquent model's `$connection` resolves to the configured product DB connection (no `connection` override outside whitelisted reporting/admin scopes)
- Same test asserts no controller, service, job, or Policy imports any class from a configured analytics-store namespace
- Test fails on new violations; baseline file allowed for one-time pre-existing exceptions (each requires a `// SECURITY: <reason>` comment)
- CI runs on every PR
**Authorization:** N/A
**Tenant isolation:** N/A — test itself
**Data + migration impact:** N/A
**API impact:** N/A
**UI states:** N/A
**Failure behaviour:** CI fails on new violation; PR blocked until removed or whitelisted (whitelist additions need ADR sign-off)
**Audit requirements:** N/A
**Test expectations:** Self-test against deliberately-violating fixture
**Dependencies:** RA.1 (skeleton — for the architecture-test namespace); ADR-0005 (landed)
**Rollback:** Delete the test file

### Story RA.7: Architecture tests for Step 5 P7 (event naming) + P8 (event versioning)

**Type:** Planning candidate
**Business objective:** Automate enforcement of Step 5 P7 + P8 (event names match `^[a-z_]+\.[a-z_]+\.[a-z_]+$`; every event payload has `event_version: int`).
**Actor:** Backend engineer.
**Business rules:** Per architecture.md Step 5 P7 + P8.
**Acceptance criteria:**
- `backend/tests/Architecture/EventNamingTest.php` asserts every dispatched event name matches the regex; inspects `Event::dispatch(...)` and outbox-emitter call sites
- `backend/tests/Architecture/EventVersioningTest.php` asserts every event payload schema includes `event_version: int`
- Both tests fail on new violations; baseline allowed for pre-existing
- CI runs on every PR
**Authorization:** N/A
**Tenant isolation:** N/A
**Data + migration impact:** N/A
**API impact:** N/A
**UI states:** N/A
**Failure behaviour:** CI fails on new violation
**Audit requirements:** N/A
**Test expectations:** Self-tests against deliberately-violating fixtures
**Dependencies:** RA.1; RA.4 (outbox emitter the test will inspect)
**Rollback:** Delete test files

### Story RA.8: Architecture test for Step 5 P3 (API versioning prefix)

**Type:** Planning candidate
**Business objective:** Automate enforcement of Step 5 P3 (every `api.php` route prefix matches `/api/v{int}/`).
**Actor:** Backend engineer.
**Business rules:** Per architecture.md Step 5 P3.
**Acceptance criteria:**
- `backend/tests/Architecture/ApiVersioningTest.php` asserts every `api.php` route matches `^/api/v\d+/`
- Test inspects Laravel's route collection at boot
- Whitelist for utility endpoints (e.g. `/api/health`) declared in config
- CI runs on every PR
**Authorization:** N/A
**Tenant isolation:** N/A
**Data + migration impact:** N/A
**API impact:** Enforces rule going forward; existing routes audited at first run (likely already compliant per current `api.php`)
**UI states:** N/A
**Failure behaviour:** CI fails on new unprefixed route
**Audit requirements:** N/A
**Test expectations:** Self-test against deliberately-violating fixture
**Dependencies:** RA.1
**Rollback:** Delete test file

### Story RA.9: OpenAPI → zod codegen pipeline (frontend toolchain)

**Type:** Planning candidate
**Business objective:** Close the deferred Step 5 decision (codegen, not hand-author) by wiring a build-time pipeline that generates frontend zod schemas from the Scramble-emitted OpenAPI spec, preventing client / server contract drift.
**Actor:** Frontend engineer + backend engineer.
**Business rules:** Per architecture.md Step 5 deferred patterns + Step 6 § Frontend ↔ backend boundary. Generated client is the single source of truth; hand-authored zod schemas at request/response boundaries forbidden.
**Acceptance criteria:**
- `frontend/package.json` gains `codegen:zod` script invoking `@hey-api/openapi-ts` or `openapi-zod-client` against `backend/storage/api-docs/openapi.yaml`
- Generated output lands under `frontend/src/api/__generated__/` (gitignored vs committed: decide and document)
- Frontend build fails if `__generated__/` stale relative to the spec
- ESLint rule (or directory restriction) forbids hand-authored zod imports outside `__generated__/`
- One existing frontend feature (e.g. operator submission list per Epic 2 / Story 2.8) migrated to consume generated client as proof
**Authorization:** N/A — frontend infra
**Tenant isolation:** N/A — frontend always queries authenticated endpoints; tenant context server-side
**Data + migration impact:** None server-side
**API impact:** Enforces server-side OpenAPI emission discipline (existing Spectral CI gate continues to apply)
**UI states:** No new states; existing migrated screens unchanged in behaviour
**Failure behaviour:** Codegen failure → frontend build fails with clear "regenerate via npm run codegen:zod" message
**Audit requirements:** N/A — frontend
**Test expectations:** Vitest test imports a generated schema and validates a known payload; Playwright e2e for migrated screen passes
**Dependencies:** RA.1 (skeleton — for backend OpenAPI emission discipline); Step 5 architecture decision (landed)
**Rollback:** Remove `codegen:zod` script; revert migrated frontend feature to hand-authored zod; delete `__generated__/`

---

## Field Augmentations — 2026-06-23 (Phase 1)

> Templated 14-field augmentation of the 23 existing stories. Per the field-completeness audit (subagent 2026-06-23): most stories carry 9–11 of 14; consistent gaps are **Rollback (~21 absent)**, **Audit (10 absent)**, **UI states (10 absent)**. Substrate stories and identity stories have *defensibly* absent fields. This appendix carries augmentation entries rather than editing each story body — preserves audit trail and avoids in-place edits to canonical-state stories.

**Template logic:**
- **Rollback** added per story (universal gap)
- **Audit** / **Tenant isolation** / **UI states** → `N/A — <one-line justification>` where defensibly absent; real one-line content where the gap is genuine
- 3 thin stories (**S1.0, S1.5, S4.5**) flagged for follow-up expansion in a separate epics-and-stories pass

### Per-story augmentation entries

**S1.0 Frontend foundation** — **Authorization** N/A (foundation; no protected route); **Tenant isolation** N/A (foundation; components tenant-agnostic by design); **Data + migration** N/A (no data layer); **API impact** N/A; **Audit** N/A (foundation; downstream feature stories carry audit); **Rollback** revert foundation commits; subsequent feature stories must inline their own primitives. **⚠ Flagged thin** — body needs expanded ACs around component boundaries + a11y CI gate's specific assertion set; recommend follow-up story `S1.0a Foundation expansion`.

**S1.1 Sign up + create org** — **Dependencies** S1.0 (frontend foundation); E4 / SP-1 supersedes the SG-OIDC-mock framing in the AC (covered by impact ledger). **Rollback** revert sign-up form + org-creation migration; test-only users continue via direct DB seeding.

**S1.2 Publish program** — **UI states** loading-while-publishing, draft → published transition feedback, error on entitlement-block (P1b reality); **Failure behaviour** publish under entitlement block → 422 with reason; **Test expectations** feature test + tenant-isolation regression; **Dependencies** S1.0, S1.1, FR-060 EntitlementService stub; **Rollback** revert publish endpoint + UI; drafts unaffected.

**S1.3 Publish form** — **UI states** form-builder draft, declarative-validator error inline, published-version-id surfacing; **Rollback** revert publish endpoint; previously-published forms remain immutable.

**S1.4 Open / close cohort** — **Test expectations** feature test for window edges (open / mid / close / closed); **Rollback** revert cohort endpoints; existing cohorts continue per their stored windows.

**S1.5 Operator Home** — **Tenant isolation** all operator views scoped via `BelongsToTenant`; **Data + migration** N/A (read-only); **API impact** GET endpoints for home dashboard tiles; **Audit** N/A — read-only; **Test expectations** feature test for tenant scoping of dashboard tiles; **Dependencies** S1.0, S1.1, S1.2; **Rollback** revert dashboard route. **⚠ Flagged thin** — ACs around dashboard composition need expansion; recommend `S1.5a Home expansion`.

**S2.1 Content-addressed blobs** — **Authorization** N/A — substrate; controllers gate uploads; **Tenant isolation** blob refcount carries `organization_id`; **API impact** N/A — internal; **UI states** N/A; **Audit** N/A — blob ops emit audit via parent submission; **Rollback** revert blob table + content-addressing service; existing uploads remain via direct URL until traffic-cutover.

**S2.2 Idempotency primitive** — **API impact** new `Idempotency-Key` header standardized; **UI states** N/A; **Audit** replay events audited per Step 5; **Dependencies** none; **Rollback** revert middleware; per-controller idempotency or none.

**S2.3 Outbox table + producer** — **Authorization** N/A — substrate; **Tenant isolation** events tagged with `organization_id`; **API impact** N/A; **UI states** N/A; **Audit** N/A — events ARE the audit substrate; **Rollback** revert table + producer; uncommitted state changes only.

**S2.4 Outbox relay** — **Authorization** N/A; **Tenant isolation** relay preserves `organization_id` on dispatch; **API impact** N/A; **UI states** N/A; **Audit** relay failures + DLQ inserts audited; **Rollback** stop relay worker; events accumulate; restart on safe deploy.

**S2.5 Audit trail** — **Authorization** N/A — substrate; **API impact** read endpoint TBD in P3; **UI states** N/A — P3 reads; **Dependencies** S2.3, S2.4; **Rollback** revert `audit_events` table; CLAUDE.md "audit-bearing events" baseline reverts to opt-in.

**S2.6 Submission snapshot** — **Authorization** server-side authz on submission; **API impact** new `POST /api/v1/cohorts/{cohort}/submissions`; **UI states** N/A — public-flow stories handle UI; **Audit** `application.submitted` enumerated; **Rollback** revert snapshot model + endpoint; existing draft submissions discarded.

**S2.7 Public idempotent submit + receipt** — **Authorization** thin pre-augment refined: applicant must be authenticated; rate-limited per session + IP; **Tenant isolation** thin pre-augment refined: cohort URL carries tenant context server-side; client-supplied `organization_id` rejected; **Rollback** revert public submit endpoint + receipt UI; applicants see "submissions closed" until rollforward.

**S2.8 Operator list + funnel** — **Audit** `decisions.exported` not in this story; funnel reads emit no audit (read-only); **Rollback** revert operator list + funnel endpoint; operators fall back to direct DB query (not user-facing).

**S3.1 Score submission** — **Rollback** revert scoring endpoint + rubric snapshot extension; previously-scored submissions retain their immutable snapshots.

**S3.2 Record / reopen decision** — **Authorization** thin pre-augment refined: only `decisions:write` ability holders; reopen requires `decisions:reopen` ability; **Tenant isolation** thin pre-augment refined: decisions scoped via cohort → organization chain; **Rollback** revert decision endpoint + reopen flow; previously-recorded decisions retain their audit rows.

**S3.3 Export CSV** — **Rollback** revert export endpoint; operators fall back to in-product list view (no CSV).

**S4.1 Native accounts model** — **Tenant isolation** N/A — Account is org-agnostic; `organization_memberships` join carries tenant binding; **API impact** new account creation + email-verification endpoints; **UI states** registration form, email-verification pending, verified; **Audit** account creation + email verification audited per RA.2 (pre-RA, audit emit directly); **Rollback** revert account model + endpoints; existing `ExternalUser` rows remain canonical pre-migration.

**S4.2 Native auth backend** — **Tenant isolation** N/A — auth is org-agnostic; **UI states** N/A — backend story; **Rollback** revert auth endpoints; existing SG-OIDC-mock auth path continues.

**S4.3 Native auth frontend** — **Tenant isolation** N/A; **Audit** login + logout audited per RA.2; **Rollback** revert login UI; users redirected to SG-OIDC-mock until rollforward.

**S4.4 SG linked provider** — **Tenant isolation** N/A — link is per-Account; **Test expectations** feature test for link / unlink / sign-in via linked SG; **Rollback** revert link/unlink endpoints + UI; `linked_identities` rows preserved; existing linked-account users continue to sign in.

**S4.5 Multi-role profiles** — **Tenant isolation** role profiles are per-Account; org-level role assignments live in `Modules/RoleAssignments`; **API impact** profile read/write endpoints per role type; **UI states** profile editor per role; **Audit** profile updates audited per RA.2; **Test expectations** feature test for each of the 7 role-profile types; **Rollback** revert profile tables + UI; existing user profile data preserved via migration. **⚠ Flagged thin** — story body lacks explicit 7-role-type breakdown; recommend follow-up `S4.5a` through `S4.5g` (one sub-story per role type).

**S4.6 SG import pipeline** — **Tenant isolation** import is per-Account; targets local profile (org-agnostic); **Rollback** revert import endpoint + UI; consented imports persist as local copies (never auto-overwrite local edits per NFR-006).

### Stories flagged for real expansion (templated patching insufficient)

- **S1.0** — Foundation expansion (component boundaries + a11y CI gate's specific assertion set). Recommend `S1.0a`.
- **S1.5** — Operator Home expansion (ACs around dashboard composition). Recommend `S1.5a`.
- **S4.5** — Multi-role profiles expansion (one sub-story per role type). Recommend `S4.5a` through `S4.5g`.

These remain in-flight gaps to be addressed in a subsequent epics-and-stories pass; not blocked.

---

## Dev-Story Handoff Contract

A dev agent implements **one story at a time with only that story's text as context** (`bmad-dev-story`). These rules make each story self-sufficient. *(Folded from the Paige + Sally review.)*

### Per-story dev-context assembly rule (normative)
The context handed to the dev for Story `N.M` is **the union of**: (a) the Story `N.M` body + ACs, (b) **every Edge-Case Hardening AC whose id begins `N.M`** — these are *normative parts of the story*, not optional, (c) this **Glossary**, (d) the **Per-story Definition of Done**, and (e) for E2 feature stories, **GATE-E2.0**. The "Edge-Case Hardening" section is an *index/review view*; the ★ ACs it lists belong to their home stories.

### Glossary (resolve definite-article nouns here)
- **submission_snapshot** — an immutable jsonb capture written at submit time: the answer values, the content-addressed blob refs, and the **resolved** form/program/rubric **version ids** in effect. Frozen on write; never altered by later source edits. (Stories 2.6, 2.7, 3.1.)
- **content-addressed version id** — `sha256` of the canonical (stable-key-ordered) serialization of a published artifact's definition; **org-scoped** (not globally enumerable). The id a snapshot pins. (Stories 1.3, 2.6.)
- **GATE-E2.0** — see checklist below. Dependent stories declare `blocked-by: GATE-E2.0`.
- **Learning Telemetry DoD** — an instrumented story is not done until its FR-080 events emit with required attributes AND are verified in a dashboard a human has looked at. (Stories 2.7, 2.8, 3.1, 3.2, 3.3.)
- **tenant isolation** — every tenant-owned record carries server-set `organization_id`; `BelongsToTenant` is opt-in per table; cross-tenant access returns 404; the **Account id (ULID)** is the user key (`sub` lives on the Startup Gate link, not the account; email is a local credential only). (All feature stories.)

### GATE-E2.0 — reliability gate checklist
Passes only when, with chaos/concurrency tests green: outbox insert is inside the domain txn (rollback leaves no orphan); the relay claims rows atomically and a crash mid-dispatch redelivers (not vanishes); idempotency replays on key+fingerprint match, 409 in-flight, 422 on fingerprint mismatch, recovers from crash-before-response; content-addressed blobs are finalized+verified and GC-protected while referenced. **Stories 2.6–2.8 are `blocked-by: GATE-E2.0` (= stories 2.1–2.5 done).**

### Per-story Definition of Done (applies to every story)
- Tests (CLAUDE.md mandate): unit + feature + **authorization** + **tenant-isolation** (cross-tenant 404), all green; lint + static analysis pass.
- The story's own ACs **and** its matching ★ Hardening ACs pass.
- Telemetry obligations met where applicable (Learning Telemetry DoD).
- `organization_id` enforced on any new tenant-owned table (+ an isolation test, AR-6).
- Docs updated where the change touches documented behavior.
- `depends-on` stories merged first.
