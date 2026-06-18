# Workflow Engine

## Purpose

The workflow engine executes configurable business state transitions without allowing arbitrary code execution.

## Core Model

```text
Current State
+ Trigger
+ Conditions
+ Authorization
= Transition
+ Actions
+ Next State
```

## Definition Tables

- workflow_definitions
- workflow_versions
- workflow_states
- workflow_transitions
- workflow_conditions
- workflow_actions
- workflow_approvals

## Runtime Tables

- workflow_instances
- workflow_history
- workflow_pending_approvals
- workflow_action_results

## Condition Format

Use a structured expression tree.

```json
{
  "all": [
    {
      "field": "assessment.average_score",
      "operator": ">=",
      "value": 70
    },
    {
      "field": "documents.required_completion",
      "operator": "=",
      "value": 100
    }
  ]
}
```

## Supported Operators

- equals
- not_equals
- greater_than
- greater_than_or_equal
- less_than
- less_than_or_equal
- in
- not_in
- contains
- contains_any
- is_null
- is_not_null

## Supported Actions

- Change status
- Assign user
- Create task
- Send notification
- Request document
- Open stage
- Close stage
- Move participant
- Create assessment
- Publish achievement
- Schedule reminder
- Queue external synchronization

## Versioning

- Draft workflow versions may be edited.
- Published versions are immutable.
- Running instances remain bound to their original version.
- Migration to a new version requires an explicit migration command.

## Safety

- No dynamic PHP
- No SQL
- No JavaScript
- No shell commands
- Field access must use a registered field resolver
- Actions must use registered action handlers
