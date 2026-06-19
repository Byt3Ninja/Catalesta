# Integration Strategy

> Owner: Architecture · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

## Purpose

This document ensures independently implemented modules operate as one coherent platform.

## Integration Principles

1. Shared contracts are defined before module implementation.
2. Every module exposes explicit application-service interfaces.
3. Cross-module communication uses DTOs, domain events, and versioned APIs.
4. No module reads another module's internal tables directly unless explicitly documented.
5. Every milestone must deliver a working vertical slice.
6. Cross-module integration tests are mandatory before merge.
7. A module is incomplete until its upstream and downstream contracts are validated.

## Core Vertical Slices

### Slice 1: Login to Organization Access

```text
Mock Startup Gate Login
→ External User Projection
→ Organization Membership
→ RBAC
→ Tenant Context
→ Authorized Dashboard
```

### Slice 2: Program Creation to Publication

```text
Organization
→ Program
→ Cohort
→ Stage Definitions
→ Forms
→ Workflow
→ Publication
```

### Slice 3: Application to Admission

```text
Login
→ Profile Consent
→ Application Draft
→ Profile Snapshot
→ Document Upload
→ Eligibility
→ Assessment
→ Workflow Decision
→ Admission
```

### Slice 4: Admission to Graduation

```text
Participant Created
→ Mentorship
→ Training
→ Tasks and Milestones
→ Final Evaluation
→ Graduation
→ Certificate
→ Achievement Publication
```

### Slice 5: Operations and Reporting

```text
Program Activities
→ Audit Events
→ Notifications
→ Search Index
→ Dashboards
→ Exports
→ Monitoring
```

## Required Cross-Module Contracts

- IdentityProvider
- ProfileProvider
- ConsentProvider
- StartupMembershipProvider
- AchievementPublisher
- TenantContext
- AuthorizationService
- ProgramRepository
- StageProgressionService
- FormSchemaProvider
- ApplicationSnapshotService
- DocumentAccessService
- AssessmentResultProvider
- WorkflowCommandBus
- RoleEligibilityService
- TaskCompletionProvider
- MentorshipOutcomeProvider
- TrainingOutcomeProvider
- GraduationDecisionService
- NotificationDispatcher
- AuditRecorder

## Integration Failure Policy

- External failures must be retried or queued.
- Internal domain failures must be explicit and transactional.
- Partial writes must be rolled back.
- Events must use outbox delivery.
- Duplicate commands must be idempotent.
- Missing downstream consumers must not silently discard events.

## UX Integration Slice

```text
Role Context
→ Role Navigation
→ Action Center
→ Guided Task
→ Validation
→ Completion Feedback
→ Analytics Event
```

UX prompts 34–41 must be completed before frontend production acceptance.
