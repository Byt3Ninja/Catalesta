# FE UI Rebuild — Program Map & Full Screen Inventory

**Date:** 2026-06-26
**Type:** Program-level decomposition (parent of many specs)
**Status:** Draft — pending user review

## Goal

Rebuild **every** Catalesta interface on **shadcn/ui + Tailwind 3** (the approved
design system — see `2026-06-26-shadcn-tailwind-foundation-design.md`), **UI-first**:
build each screen against mock data first to validate the complete product cycle,
then wire screens to real APIs in a final pass.

## Strategy (locked decisions)

1. **Framework:** shadcn/ui + Tailwind 3, full replacement of the `ds-*` CSS layer.
   Zinc + indigo, Linear/Vercel aesthetic. RTL (Arabic/Tajawal), dark mode, and
   a11y/contrast suites preserved as first-class.
2. **Scope:** the full scope register — 24 modules + extended scope + SaaS
   commercial plane, all 10 role workspaces. ~170 inventory rows below, which
   collapse to ~120 distinct screens once template/variant rows (e.g. the nine
   print/PDF documents share one templated renderer) are merged during planning.
3. **UI-first:** screens render from **MSW** (Mock Service Worker) fixtures.
   Screens call the **real `api/` client functions**; MSW intercepts `fetch` and
   returns mock data. "Wiring APIs" (slice 9) = disable MSW and point at the live
   backend — no screen changes, because screens always used real client signatures.
4. **Sequencing:** Foundation (slice 0) is a hard prerequisite. Slices 1–8 are
   largely independent once Foundation lands and may be reordered/parallelized.
   API wiring (slice 9) runs per-screen and can begin per-slice as each completes.
5. **No backend/auth/tenancy changes.** Purely presentation + a dev-only MSW
   harness. The `X-Organization-Id` header and Sanctum session auth are untouched.

## Slice map

| # | Slice | Screens | Depends on |
|---|-------|--------:|-----------|
| 0 | Foundation (tooling, theme, primitives, AppShell, MSW harness, Login + Programs flagships) | ~6 | — |
| 1 | Identity & shell complete | ~16 | 0 |
| 2 | Operator: setup → intake | ~12 | 0 |
| 3 | Operator: selection & evaluation | ~12 | 0, 2 |
| 4 | Founder / applicant portal | ~12 | 0 |
| 5 | Delivery & role workspaces | ~19 | 0 |
| 6 | SaaS commercial plane | ~22 | 0 |
| 7 | Administration & compliance | ~11 | 0 |
| 8 | Extended features (6 sub-slices: 8a–8f) | ~69 | 0 |
| 9 | API-wiring pass (per screen) | — | the screen's slice |

Totals: ~170 inventory rows across slices 0–8 (≈120 distinct screens after
merging template/variant rows). Slice 9 adds no new screens.

Each slice gets its own spec → implementation plan → build. Slice 8 fans into
sub-slices (one per feature group). Slice 9 is a recurring activity, not a
big-bang: a screen may be wired as soon as its slice is built and its API exists.

## Mock-data architecture (MSW)

- Add `msw` as a dev dependency; `src/mocks/` holds `handlers.ts` (per-endpoint
  fixtures mirroring the real API response schemas in `src/schemas/`) and
  `browser.ts` (worker). Started only in dev (`import.meta.env.DEV`) from
  `main.tsx`, gated behind a flag (`VITE_USE_MOCKS`).
- Handlers are organized by module to mirror `src/api/`. Fixtures are typed
  against the same Zod schemas the real clients parse, so a drift between mock
  and contract fails typecheck/parse — keeping the prototype honest.
- Slice 9: set `VITE_USE_MOCKS=false` (or remove the dev start) per environment;
  screens hit the real backend unchanged. Verify each screen end-to-end with the
  Playwright-against-running-stack pattern already used for FE-2.5.

## Full screen inventory

Status legend: **EXISTS** = a page already exists (re-skin in place), **NEW** =
build fresh. Overlapping screens surfaced by multiple domains are assigned to a
single slice here (dedup notes inline).

