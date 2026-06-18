# Catalesta — Product Brief / PR-FAQ (Full Platform Edition)

**Status:** Draft v2 · **Date:** 2026-06-18 · **Owner:** Product
**Decisions baked in:** Target customer = accelerators & incubators · Primary market = Egypt / MENA · Lead problem = fragmented program tooling · Scope stance = full platform is the v1 bet

> This brief is the *why / who / outcomes* layer above the build kit, **plus** a complete feature catalog of the platform. It gives the existing specs (`docs/00`–`docs/36`, `prompts/INDEX.md`) a problem to be measured against and a single readable inventory of everything Catalesta does. It does not change scope or architecture; it documents them.

---

## The one-liner

**Catalesta is the configurable, multi-tenant operating system for incubation and acceleration programs** — one system to run the full lifecycle (Application → Eligibility → Initial Evaluation → Mentorship → Training → Final Evaluation → Graduation → Alumni Follow-Up), replacing the spreadsheets, generic form tools, and email threads that programs cobble together today.

---

## Press Release (written from the future — launch day)

**FOR IMMEDIATE RELEASE — Cairo**

### Catalesta launches the first end-to-end platform purpose-built for running startup programs

**Accelerators and incubators across Egypt and MENA can now run an entire program — from open call to alumni follow-up — in one configurable, auditable system instead of a patchwork of spreadsheets, forms, and inboxes.**

Today most accelerators and incubators run their programs on a stack that was never designed for the job: a form builder for applications, a spreadsheet for scoring, email and chat for coordination, a separate tool for mentorship scheduling, and a manual scramble at reporting time. The result is lost applications, inconsistent and disputable evaluation, no single source of truth, and weeks of staff time spent stitching data together. Every new program or cohort starts the assembly from scratch.

Catalesta replaces that patchwork with a single platform. Program teams configure stages, forms, and scoring once, publish them as immutable versioned templates, and run every cohort the same trustworthy way. Applications, documents, evaluations, mentorship, training, graduation, and outcome reporting all live in one tenant-isolated system. Scoring is decimal-accurate, versioned, and consent-aware, so selection decisions are auditable and defensible. Programs that ran on five tools and a shared drive now run on one.

"We used to lose the first week of every cohort just reconciling applications across tools, and we could never give our funders a clean outcomes report," said a program director at a launch design-partner accelerator. "With Catalesta we configured our program once, opened applications the same day, and our evaluators scored against the same rubric. Selection took hours, not weeks — and the audit trail was already there."

Catalesta is built MENA-first: fully bilingual Arabic/English with right-to-left support, integrated with Geidea for billing, and designed to meet regional data-protection expectations. Organizations get their own subdomain (and optional verified custom domain), controlled branding, and subscription plans that scale with the programs they run.

Getting started takes minutes: an organization signs up, starts a trial, configures and publishes its first program, and opens applications — no implementation project required.

---

## The brief

### Problem (lead pain)
Programs run on **fragmented tooling**. There is no single configurable system for the full application→graduation lifecycle, so program teams assemble spreadsheets, form builders, email, and generic SaaS for every cohort. This causes lost data, inconsistent and non-auditable evaluation, no single source of truth, heavy manual reporting, and zero reuse across programs.

### Target customer
- **Segment:** Accelerators and incubators running structured cohort programs.
- **Market:** Egypt and the wider MENA region (drives Arabic/English + RTL, Geidea billing, regional compliance posture).
- **Buyer:** Program Director / Operations Lead — owns program delivery and answers to funders/leadership.
- **Primary users:** Program staff/admins, evaluators/judges, mentors, trainers, and founders/applicants (mapped to the role workspaces in `docs/13`/`docs/15`).
- **Stakeholders (not daily users):** Funders, sponsors, and leadership who consume outcomes and reporting.

### Jobs to be done (top 3)
1. **"Run a whole program in one place"** — configure stages/forms/scoring once, open applications, evaluate, select, and graduate without leaving the system.
2. **"Make selection trustworthy and defensible"** — score consistently against versioned rubrics with an immutable, consent-aware audit trail.
3. **"Prove the program worked"** — report cohort progress and outcomes to funders and leadership without a manual data scramble.

### Why us / why now
The build kit already encodes the hard, defensible parts: clean Startup Gate ↔ Program Platform domain boundaries, strict multi-tenant isolation, immutable-and-versioned published artifacts, decimal scoring, consent-aware access, and a MENA-native stack (bilingual, Geidea). Competitors are either generic (form/spreadsheet tools with no lifecycle) or US/EU-first (no Arabic/RTL, no Geidea, wrong compliance posture).

