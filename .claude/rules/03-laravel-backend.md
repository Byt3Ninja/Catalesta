---
paths:
  - "app/**/*.php"
  - "Modules/**/*.php"
  - "modules/**/*.php"
  - "routes/**/*.php"
  - "tests/**/*.php"
  - "composer.json"
---

# Laravel Backend Rules

- Follow the Laravel and PHP versions declared by the repository.
- Follow PSR-12 and repository formatting rules.
- Use strict types where established by the codebase.
- Use typed properties, parameters, and return values.
- Use Form Request objects or equivalent centralized validation for HTTP input.
- Use policies, gates, middleware, or centralized permission services for
  authorization.
- Keep controllers thin.
- Put orchestration in application services and business invariants in cohesive
  domain services or models.
- Use transactions for multi-record consistency changes.
- Dispatch external side effects after commit when required.
- Avoid model events for hidden critical business workflows.
- Guard mass assignment.
- Avoid raw SQL unless justified, parameterized, tested, and documented.
- Prevent N+1 queries with intentional eager loading.
- Paginate unbounded collections.
- Use immutable value objects for identifiers, money, percentages, scores, and
  other constrained domain values where appropriate.
- Do not catch broad exceptions unless translating, recording, or compensating
  them.
- Never swallow exceptions silently.
- Use dependency injection rather than service-locator access in domain logic.
- Add PHPDoc only when types or non-obvious contracts are not expressible in
  native PHP.
