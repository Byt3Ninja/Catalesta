---
status: final
created: 2026-06-20
updated: 2026-06-20
scope: Phase 1a (Selection MVP)
sources:
  - _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
  - docs/ux/
references: DESIGN.md
---
# Catalesta — EXPERIENCE.md (Phase 1a)

Owns *how it works* — IA, behavior, states, interactions, accessibility, flows. Visual identity is `DESIGN.md`; tokens are referenced by `{name}`. On conflict with any mock/import, the spines win; between the spines, **DESIGN governs the visual layer and EXPERIENCE the behavioral layer** — neither overrides the other's domain. Scope is **Phase 1a (Selection MVP)** only; later-phase surfaces (dashboard/Action Center, billing workspace, mentorship/training, custom domains/branding) are explicitly out per PRD §11.

## Foundation

- **Form factor — two surfaces, one identity.**
  - **Operator console** — responsive **web**, desktop-first but usable to ~768px. Two-zone layout (context rail + work area).
  - **Public application page** — **mobile-web first** (PRD §1: applicant traffic is mobile-dominant). Single column, no app chrome, works unauthenticated up to the auth step.
- **UI system:** none inherited — custom components built from `DESIGN.md` tokens. (When an implementation framework is chosen, it inherits these tokens; this spine specifies behavior, not framework.)
- **Identity model:** all users authenticate via Startup Gate `sub` (mock in P1a). Roles in P1a: **Operator/Admin** and **Applicant**. (Evaluator is the operator in P1a; dedicated evaluator workspace is P2+.)
- **Directionality:** every screen renders in **LTR (English) and RTL (Arabic)** — see RTL & Bilingual Behavior. This is a P1a requirement (NFR-011 "renders RTL").

## Information Architecture

P1a surfaces and where they live (each maps to a PRD FR/UJ; curated down from the full-scope `docs/ux/*`):

**Entry (operator + applicant)**
- **Auth** — Startup Gate OIDC (mock in P1a). A redirect handoff: app → IdP → return. Not a designed form (the IdP owns the credential screen), but the app owns the *transitions and their states* (see State Patterns → Auth). The public application page is usable unauth up to the **Apply** tap, which triggers auth.
- **Signup → org creation** (operator, `FR-002`) — first-run: after first auth, an operator with no org is routed to a **create-organization** form (org name; the creator becomes admin). This is the product's first screen; states in State Patterns → Signup/Org-create.

