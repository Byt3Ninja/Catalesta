---
stepsCompleted: [1, 2, 3, 4]
status: complete
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
- FR-001: Authenticate via Startup Gate OIDC; immutable `sub` is the user key (mock in P1a; "done" = passes vs mock + provider-agnostic adapter).
- FR-002: Signing up creates an Organization; creator becomes admin.
- FR-003: Every tenant-owned record carries server-set `organization_id` (never mass-assignable).
- FR-004: Tenant queries fail-closed — unresolved tenant returns no rows; cross-tenant returns 404.
- FR-005: RBAC scopes permissions per organization.
- FR-006: Profile reads are consent-aware via the `ConsentProvider` seam (mock in P1a).

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
- NFR-002: Identity integrity — `sub` is the only cross-system key; email never identifies.
- NFR-003: Immutability & versioning — published artifacts immutable; submissions captured as immutable snapshots.
- NFR-004: Decimal arithmetic — `DECIMAL` math, no floats in score paths.
- NFR-005: No arbitrary code in rules — declarative; validator rejects non-allowed nodes (acceptance-tested).
- NFR-006: Consent-aware access via `ConsentProvider` seam (mock P1a).
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
- AR-8: External integrations behind interfaces only — Startup Gate OIDC (mock → real FR-157), Geidea (sandbox, no charge in P1a).

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

### Epic 3: Score & Decide *(gated on Epic 2 evidence)*
The operator scores submissions against the published rubric (decimal), records defensible accept/reject decisions (audited, reopenable), and exports the decision list. **Entry assumption (contract):** scoring reads the **immutable snapshot, never the live form**. **Gate:** do not start until Epic 2 shows applicants actually submit.
**FRs covered:** 040, 041, 042, 043, 081.

### Cross-cutting deliverable: Learning Telemetry *(named, acceptance-gated — not a separate build epic)*
The World-A/B learning data (FR-080) — `application.viewed/started/abandoned{step}` (Epic 2), `submission.scored{elapsed}` / `decision.recorded{time_to_decision}` / `decisions.exported` + export-then-leave (Epic 3). **DoD rule:** no Epic 2/3 story closes until its events emit **and are verified in a dashboard a human has looked at.** Surfaced to the operator (the funnel) as well as to the team.
**FRs covered:** 080 (+ 081 dispute/reopen event from Epic 3).

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

## Dev-Story Handoff Contract

A dev agent implements **one story at a time with only that story's text as context** (`bmad-dev-story`). These rules make each story self-sufficient. *(Folded from the Paige + Sally review.)*

### Per-story dev-context assembly rule (normative)
The context handed to the dev for Story `N.M` is **the union of**: (a) the Story `N.M` body + ACs, (b) **every Edge-Case Hardening AC whose id begins `N.M`** — these are *normative parts of the story*, not optional, (c) this **Glossary**, (d) the **Per-story Definition of Done**, and (e) for E2 feature stories, **GATE-E2.0**. The "Edge-Case Hardening" section is an *index/review view*; the ★ ACs it lists belong to their home stories.

### Glossary (resolve definite-article nouns here)
- **submission_snapshot** — an immutable jsonb capture written at submit time: the answer values, the content-addressed blob refs, and the **resolved** form/program/rubric **version ids** in effect. Frozen on write; never altered by later source edits. (Stories 2.6, 2.7, 3.1.)
- **content-addressed version id** — `sha256` of the canonical (stable-key-ordered) serialization of a published artifact's definition; **org-scoped** (not globally enumerable). The id a snapshot pins. (Stories 1.3, 2.6.)
- **GATE-E2.0** — see checklist below. Dependent stories declare `blocked-by: GATE-E2.0`.
- **Learning Telemetry DoD** — an instrumented story is not done until its FR-080 events emit with required attributes AND are verified in a dashboard a human has looked at. (Stories 2.7, 2.8, 3.1, 3.2, 3.3.)
- **tenant isolation** — every tenant-owned record carries server-set `organization_id`; `BelongsToTenant` is opt-in per table; cross-tenant access returns 404; `sub` (never email) is the user key. (All feature stories.)

### GATE-E2.0 — reliability gate checklist
Passes only when, with chaos/concurrency tests green: outbox insert is inside the domain txn (rollback leaves no orphan); the relay claims rows atomically and a crash mid-dispatch redelivers (not vanishes); idempotency replays on key+fingerprint match, 409 in-flight, 422 on fingerprint mismatch, recovers from crash-before-response; content-addressed blobs are finalized+verified and GC-protected while referenced. **Stories 2.6–2.8 are `blocked-by: GATE-E2.0` (= stories 2.1–2.5 done).**

### Per-story Definition of Done (applies to every story)
- Tests (CLAUDE.md mandate): unit + feature + **authorization** + **tenant-isolation** (cross-tenant 404), all green; lint + static analysis pass.
- The story's own ACs **and** its matching ★ Hardening ACs pass.
- Telemetry obligations met where applicable (Learning Telemetry DoD).
- `organization_id` enforced on any new tenant-owned table (+ an isolation test, AR-6).
- Docs updated where the change touches documented behavior.
- `depends-on` stories merged first.
