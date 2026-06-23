---
paths:
  - "Modules/Forms/**/*.php"
  - "Modules/Workflows/**/*.php"
  - "Modules/Assessments/**/*.php"
  - "Modules/Stages/**/*.php"
  - "Modules/Applications/**/*.php"
  - "Modules/FinalEvaluation/**/*.php"
  - "modules/forms/**/*.php"
  - "modules/workflows/**/*.php"
  - "modules/assessments/**/*.php"
  - "database/**/*form*"
  - "database/**/*workflow*"
  - "database/**/*assessment*"
  - "tests/**/*Workflow*.php"
  - "tests/**/*Assessment*.php"
  - "tests/**/*Form*.php"
---

# Forms, Workflows, Scoring, and Versioning Rules

## Published Definitions

Published forms, schemas, workflows, stages, assessments, scoring models, formal
evaluation templates, and plan definitions are immutable.

- Changes create a new version.
- Draft versions may change until publication.
- Existing executions retain their original version reference.
- Do not mutate published JSON/configuration in place.
- Do not infer historical definitions from current mutable records.

## Formal Snapshots

At formal submission or execution boundaries, capture immutable snapshots of:

- Relevant questions and options
- Submitted values
- Documents and checksums where required
- Scoring model and weights
- Workflow/stage version
- Actor and tenant context
- Submission timestamp

## State Transitions

- Represent transitions explicitly.
- Validate actor, tenant, current state, and allowed transition.
- Execute state change and required records transactionally.
- Record actor, previous state, new state, reason, and timestamp.
- Make retryable transitions idempotent.
- Do not permit unrestricted model updates to lifecycle state.

## Rules and Scoring

- No arbitrary code execution.
- Use an allowlisted rule vocabulary and validated operators.
- Use fixed-precision decimals.
- Define scale, precision, rounding, normalization, and missing-value rules.
- Store calculation inputs and exact scoring-model version.
- Historical scores must remain reproducible.
