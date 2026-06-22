# Claude Code Repository Instructions

## Mission

Build Catalesta as a production-grade Laravel modular monolith for configurable,
multi-tenant incubation, acceleration, mentorship, training, evaluation,
graduation, and post-graduation programs.

The platform must remain operational without Startup Gate or any optional
external integration.

## Instruction Authority

Apply instructions in this order:

1. Explicit instructions for the current task
2. Approved BMAD story and acceptance criteria
3. Approved Architecture Decision Records
4. This `CLAUDE.md`
5. `docs/project-context.md`
6. Approved BMAD product, UX, architecture, and release documents
7. Existing verified application behaviour
8. Assumptions

Do not silently resolve material contradictions. Record the contradiction, mark
the affected work blocked, and continue only unaffected work.

Source code, database constraints, configuration, and tests define current
behaviour. Approved BMAD artifacts define intended behaviour.

## Methodology Boundary

BMAD owns product scope, business rules, requirements, architecture, UX, epics,
stories, acceptance criteria, traceability, and release scope.

Superpowers owns story-level planning, worktree isolation, TDD, systematic
debugging, incremental implementation, refactoring, reviews, and verification.

Invoke applicable Superpowers skills through Claude Code's Skill mechanism.
Never read installed skill files manually as a substitute for invoking a skill.

Do not use implementation work to redefine approved product scope. Reopen an
approved story only when it is contradictory, incomplete, unsafe, or technically
impossible.

## Architecture Ownership

- Use a Laravel modular monolith.
- Catalesta owns local accounts, identity links, general profiles, role profiles,
  memberships, consent records, and profile provenance.
- Startup Gate is an optional OIDC provider and consent-based profile-import
  source. It is never Catalesta's system of record.
- The program platform owns organizations, programs, cohorts, stages,
  applications, evaluations, role assignments, mentorship, training,
  graduation, workflows, and reporting.
- Keep integrations behind interfaces.
- Preserve module boundaries. Do not bypass them through uncontrolled direct
  database access.

## Identity Invariants

- The primary user identifier is the local `Account` ULID.
- Email is a local login credential and verified contact attribute only.
- Never use email as a cross-system, cross-tenant, ownership, or linkage key.
- Identify external identities by issuer and immutable subject.
- A Startup Gate `sub` is unique per issuer, never reassigned, and never a local
  primary key.
- Local registration and authentication must work independently of Startup Gate.
- Linking and unlinking external identities require authenticated confirmation
  and audit records.

## Tenant Invariants

- Every tenant-owned aggregate must have an enforceable organization boundary.
- Use direct `organization_id` where records are independently queried,
  authorized, exported, audited, or exposed to background processing.
- Every tenant query and mutation must enforce tenant isolation.
- Never trust a client-supplied organization identifier without server-side
  membership and authorization validation.
- Cross-tenant access is denied by default.
- Background jobs must restore and validate tenant context.
- Unknown tenant hosts must be rejected. Never select a fallback tenant.

## Authorization and Privacy

- Enforce authorization server-side. Frontend visibility is not authorization.
- Deny access by default.
- Validate membership, status, role, permission, ownership, and tenant context.
- Locally owned profile access is governed by authorization, tenant membership,
  purpose, privacy settings, and data minimization.
- Explicit consent is required for Startup Gate import, external sharing,
  optional disclosure, and processing beyond the declared purpose.
- Imported profile values are local editable copies and must never automatically
  overwrite locally modified fields.

## Versioning and Historical Integrity

Published forms, form schemas, workflows, assessments, scoring models, stages,
formal evaluation templates, and plan definitions are immutable and versioned.

Formal submissions and executions must retain immutable snapshots and exact
version references. Historical results must remain reproducible.

Use decimal arithmetic for authoritative scoring. Define precision, scale,
rounding, weight normalization, and missing-value behaviour.

Do not allow arbitrary code execution in rules, workflows, branding, or
templates.

## SaaS, Billing, Domains, and Branding

- Domain modules use `EntitlementService`; never check plan names.
- Enforce usage limits server-side without hiding or deleting existing data.
- Keep Geidea behind payment-provider interfaces.
- Browser payment returns are not authoritative.
- Verify and idempotently process server callbacks.
- Never store raw card numbers or CVV.
- Custom domains require ownership verification and active TLS.
- Branding allows controlled tokens and validated assets only, never arbitrary
  CSS, JavaScript, or executable HTML.

## Required Modules

Identity, Organizations, Profiles, Startups, Programs, Cohorts, Stages, Forms,
Applications, Documents, Assessments, Workflows, RoleAssignments, Tasks,
Mentorship, Training, FinalEvaluation, Graduation, Notifications, Integrations,
Reporting, Search, Administration, Audit, Billing, Entitlements, TenantDomains,
and Branding.

New or overlapping modules require an approved architecture decision.

## Work Classification

- Product or architecture change: use BMAD; implement only after readiness.
- Approved story: use Superpowers execution without changing approved scope.
- Defect: use systematic debugging, prove root cause, and add a failing
  regression test before the fix.
- Refactoring: preserve behaviour and add characterization tests first.
- Documentation-only work: verify against source, tests, constraints, and
  approved architecture.

## Task Protocol

Before changes:

1. Read the assigned story, `docs/project-context.md`, relevant ADRs, and
   architecture documents.
2. Inspect the implementation and existing tests.
3. Read `graphify-out/GRAPH_REPORT.md` before broad architecture, dependency,
   impact, module-discovery, or repository-wide analysis when it exists and is
   current.
4. Perform blast-radius analysis.
5. Report the plan, files, schema/API impact, assumptions, security and tenant
   risks, migration impact, rollback concerns, and acceptance-criteria-to-test
   mapping.
6. Do not implement a materially incomplete or non-ready story.

After changes, run repository-defined formatting, linting, static analysis,
unit, feature, authorization, tenant-isolation, contract, frontend, build,
security, migration, and rollback checks as applicable.

Do not invent commands when repository scripts or CI definitions exist.

## Completion Standard

Never claim complete, fixed, secure, production-ready, or fully tested from code
inspection alone.

Every completion report must include:

- Story or issue identifier
- Acceptance-criteria matrix
- Files and migrations changed
- API and UI impact
- Tests added or changed
- Exact commands executed and actual results
- Authorization, tenant-isolation, and security validation
- Migration and rollback results
- Documentation updates
- Known limitations and remaining risks

Use `Not verified` for checks that were not executed.
