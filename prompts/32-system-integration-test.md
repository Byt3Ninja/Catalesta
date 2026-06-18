# Claude Task: Full System Integration Test

## Goal

Validate the complete platform as one system.

## Required Scenarios

1. Founder logs in through mock Startup Gate.
2. Founder grants profile consent.
3. Organization creates a program and cohort.
4. Program manager configures stages, forms, documents, assessments, and workflow.
5. Founder submits an application.
6. System captures a profile snapshot.
7. Eligibility rules execute.
8. Evaluators are assigned.
9. Assessment completes.
10. Workflow accepts the application.
11. Participant is admitted.
12. Mentor is matched and assigned.
13. Mentorship sessions complete.
14. Training completes.
15. Tasks and milestones complete.
16. Final evaluation completes.
17. Graduation is approved.
18. Certificate is issued.
19. Achievement is published to Startup Gate mock.
20. Reports and audit records are generated.

## Acceptance Criteria

- All scenarios pass in CI.
- No cross-tenant leakage occurs.
- All external calls are idempotent.
- All critical actions are auditable.
- All failures have deterministic recovery behavior.
