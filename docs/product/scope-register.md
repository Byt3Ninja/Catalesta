# Catalesta Scope Register

> Owner: Product · Last-updated: 2026-06-19 · Source-of-truth: **this file** (authoritative for scope)

This register is the **single source of truth for product scope**. It defines
*what* Catalesta is. It does **not** decide build order — that is
`../plan/roadmap.md`. It carries **no implementation status** — that is
`../status/implementation-status.md`. Any other doc that describes scope must
reference this register, not restate it.

- **Canonical module count: 24.**
- **Numbering authority: build-spec IDs `00`–`68`** (see `../plan/build-specs/`). All other docs reference these IDs; no competing numbered list exists.

---

## Modules (canonical — 24)

Per `CLAUDE.md` "Required Modules." Each module's deep spec is its primary
build-spec under `../plan/build-specs/`.

| # | Module | Responsibility | Primary build-spec |
|---|---|---|---|
| 1 | Identity | Global identity via Startup Gate; `sub` as immutable user id; auth | `01`, `02` |
| 2 | Organizations | Tenant organizations; tenancy root; RBAC | `03` |
| 3 | Profiles | General + role profiles; consent-aware access | `02` |
| 4 | Startups | Startup entities; memberships; delegation | `04` |
| 5 | Programs | Program definitions; policies; templates; cloning | `05` |
| 6 | Cohorts | Cohort instances; enrollment windows | `05` |
| 7 | Stages | Configurable, versioned stage engine; entry/exit rules | `06` |
| 8 | Forms | Form builder; conditional logic; calculated fields | `07` |
| 9 | Applications | Application management; immutable snapshots | `08` |
| 10 | Documents | Document management; storage | `09` |
| 11 | Assessments | Assessment/scoring engine (decimal arithmetic) | `10` |
| 12 | Workflows | Workflow engine; expression-tree conditions | `11` |
| 13 | RoleAssignments | Role eligibility and assignments | `12` |
| 14 | Tasks | Tasks and milestones | `13` |
| 15 | Mentorship | Mentor matching and sessions | `14` |
| 16 | Training | Training delivery | `15` |
| 17 | FinalEvaluation | Final evaluation | `16` |
| 18 | Graduation | Graduation, alumni, follow-up | `17` |
| 19 | Notifications | Notifications and communications | `18` |
| 20 | Integrations | Calendar/meeting and external integrations (behind interfaces) | `19` |
| 21 | Reporting | Reporting and dashboards | `20` |
| 22 | Search | Search and directories | `21` |
| 23 | Administration | Administration and configuration | `22` |
| 24 | Audit | Audit and compliance | `24` |

---

## Core lifecycle

`Application → Eligibility → Initial Evaluation → Mentorship → Training → Final
Evaluation → Graduation → Alumni Follow-Up`. All stages are configurable
templates. See `lifecycle.md`.

---

## Extended scope

Reconciled from master-scope **and** the product brief (brief-dropped items are
restored here and marked †). Each item is defined in `features/`.

| Capability | Build-spec | Definition |
|---|---|---|
| Interviews & live screening | `42` | `features/interviews-public-programs.md` |
| Public program pages & discovery | `43` | `features/interviews-public-programs.md` |
| Waitlists & conditional admission † | `43` | `features/interviews-public-programs.md` |
| Personalized tracks † | `06` | `features/personalized-tracks.md` |
| Partners, sponsors, funders | `44` | `features/program-operations-finance.md` |
| Program finance & grants | `45` | `features/program-operations-finance.md` |
| Timesheets & resource utilization | `46` | `features/program-operations-finance.md` |
| Service requests & marketplace | `47` | `features/service-marketplace.md` |
| Messaging & collaboration | `48` | `features/service-requests-collaboration.md` |
| Surveys & feedback (NPS) | `49` | `features/surveys-hackathons-knowledge.md` |
| Hackathons & challenges | `50` | `features/surveys-hackathons-knowledge.md` |
| Knowledge base | `51` | `features/surveys-hackathons-knowledge.md` |
| Program simulation | `52` | `features/simulation-validation.md` |
| Configuration validation | `52` | `features/simulation-validation.md` |
| Outcomes & impact framework | `53` | `features/outcomes-impact.md` |
| Risk & intervention | `54` | `features/risk-intervention.md` |
| Data lifecycle & privacy rights | `55` | `../architecture/data-privacy-rights.md` |
| Bulk operations & data quality | `56` | `features/bulk-operations-data-quality.md` |
| Version migration | `57` | `features/bulk-operations-data-quality.md` |
| Print & formal documents † | `29`-era | `features/formal-documents.md` |
| Support case management † | `28`-era | `features/support-cases.md` |
| Achievements (trusted publication) | `02` | `features/achievements-trusted-publication.md` |

---

## SaaS commercial scope

