# Domain Boundaries

> Owner: Architecture · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

Identity, Profiles, Organizations, Startups, Programs, Cohorts, Stages, Forms, Applications, Documents, Assessments, Workflows, Role Assignments, Tasks, Mentorship, Training, Final Evaluation, Graduation, Notifications, Integrations, Reporting, Search, Administration, Audit.

## Phase 2 Implementation Status

The following modules shipped in Phase 2 and are fully implemented:

| Module | Location | Status |
|---|---|---|
| Programs | `app/Modules/Programs` | Implemented |
| Cohorts | `app/Modules/Cohorts` | Implemented |
| Stages | `app/Modules/Stages` | Implemented |

**Shared kernels** used by the above modules (also implemented):

- `app/Shared/Rules` — `Operator` enum, `FieldResolver` interface + `FieldResolverRegistry`, `ExpressionValidator`, `ExpressionEvaluator` (decimal-safe via `brick/math`).
- `app/Shared/Versioning` — `VersionStatus`, `Versionable`, `ImmutableWhenPublished` trait, `VersionPublisher`.

All Phase 2 tenant-owned tables are enumerated in `data-ownership.md`. Tenant isolation is fail-closed via `BelongsToTenant`; see `tenancy-isolation.md`.
