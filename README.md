# Program Management Platform Documentation Pack

This repository documentation pack is designed for Claude Code to build a configurable incubation and acceleration program management platform.

The platform integrates with Startup Gate as the future identity and profile provider. During the initial implementation phase, Startup Gate OIDC and profile APIs are mocked.

## Primary Objectives

- Build a multi-tenant program management platform.
- Support configurable program stages and workflows.
- Support application, screening, mentorship, training, final evaluation, graduation, and post-graduation follow-up.
- Use Startup Gate as the future source of truth for users, general profiles, role profiles, and profile-sharing consent.
- Use a mock Startup Gate provider until the real integration is available.
- Maintain strict tenant isolation, auditability, versioning, and test coverage.

## Recommended Technology Stack

- Backend: Laravel modular monolith
- Frontend: React + TypeScript
- Database: PostgreSQL
- Cache and queues: Redis
- File storage: S3-compatible object storage
- Authentication: Mock Startup Gate OIDC for the initial phase
- API style: Versioned REST APIs
- Deployment: Docker
- CI/CD: GitHub Actions

## Documentation Order

Claude Code should read the files in this order:

1. `CLAUDE.md`
2. `docs/01-product-scope.md`
3. `docs/02-system-architecture.md`
4. `docs/03-domain-model.md`
5. `docs/04-data-model.md`
6. `docs/05-modules.md`
7. `docs/06-api-contracts.md`
8. `docs/07-workflow-engine.md`
9. `docs/08-form-builder.md`
10. `docs/09-assessment-engine.md`
11. `docs/10-startup-gate-mock.md`
12. `docs/11-security.md`
13. `docs/12-testing-strategy.md`
14. `docs/13-devops.md`
15. `docs/14-delivery-roadmap.md`
16. `docs/15-definition-of-done.md`

## Build Sequence

Do not build the entire system in one task.

Implement in phases:

1. Repository foundation
2. Tenant and identity foundation
3. Program and cohort management
4. Configurable stage engine
5. Form Builder
6. Application management
7. Assessment engine
8. Workflow engine
9. Mentorship
10. Training
11. Final evaluation and graduation
12. Reporting
13. Startup Gate real integration replacement

Each phase must include migrations, services, policies, API endpoints, tests, and documentation updates.