| Capability | Build-spec | Definition |
|---|---|---|
| Versioned immutable plans | `58` | `../saas/plans-entitlements-usage.md` |
| Feature entitlements (`EntitlementService`) | `58` | `../saas/plans-entitlements-usage.md` |
| Usage metering & limits (server-side) | `60` | `../saas/plans-entitlements-usage.md` |
| Subscription lifecycle (trials, dunning) | `59` | `../saas/subscriptions-billing.md` |
| Upgrades / downgrades / add-ons | `62` | `../saas/subscriptions-billing.md` |
| Geidea recurring billing + Hosted Payment Page | `61` | `../saas/geidea-payments.md` |
| SaaS administration | `63` | `../saas/commercial-architecture.md` |
| SaaS security & testing | `64` | `../saas/security-testing.md` |
| Billing & usage UX | `65` | `../ux/saas-billing-ux.md` |
| Tenant subdomains & verified custom domains (TLS) | `66` | `../saas/domains-branding.md` |
| Tenant branding / white-label | `67` | `../saas/white-label-levels.md` |

---

## Build-spec index (00–68) — the canonical numbering

Files live in `../plan/build-specs/`. Cross-cutting (✦) specs are infra/integration, not a single module.

| ID | Capability | Module |
|---|---|---|
| 00 | Repository Bootstrap | ✦ infra |
| 01 | Mock Startup Gate OIDC | Identity |
| 02 | Identity, Profiles, Consent | Identity / Profiles |
| 03 | Organizations, Tenancy, RBAC | Organizations |
| 04 | Startups, Memberships, Delegation | Startups |
| 05 | Programs, Cohorts, Templates | Programs / Cohorts |
| 06 | Stage Engine | Stages |
| 07 | Form Builder | Forms |
| 08 | Application Management | Applications |
| 09 | Document Management | Documents |
| 10 | Assessment Engine | Assessments |
| 11 | Workflow Engine | Workflows |
| 12 | Role Eligibility & Assignments | RoleAssignments |
| 13 | Tasks & Milestones | Tasks |
| 14 | Mentorship | Mentorship |
| 15 | Training | Training |
| 16 | Final Evaluation | FinalEvaluation |
| 17 | Graduation, Alumni, Follow-Up | Graduation |
| 18 | Notifications & Communications | Notifications |
| 19 | Calendar & Meeting Integrations | Integrations |
| 20 | Reporting & Dashboards | Reporting |
| 21 | Search & Directories | Search |
| 22 | Administration & Configuration | Administration |
| 23 | Public API & Webhooks | ✦ Administration/Integrations |
| 24 | Audit & Compliance | Audit |
| 25 | Localization & Accessibility | ✦ cross-cutting |
| 26 | Security Hardening | ✦ cross-cutting |
| 27 | Observability & Operations | ✦ cross-cutting |
| 28 | Data Migration, Import, Export | ✦ cross-cutting |
| 29 | Performance & Production Readiness | ✦ cross-cutting |
| 30 | Real Startup Gate Cutover | Identity ✦ |
| 31 | Integration Orchestration | ✦ integration |
| 32 | Full System Integration Test | ✦ integration |
| 33 | Release Readiness Review | ✦ integration |
| 34 | UX Research & Information Architecture | UX |
| 35 | Design System | UX |
| 36 | Role-Based Navigation | UX |
| 37 | Onboarding & Progressive Disclosure | UX |
| 38 | Forms Application Experience | UX |
| 39 | Dashboard Action Center | UX |
| 40 | Mobile Responsive Experience | UX |
| 41 | Usability Testing & Analytics | UX |
| 42 | Interviews & Live Screening | Extended |
| 43 | Public Program Pages & Discovery | Extended |
| 44 | Partners, Sponsors, Funders | Extended |
| 45 | Program Finance & Grants | Extended |
| 46 | Timesheets & Resource Utilization | Extended |
| 47 | Service Requests & Marketplace | Extended |
| 48 | Messaging & Collaboration | Extended |
| 49 | Surveys, Feedback, NPS | Extended |
| 50 | Hackathons & Challenges | Extended |
| 51 | Knowledge Base & Content Library | Extended |
| 52 | Program Simulation & Validation | Extended |
| 53 | Program Outcomes & Impact Framework | Extended |
| 54 | Risk & Intervention Management | Extended |
| 55 | Data Lifecycle & Privacy Rights | Extended |
| 56 | Bulk Operations & Data Quality | Extended |
| 57 | Version Migration Management | Extended |
| 58 | SaaS Plans & Entitlements | SaaS |
| 59 | Subscription Lifecycle | SaaS |
| 60 | Usage Metering & Limits | SaaS |
| 61 | Geidea Billing & Payments | SaaS |
| 62 | Upgrades, Downgrades, Add-ons | SaaS |
| 63 | SaaS Administration | SaaS |
| 64 | SaaS Security Testing | SaaS |
| 65 | SaaS Billing & Usage UX | SaaS |
| 66 | Tenant Subdomains & Custom Domains | SaaS |
| 67 | Tenant Basic Branding | SaaS |
| 68 | SaaS End-to-End Integration | SaaS ✦ |

---

## Reconciliation notes

- **Module count reconciled to 24** (CLAUDE.md Required Modules), confirmed by the
  owner 2026-06-19. An earlier figure of "20" came from a partial folder count — a
  build detail, not intended scope. What is built vs. pending is recorded in
  `../status/implementation-status.md`, never here.
- **Single numbering:** the prior three numbering schemes (prompt index,
  dependency-graph, brief catalog) are collapsed onto the build-spec IDs `00`–`68`
  above. The brief no longer restates the catalog; it references this register.
- **Brief-dropped extended items restored** (marked † above): waitlists/conditional
  admission, personalized tracks, print/formal documents, support case management.
