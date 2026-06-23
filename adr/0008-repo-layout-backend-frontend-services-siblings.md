# ADR 0008: Repository Layout — `backend/` `frontend/` `services/` as Siblings

## Status

Accepted

## Context

The original docs pack implied a root-level layout (top-level `app/` and
`frontend/`), which conflates the Laravel application root with the repository
root and leaves no clean home for auxiliary services such as the Startup Gate
OIDC mock. With three distinct deliverables — the Laravel backend, the React
frontend, and supporting services — a flat root layout would force tooling,
path conventions, and CI to special-case each one.

This resolution is recorded in auto-memory `architecture-decisions.md` § 3
(2026-06-18).

## Decision

Lay out the repository as three sibling top-level directories:

- **`backend/`** — the Laravel application, with `app/Modules/<Module>/` inside
  it (the modular monolith of [ADR-0001](0001-modular-monolith.md)).
- **`frontend/`** — the React application.
- **`services/`** — supporting services, e.g. `services/startup-gate-mock/`
  (the mock OIDC + profile provider of
  [ADR-0003](0003-mocked-oidc-first.md)).

The repository root is **not** the Laravel application root; `app/Modules/`
lives under `backend/`, never at the repository top level.

## Alternatives Considered

- **Root-level Laravel app (`app/`, `frontend/` at the repo root).** Rejected.
  Conflates repo root with the Laravel app root; no clean home for `services/`;
  forces tooling and CI to special-case paths.
- **Separate repositories per deliverable (polyrepo).** Rejected. Premature for a
  small team; cross-cutting changes (a contract shared by backend and frontend)
  would span repos and lose atomic review, which the modular-monolith stance
  (ADR-0001) deliberately avoids.

## Consequences

- **Positive:** Each deliverable has an unambiguous home; CI, deptrac, and path
  conventions key off `backend/`, `frontend/`, `services/` without special cases.
- **Positive:** Adding a new auxiliary service is a new folder under `services/`,
  not a structural change.
- **Constraint:** Documentation and tooling must reference module paths as
  `backend/app/Modules/...`; a bare `app/Modules/...` (repo-root-relative) is
  incorrect.

## References

- Auto-memory `architecture-decisions.md` § 3 (2026-06-18)
- `docs/project-context.md` — § Repo layout
- ADR-0001 — modular monolith (`backend/app/Modules/`)
- ADR-0003 — `services/startup-gate-mock/`
- `docs/repository-audit.md` — F-005 (ADR coverage of auto-memory decisions)
