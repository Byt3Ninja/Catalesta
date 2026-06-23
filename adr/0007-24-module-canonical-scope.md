# ADR 0007: 24 Modules as Canonical Scope Source of Truth

## Status

Accepted

## Context

The module count was inconsistent across the docs pack: an earlier
`docs/05-modules.md` implied roughly 20 modules, while the CLAUDE.md "Required
Modules" list enumerates 24. The scope register, roadmap, and architecture all
need one authoritative count, because "is module X in scope?" drives epic
placement, the roadmap, and whether a thin spec must be written.

Confounding the count is an as-built fact: only 20 module folders are currently
scaffolded — FinalEvaluation, Notifications, Search, and Administration are not
yet present. Conflating *intended scope* (24) with *current as-built state* (20)
is what produced the contradiction.

This resolution is recorded in auto-memory `architecture-decisions.md` § 2
(confirmed 2026-06-19).

## Decision

The canonical module count is **24** — the CLAUDE.md "Required Modules" list —
and it is the single source of truth for the scope register, superseding the
earlier "20" figure.

- The 24 are the **intended scope**. `docs/product/scope-register.md` lists all
  24.
- The fact that only 20 folders are scaffolded (missing: FinalEvaluation,
  Notifications, Search, Administration) is an **as-built / status fact**, tracked
  in `docs/status/`, **not** in the scope register.
- The four modules absent from the old `docs/05-modules.md` get thin specs
  derived from the data model and the non-negotiable rules.
- New or overlapping modules beyond the 24 require an approved architecture
  decision (CLAUDE.md § Required Modules).

## Alternatives Considered

- **Treat 20 (the scaffolded folders) as canonical scope.** Rejected. Conflates
  as-built state with intended scope; would silently drop four required modules
  from the register.
- **Leave the count ambiguous and resolve per-module as needed.** Rejected. Epic
  placement and the roadmap need a fixed denominator; ambiguity re-litigates the
  same question on every phase boundary.

## Consequences

- **Positive:** Scope register and CLAUDE.md agree on 24; "in scope?" has one
  answer. Scope (24, intended) and status (20, as-built) are cleanly separated
  into the register vs `docs/status/`.
- **Constraint:** The four absent modules need explicit roadmap phase placement
  (tracked by Epic 0 Story 0.7) before their phase-affected stories enter Ready.
- **Negative (minor):** Reviewers must remember that a module being in the
  register does not imply it is scaffolded; the status doc is the as-built
  authority.

## References

- Auto-memory `architecture-decisions.md` § 2 (2026-06-19)
- CLAUDE.md — § Required Modules
- `docs/product/scope-register.md` — 24-module register
- `docs/status/` — as-built module status (20 scaffolded)
- `docs/repository-audit.md` — F-007 (4 absent modules), F-005 (ADR coverage)
