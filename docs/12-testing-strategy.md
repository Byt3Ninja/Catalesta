# Testing Strategy

## Test Layers

### Unit Tests

- Rule evaluation
- Score calculation
- Transition guards
- Eligibility checks
- Capacity checks
- Conflict checks
- Field mapping
- Versioning rules

### Feature Tests

- API endpoints
- Authorization
- Tenant isolation
- Application submission
- Assessment assignment
- Workflow transitions
- Graduation approval

### Contract Tests

- OIDC discovery
- Token exchange
- UserInfo claims
- Profile API responses
- Consent API
- Achievement publication
- Webhook payloads

### Integration Tests

- PostgreSQL
- Redis
- Object storage
- Queue workers
- Mock Startup Gate
- Email and notification adapters

### End-to-End Tests

- User login
- Program creation
- Application submission
- Evaluation
- Mentor assignment
- Training completion
- Graduation

## Mandatory Security Tests

- Cross-tenant access blocked
- Unauthorized stage transition blocked
- Expired token rejected
- Invalid issuer rejected
- Invalid audience rejected
- Revoked consent enforced
- Duplicate webhook handled idempotently
- Sensitive documents inaccessible without permission

## Coverage Priorities

Prioritize critical domain rules over superficial line coverage.

Critical modules should maintain high branch coverage:

- Identity
- Tenant isolation
- Workflow
- Assessment
- Authorization
- Graduation
