# Claude Task: Implement Identity and Tenancy Foundation

## Goal

Implement mock Startup Gate OIDC login, external user projection, organizations, memberships, RBAC, and tenant isolation.

## Inputs

- `docs/10-startup-gate-mock.md`
- `docs/11-security.md`
- `docs/12-testing-strategy.md`

## Outputs

- Mock OIDC provider
- OIDC client integration
- External user mapping
- Organization model
- Membership model
- Roles and permissions
- Tenant middleware
- Policies
- Audit logging

## Acceptance Criteria

- User can log in through the mock provider
- `sub` is used as the immutable external identifier
- User can belong to multiple organizations
- Cross-tenant access is blocked
- Unauthorized actions return 403
- Tenant isolation tests pass
- Invalid OIDC tokens are rejected
