---
paths:
  - "tests/**/*"
  - "phpunit.xml"
  - "phpunit.xml.dist"
  - "pest.php"
  - "package.json"
  - "vite.config.*"
  - "vitest.config.*"
  - "jest.config.*"
  - ".github/workflows/**/*"
  - "composer.json"
---

# Testing and Quality Rules

## Red-Green-Refactor

For each behaviour change:

1. Write or update a test first.
2. Run it and confirm expected failure.
3. Implement the smallest correct behaviour.
4. Run the focused test.
5. Refactor without changing behaviour.
6. Run relevant regression tests.

Do not weaken assertions, delete valid tests, or mock the behaviour under test
merely to make checks pass.

## Coverage by Risk

Test where applicable:

- Happy path
- Invalid input
- Unauthenticated and unauthorized access
- Cross-tenant access
- Invalid state transition
- Duplicate/replayed request
- Idempotency
- External failure and timeout
- Concurrency or race-sensitive behaviour
- Audit creation
- Notification dispatch
- Migration forward and rollback
- Historical version/snapshot integrity

## Test Design

- Test observable behaviour rather than private implementation details.
- Keep tests deterministic and isolated.
- Freeze time where time affects behaviour.
- Use factories/builders that preserve domain validity.
- Do not call live production services.
- Contract-test provider adapters at their boundary.
- Add a regression test for every defect.
- Use characterization tests before refactoring untested legacy behaviour.
- Run repository-defined lint, formatting, static analysis, type checking,
  security, test, and build commands.
- Report exact commands and actual results.
