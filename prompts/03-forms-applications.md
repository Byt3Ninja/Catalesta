# Claude Task: Implement Form Builder and Applications

## Goal

Implement versioned forms, conditional fields, Startup Gate profile mappings, applications, profile snapshots, and eligibility checks.

## Inputs

- `docs/08-form-builder.md`
- `docs/10-startup-gate-mock.md`
- `docs/06-api-contracts.md`

## Outputs

- Form Builder backend
- Form renderer frontend
- Application lifecycle
- Profile snapshot service
- Eligibility service
- Tests

## Acceptance Criteria

- Form can be configured without code changes
- Published forms are immutable
- User can save a draft
- Submission captures an immutable profile snapshot
- Eligibility rules run deterministically
- Consent is checked before profile data is loaded
