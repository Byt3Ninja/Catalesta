# System Architecture

## High-Level Architecture

```text
Startup Gate Mock OIDC and Profile API
            |
            | OIDC / OAuth / Profile API
            v
Program Management Platform
  - Laravel API
  - React Web Application
  - PostgreSQL
  - Redis
  - S3-Compatible Storage
  - Queue Workers
  - Scheduler
```

## System Boundaries

### Startup Gate

Future responsibilities:

- Authentication
- General profile ownership
- Role profile ownership
- Startup membership
- Consent
- Verification
- Shared directories
- Achievement registry

### Program Platform

Responsibilities:

- Organizations
- Programs
- Cohorts
- Stages
- Applications
- Evaluations
- Mentorship
- Training
- Tasks
- Graduation
- Program-specific assignments
- Reporting

## Backend Architecture

Use a Laravel modular monolith.

Each module owns:

- Domain rules
- Application services
- Database access
- API handlers
- Authorization policies
- Events
- Jobs
- Tests

Cross-module communication should use:

- Application service contracts
- Domain events
- Explicit DTOs

Avoid direct access to another module's internal tables unless the relationship is deliberately documented.

## Frontend Architecture

Use React and TypeScript.

Recommended layers:

```text
src/
  app/
  features/
  components/
  hooks/
  api/
  schemas/
  pages/
  tests/
```

Use:

- React Query for server state
- React Hook Form for form handling
- Zod for client validation
- Component library with Storybook
- Playwright for end-to-end testing

## Multi-Tenancy

Use:

- Shared PostgreSQL database
- Shared schema
- `organization_id` on tenant-owned records
- Tenant middleware
- Tenant-aware policies
- Tenant-aware queries
- Composite constraints
- Optional PostgreSQL RLS as defense in depth

## Eventing

Use database transactions plus a transactional outbox.

Internal events:

- ApplicationSubmitted
- ApplicationAccepted
- StageCompleted
- MentorAssigned
- TrainingCompleted
- ParticipantGraduated

External events:

- ProfileUpdated
- ConsentRevoked
- RoleProfileApproved
- AchievementPublished

## Storage

- PostgreSQL for structured data
- JSONB for limited configurable definitions
- Redis for cache, queues, locks, and sessions
- S3-compatible object storage for uploaded documents
