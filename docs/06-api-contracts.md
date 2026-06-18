# API Contracts

## API Conventions

- Prefix all endpoints with `/api/v1`
- Use JSON
- Use ISO 8601 timestamps
- Use cursor or page pagination
- Return standard error objects
- Use idempotency keys for retryable commands

## Standard Error Format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The request is invalid.",
    "details": {
      "field": ["Reason"]
    },
    "correlation_id": "corr_123"
  }
}
```

## Identity

```text
GET    /api/v1/auth/session
POST   /api/v1/auth/logout
GET    /api/v1/me
GET    /api/v1/me/profile
GET    /api/v1/me/role-profiles
GET    /api/v1/me/startups
```

## Organizations

```text
GET    /api/v1/organizations
POST   /api/v1/organizations
GET    /api/v1/organizations/{id}
PATCH  /api/v1/organizations/{id}
```

## Programs

```text
GET    /api/v1/programs
POST   /api/v1/programs
GET    /api/v1/programs/{id}
PATCH  /api/v1/programs/{id}
POST   /api/v1/programs/{id}/publish
POST   /api/v1/programs/{id}/clone
```

## Cohorts

```text
POST   /api/v1/programs/{program}/cohorts
GET    /api/v1/cohorts/{id}
PATCH  /api/v1/cohorts/{id}
```

## Stages

```text
GET    /api/v1/programs/{program}/stages
POST   /api/v1/programs/{program}/stages
PATCH  /api/v1/stages/{id}
POST   /api/v1/stages/{id}/publish
POST   /api/v1/stages/reorder
```

## Forms

```text
POST   /api/v1/forms
GET    /api/v1/forms/{id}
PATCH  /api/v1/forms/{id}
POST   /api/v1/forms/{id}/publish
POST   /api/v1/forms/{id}/versions
```

## Applications

```text
POST   /api/v1/programs/{program}/applications
GET    /api/v1/applications/{id}
PATCH  /api/v1/applications/{id}
POST   /api/v1/applications/{id}/submit
POST   /api/v1/applications/{id}/return
POST   /api/v1/applications/{id}/decision
```

## Assessments

```text
POST   /api/v1/assessment-templates
POST   /api/v1/assessment-templates/{id}/publish
POST   /api/v1/assessments/{id}/assign
POST   /api/v1/assessment-assignments/{id}/submit
GET    /api/v1/applications/{id}/assessment-result
```

## Mentorship

```text
POST   /api/v1/mentor-assignments
POST   /api/v1/mentor-assignments/{id}/accept
POST   /api/v1/mentorship-plans
POST   /api/v1/mentorship-sessions
POST   /api/v1/mentorship-sessions/{id}/complete
```

## Training

```text
POST   /api/v1/training-programs
POST   /api/v1/training-programs/{id}/enroll
POST   /api/v1/training-sessions/{id}/attendance
POST   /api/v1/training-assignments/{id}/submit
```

## Graduation

```text
POST   /api/v1/participants/{id}/graduation/evaluate
POST   /api/v1/participants/{id}/graduation/approve
POST   /api/v1/participants/{id}/certificate
```
