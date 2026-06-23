---
paths:
  - "app/**/*.php"
  - "Modules/**/*.php"
  - "modules/**/*.php"
  - "routes/**/*.php"
  - "config/**/*.php"
  - "bootstrap/**/*.php"
  - "composer.json"
  - "composer.lock"
---

# Laravel Modular Monolith Architecture Rules

- Keep each domain capability inside its owning module.
- Expose cross-module behaviour through explicit application services,
  contracts, domain events, or approved read models.
- Do not reach into another module's internal repositories, tables, private
  services, or implementation classes.
- Shared kernel code must be small, stable, and domain-neutral.
- Do not create a generic `Helpers` or `Utils` dumping ground.
- Avoid circular module dependencies.
- Record new module dependencies in architecture documentation.
- Shared identity and tenancy abstractions belong in their approved ownership
  modules, not duplicated in feature modules.
- Keep HTTP, application, domain, and infrastructure concerns separated.
- Domain rules must not depend directly on framework request objects.
- Provider-specific integration payloads must not leak into domain models.
- New modules and major boundary changes require an ADR.
- Use events for legitimate decoupling, not to hide synchronous invariants.
- Keep transactions within a clear consistency boundary.
- Do not split into microservices without an approved architecture decision.
