# Data Ownership

> Owner: Architecture · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

| Data | Owner |
|---|---|
| User identity | Startup Gate |
| General profile | Startup Gate |
| Role profiles | Startup Gate |
| Startup memberships | Startup Gate |
| Consent | Startup Gate |
| Program configuration | Program Platform |
| Applications | Program Platform |
| Evaluations | Program Platform |
| Program assignments | Program Platform |
| Graduation | Program Platform |
| Public achievements | Startup Gate after trusted publication |

Formal applications store immutable profile snapshots.

## Phase 2 Implementation Status (Programs / Cohorts / Stages)

The following tables are **implemented and tenant-owned** (all carry `organization_id`; all models use `BelongsToTenant`). They belong to the **Program Platform** category above.

| Table | Model | Module |
|---|---|---|
| `programs` | `Program` | Programs |
| `program_policies` | `ProgramPolicyRecord` | Programs |
| `program_role_requirements` | `ProgramRoleRequirement` | Programs |
| `program_templates` | `ProgramTemplate` | Programs |
| `cohorts` | `Cohort` | Cohorts |
| `program_stages` | `ProgramStage` | Stages |
| `stage_versions` | `StageVersion` | Stages |
| `stage_rules` | `StageRule` | Stages |
| `stage_transitions` | `StageTransition` | Stages |
| `participant_stage_statuses` | `ParticipantStageStatus` | Stages |
| `stage_instances` | `StageInstance` | Stages |

Every query against these tables is automatically scoped by `organization_id` via the fail-closed `BelongsToTenant` global scope (see `tenancy-isolation.md`). Composite unique constraints are scoped by organization-local columns, not global ids. `organization_id` is never mass-assignable — it is server-set by `TenantContext::organizationId()` on the `creating` hook.
