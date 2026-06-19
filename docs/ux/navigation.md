# Role-Based Navigation

> Owner: UX · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

## Purpose

Adapt navigation and actions to active role, organization, program, and cohort context.

## Context Selector

The user must always be able to see and switch:

- current role
- current organization
- current program
- current cohort

Example:

```text
Role: Mentor
Organization: GrowthLabs
Program: FinTech Accelerator 2026
Cohort: Spring 2026
```

## Rules

- Hide irrelevant modules
- Preserve last valid context
- Prevent access by URL when permission is absent
- Update dashboard content after context switch
- Update notifications after context switch
- Support users with multiple simultaneous roles
- Avoid duplicate accounts

## Navigation Model

Use task-oriented labels rather than internal module names.

Bad:

```text
Stages
Forms
Workflow
Assignments
```

Better:

```text
Program Journey
Application Setup
Selection Process
Delivery Activities
```
