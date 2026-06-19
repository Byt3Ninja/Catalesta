# Catalesta Documentation Map

> Owner: Platform · Last-updated: 2026-06-19 · Source-of-truth: this file (for navigation)

## Purpose

This is the documentation map. **Two files are authoritative:**

- **[`product/scope-register.md`](product/scope-register.md)** defines *what* we build.
- **[`plan/roadmap.md`](plan/roadmap.md)** decides *when and in what order*.

Every other doc references these two and restates **neither scope nor sequence**.

## Folder semantics

| Folder | Owns | Question |
|---|---|---|
| `product/` | functional scope, brief, lifecycle, feature definitions | **WHAT** (intent) |
| `architecture/` | technical decisions, boundaries, data ownership, security, resilience | **HOW** |
| `saas/` | commercial plane: plans, entitlements, billing, domains, branding | **HOW (commercial)** |
| `ux/` | experience layer: strategy, design system, navigation, flows | **HOW (experience)** |
| `quality/` | testing strategy, integration testing | **VERIFICATION** |
| `plan/` | roadmap, dependency graph, release gates, build specs, engineering phases | **WHEN / order** |
| `status/` | as-built implementation status, bootstrap, engineering notes | **AS-BUILT** |

## Conventions

- **No global filename numbers.** Semantic folders + descriptive kebab-case names. The only allowed numbering is the build-spec IDs `00`–`68` under `plan/build-specs/`.
- **Canonical module count: 24** (the `CLAUDE.md` "Required Modules" list). Stated identically everywhere.
- **Canonical numbering: build-spec IDs `00`–`68`.** The register, dependency-graph, and brief reference these IDs; no competing numbered list exists.
- Every doc starts with a header block: `Owner · Last-updated · Source-of-truth: <link>`.

## Update rules

1. Changing scope → edit **`product/scope-register.md`** first; downstream docs reference it.
2. Changing build order → edit **`plan/roadmap.md`** first.
3. **Never** put implementation status in scope/plan docs — status lives only in `status/`.
4. Adding a doc → add a row to the **Doc map** below.

## Doc map

### product/ — WHAT (intent)
| Path | Purpose |
|---|---|
| `product/scope-register.md` | ★ **AUTHORITATIVE — scope.** 24 modules, full surface, build-spec IDs |
| `product/product-brief.md` | positioning / PR-FAQ; references the register |
| `product/lifecycle.md` | core participant lifecycle |
| `product/data-residency-retention.md` | residency decision + retention values *(proposed)* |
| `product/features/*` | one file per extended capability (tracks, marketplace, surveys, outcomes, risk, simulation, bulk, formal docs, support, interviews, ops/finance, collaboration, trusted publication) |

### architecture/ — HOW
| Path | Purpose |
|---|---|
| `architecture/overview.md` | architecture overview |
| `architecture/domain-boundaries.md` | Startup Gate ↔ Program Platform boundaries |
| `architecture/data-ownership.md` | who owns which data / tables |
| `architecture/data-privacy-rights.md` | DSR rights + data lifecycle |
| `architecture/security-baseline.md` | security baseline + secrets rotation |
| `architecture/tenancy-isolation.md` | fail-closed tenant isolation |
| `architecture/shared-contracts.md` | shared module contracts |
| `architecture/integration-strategy.md` | external integration strategy |
| `architecture/devops-observability.md` | devops + observability/ops |
| `architecture/resilience-dr.md` | resilience, DR targets, tenant offboarding *(proposed)* |
| `architecture/admin-impersonation-audit.md` | staff impersonation + audit *(proposed)* |

### saas/ — commercial plane
| Path | Purpose |
|---|---|
| `saas/commercial-architecture.md` | SaaS commercial architecture / admin |
| `saas/plans-entitlements-usage.md` | plans, entitlements, usage metering |
| `saas/subscriptions-billing.md` | subscription lifecycle (incl. `restricted` status) |
| `saas/geidea-payments.md` | Geidea billing + HPP |
| `saas/domains-branding.md` | subdomains, custom domains, branding, email deliverability |
| `saas/white-label-levels.md` | branding/white-label tiers *(proposed)* |
| `saas/security-testing.md` | SaaS-plane security testing |

### ux/ — experience
| Path | Purpose |
|---|---|
| `ux/strategy.md` · `ux/design-system.md` · `ux/navigation.md` · `ux/onboarding.md` · `ux/forms-application.md` · `ux/dashboard.md` · `ux/responsive-mobile.md` · `ux/usability-analytics.md` · `ux/saas-billing-ux.md` | experience layer docs |

### quality/ — verification
| Path | Purpose |
|---|---|
| `quality/testing-strategy.md` · `quality/integration-testing.md` | testing strategy + integration testing |

### plan/ — WHEN / order
| Path | Purpose |
|---|---|
| `plan/roadmap.md` | ★ **AUTHORITATIVE — sequence.** Phases, MVP cut line, deferred backlog |
| `plan/dependency-graph.md` | hard dependencies + parallel bands |
| `plan/release-gates.md` | release increments + exit criteria |
| `plan/build-specs/` | the 69 build specs (`00`–`68`); index in its `README.md` |
| `plan/phases/` | engineering plans + specs (historical/active phase work) |

### status/ — AS-BUILT
| Path | Purpose |
|---|---|
| `status/implementation-status.md` | the only place build status is tracked |
| `status/phase-2-notes.md` | Programs/Cohorts/Stages internals |
| `status/bootstrap.md` | Phase 0 repository foundation |

> Docs marked *(proposed)* contain default decisions pending owner ratification.
