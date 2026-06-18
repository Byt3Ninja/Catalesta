# Assessment Engine

## Goals

- Configurable weighted assessments
- Multiple evaluator models
- Rubrics
- Disqualifying criteria
- Deterministic scoring
- Full audit trail

## Structure

```text
Assessment Template
  Category
    Subcategory
      Criterion
      Weight
      Scale
      Rubric
      Evidence Requirement
```

## Numeric Precision

Use decimal values.

Recommended columns:

```text
raw_score numeric(10,4)
normalized_score numeric(10,4)
weight numeric(7,4)
weighted_score numeric(12,4)
final_score numeric(12,4)
```

## Calculation Rules

```text
criterion_score × criterion_weight = weighted_score
sum(weighted_scores) = evaluator_total
aggregate(evaluator_totals) = final_score
```

Supported aggregation:

- average
- median
- weighted average
- remove highest and lowest
- committee override with reason

## Disqualifying Criteria

A failed disqualifying criterion may override the total score.

## Evaluator Assignment

Support:

- manual
- random
- expertise-based
- sector-based
- load-balanced
- conflict-aware
- blind review
- double-blind review

## Audit Requirements

Record:

- formula version
- assessment version
- criterion values
- evaluator
- timestamps
- overrides
- decision reasons
