# Domain Model

## Identity

- ExternalUser
- LocalUserProjection
- ProfileSnapshot
- ConsentReference
- ExternalRoleProfile

## Organization

- Organization
- OrganizationMembership
- OrganizationRole
- OrganizationPermission

## Startup

- Startup
- StartupMembership
- FounderRelationship
- CoFounderRelationship
- TeamMemberRelationship
- Delegation

## Program

- Program
- ProgramTemplate
- Cohort
- ProgramRoleRequirement
- ProgramPolicy

## Stage

- StageDefinition
- StageVersion
- StageTransition
- StageEntryRule
- StageExitRule
- StageInstance

## Form

- FormDefinition
- FormVersion
- FormSection
- FormField
- FormRule
- FormSubmission
- FormAnswer

## Application

- Application
- ApplicationParticipant
- ApplicationProfileSnapshot
- EligibilityResult
- ApplicationDecision

## Assessment

- AssessmentTemplate
- AssessmentVersion
- AssessmentCategory
- AssessmentCriterion
- AssessmentRubric
- AssessmentAssignment
- AssessmentSubmission
- Score
- AssessmentResult

## Workflow

- WorkflowDefinition
- WorkflowVersion
- WorkflowState
- WorkflowTransition
- WorkflowCondition
- WorkflowAction
- WorkflowInstance
- WorkflowHistory

## Mentorship

- MentorAssignment
- MentorshipPlan
- MentorshipSession
- SessionAttendance
- MentorFeedback
- StartupFeedback

## Training

- TrainingProgram
- TrainingModule
- TrainingSession
- Enrollment
- Attendance
- Assignment
- Quiz
- QuizAttempt
- TrainingResult

## Graduation

- GraduationRule
- GraduationDecision
- Certificate
- AlumniRecord
- FollowUpPlan
- FollowUpRecord

## Core Invariants

1. A published version cannot be modified.
2. A running application is bound to the published versions active at submission.
3. Tenant-owned records cannot cross organization boundaries.
4. Program assignments require valid eligibility and authorization.
5. Final scores are calculated server-side.
6. Formal decisions must be auditable.
7. Profile snapshots remain immutable.
