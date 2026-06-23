---
paths:
  - "app/**/*.php"
  - "Modules/**/*.php"
  - "modules/**/*.php"
  - "config/**/*log*.php"
  - "config/**/*queue*.php"
  - "config/**/*cache*.php"
  - "deploy/**/*"
  - "infrastructure/**/*"
  - "docker-compose*.yml"
  - "compose*.yml"
  - "Dockerfile*"
  - ".github/workflows/**/*"
---

# Observability and Operations Rules

- Log structured events with correlation/request identifiers.
- Include tenant and actor identifiers only where authorized and non-sensitive.
- Never log secrets, tokens, passwords, raw payment data, or unnecessary profile
  fields.
- Record failures at the boundary where they can be acted upon.
- Avoid duplicate logging of the same exception at every layer.
- Define metrics for critical workflows, jobs, integrations, and payment events.
- Add health checks for required dependencies without exposing secrets.
- Distinguish liveness from readiness.
- Define alerts around user-impacting symptoms and failed invariants.
- Background jobs require retry limits, dead-letter/failure handling, and
  operational visibility.
- External integrations require latency, error, timeout, and rate-limit metrics.
- Critical state transitions and privileged operations require audit records.
- New infrastructure dependencies require backup, recovery, scaling, and
  ownership documentation.
- Do not claim production readiness without deployment, observability, backup,
  recovery, and rollback evidence.
