# ADR 0006: "Cohort" Canonical Naming and `program_cycles` → `cohorts` Rename

## Status

Accepted

## Context

The original docs pack carried two competing names for the same concept — a
time-boxed run of a program through which a group of participants moves
together. The domain model, modules, API, and roadmap used **Cohort**, while an
early data-model draft named the table `program_cycles`. A single concept with
two names in the canonical docs biases implementation: schema, route names, and
module folders would diverge depending on which doc an implementer read first.

This was one of the doc-contradiction resolutions recorded in auto-memory
`architecture-decisions.md` § 1 (2026-06-18).

## Decision

Use **"Cohort"** as the canonical term everywhere — domain model, modules, API,
UI, and documentation. Rename the data-model table `program_cycles` → `cohorts`
to match.

- The aggregate, its module (`app/Modules/Cohorts/`), its table (`cohorts`), and
  its API resources all use the `cohort` term.
- `program_cycles` is retired; no canonical doc, schema, or route may reintroduce
  it.

## Alternatives Considered

- **Keep `program_cycles` for the table, "Cohort" for the domain term.**
  Rejected. A name split between schema and domain language is exactly the
  ambiguity this decision removes; every join and migration would carry the
  mismatch.
- **Standardize on `program_cycles`.** Rejected. The domain model, modules, API,
  and roadmap already used "Cohort"; renaming those to `program_cycles` would
  touch far more surface and contradicts the ubiquitous-language intent.

## Consequences

- **Positive:** One name across schema, code, API, and docs; no translation layer
  between domain language and storage.
- **Status:** Already implemented. The `cohorts` table exists
  (`backend/database/migrations/2026_06_18_002400_create_cohorts_table.php`); the
  existing API uses `cohorts`. This ADR records the decision that the
  implementation already reflects.
- **Negative (minor):** Any future external reference to a "program cycle" must be
  read as a synonym for cohort and corrected on sight.

## References

- Auto-memory `architecture-decisions.md` § 1 (2026-06-18)
- `docs/product/scope-register.md` — Cohorts module
- `backend/database/migrations/2026_06_18_002400_create_cohorts_table.php`
- `docs/repository-audit.md` — F-005 (this ADR surfaces an auto-memory-only
  decision as a citable ADR)