### Scope stance for v1
**The full platform is the v1 bet.** v1 delivers the complete feature catalog below — core lifecycle, experience layer, extended capabilities, and SaaS commercial scope — executed in the dependency order of `prompts/INDEX.md` (68 prompts) behind the release gates in `docs/12`. *Known trade-off (internal FAQ Q3): this is a large, mostly-sequential commitment; we mitigate it with early design partners and by treating gate completions as learning checkpoints.*

### How we'll know it worked (proposed — needs ratification)
- **North Star (candidate):** *Programs published per active tenant per quarter*.
- **Activation:** Time from tenant signup → first **published** program.
- **Engagement/value:** Applications processed per published program; cohorts run to graduation.
- **Retention/commercial:** Tenant net revenue retention (NRR); trial → paid conversion.
- **Quality gates (already defined):** UX task-success / low-abandonment metrics (`docs/13`, `docs/20`) and release gates (`docs/12`).

---

## Platform at a glance

Catalesta is a **Laravel modular monolith** organized into two domains and a SaaS control plane:

- **Startup Gate (global identity domain)** — owns the immutable user identity (`sub`), general and role profiles, startup memberships, consent, verification, shared directories, and achievements. The cross-system identifier is always `sub`, never email.
- **Program Platform (tenant domain)** — owns organizations, programs, cohorts, stages, forms, applications, documents, assessments, workflows, role assignments, tasks, mentorship, training, final evaluation, graduation, and reporting. Every tenant-owned record carries `organization_id` and every query enforces tenant isolation.
- **SaaS control plane** — plans, entitlements, usage metering, subscriptions, billing/payments (Geidea), domains, and branding.

**Delivery shape:** 68 capabilities across 6 groups → Foundation & Core Lifecycle (prompts 00–30), Integration & Release (31–33), Experience Layer (34–41), Extended Capabilities (42–57), and SaaS Commercial (58–68).

---

## Full feature catalog

### A. Foundation & Identity
1. **Repository & platform bootstrap** — modular monolith skeleton, module conventions, CI scaffolding.
2. **Startup Gate identity (OIDC)** — single sign-on against Startup Gate; mock provider for development, real cutover for production. `sub` is the immutable user key.
3. **Identity, Profiles & Consent** — general profiles, role profiles, and consent records; all profile access is consent-aware, and consent state is enforced on every read.
4. **Organizations, Tenancy & RBAC** — tenant organizations, host/subdomain tenant resolution (unknown hosts rejected), role-based access control scoped per organization.
5. **Startups, Memberships & Delegation** — startup entities, founder/member memberships, and delegated access so teams can act on a startup's behalf.

### B. Program Configuration & Lifecycle Engines
6. **Programs, Cohorts & Templates** — configurable programs and cohorts built from reusable templates; programs are not frozen, but published sub-artifacts are.
7. **Stage Engine** — configurable lifecycle stages (Application → Eligibility → Initial Evaluation → Mentorship → Training → Final Evaluation → Graduation → Alumni Follow-Up); **published stage versions are immutable and versioned**.
8. **Form Builder** — dynamic form definitions for any stage; published forms are immutable and versioned; no arbitrary code execution in form logic.
9. **Application Management** — application intake, status tracking, and progression through stages; formal submissions capture **immutable snapshots**.
10. **Document Management** — document upload, classification, and association with applications/participants, with versioning.
11. **Assessment Engine** — rubric-based scoring with **decimal arithmetic**; published assessments are immutable and versioned; scoring is reproducible and auditable.
12. **Workflow Engine** — configurable transitions/automation between stages; declarative rules only, no arbitrary code execution.
13. **Role Eligibility & Assignments** — dynamic role definitions, eligibility rules, and assignment of evaluators/mentors/judges to applications, cohorts, and startups.
14. **Tasks & Milestones** — task and milestone tracking for participants and staff across the program timeline.

### C. Program Delivery
15. **Mentorship** — mentor matching/assignment, sessions, and mentorship tracking.
16. **Training** — training modules/curricula, attendance, and progress.
17. **Final Evaluation** — end-of-program evaluation with decimal scoring and immutable snapshots, feeding graduation decisions.
18. **Graduation, Alumni & Follow-Up** — graduation decisions/records, alumni status, and post-graduation follow-up tracking.

