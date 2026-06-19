# Phase 1 Design — Identity & Tenancy

Status: Approved (2026-06-18)
Scope: `prompts/01-identity-tenancy.md`
Source contracts: `docs/10-startup-gate-mock.md`, `docs/11-security.md`, `docs/12-testing-strategy.md`, `docs/04-data-model.md`, `docs/03-domain-model.md`
Governing decisions: `~/.claude/.../memory/architecture-decisions.md`

## 1. Goal

Implement mock Startup Gate OIDC login, an external-user projection keyed on the
immutable `sub`, consent-aware profile reads, immutable profile snapshots,
organizations + memberships, org-scoped RBAC, tenant isolation, and audit
logging. The mock must be replaceable by the real Startup Gate through
configuration and adapter changes only.

## 2. Non-negotiable constraints (from the task + CLAUDE.md)

- Preserve the final OIDC and profile API contracts (docs/10).
- Keep mock-specific implementation behind adapters; no mock logic in domain modules.
- Use the Startup Gate subject `sub` as the immutable user identifier.
- Never use email as the user-linkage key.
- Do not duplicate full Startup Gate profiles — only a local projection and
  immutable application/profile snapshots (CLAUDE rule 15).
- All profile sharing is consent-aware (rule 16).
- Every tenant-owned record carries `organization_id`; every tenant query is scoped (rules 6–7).
- Sensitive actions are server-authorized (rule 17).
- Every feature has tests (rule 13).

## 3. Topology — one codebase, two runtime roles

One Laravel image runs in two roles:

- **Platform** (`laravel-api`, `queue-worker`, `scheduler`, `nginx`): depends only on
  identity/profile **adapter interfaces**. Never imports mock code.
- **Startup Gate Mock** (`startup-gate-mock` container): an isolated
  `app/StartupGateMock/` namespace, deliberately **outside `app/Modules`**. Its
  routes register only when `APP_ROLE=mock`. Issues **RS256-signed JWTs** and a
  real JWKS endpoint. Replaces the Phase-0 Node placeholder.

`APP_ROLE` (default `platform`) gates a `StartupGateMockServiceProvider` that
registers mock routes; when `platform`, those routes never load. Both roles share
the image and `composer` deps but expose disjoint route sets.

Because the mock faithfully reproduces the real contract, the platform-side
adapter is a plain **Startup Gate HTTP client** configured by `OIDC_ISSUER`,
`PROFILE_API_BASE_URL`, and the discovered JWKS URL. Phase 12 changes only those
config values and the JWKS source — no platform code change.

## 4. Adapter seam (in `app/Modules/Identity`)

Interfaces (per docs/10 "Adapter Interfaces"):

| Interface | Responsibility (Phase 1) |
|---|---|
| `IdentityProvider` | discovery, build authorize URL (state/nonce/PKCE), exchange code, validate ID token (JWKS/iss/aud/exp/nonce), refresh, revoke, logout, userinfo |
| `ProfileProvider` | read general profile for granted scopes |
| `ConsentProvider` | read consents |
| `RoleProfileProvider` | read role profiles |
| `StartupMembershipProvider` | read startup memberships |
| `AchievementPublisher` | **interface only**; platform-side consumption deferred to Graduation phase |

`IdentityServiceProvider` binds the concrete `StartupGate*` HTTP implementations,
selected by `config('identity.provider')` (`IDENTITY_PROVIDER`). Tests bind in-memory
fakes against the same interfaces.

## 5. Authentication flow — Authorization Code + PKCE → Sanctum session

1. SPA → `GET /api/v1/auth/login`. Platform generates `state`, `nonce`, PKCE
   `code_verifier`/`code_challenge` (S256), stores them in the session, returns the
   mock `/oauth/authorize` URL.
2. Mock authenticates the user (seed-user selection / `login_hint=sub`) and
   redirects to `OIDC_REDIRECT_URI` = `http://localhost:3000/auth/callback?code&state`.
3. SPA → `POST /api/v1/auth/callback {code, state}`. Platform validates `state`,
   exchanges `code` at `/oauth/token` with the PKCE `code_verifier`, receiving
   `id_token`, `access_token`, `refresh_token`.
4. Platform validates the ID token: **signature via JWKS, `iss`, `aud`, `exp`,
   `nonce`**. Rejects on any failure (401). Extracts `sub`.
5. **Upsert `external_users` by `startup_gate_subject_id = sub`** (immutable key).
   Refresh of mutable projection fields (email, name, avatar, locale,
   profile_version) is allowed; the `sub` link is never changed. Access/refresh
   tokens are stored **encrypted** in `external_user_tokens`. A **Sanctum SPA
   session** is established for the projected user.