### Slice 0 — Foundation
| Screen | Purpose | Status |
|--------|---------|--------|
| Tooling/theme/primitives | Tailwind+shadcn, theme tokens, `Button/Field/Banner/Loading/StateBlock/Link` rewritten in place | EXISTS (rewrite) |
| AppShell + Context Selector | Header (brand, org, theme toggle), sidebar nav, page container; role/org/program/cohort switcher | EXISTS (rewrite) + NEW selector |
| MSW harness | `src/mocks/` worker + handlers, dev-gated | NEW |
| LoginPage (flagship) | Branded auth card | EXISTS |
| ProgramsPage (flagship) | Shell + page header + list + states | EXISTS |

### Slice 1 — Identity & shell complete
| Screen | Purpose | Role | Status |
|--------|---------|------|--------|
| RegisterPage | Create native account | All | EXISTS |
| ForgotPasswordPage | Request reset | All | EXISTS |
| ResetPasswordPage | Set new password | All | EXISTS |
| EmailVerifiedPage | Verification success | All | EXISTS |
| VerifyEmailNotice | Unverified interstitial | All | EXISTS |
| AuthCallbackPage | OIDC/SSO return | All | EXISTS |
| OnboardingPage | Create organization | Operator | EXISTS |
| Action Center home (operator) | Urgent actions, deadlines, next action | Operator | EXISTS (partial) |
| Action Center home (founder) | Founder-scoped actions/deadlines | Founder | NEW |
| Action Center home (mentor/trainer/evaluator/…) | Role-scoped action cards | Staff roles | NEW |
| Profile view | General + role profiles | All | NEW |
| Profile edit | Edit profile/role attributes | All | NEW |
| Consent management | Grant/revoke profile-data access | All | NEW |
| Notifications center | List/filter/act on notifications | All | NEW |
| Notification preferences | Channels, frequency, quiet hours | All | NEW |
| Global search | Unified search across entities | All | NEW |

### Slice 2 — Operator: setup → intake
| Screen | Purpose | Status |
|--------|---------|--------|
| Program detail | View/edit/clone/publish | EXISTS |
| Program cohorts section | List/create cohorts under program | EXISTS |
| Cohort detail | Edit metadata, enrollment window | EXISTS |
| Cohort setup wizard | Create → attach form → attach stages → dates → open | NEW |
| Form binding | Attach published form version to cohort | NEW |
| Stage-engine config | Attach stage template, entry/exit gates | NEW |
| Form builder | Field palette, conditional logic, validation | NEW (ApplyField primitives exist) |
| Form preview | Read-only applicant render (incl. RTL) | NEW |
| Stages list/version view | Versioned stages (immutable) | NEW |
| Program configuration hub | Tabbed: stages/forms/roles/workflows/notifications | NEW |
| Templates gallery | Program/stage/form templates to clone | NEW |
| Cohort enrollment window editor | Open/close windows, capacity | NEW |

### Slice 3 — Operator: selection & evaluation
| Screen | Purpose | Status |
|--------|---------|--------|
| Applicants list / funnel | Funnel metrics, submissions list, export | EXISTS (SubmissionsPage) |
| Application detail (snapshot) | Immutable answer snapshot | EXISTS (SubmissionDetailPage) |
| Eligibility review | Auto result + manual override | NEW |
| Scoring / assessment console | Rubric scoring, decimal, draft/submit | NEW |
| Scoring summary dashboard | Applicant × evaluator completion grid | NEW |
| Evaluator assignment | Assign evaluators, COI rules | NEW |
| Role eligibility config | Role templates, eligibility rules | NEW |
| Workflow automation config | Stage transitions, expression-tree rules | NEW |
| Final evaluation setup | Final rubric, graduation thresholds | NEW |
| Final evaluation review | Score participants, decision | NEW |
| Reports dashboard | Funnel, time-to-decision, outcomes | NEW |
| Participant tracking | Active participants, stage, progress | NEW |

