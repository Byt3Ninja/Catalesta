# Shared Contracts

## Contract Versioning

All shared DTOs and events must include a version.

Example:

```json
{
  "type": "ApplicationSubmitted",
  "version": 1,
  "event_id": "evt_123",
  "organization_id": "org_1",
  "correlation_id": "corr_1",
  "occurred_at": "2026-06-18T12:00:00Z",
  "payload": {}
}
```

## Shared DTO Rules

- Immutable DTOs
- Explicit validation
- No ORM models crossing module boundaries
- No hidden lazy loading
- No unversioned external payloads
- No direct frontend dependency on database schemas

## Required Event Catalog

- UserAuthenticated
- ProfileConsentGranted
- ProfileConsentRevoked
- StartupMembershipUpdated
- ProgramPublished
- CohortOpened
- ApplicationSubmitted
- EligibilityCompleted
- AssessmentCompleted
- ApplicationAccepted
- ParticipantAdmitted
- StageCompleted
- MentorAssigned
- MentorshipCompleted
- TrainingCompleted
- FinalEvaluationCompleted
- ParticipantGraduated
- CertificateIssued
- AchievementPublished

## Contract Test Rule

Every producer must publish fixtures.

Every consumer must validate against those fixtures in CI.
