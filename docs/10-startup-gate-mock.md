# Startup Gate Mock OIDC and Profile Provider

## Purpose

Provide a local mock that reproduces the future Startup Gate identity and profile contracts.

The mock must be replaceable with the real Startup Gate integration through configuration and adapter replacement only.

## Planned Production Role

Startup Gate will own:

- User authentication
- General profile
- Role profiles
- Startup memberships
- Global role verification
- Consent
- Shared directories
- Achievements

## Mock Components

1. Mock OIDC Provider
2. Mock OAuth Authorization Server
3. Mock UserInfo endpoint
4. Mock Profile API
5. Mock Role Profile API
6. Mock Consent API
7. Mock Startup Membership API
8. Mock Achievement API
9. Mock webhook publisher

## Mock OIDC Endpoints

```text
GET  /.well-known/openid-configuration
GET  /oauth/authorize
POST /oauth/token
GET  /oauth/userinfo
GET  /.well-known/jwks.json
POST /oauth/revoke
POST /oauth/logout
```

## Required Claims

```json
{
  "sub": "sg_user_01",
  "iss": "http://startup-gate-mock:8080",
  "aud": "program-platform",
  "email": "founder@example.com",
  "email_verified": true,
  "name": "Mock Founder",
  "locale": "en",
  "profile_updated_at": 1781712000
}
```

## OAuth Scopes

```text
openid
profile.basic.read
profile.professional.read
profile.founder.read
profile.mentor.read
profile.service_provider.read
profile.startups.read
profile.documents.read
profile.updates.propose
profile.achievements.write
```

## Mock Profile Endpoints

```text
GET  /api/v1/me
GET  /api/v1/me/profile
GET  /api/v1/me/role-profiles
GET  /api/v1/me/startups
GET  /api/v1/me/consents
POST /api/v1/profile-update-proposals
POST /api/v1/program-achievements
```

## Mock Seed Users

Create at least:

- Founder only
- Founder and mentor
- Mentor only
- Evaluator
- Trainer
- Service provider
- Organization administrator
- User with revoked consent
- User with incomplete profile
- User with expired role verification

## Adapter Interfaces

```text
IdentityProvider
ProfileProvider
ConsentProvider
RoleProfileProvider
StartupMembershipProvider
AchievementPublisher
```

## Environment Configuration

```env
IDENTITY_PROVIDER=mock
OIDC_ISSUER=http://startup-gate-mock:8080
OIDC_CLIENT_ID=program-platform
OIDC_CLIENT_SECRET=local-secret
OIDC_REDIRECT_URI=http://localhost:3000/auth/callback
PROFILE_API_BASE_URL=http://startup-gate-mock:8080/api/v1
```

## Replacement Strategy

When Startup Gate is ready:

1. Change provider configuration.
2. Replace mock adapter implementations.
3. Run contract tests against Startup Gate sandbox.
4. Keep domain modules unchanged.
5. Remove mock-only UI from production.
6. Preserve mock server for local and automated testing.
