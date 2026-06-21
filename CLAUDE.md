# Claude Code Repository Instructions

## Mission

Build a production-grade multi-tenant platform for configurable incubation, acceleration, mentorship, training, evaluation, graduation, and post-graduation programs.

## Non-Negotiable Rules

1. Use a Laravel modular monolith.
2. Catalesta owns global identity, accounts, general and role profiles, memberships, and consent as system of record. Startup Gate is an optional external identity provider (SSO) and a consented profile-import source — never the system of record.
3. The program platform owns organizations, programs, cohorts, stages, applications, evaluations, assignments, mentorship, training, graduation, and reporting.
4. The primary user identifier is the local Account id (ULID). A Startup Gate `sub`, when an account is linked, is the immutable identifier of that linked external identity — unique, never reassigned, never the primary key.
5. Email is a local login credential only. Never use email as a cross-system, cross-tenant, or external-linkage identifier; use the Account id locally and `sub` for Startup Gate linkage.
6. Every tenant-owned record must include `organization_id`.
7. Every tenant query must enforce tenant isolation.
8. Published forms, workflows, assessments, and stages are immutable and versioned.
9. Use decimal arithmetic for scoring.
10. No arbitrary code execution in rules.
11. All profile access must be consent-aware, including locally-owned profiles. Importing any field from Startup Gate requires explicit, field-level consent; imported data is a local editable copy and must never auto-overwrite locally modified fields.
12. Formal submissions must capture immutable snapshots.
13. Keep external integrations behind interfaces.
14. Every feature requires tests and documentation updates.
15. Do not commit secrets.

## Required Modules

Identity, Organizations, Profiles, Startups, Programs, Cohorts, Stages, Forms, Applications, Documents, Assessments, Workflows, RoleAssignments, Tasks, Mentorship, Training, FinalEvaluation, Graduation, Notifications, Integrations, Reporting, Search, Administration, Audit.

## Task Protocol

Before coding: summarize the plan, files, schema changes, assumptions, security risks, and rollback concerns.

After coding: run linting, static analysis, unit tests, feature tests, authorization tests, tenant-isolation tests, contract tests, frontend checks, and update documentation.

## SaaS, Payment, and Tenant Domain Rules

1. Plans are versioned and immutable after publication.
2. Domain modules must use `EntitlementService`; never check plan names.
3. Usage limits are enforced server-side.
4. Reaching limits must not hide or delete existing tenant data.
5. Geidea integration must remain behind payment-provider interfaces.
6. Browser payment returns are not authoritative.
7. Geidea callbacks must be verified and processed idempotently.
8. Never store raw card numbers or CVV.
9. Custom domains require ownership verification and active TLS.
10. Tenant resolution from host names must reject unknown hosts.
11. Branding permits controlled tokens and assets only, not arbitrary CSS or scripts.

## Graphify Knowledge Graph

This project uses Graphify for codebase and architecture navigation.

### Required workflow

Before architecture analysis, dependency analysis, impact analysis, large
refactoring, module discovery, or codebase-wide searching:

1. Check whether `graphify-out/GRAPH_REPORT.md` exists.
2. Read `graphify-out/GRAPH_REPORT.md` before using broad Glob, Grep, rg,
   find, or repository-wide file searches.
3. Use Graphify queries to identify relevant:
   - modules
   - files
   - symbols
   - dependencies
   - communities
   - highly connected nodes
4. Inspect the actual source files before making conclusions or changes.
5. Treat source code, database constraints, configuration, and tests as the
   authoritative sources when they conflict with the generated graph.
6. Perform blast-radius analysis before modifying shared services, models,
   middleware, authorization logic, schemas, or public APIs.
7. Regenerate the graph after substantial architectural or structural changes.
