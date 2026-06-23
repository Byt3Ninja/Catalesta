---
paths:
  - "Modules/Integrations/**/*.php"
  - "Modules/Notifications/**/*.php"
  - "modules/integrations/**/*.php"
  - "modules/notifications/**/*.php"
  - "app/Jobs/**/*.php"
  - "app/Listeners/**/*.php"
  - "app/Events/**/*.php"
  - "config/**/*services*.php"
  - "tests/**/*Integration*.php"
  - "tests/**/*Notification*.php"
---

# Integrations, Jobs, Events, and Notifications Rules

Every external integration must define:

- Interface owned by Catalesta
- Adapter implementation
- Authentication and secret storage
- Connect and read timeouts
- Retry classification and backoff
- Idempotency behaviour
- Rate-limit handling
- Error translation
- Logging and metrics
- Degraded behaviour
- Reconciliation/recovery process
- Contract tests

Rules:

- Do not leak provider SDK objects into domain modules.
- Do not retry permanent validation, authentication, authorization, or
  unsupported-operation failures.
- Treat ambiguous timeout outcomes carefully; reconcile before duplicating
  side effects.
- Queue slow or failure-prone external operations where appropriate.
- Jobs must be idempotent and bounded.
- Jobs must restore tenant context and clear it afterward.
- Dispatch jobs/events after database commit when they depend on committed data.
- Use an outbox or equivalent pattern for critical cross-boundary delivery when
  required by reliability.
- Notifications must respect user preference, consent, tenant, locale, and
  delivery eligibility.
- Never expose one tenant's recipient data to another tenant.
- Record delivery status without storing sensitive provider payloads.
