# Security Requirements

## Authentication

- OIDC Authorization Code Flow
- PKCE
- State validation
- Nonce validation
- Issuer validation
- Audience validation
- Token expiration validation
- JWKS key rotation
- Refresh-token rotation
- Logout and revocation

## Authorization

- Organization-scoped RBAC
- Program-scoped permissions
- Field-level access controls
- Server-side policy enforcement
- Least privilege
- Delegation boundaries

## Tenant Isolation

- Tenant middleware
- Organization ID on tenant records
- Tenant-aware policies
- Tenant-aware repository queries
- Composite unique constraints
- Tenant isolation tests
- Optional PostgreSQL RLS

## Sensitive Data

- Encrypt secrets and integration credentials
- Use signed URLs for documents
- Restrict evaluation visibility
- Avoid storing unnecessary profile fields
- Log access to sensitive records
- Apply retention rules

## Upload Security

- MIME validation
- Extension validation
- File-size limits
- Malware scanning
- Private storage
- Signed download URLs
- Versioning

## API Security

- Rate limits
- Idempotency keys
- Request validation
- Correlation IDs
- Signed webhooks
- Replay protection
- Input normalization

## Audit

Record:

- Actor
- Organization
- Program
- Action
- Target
- Before value
- After value
- Timestamp
- IP address
- Correlation ID
- Result
