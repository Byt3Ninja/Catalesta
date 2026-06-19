# Integration Testing

> Owner: Quality · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

## Required Suites

### Suite A: Identity and Tenancy

- Login through mock OIDC
- External subject mapping
- Organization selection
- Tenant context
- Permission enforcement

### Suite B: Application Lifecycle

- Profile consent
- Application form rendering
- Snapshot capture
- Document upload
- Eligibility execution
- Assessment assignment
- Workflow decision
- Admission

### Suite C: Program Delivery

- Stage entry
- Mentor assignment
- Session completion
- Training enrollment
- Attendance
- Assignment completion
- Milestone completion
- Stage transition

### Suite D: Graduation

- Final evaluation
- Committee decision
- Graduation rule evaluation
- Certificate generation
- Alumni creation
- Achievement publication

### Suite E: Cross-Cutting

- Audit trail
- Notifications
- Search indexing
- Reporting
- Webhook idempotency
- Failure retries
- Tenant isolation

## CI Integration Gate

A pull request cannot merge when:

- shared contract tests fail
- migration tests fail
- any vertical-slice smoke test fails
- tenant-isolation tests fail
- authorization tests fail
- event consumer compatibility fails
