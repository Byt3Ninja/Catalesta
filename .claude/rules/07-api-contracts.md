---
paths:
  - "routes/**/*.php"
  - "app/Http/**/*.php"
  - "Modules/**/Http/**/*.php"
  - "modules/**/http/**/*.php"
  - "openapi/**/*"
  - "docs/api/**/*"
  - "tests/**/*Api*.php"
  - "tests/**/*Contract*.php"
---

# API and Contract Rules

- Follow existing API versioning, envelope, pagination, and error conventions.
- Validate all external input.
- Authorize and establish tenant context before resource access.
- Do not expose internal stack traces, SQL, secrets, or provider payloads.
- Return stable machine-readable error codes with safe human-readable messages.
- Use correct HTTP semantics and status codes.
- Define pagination for collections and upper-bound page sizes.
- Define sorting and filtering allowlists.
- Prevent mass assignment and over-posting.
- Use idempotency keys for retryable create/payment/state-change operations where
  duplicate execution is unsafe.
- Document compatibility impact.
- Do not introduce a breaking change without explicit approval, versioning, and
  consumer migration planning.
- Update OpenAPI or the repository's authoritative API contract.
- Add contract tests for public and integration-facing endpoints.
- Treat webhook endpoints as authenticated external APIs with replay protection,
  idempotency, and bounded payload handling.
