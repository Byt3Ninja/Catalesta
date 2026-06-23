# BMAD and Superpowers Execution Rules

## Authority

- BMAD defines what must be built and why.
- Superpowers defines how an approved story is implemented and verified.
- An implementation plan cannot change product scope, business rules, domain
  ownership, architecture, or acceptance criteria.
- A code-level discovery that invalidates an approved requirement must be
  recorded as a contradiction, not silently repaired through assumption.

## Skill Use

- Invoke relevant Superpowers skills with Claude Code's Skill mechanism before
  acting.
- Use process skills before implementation skills.
- Use systematic debugging for defects.
- Use TDD for behaviour changes.
- Use verification-before-completion before any completion claim.
- Do not manually read plugin skill files instead of invoking the skill.
- User instructions and repository instructions remain authoritative over
  plugin workflows.

## BMAD Readiness Gate

A story is ready only when applicable items are explicit:

- Business objective and actor
- Business rules and functional requirements
- Testable acceptance criteria
- Authorization and tenant-isolation requirements
- Data ownership and schema impact
- API contracts
- UI states
- Validation and failure scenarios
- Audit and observability requirements
- Dependencies
- Migration and rollback considerations

Mark incomplete work `Blocked`. Do not fill material gaps with assumptions.

## Execution Sequence

1. Establish authoritative context.
2. Inspect existing behaviour and tests.
3. Identify the smallest coherent change.
4. Map acceptance criteria to tests.
5. Create an isolated branch or worktree.
6. Produce a file-level plan.
7. Apply red-green-refactor.
8. Run focused and regression verification.
9. Perform specification-compliance review.
10. Perform code-quality and security review.
11. Update documentation.
12. Produce evidence-based completion report.