### Slice 4 — Founder / applicant portal
| Screen | Purpose | Status |
|--------|---------|--------|
| Public program discovery/landing | Program overview, eligibility, CTA | NEW |
| Registration of interest | Waitlist/demand capture | NEW |
| Apply multi-step form | Stepped apply, autosave/resume | EXISTS (ApplyPage) |
| Application review & confirm | Final review before immutable submit | EXISTS |
| My application status | Post-submit tracking | NEW |
| Required actions / tasks | Tasks, milestones, doc requests | NEW |
| My startup profile | Startup entity, team, delegation | NEW |
| Program journey / timeline | Founder-side lifecycle timeline | NEW |
| My sessions (mentorship) | Scheduled sessions, notes, attendance | NEW |
| My training | Enrolled courses, attendance, completion | NEW |
| My documents | Required/submitted/approved/rejected | NEW |
| Messages / inbox | Comms from staff/mentors | NEW |

### Slice 5 — Delivery & role workspaces
| Screen | Purpose | Role | Status |
|--------|---------|------|--------|
| My mentees | Roster, status, quick actions | Mentor | NEW |
| Mentor session plan | Plan agendas/prep | Mentor | NEW |
| Session notes & attendance | Record outcomes/attendance | Mentor | NEW |
| Mentor availability calendar | Set slots/blockouts | Mentor | NEW |
| My training sessions | Roster, attendance, materials | Trainer | NEW |
| Training attendance | Check-in/out, absence | Trainer | NEW |
| Training materials | Upload/organize content | Trainer | NEW |
| My assigned evaluations | Scoring queue w/ COI | Evaluator | NEW |
| Scoring screen | Score vs rubric | Evaluator | EXISTS (reuses SubmissionDetail) |
| Conflict declaration | Recuse/disclose | Evaluator/Judge | NEW |
| Judge panel dashboard | Aggregate panel scores, consensus | Judge | NEW |
| My service offerings | Provider catalog | Service Provider | NEW |
| Service requests queue | Incoming requests | Service Provider | NEW |
| Service request detail | Accept/deliver/evidence | Service Provider | NEW |
| Program logistics & calendar | Master session/deadline calendar | Coordinator | NEW |
| Coordinator task queue | Scheduling/venue tasks | Coordinator | NEW |
| Final evaluation result (founder) | Decision, remediation/graduation | Founder | NEW |
| Alumni profile / follow-up | Graduation record, outcomes | Alumni | NEW |
| Participant sessions/training/tasks | Delivery surfaces (shared w/ slice 4 founder) | Participant | NEW |

### Slice 6 — SaaS commercial plane
| Screen | Purpose | Status |
|--------|---------|--------|
| Public pricing & plan selection | Tiered plans, annual/monthly | NEW |
| Geidea hosted checkout hand-off | Redirect to HPP | NEW |
| Payment return handler | Capture return, confirm via callback | NEW |
| Current subscription dashboard | Plan, renewal, status, actions | NEW |
| Usage & limits dashboard | Consumption vs entitlements | NEW |
| Plan comparison | Feature matrix for up/downgrade | NEW |
| Add-ons management | Seats/storage/API add-ons | NEW |
| Upgrade / downgrade flow | Proration, effective date | NEW |
| Payment methods | Saved methods (masked) | NEW |
| Invoices & billing history | Invoices, tax, PDF | NEW |
| Renewal & dunning | Past-due, grace, restore | NEW |
| Cancellation & offboarding | Reason, export window | NEW |
| Domain setup wizard | Subdomain/custom domain, DNS, TLS | NEW |
| Domain management | Status, certs, remove | NEW |
| Branding setup wizard | Logo/colors/preview | NEW |
| Branding management | Asset library, email sender | NEW |
| White-label level config | Entitlement level + upsell | NEW |
| SaaS admin console | Plan builder, entitlements, dunning policy | NEW (super-admin) |
| Trial conversion prompt | Trial-ending CTA | NEW |
| Entitlement / paywall prompt | Limit block + upgrade | NEW |
| Billing contacts | Invoice/dunning recipients | NEW |
| Tax details | Tax ID, exemptions | NEW |

