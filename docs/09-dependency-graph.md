# Workstream Dependency Graph

## Hard Dependencies

```text
00 Repository Bootstrap
  ↓
01 Mock Startup Gate OIDC
  ↓
02 Identity, Profiles, Consent
  ↓
03 Organizations, Tenancy, RBAC
  ↓
04 Startups, Memberships, Delegation
  ↓
05 Programs, Cohorts, Templates
  ↓
06 Stage Engine
  ↓
07 Form Builder
  ↓
08 Application Management
  ↓
09 Document Management
  ↓
10 Assessment Engine
  ↓
11 Workflow Engine
  ↓
12 Role Eligibility and Assignments
  ↓
13 Tasks and Milestones
  ↓
14 Mentorship
  ↓
15 Training
  ↓
16 Final Evaluation
  ↓
17 Graduation, Alumni, Follow-Up
```

## Parallelizable After Core Foundation

After workstreams 00–06:

- 07 Form Builder
- 09 Document Management
- 13 Tasks and Milestones
- 18 Notifications and Communications
- 22 Administration and Configuration
- 25 Localization and Accessibility

After workstreams 08–12:

- 14 Mentorship
- 15 Training
- 19 Calendar and Meeting Integrations
- 21 Search and Directories

After workstreams 14–17:

- 20 Reporting and Dashboards
- 24 Audit and Compliance
- 27 Observability and Operations

## Merge Rule

Do not merge a dependent workstream before its required contracts exist and contract tests pass.