**Operator console** (context rail: Org → Program → Cohort)
- **Home** — current cohorts + the one next action ("open a cohort", "N submissions to score"). Deliberately minimal — *not* the full Action Center (that needs P2/P3 data that doesn't exist yet).
- **Program** → create / publish (`FR-010/012`)
- **Cohort** → open, set enrollment window, **attach** a published form (`FR-011/020`). P1a is **attach-only**: the operator picks/configures the 8 enumerated field types on a simple form; the full visual form *builder* (conditional logic, calculated fields) is P3 (`FR-127`).
- **Submissions** (per cohort) → list (`FR-034`) → **Submission detail** → **Score** (`FR-040`) → **Decision** accept/reject/reopen (`FR-042/081`) → **Export CSV** (`FR-043`)
- **Account/Org** → org settings; **limit banner** surfaces here + on the blocked create action (`FR-062`)

**Public (applicant)**
- **Program/cohort landing** (public, unauth) → **Apply** (auth via `sub`) → **Application form** (`FR-021/020`) → **Submit** (`FR-032`) → **Status** (`FR-030`)

**IA closure:** every UJ-1/UJ-2 step lands on a surface above; every surface above is reached by a journey step. No orphan surfaces; no stated P1a need without a home.

## Voice and Tone

Microcopy voice (brand voice lives in DESIGN.md → Brand & Style). Confirmed 2026-06-20:
- **Plain, exact, reassuring.** This product makes consequential decisions; copy is never cute, never vague.
- **Operator side:** direct and task-first ("Publish program", "Score Solar Nile", "Export 24 decisions"). Numbers stated exactly.
- **Applicant side:** warm and de-risking. The applicant is nervous; reduce anxiety ("Your application was received. You can't edit it after this — review now." / "You've already applied to this cohort.").
- **Errors:** say what happened + what to do, never blame the user. ("This cohort closed on 18 Jun. Applications are no longer accepted.")
- **Bilingual:** Arabic copy is authored, not machine-translated; tone parity, not literal parity. Maintain a bilingual copy deck (P1a strings).

## Component Patterns (behavioral)

Visual specs in DESIGN.md → Components. Behavior:
- **Primary button** — one per screen; on submit shows a loading state and is disabled until re-enabled by the result (never double-fires; pairs with idempotency `FR-032`).
- **Disabled control** — never silent: always shows why on hover/focus and adjacent helper text ("Publish unavailable — add at least one form field").
- **Score input** — decimal only, validates against rubric max, shows `value / max`; inline error on out-of-range; autosaves on blur (draft) before an explicit "Submit score".
- **Table row (submissions)** — whole row is the target to open detail; status badge (icon+text); selected state for bulk export.
- **Banner (limit)** — appears on the affected create action *and* org page; states the limit, what's blocked, and that **existing data + reads/exports are unaffected** (`FR-062`); links to the org page. A `{warning}` *approaching* state precedes the *reached* block. **Phase note:** entitlement is allow-all in P1a (`FR-060`), so this banner has **no live trigger until P1b** (`FR-061` counter) — the surface is designed now but wired in P1b; don't build a 1a block that can never fire.
- **File upload (application)** — type/size constraints shown up front; progress; per-file error; removable; content-addressed so a re-upload of the same file is deduped invisibly.
- **Empty state** — every list/first-use screen explains the state + the single next action (see State Patterns).

## State Patterns

Every P1a surface specifies these states (the review found them missing across `docs/ux/*`):
- **Auth** (`FR-001`) — pending/redirecting ("Signing you in…"); **auth failure** ("Couldn't sign you in — try again"); **IdP/mock unavailable** (retry + plain explanation, no stack); return-from-OIDC lands on the intended deep link (tenant-scoped) or, if none, Home/org-create.
- **Signup / org-create** (`FR-002`) — empty first-run prompt ("Create your organization to get started"); validation (name required); **name accepted** → operator becomes admin, lands on Home; server error preserves the entered name. The form must not be skippable — an operator with no org cannot reach console surfaces.
- **Empty** — Submissions: "No applications yet. Share your cohort link." + copy-link action. Score queue: "Nothing to score yet." Decisions/export: "No decisions recorded."
- **Loading** — skeleton rows for lists; inline spinners for actions; never a blank flash.
- **Error** — form/API errors inline; a non-destructive retry; preserve entered data (no loss after error).
- **Permission / not-found** — cross-tenant or missing resource (`FR-004` → 404): a neutral "Not found or you don't have access" — never reveal another tenant's existence.
- **Closed cohort** (`FR-033` → 422, applicant): "This cohort closed on {date}. Applications are no longer accepted." with the program's other open cohorts if any.
- **Already submitted / idempotent** (`FR-032`, applicant): "You've already applied to this cohort." → show status, not a second form.
- **Immutable-after-submit** — before final submit, an explicit confirm ("You can't edit after submitting"); after submit, the snapshot is read-only (`FR-031`).
- **Limit reached** (`FR-062`) — create blocked with the banner; reads/exports stay live.

## Interaction Primitives

- **Navigation** — context rail persists Org→Program→Cohort; breadcrumbs in the work area; deep links are tenant-scoped.
- **Selection & bulk** — submissions multi-select → export selected/all.
- **Confirmation** — destructive/irreversible actions (final submit, reopen a decision) use a modal with the consequence stated; everything else optimistic with undo where safe.
- **Autosave vs commit** — score drafts autosave; decisions and applicant submit are explicit commits (audited, `FR-052`).
- **Feedback** — toasts for async success ("Exported 24 decisions"); inline for validation.
- **Keyboard** — full keyboard path for the applicant form (FR-020 fields) and the scoring screen; Enter submits where unambiguous; focus never trapped.

## Accessibility Floor (Phase 1a)

A P1a floor distinct from the P4 full WCAG 2.2 AA hardening (PRD NFR-011/FR-156). P1a must ship:
- **Contrast** — body/secondary text ≥ 4.5:1, large & UI ≥ 3:1, **both modes, verified against the DESIGN.md tokens** (see DESIGN.md → Colors contrast rules). The accent/info/status hues are not used as normal text; primary buttons use `{accentBtn}`; input boundaries use `{inputBorder}` (≥3:1, **WCAG 1.4.11**); disabled *reason* text uses `{inkMuted}`.
- **Automated gate (in P1a CI)** — a minimal check on every build: contrast (axe/Lighthouse), missing form-label, and `lang`/`dir` presence. (Full AA audit stays P4; the *gate* does not — it's what keeps the token claims true.)
- **Keyboard**: every P1a action reachable and operable by keyboard; visible `{focus}` ring (never removed). Each submission **row exposes a focusable "open detail" control** (the startup name as a link/button) distinct from the bulk-select checkbox — never a click-only `<tr>`.
- **Modals** (final submit, reopen decision): `role="dialog"`/`aria-modal`, focus moves into and is **contained** while open, Esc dismisses (where non-destructive), focus **restores to the trigger** on close. Enter never bypasses the confirm step.
- **Screen reader**: labelled inputs (the 8 FR-020 field types); field errors via **`aria-invalid` + `aria-describedby`** associating the helper text (not just visual adjacency); form/API/banner errors via an `aria-live` region; status-badge icon `aria-hidden` (the word is the equivalent); score field's accessible name carries the rubric **max**; loading skeletons `aria-busy`; success toasts to a polite live region; real `<table>` semantics with header associations; Arabic content marked `lang="ar"`.
- **Status not by color alone** (icon + text everywhere).
- **Touch targets** ≥ 44px on the mobile application page.
- **Motion**: respect `prefers-reduced-motion` (gate skeleton shimmer + toast slide-ins; loading has a non-animated text fallback).
- Out of P1a floor (→ P4): full WCAG 2.2 AA audit, complex-widget ARIA beyond the above, operator-console touch-target floor.

## RTL & Bilingual Behavior

The behavior the review found unspecified against NFR-011's "renders RTL" bar:
- **Direction switch** — UI language toggles `dir` on the document; layout mirrors via logical properties (no per-screen RTL CSS). Tested: every P1a screen in `dir="rtl"`.
- **Mixed-direction content** — an Arabic label with a Latin/numeric value keeps each run in its own `dir` and face; numbers/scores stay LTR/`{typography.mono}` tabular even within RTL rows. **Every interpolated value in bilingual copy** — not just table cells — is wrapped in `bdi`/`unicode-bidi: isolate` (e.g. "This cohort closed on `{date}`", "Score `{startup}`", "Exported `{n}` decisions"); un-isolated interpolation is the NFR-011 bidi-reorder bug.
- **Numerals** — Western Arabic numerals (0-9) for scores/IDs/data everywhere, including the Arabic UI (cross-locale consistency). *Confirmed 2026-06-20.*
- **Dates** — Gregorian, locale-formatted; no Hijri in P1a. **Pin the numbering system to Latin digits** (`ar-u-nu-latn` or equivalent) so Arabic-locale formatting still renders 0-9, consistent with the numerals rule. *Confirmed 2026-06-20.*
- **Form fields** — text inputs auto-detect direction per field (`dir="auto"`) so an Arabic answer renders RTL even in an English UI and vice-versa.
- **Icons/chevrons** — directional icons (back, next, breadcrumb) mirror under RTL; non-directional (status) do not.
- **Both modes × both directions** = 4 render targets the two PRD-critical screens are checked against.

## Instrumentation Surfaces (reconciled to PRD FR-080)

The UX must make the World-A/B events capturable (the review found the old taxonomy diverged). Required UX affordances:
- **Stepped application form** (pinned pattern) so `application.started` / `application.abandoned{step}` (→ C3 drop-off) are real: the public form is **multi-step with Next/Back, a progress indicator, and per-section autosave** (resume from last section, no data loss); each step boundary emits progress. (Not a free-scroll single page — the stepping is what makes drop-off measurable.)
- **Visible, loggable rubric edit** — editing rubric criteria mid-cohort is an explicit operator action that emits `rubric.edited{cohort,phase}` (a World-A signal).
- **Recorded decision time** — `decision.recorded{time_to_decision}` (→ M3) captured at the accept/reject commit.
- **Export-then-leave** — `decisions.exported` + a session signal if no further in-product action follows; the export action is instrumented, not just a file download.
- `application.viewed` on the public landing; `submission.scored{elapsed}` on score commit.
- These are behavioral requirements on S6/S9/S10/S11 — not visible UI, but they constrain the IA (the form must be stepped; the rubric edit must be a first-class action).

## Key Flows

**Flow 1 — Layla runs an intake (operator, UJ-1).** Layla (ops lead, Cairo accelerator) signs up → her org is created → she creates a program and **publishes** it → opens a **cohort** with an enrollment window and attaches a form (8 field types) → copies the public link and shares it. Applications arrive; she opens **Submissions**, sees the list fill, opens one, **scores** it against the published rubric (decimal, `value/max`), and — **climax** — marks **accept/reject**: the decision is recorded with her `sub`, time-to-decision captured, immutable. She repeats, then **exports** the decisions (CSV). Every step tenant-isolated and audited. *The peak moment is the decision commit — it must feel deliberate, defensible, and final (with a reopen escape hatch).*

**Flow 2 — Omar applies (applicant, UJ-2, mobile-web Arabic/RTL).** Omar opens the public cohort page on his phone, in Arabic (RTL). He taps **Apply**, authenticates, and completes the form section by section (autosave, no data loss). At the end — **climax** — a confirm step warns *"You can't edit after submitting"*; he submits once. A duplicate tap is idempotent (he sees "already applied", not a second submission). He lands on a **status** screen; his submitted data is frozen and cannot be silently altered. *The peak moment is the irreversible submit — copy and confirmation must make it feel safe, not scary.*

## Open items

- Voice/tone, numerals (Western), dates (Gregorian only) — **confirmed 2026-06-20**.
- Key-screen mocks **rendered** → `mockups/p1a-key-screens.html` (operator scoring/decision + mobile-web RTL application page).
- **Validation gate (2026-06-20)** run — rubric walker (strong) + accessibility/RTL lens. Critical + high findings **applied** (dark-button contrast → `{accentBtn}`; accent/status text rule; input boundary `{inputBorder}`; signup/org-create + auth surfaces & states; stepped-form pinned; FR-062 P1a/P1b phase note; bidi isolation generalized; date numbering pinned; modal/row/aria floor additions; minimal a11y CI gate pulled into P1a). Full reviews: `review-rubric.md`, `review-accessibility.md`.
- **Carried (medium, for build/Update):** raise exact `{inputBorder}` value with a tool to confirm ≥3:1; reconcile RTL test scope (the 4-target check currently names the 2 critical screens — extend to all operator screens or state "rendered RTL, spot-checked on 2"); operator-console touch-target floor (deferred to P4); table/grid header-association detail; export large/partial-failure state.
- Inherits the PRD's UX-relevant open questions: instrumentation must match FR-080 exactly; RTL floor ties to NFR-011.