### D. Platform Services
19. **Notifications & Communications** — multi-channel notifications and templated communications across the lifecycle.
20. **Calendar & Meeting Integrations** — calendar and meeting scheduling integrations, kept behind integration interfaces.
21. **Reporting & Dashboards** — operational and program reporting with dashboards for staff and stakeholders.
22. **Search & Directories** — search across programs/participants and shared directories (consent-aware).
23. **Administration & Configuration** — tenant administration and platform configuration surfaces.
24. **Public API & Webhooks** — versioned public API and webhook delivery for external integration.
25. **Audit & Compliance** — comprehensive audit trail of significant actions for compliance and dispute resolution.

### E. Cross-Cutting Platform Qualities
26. **Localization & Accessibility** — bilingual Arabic/English with full RTL; WCAG 2.2 AA as a first-class requirement.
27. **Security Hardening** — tenant isolation, consent enforcement, secure defaults (per `docs/04`).
28. **Observability & Operations** — logging, metrics, and operational tooling.
29. **Data Migration, Import & Export** — data import/export and migration tooling for onboarding and portability.
30. **Performance & Production Readiness** — performance budgets and production-readiness criteria.
31. **Startup Gate Cutover** — migration from mock to the real Startup Gate identity provider.
32. **Integration Orchestration, System Integration Test & Release Readiness Review** — cross-module integration, full system test, and a formal release gate (prompts 31–33).

### F. Experience Layer (UX)
33. **UX Research & Information Architecture** — research-grounded IA for the platform.
34. **Design System** — shared component/design system (`docs/14`).
35. **Role-Based Navigation** — distinct role workspaces (founder, mentor, evaluator/judge, trainer, staff…) per `docs/15`.
36. **Onboarding & Progressive Disclosure** — guided onboarding that reveals complexity gradually.
37. **Forms & Application Experience** — high-completion application UX (`docs/17`).
38. **Dashboard & Action Center** — an Action Center home pattern surfacing what each role must do next (`docs/18`).
39. **Mobile & Responsive Experience** — responsive/mobile UX (`docs/19`).
40. **Usability Testing & Analytics** — outcome-oriented usability metrics: task success, low abandonment, time-to-complete — *not* session-duration vanity metrics (`docs/20`).

### G. Extended Capabilities
41. **Interviews & Live Screening** — scheduled interviews and live screening as part of selection.
42. **Public Program Pages & Discovery** — public-facing program pages and discovery for open calls.
43. **Partners, Sponsors & Funders** — partner/sponsor/funder entities and their relationships to programs.
44. **Program Finance & Grants** — budgets, stipends, grants, and milestone-based disbursement.
45. **Timesheets & Resource Utilization** — time tracking and resource utilization reporting.
46. **Service Requests & Marketplace** — service requests and a marketplace of program services.
47. **Messaging & Collaboration** — in-platform messaging and collaboration.
48. **Surveys, Feedback & NPS** — surveys, structured feedback, and NPS.
49. **Hackathons & Challenges** — hackathon/challenge program types.
50. **Knowledge Base & Content Library** — knowledge base and reusable content library.
51. **Program Simulation & Validation** — simulate/validate a program configuration before launch.
52. **Program Outcomes & Impact Framework** — baselines, targets, actuals, and theory-of-change outcome tracking (`docs/25`).
53. **Risk & Intervention Management** — participant risk flags and intervention tracking.
54. **Data Lifecycle & Privacy Rights** — DSR rights (access, erasure, portability, objection), retention categories, and data-lifecycle handling.
55. **Bulk Operations & Data Quality** — bulk actions and data-quality management.
56. **Version Migration Management** — migrating tenants/configurations across versioned artifacts.

### H. SaaS Commercial Scope
57. **Plans & Entitlements** — versioned, immutable-after-publication plans; entitlements consumed via `EntitlementService` (no module ever checks plan names).
58. **Subscription Lifecycle** — trials, active, upgrades, downgrades, suspension, and enterprise contracts.
59. **Usage Metering & Limits** — server-side usage metering and limits; reaching a limit notifies (at 80%) and preserves data + read access (at 100%) — never deletes tenant data or interrupts in-progress critical workflows.
60. **Geidea Billing & Payments** — recurring billing and Hosted Payment Page via Geidea, behind a payment-provider interface; browser returns are not authoritative; callbacks are signature-verified and processed idempotently; no raw card/CVV storage.
61. **Upgrades, Downgrades & Add-ons** — plan changes and add-on entitlements.
62. **SaaS Administration** — plan/subscription/billing administration.
63. **SaaS Security Testing** — security tests specific to the commercial plane.
64. **Billing & Usage UX** — tenant-facing billing and usage dashboards.
65. **Tenant Subdomains & Custom Domains** — tenant subdomains plus verified custom domains with automatic TLS lifecycle; ownership verification required.
66. **Tenant Branding** — controlled branding tokens and assets (no arbitrary CSS/scripts).
67. **SaaS End-to-End Integration** — full commercial-plane integration test.

