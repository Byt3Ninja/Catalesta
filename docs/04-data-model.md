# Data Model

> **Phase 1 implementation status (2026-06-18):** The following tables are
> implemented and migrated: `external_users`, `external_user_tokens`,
> `profile_snapshots`, `organizations`, `organization_memberships`,
> `organization_roles`, `organization_permissions`, `role_permission_assignments`,
> `organization_membership_roles`, `audit_logs`. All remaining sections are
> design targets for future phases.

## Identity and Profile Projection

```text
external_users
- id
- startup_gate_subject_id
- email
- display_name
- avatar_url
- locale
- profile_version
- synchronization_status
- synchronized_at
- created_at
- updated_at

profile_snapshots
- id
- external_user_id
- context_type
- context_id
- profile_version
- payload_json
- consent_reference
- hash
- captured_at
```

## Organizations

```text
organizations
organization_memberships
organization_roles
organization_permissions
role_permission_assignments
```

## Startups

```text
startups
startup_memberships
startup_relationship_types
startup_delegations
```

## Programs

```text
programs
program_templates
program_cycles
program_policies
program_role_requirements
program_role_assignments
```

## Stages

```text
program_stages
stage_versions
stage_transitions
stage_rules
stage_instances
participant_stage_statuses
```

## Forms

```text
forms
form_versions
form_sections
form_fields
form_field_options
form_rules
form_assignments
form_submissions
form_answers
```

## Applications

```text
applications
application_participants
application_profile_snapshots
eligibility_results
eligibility_rule_results
application_decisions
```

## Documents

```text
document_types
document_requirements
documents
document_versions
document_reviews
document_access_grants
```

## Assessments

```text
assessment_templates
assessment_versions
assessment_categories
assessment_criteria
assessment_rubrics
assessment_assignments
assessment_submissions
assessment_scores
assessment_results
evaluation_decisions
```

## Workflows

```text
workflow_definitions
workflow_versions
workflow_states
workflow_transitions
workflow_conditions
workflow_actions
workflow_instances
workflow_history
workflow_approvals
```

## Mentorship

```text
mentor_assignments
mentorship_plans
mentorship_sessions
mentorship_attendance
mentorship_tasks
mentorship_feedback
```

## Training

```text
training_programs
training_modules
training_sessions
training_enrollments
training_attendance
training_assignments
training_submissions
quizzes
quiz_questions
quiz_attempts
training_results
```

## Graduation

```text
graduation_rules
graduation_decisions
certificates
alumni_records
follow_up_plans
follow_up_records
```

## Infrastructure

```text
outbox_events
webhook_deliveries
idempotency_keys
audit_logs
notifications
scheduled_jobs
```

## Data Type Rules

- Use UUID or ULID primary keys.
- Use `numeric` or `decimal` for scoring.
- Use UTC timestamps.
- Use JSONB only for bounded configuration.
- Add `organization_id` to tenant-owned records.
- Add indexes for foreign keys and common filters.
- Use composite unique constraints where tenant scope matters.
