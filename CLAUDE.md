# Claude Code Repository Instructions

## Mission

Build a production-grade multi-tenant platform for configurable incubation, acceleration, mentorship, training, evaluation, graduation, and post-graduation programs.

## Non-Negotiable Rules

1. Use a Laravel modular monolith.
2. Startup Gate owns global identity, general profiles, role profiles, startup memberships, consent, verification, shared directories, and achievements.
3. The program platform owns organizations, programs, cohorts, stages, applications, evaluations, assignments, mentorship, training, graduation, and reporting.
4. Use Startup Gate `sub` as the immutable user identifier.
5. Never use email as the cross-system identifier.
6. Every tenant-owned record must include `organization_id`.
7. Every tenant query must enforce tenant isolation.
8. Published forms, workflows, assessments, and stages are immutable and versioned.
9. Use decimal arithmetic for scoring.
10. No arbitrary code execution in rules.
11. All profile access must be consent-aware.
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