> Numbering above groups the 68 build prompts thematically; exact build order is `prompts/INDEX.md`.

---

## Commercial model

Billing scales with the value a tenant gets, **not** raw registered users (applicants, mentors, evaluators, and alumni have different commercial value). Metered/plan dimensions include:

active programs · cohorts per year · internal staff seats · annual applications · active participants · external collaborator pool · storage · automation executions · custom reports · API requests · integrations · custom domains · white-label level · audit retention · support SLA.

Plans are **versioned and immutable after publication**; enterprise tenants can be invoiced manually.

---

## Non-negotiable platform guarantees (these are the product's differentiators)
1. **Multi-tenant isolation** — every tenant record carries `organization_id`; every query enforces isolation; unknown hosts are rejected.
2. **Immutable, versioned artifacts** — published forms, workflows, assessments, and stages cannot change; new versions are created instead.
3. **Trustworthy scoring** — decimal arithmetic, no arbitrary code in rules, immutable submission snapshots.
4. **Consent-aware everywhere** — all profile access respects consent state.
5. **Identity integrity** — Startup Gate `sub` is the immutable cross-system key; email is never the identifier.
6. **Payment integrity** — provider-interface isolation, verified idempotent callbacks, no raw card/CVV, browser returns non-authoritative.
7. **Data-respecting limits** — hitting a usage limit never hides or deletes existing tenant data.

---

## Customer FAQ

**Q: Do we have to replace all our tools at once?**
A: No. A program team can run a single new program end-to-end in Catalesta while legacy programs wind down; each program is configured independently.

**Q: Is it in Arabic?**
A: Yes — bilingual Arabic/English with full RTL as a first-class feature.

**Q: How do we pay?**
A: Self-serve subscription plans billed through Geidea (hosted payment page + recurring billing), plus manual invoicing for enterprise tenants.

**Q: Can we use our own branding and domain?**
A: Yes. Every tenant gets a subdomain with controlled branding tokens; verified custom domains with automatic TLS are supported.

**Q: Is our data isolated from other organizations?**
A: Yes. Every tenant-owned record carries `organization_id`, every query enforces isolation, and profile access is consent-aware.

**Q: Can we trust the evaluation results?**
A: Yes. Published forms, workflows, assessments, and stages are immutable and versioned; scoring uses decimal arithmetic; formal submissions capture immutable snapshots — so decisions are reproducible and auditable.

**Q: What happens if we hit a plan limit mid-cohort?**
A: You're notified at 80%, and at 100% your data and read access are preserved — Catalesta never deletes your data or interrupts an in-progress critical workflow.

---

## Internal FAQ

**Q1: Why accelerators/incubators and MENA first?**
A: The existing build (Geidea, Arabic/RTL, the program lifecycle) is already shaped for this segment and region, so the product is differentiated and complete on day one rather than generic everywhere.

**Q2: What is explicitly NOT in v1?**
A: With "full scope is the bet," nothing in the catalog is formally deferred. The honest lever is *sequence*, not scope: low-evidence extended modules (hackathons, timesheets, simulation) sit late in `prompts/INDEX.md` and can slip without blocking a sellable product. We should still name an internal "first sellable slice" for design partners — proposed: signup → publish program → applications → selection → billing.

**Q3: What is the biggest risk?**
A: Building all 68 units in dependency order before the market validates which third matters most. Mitigation: recruit 2–3 design-partner accelerators now; treat each release gate as a learning checkpoint; be willing to re-sequence later prompts based on usage.

**Q4: What compliance regime applies?**
A: MENA-first implies Egypt PDPL as the baseline, with GDPR-grade data-rights handling (the DSR rights in `docs/25` already point this way). Concrete retention values and a residency decision are open questions, not resolved here.

**Q5: Relationship to the existing docs?**
A: This brief is the *why/who/outcomes* + feature-catalog layer. It does not redefine the architecture (`docs/01`–`docs/12`), the UX strategy (`docs/13`–`docs/29`), or the SaaS architecture (`docs/30`–`docs/36`).

---

## Open questions for stakeholders
1. **Activation/North Star targets:** what numeric targets do we commit to with the first design partners?
2. **First sellable slice:** do we ratify an internal MVP boundary even though full scope is the bet?
3. **Compliance:** Egypt PDPL only, or PDPL + GDPR posture? Data-residency requirements?
4. **Pricing & packaging:** how many tiers, entry price, trial length, and which plan dimensions are metered at launch?
5. **Design partners:** which 2–3 accelerators/incubators do we recruit to validate the bet?
