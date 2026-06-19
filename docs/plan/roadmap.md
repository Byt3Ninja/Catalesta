# Catalesta Plan of Record (Roadmap)

> Owner: Delivery · Last-updated: 2026-06-19 · Source-of-truth: **this file** (authoritative for sequence)

This is the **plan of record** — the single place the build sequence is decided.
It references `../product/scope-register.md` for *what* exists (by build-spec ID)
and never restates scope. The prior instruction **"execute prompts in numeric
order" is retired**; build order is the phases below, which honor the
dependency bands in `dependency-graph.md`.

---

## MVP cut line — first sellable slice

**Selection MVP + billing:**
`signup → publish program → applications → selection/scoring → Geidea billing`.

This proves the product's core differentiator (trustworthy, defensible
selection) and carries a revenue path. Everything outside it is sequenced
*after* the MVP, not dropped (see Deferred backlog).

---

## Phases

Each phase lists its build-spec IDs (defined in the register) and gates against
`release-gates.md`.

| Phase | Theme | Build-spec IDs | Entry → Exit |
|---|---|---|---|
| **0** | Foundation — identity, tenancy, RBAC | `00`–`04` | repo + mock IdP → fail-closed tenant isolation verified |
| **1** | Program config kernel — programs, cohorts, stages | `05`–`07` | rule kernel + versioning → published stages immutable, clone/templates |
| **2** | Cross-cutting substrates — transactional outbox + idempotency; entitlement-enforcement seam | new specs (infra, ref `26`/`27`/`58`/`60`) | substrates absent → all later writes idempotent + entitlement-gated |
| **3** | Selection MVP — forms, applications, assessments/scoring | `07`–`10` | kernel ready → applicant can apply, be scored, selected |
| **4** | Commercial plane — plans/entitlements/usage, subscriptions, Geidea billing | `58`–`62` | entitlement seam ready → tenant can subscribe + pay via Geidea |
| **5** | Sale-readiness — offboarding, secrets, data-residency, impersonation+audit | from gap docs | MVP feature-complete → first sale safe to onboard |

**Phase rule:** Phase 2 (substrates) must land before Phases 3–4 — outbox/
idempotency and the entitlement seam are load-bearing for every feature write
and every billing limit. Building features first re-incurs the retrofit cost the
scope review flagged.

---

## Deferred backlog (documented, not dropped)

Sequenced after the MVP. Each references its register entry.

| Deferred item | Build-spec / feature |
|---|---|
| Document management | `09` |
| Workflow engine (beyond MVP rules) | `11` |
| Role eligibility & assignments | `12` |
| Tasks & milestones | `13` |
| Mentorship | `14` |
| Training | `15` |
| Final evaluation | `16` |
| Graduation, alumni, follow-up | `17` |
| Notifications & communications | `18` |
| Calendar/meeting integrations | `19` |
| Reporting & dashboards | `20` |
| Search & directories | `21` |
| Localization & accessibility | `25` |
| UX experience layer | `34`–`41` |
| Extended capabilities | `42`–`57` (see register Extended scope) |
| Custom domains & branding | `66`–`67` |
| SaaS admin / billing UX / E2E | `63`, `65`, `68` |
| Federated SSO (real Startup Gate cutover) | `30` |
| Full DR targets beyond baseline | `../architecture/resilience-dr.md` |

---

## Dependency reference

Hard dependencies and parallelizable bands live in `dependency-graph.md` — this
roadmap does not restate them. Release increments and their exit criteria live in
`release-gates.md`.

## Where build progress lives

What is built vs. pending is tracked only in `../status/implementation-status.md`.
This roadmap states intent and order, not progress.
