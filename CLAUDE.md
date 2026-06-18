# Claude Code Project Instructions

## Mission

Build a production-grade multi-tenant platform for managing incubation, acceleration, mentorship, training, evaluation, and graduation programs.

Startup Gate will become the identity provider and profile owner. During the first implementation phase, use a local mock OIDC provider and mock profile APIs with the same planned contracts.

## Non-Negotiable Rules

1. Use a modular monolith.
2. Do not create microservices unless explicitly approved.
3. Startup Gate owns global identity and reusable profile data.
4. The program platform owns program-specific operational records.
5. Use immutable external subject identifiers. Never use email as the primary cross-system identifier.
6. Every tenant-owned record must include `organization_id`.
7. Every tenant-scoped query must enforce tenant isolation.
8. Published forms, workflows, and assessments are immutable and versioned.
9. Use decimal arithmetic for weighted scoring.
10. Do not allow arbitrary PHP, SQL, JavaScript, or shell execution in rule definitions.
11. Every external event must be signed, versioned, auditable, and idempotent.
12. Every public API must be versioned.
13. Every feature must include tests.
14. Do not place business logic in controllers.
15. Do not duplicate Startup Gate profile data beyond an approved local projection or immutable application snapshot.
16. All profile sharing must be consent-aware.
17. All sensitive actions must be server-authorized.
18. Do not commit secrets.
19. Update documentation when behavior or contracts change.
20. Stop and report conflicts between requirements instead of silently choosing an unsafe implementation.

## Required Project Structure

```text
app/
  Modules/
    Identity/
    Organizations/
    Profiles/
    Startups/
    Programs/
    Cohorts/
    Stages/
    Forms/
    Applications/
    Documents/
    Assessments/
    Workflows/
    RoleAssignments/
    Mentorship/
    Training/
    Tasks/
    Graduation/
    Reporting/
    Integrations/
    Audit/

frontend/
  src/
    app/
    features/
    components/
    hooks/
    api/
    schemas/
    pages/
    tests/

docs/
tests/
```

## Module Structure

Each backend module should follow:

```text
Module/
  Domain/
  Application/
  Infrastructure/
  Http/
  Policies/
  Events/
  Jobs/
  Tests/
```

## Coding Standards

- PHP strict types.
- TypeScript strict mode.
- Explicit DTOs for application boundaries.
- Services must be small and single-purpose.
- Domain events should describe completed business facts.
- Repository interfaces only where they improve testability or isolate infrastructure.
- Use database transactions around multi-record business operations.
- Use idempotency keys for externally retried commands.
- Use queues for slow or retryable operations.
- Use policies and permissions for authorization.
- Use UUID or ULID identifiers consistently.
- Use UTC in storage.
- Use ISO 8601 in APIs.

## Pull Request Requirements

Every change must include:

- Scope summary.
- Architectural impact.
- Database migration impact.
- Security impact.
- API changes.
- Tests added.
- Rollback considerations.
- Documentation changes.

## Claude Task Format

Every implementation task should contain:

```text
Goal
Context
Inputs
Outputs
Constraints
Acceptance Criteria
Tests
Files Allowed to Change
Files Not Allowed to Change
```

## Mock Identity Rule

Until Startup Gate OIDC is ready:

- Implement a mock OIDC provider.
- Use the same issuer, subject, claims, scopes, consent, and profile API contracts planned for production.
- Keep authentication behind interfaces.
- Do not spread mock-specific logic through domain modules.
- The production integration must be replaceable by configuration and adapter changes only.


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