### Slice 7 — Administration & compliance
| Screen | Purpose | Status |
|--------|---------|--------|
| Organization settings | Name, defaults, integrations | NEW |
| Organization members | Members, roles, invites | NEW |
| Role & permission management | Custom roles, permission matrix | NEW |
| Audit log | Immutable action trail, filter, export | NEW |
| Compliance / retention report | Retention status, exports | NEW |
| Feature flags | Enable/disable features | NEW |
| Taxonomy management | Skills/sectors/categories | NEW |
| Integrations | Calendar/meeting/external connectors | NEW |
| User sessions | View/revoke active sessions | NEW |
| Privacy & data rights | Export/deletion requests | NEW |
| Accessibility settings | Font/contrast/RTL/shortcuts | NEW |

### Slice 8 — Extended features (sub-slices)
Grouped by feature; each group is a sub-slice with its own spec/plan.

- **8a Interviews & public programs:** public landing, registration-of-interest,
  interview scheduling board, interview scorecard, interview panel roster,
  waitlist & conditional-admission dashboard. (6)
- **8b Tracks & operations-finance:** personalized-track config, participant
  track assignment, partner/sponsor directory, partner permissions, budget
  overview, budget categories, grants/stipends, expense claims, budget/tax audit,
  timesheet entry, timesheet approval, resource-utilization dashboard. (12)
- **8c Service marketplace & collaboration:** provider directory, listing detail,
  request submission, request triage, fulfillment tracker, request feedback;
  application comments, mentor–startup thread, internal staff notes. (9)
- **8d Surveys, hackathons, knowledge:** survey builder, survey distribution/
  analytics, response viewer; hackathon setup, judging, results, team hub;
  KB library, KB publisher, KB search. (10)
- **8e Quality, simulation, outcomes, risk:** simulation runner, simulation
  results, configuration-validation report; outcomes builder, outcomes data
  entry, outcomes dashboard; risk register, intervention planner, risk dashboard;
  bulk invite, bulk role/status, bulk export, data-quality dashboard, data-quality
  repair, version-migration planner, controlled migration. (16)
- **8f Documents, support, achievements:** application dossier, evaluation
  summary, decision letter, mentorship report, training transcript, graduation
  report, certificate, sponsor report, audit report (print/PDF); support intake,
  support dashboard, support detail; setup checklist, contextual help;
  achievement attestation, achievement history. (16)

### Slice 9 — API-wiring pass
For each built screen: confirm the real `api/` client exists (or add it against
the documented contract), disable MSW for that endpoint, and verify end-to-end
against the running stack (Playwright + network assertion, as done for FE-2.5).
Screens whose backend module is still Scaffold/Absent remain on MSW until the
backend lands — tracked, not silently dropped.

## Risks / call-outs

- **Scale:** ~130 screens. This document is the parent; real work happens in
  per-slice specs/plans. Do not attempt as one plan.
- **Backend readiness gap:** many modules are Scaffold/Absent
  (`docs/status/implementation-status.md`). Those screens prototype on MSW now
  and wire later; slice 9 must not present an unwired screen as "done".
- **Mock/contract drift:** mitigated by typing fixtures against `src/schemas/`.
- **Test churn:** rewriting shared primitives in place breaks `ds-*` class
  assertions; bounded and fixed within slice 0 (per the foundation design).
- **RTL/a11y regressions:** every slice keeps the a11y + contrast suites green.

## Out of scope

Backend, auth, tenancy, authorization changes. New product capabilities beyond
the scope register. Real payment processing (Geidea checkout is a mocked hand-off
in the prototype).

## Next step

Brainstorm **Slice 0 (Foundation)** in detail as the first sub-project — building
on the approved `2026-06-26-shadcn-tailwind-foundation-design.md`, extended with
the MSW harness — then produce its implementation plan and build it. Subsequent
slices follow the same spec → plan → build cycle.
