# Claude Task: Implement Assessment and Workflow Engines

## Goal

Implement versioned assessments, weighted scoring, rubrics, evaluator assignments, workflow states, transitions, conditions, actions, approvals, and history.

## Inputs

- `docs/07-workflow-engine.md`
- `docs/09-assessment-engine.md`
- `docs/11-security.md`

## Outputs

- Assessment module
- Workflow module
- Registered condition resolvers
- Registered action handlers
- Scoring services
- APIs
- Tests

## Acceptance Criteria

- Scores are calculated with decimal arithmetic
- Disqualifying criteria work
- Published definitions are immutable
- Running workflow instances remain bound to their version
- No arbitrary code execution exists
- Workflow history is complete