6. `GET /api/v1/auth/session` returns the current session/user; `GET /api/v1/me`
   returns the projection; `POST /api/v1/auth/logout` revokes tokens at the mock
   and clears the session.

Refresh-token rotation: when the stored access token is expired, the platform
refreshes via `IdentityProvider::refresh` and rotates the stored tokens.

## 6. Data model (Phase 1 subset of docs/04)

ULID primary keys (Laravel `HasUlids`), UTC timestamps, JSONB for bounded config.

### Global (NOT tenant-owned — no `organization_id`)

- `external_users` — the LocalUserProjection. Columns: `id`,
  `startup_gate_subject_id` (unique, immutable), `email`, `display_name`,
  `avatar_url`, `locale`, `profile_version`, `synchronization_status`,
  `synchronized_at`, `is_platform_admin` (bool, default false), timestamps.
  This is the authenticatable model.
- `external_user_tokens` — `id`, `external_user_id`, `access_token` (encrypted),
  `refresh_token` (encrypted), `scopes`, `expires_at`, timestamps.
- `profile_snapshots` — immutable, insert-only. `id`, `external_user_id`,
  `context_type`, `context_id`, `profile_version`, `payload_json` (JSONB),
  `consent_reference`, `hash` (sha-256 over canonical payload), `captured_at`.
  No `updated_at` semantics; updates are forbidden at the model layer.
- `organization_permissions` — permission catalog: `id`, `key` (unique),
  `description`. Seeded.
- `audit_logs` — `id`, `actor_external_user_id` (nullable), `organization_id`
  (nullable), `action`, `target_type`, `target_id`, `before` (JSONB), `after`
  (JSONB), `ip_address`, `correlation_id`, `result`, `created_at`.

### Tenant-owned (`organization_id` required)

- `organizations` — tenant root: `id`, `name`, `slug` (unique), `branding` (JSONB), timestamps.
- `organization_memberships` — `id`, `organization_id`, `external_user_id`,
  `status` (active/invited/suspended), timestamps. Unique(`organization_id`,`external_user_id`).
- `organization_roles` — `id`, `organization_id`, `key`, `name`, `is_system`.
  Unique(`organization_id`,`key`).
- `organization_membership_roles` — pivot: `membership_id`, `organization_role_id`.
  Unique(pair).
- `role_permission_assignments` — `organization_role_id`, `organization_permission_id`.
  Unique(pair).

`organizations` is the tenant root and has **no** `organization_id` column; it is
not filtered by the `BelongsToTenant` trait. Access to organizations is scoped by
the requesting user's memberships (you can read/manage only orgs you belong to,
subject to permissions). All other tenant-owned tables carry `organization_id` and
are scoped by the trait.

## 7. Tenant isolation

- `ResolveTenant` middleware: reads `X-Organization-Id`; 400 if malformed; verifies
  an **active membership** for the authenticated user; **403** if not a member;
  populates a request-scoped `TenantContext` singleton (organization, membership,
  effective permission set).
- `BelongsToTenant` trait: a global Eloquent scope filters tenant models by
  `TenantContext` org, and a `creating` hook stamps `organization_id`. Models used
  outside a tenant request (seeders, mock) bypass via an explicit unscoped path.
- Composite unique constraints always include `organization_id`.
- Postgres RLS: **deferred** (documented defense-in-depth for a later hardening pass).

## 8. Authorization (custom, org-scoped)

- No `spatie/laravel-permission` (it is global; we need per-org). Seeded permission
  catalog (e.g., `organizations.manage`, `members.manage`, `members.invite`,
  `roles.manage`). Roles are per-organization; members get roles via the pivot;
  roles grant permissions via `role_permission_assignments`.
- Effective permissions are resolved once per request into `TenantContext`.
  Policies and a `TenantGate` check `TenantContext->can($permission)`. Failures → **403**.
- `is_platform_admin` on `external_users` bypasses tenant/permission checks
  (minimal Platform Administrator support for Phase 1).

## 9. Startup Gate mock surface (docs/10)

OIDC: `GET /.well-known/openid-configuration`, `GET /oauth/authorize`,
`POST /oauth/token`, `GET /oauth/userinfo`, `GET /.well-known/jwks.json`,
`POST /oauth/revoke`, `POST /oauth/logout`.

Profile: `GET /api/v1/me`, `GET /api/v1/me/profile`, `GET /api/v1/me/role-profiles`,
`GET /api/v1/me/startups`, `GET /api/v1/me/consents`,
`POST /api/v1/profile-update-proposals`, `POST /api/v1/program-achievements`.

