# Definition of Done

A feature is complete only when all applicable conditions are met.

## Functional

- Acceptance criteria pass
- Validation is implemented
- Error states are handled
- Business invariants are enforced
- Authorization is enforced server-side

## Technical

- Migrations are reviewed
- Code follows module boundaries
- Static analysis passes
- Linting passes
- No secrets are committed
- No mock-specific logic leaks into domain modules

## Testing

- Unit tests added
- Feature tests added
- Authorization tests added
- Tenant isolation tests added
- Contract tests updated
- End-to-end tests added for critical flows

## Documentation

- API documentation updated
- Architecture documentation updated
- Data model updated
- Environment variables documented
- Migration and rollback notes added

## Operational

- Logs are meaningful
- Metrics are available where needed
- Failed jobs are recoverable
- Idempotency is implemented for retryable operations
- Rollback path is documented

## Security

- Sensitive data exposure reviewed
- Permissions reviewed
- Tenant isolation verified
- Upload security applied
- Audit events generated