- Claims and scopes exactly per docs/10 (`sub`, `iss`, `aud`, `email`,
  `email_verified`, `name`, `locale`, `profile_updated_at`; the nine OAuth scopes).
- **RS256** keypair; JWKS publishes the public key. In `testing`, a fixed key pair
  is used for determinism.
- Transient auth codes and tokens stored in Redis (cache) with TTL.
- **10 seed personas** per docs/10: founder-only; founder+mentor; mentor-only;
  evaluator; trainer; service-provider; org-admin; revoked-consent;
  incomplete-profile; expired-role-verification. Stored as mock fixtures
  (JSON/seeder), isolated from platform tables.
- Consent gating: profile endpoints honor the persona's granted scopes/consents;
  revoked-consent persona yields consent-denied responses where applicable.
- Mock webhook publisher: **payload builders + HMAC signing** are implemented and
  contract-tested for `ProfileUpdated`, `ConsentRevoked`, `RoleProfileApproved`,
  `AchievementPublished`. Full inbound delivery/processing is **deferred**
  (Integrations module + transactional outbox).

## 10. Profile snapshots & consent (Phase 1 behavior)

- On demand (e.g., capturing identity at login or when a future application needs
  it), the platform calls `ProfileProvider`/`ConsentProvider` through adapters and
  writes an **immutable `profile_snapshots` row** with a content `hash` and the
  `consent_reference` under which it was captured.
- The platform never stores a full mirrored profile table — only the projection
  (§6) and these immutable snapshots. Consent state is checked before any profile
  read; denied scopes are omitted, not faked.

## 11. API endpoints added (platform, `/api/v1`)

- `GET /auth/login`, `POST /auth/callback`, `GET /auth/session`, `POST /auth/logout`
- `GET /me`, `GET /me/profile`, `GET /me/role-profiles`, `GET /me/startups`
- `GET /organizations`, `POST /organizations`, `GET /organizations/{id}`, `PATCH /organizations/{id}`
- Membership/role management under organizations (server-authorized).

All errors use the standard error object from docs/06 with `correlation_id`.

## 12. Testing strategy (TDD; docs/12)

- **Unit:** ID-token validation (good/expired/wrong-iss/wrong-aud/bad-sig/bad-nonce),
  snapshot hashing + immutability, permission resolution, tenant scope filtering,
  PKCE challenge/verifier.
- **Feature:** full login flow; `sub` used as identity (email change does not fork
  the user; same `sub` + new email updates the same projection); user in multiple
  orgs; cross-tenant read/write **blocked**; unauthorized action → **403**;
  invalid/expired/wrong-issuer/wrong-audience token **rejected** (401);
  revoked-consent enforced.
- **Contract:** every mock endpoint in §9 — discovery document, token exchange,
  userinfo claims, profile/consent/role/startup payload shapes, achievement
  publication response, webhook payload + signature.
- **Tenant-isolation suite** (mandatory security tests, docs/12): cross-tenant
  access blocked; unauthorized stage/resource transition blocked; expired token
  rejected; invalid issuer/audience rejected; revoked consent enforced.
- Coverage focus on Identity, Tenant isolation, Authorization (docs/12 priorities).

## 13. New dependencies

- `laravel/sanctum` — SPA cookie session for the platform API.
- `firebase/php-jwt ^7.0` — RS256 JWS signing (mock) and verification + JWKS
  handling (platform). Patches CVE-2025-45769; used for signing and JWKS-based
  token validation.
- `symfony/uid` — ships with Laravel; ULIDs via `HasUlids`.

## 14. Explicitly deferred (not Phase 1)

- Platform-side achievement publication and profile-update-proposal consumption
  (Profiles/Graduation phases). Mock still exposes the endpoints + contract tests.
- Inbound webhook delivery/processing pipeline (Integrations + outbox).
- Postgres RLS hardening.
- Real Startup Gate integration (Phase 12) — config/adapter swap only.

## 15. Acceptance criteria mapping (prompts/01)

| Criterion | Covered by |
|---|---|
| Login via mock provider | §5, mock §9 |
| `sub` is the immutable identifier | §5.5, §6 (`external_users`), feature test §12 |
| User in multiple organizations | §6 memberships, feature test §12 |
| Cross-tenant access blocked | §7, tenant-isolation suite §12 |
| Unauthorized actions → 403 | §8, feature test §12 |
| Tenant isolation tests pass | §7, §12 |
| Invalid OIDC tokens rejected | §5.4, unit + feature tests §12 |
