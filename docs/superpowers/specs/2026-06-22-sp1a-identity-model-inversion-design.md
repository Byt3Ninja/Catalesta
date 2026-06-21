# SP-1a — Identity Model Inversion (behavior-preserving) — Design

- **Date:** 2026-06-22
- **Status:** Approved (design); pending implementation plan
- **Parent:** Epic 4 "Standalone Identity, Accounts & Profiles" → SP-1. SP-1 was split into **SP-1a (this spec, model inversion)** and **SP-1b (native auth feature)**.
- **Upstream spec:** `docs/superpowers/specs/2026-06-21-standalone-identity-design.md` (merged) — the identity-ownership inversion. This spec implements the data-model hinge for the Startup-Gate side, behavior-preserving.

## 1. Purpose & invariant

Turn the current `ExternalUser`-keyed-on-`sub` identity model into the target **`Account` + `linked_identities`** model **without changing any observable behavior**. After SP-1a: same OIDC-mock login, same Sanctum SPA session cookie, same `/auth/*` JSON, same tenant resolution, same membership/audit/stage behavior. Only the internal schema and model vocabulary change.

**Hard invariant:** every existing auth/tenant/membership/stage test passes (adjusted only for renamed identifiers). No password, no registration, no email verification, no new UI, no session-shape change — those are SP-1b and later.

**Why this slice exists separately:** the rename + FK repoint crosses 6 tables, the hot `ResolveTenant` middleware, and the audit actor column. Isolating it as a behavior-preserving refactor de-risks that blast radius and keeps it independently reviewable, before the native-auth feature stacks on top.

## 2. Current state (verified)

- `external_users` (ULID PK) **already implements `Authenticatable`** (`HasUlids`, `remember_token`). Columns: `startup_gate_subject_id` (unique `sub`), `email` (nullable, display-only), `display_name`, `avatar_url`, `locale`, `profile_version`, `synchronization_status`, `synchronized_at`, `is_platform_admin`, `remember_token`, timestamps. **No `password` / `email_verified_at`.**
- `external_user_tokens` (ULID PK): `external_user_id` (indexed, no DB FK), `access_token`/`refresh_token` (encrypted casts), `scopes` (jsonb), `expires_at`. One row per user; replaced on each login, deleted on logout.
- OIDC login: `AuthController` (login/callback/logout) → `CompleteLogin::handle($code)` upserts `ExternalUser::projectFromClaims` by `sub`, replaces the token row, captures a profile snapshot, `Auth::login($user)` on the `web` session guard, `session()->regenerate()`. `config/auth.php` `users` provider → `ExternalUser`; `bootstrap/app.php` `statefulApi()`.
- Membership FK `organization_memberships.external_user_id` → `external_users(id)` (DB FK cascade), unique `(organization_id, external_user_id)`.
- Other FK columns: `profile_snapshots.external_user_id` (DB FK cascade), `participant_stage_statuses.external_user_id` (indexed, no FK), `audit_logs.actor_external_user_id` (nullable, indexed, no FK).
- Frontend session shape (`frontend/src/schemas/session.ts`): `{ id, startup_gate_subject_id, email, display_name }` wrapped as `{ user: … }`. Cookie transport, `credentials: 'include'`.
- **Assumption (confirmed in brainstorm):** no production user rows — Phase 1a, OIDC mock, no pilot. There is therefore no data migration — the create-migrations are edited to emit the target schema directly (see §4). Tests run on fresh in-memory SQLite (`RefreshDatabase`), reinforcing this.

## 3. Target schema

### `accounts` (rename of `external_users`)
Keep: `id` (ULID PK), `email` (nullable in 1a — SP-1b makes it the unique login credential), `display_name`, `avatar_url`, `locale`, `is_platform_admin`, `remember_token`, timestamps.
Drop (move to the link): `startup_gate_subject_id`, `synchronization_status`, `synchronized_at`, `profile_version`.
SP-1b will add `password` (nullable) and `email_verified_at`.

### `linked_identities` (new)
| Column | Type | Notes |
|---|---|---|
| `id` | ULID PK | |
| `account_id` | ULID | FK → `accounts(id)` ON DELETE CASCADE |
| `provider` | string | `'startup_gate'` (the only value today) |
| `subject_id` | string | the immutable Startup-Gate `sub` |
| `display_name`, `avatar_url`, `locale` | nullable | snapshot from OIDC claims |
| `profile_version` | unsignedBigInteger default 0 | carried from the old column |
| `synchronization_status` | string default `'pending'` | |
| `synchronized_at` | timestamptz nullable | |
| `linked_at` | timestamptz | when the link was established |
| `last_login_at` | timestamptz nullable | updated on each successful OIDC login |
| timestamps | | |

Constraints: **UNIQUE `(provider, subject_id)`** (a `sub` maps to exactly one link) and **UNIQUE `(account_id, provider)`** (one link per provider per account).

### `linked_identity_tokens` (rename of `external_user_tokens`)
Same columns and encryption; FK column `external_user_id` → **`linked_identity_id`** (ULID) → `linked_identities(id)`. Kept as a separate table (not folded onto `linked_identities`) to minimize behavioral change and preserve the existing pattern.

### FK repoints (`external_user_id` → `account_id`, ULID, preserve existing cascade/index/unique semantics)
- `organization_memberships.account_id`; unique → `(organization_id, account_id)`; DB FK cascade preserved.
- `profile_snapshots.account_id`; DB FK cascade preserved.
- `participant_stage_statuses.account_id`; indexed, no DB FK (unchanged).
- `audit_logs.actor_external_user_id` → **`actor_account_id`** (nullable, indexed, no DB FK).

## 4. Migration (edit the original create-migrations — no data migration)

**Decided 2026-06-22:** Tests run on fresh in-memory SQLite with `RefreshDatabase` (every suite rebuilds the schema from the create-migrations), and there is no production data (Phase 1a, OIDC mock; dev re-migrates fresh). Layering a rename/backfill migration would be both SQLite-fragile (column rename + FK drop/recreate trigger table rebuilds) and pointless (no rows to migrate). So SP-1a **edits the original `2026_06_18_*` create-migrations to emit the target schema directly.** This is acceptable precisely because the repo is pre-production and every environment rebuilds from scratch.

Migration file changes:
1. `…000200_create_external_users_table.php` → rename file to `…000200_create_accounts_table.php`; `Schema::create('accounts', …)` keeping `email`, `display_name`, `avatar_url`, `locale`, `is_platform_admin`, `rememberToken`, timestamps; **omit** `startup_gate_subject_id`, `synchronization_status`, `synchronized_at`, `profile_version` (they move to the link).
2. **New** `…000250_create_linked_identities_table.php` (sorts after accounts, before tokens) — the `linked_identities` schema with FK → `accounts`, unique `(provider, subject_id)` and `(account_id, provider)`.
3. `…000300_create_external_user_tokens_table.php` → rename file to `…000300_create_linked_identity_tokens_table.php`; `Schema::create('linked_identity_tokens', …)` with `linked_identity_id` (indexed) instead of `external_user_id`.
4. `…000400` profile_snapshots: column `external_user_id` → `account_id`; `->on('accounts')`.
5. `…001300` organization_memberships: `external_user_id` → `account_id`; unique `(organization_id, account_id)`; `->on('accounts')`.
6. `…002900` participant_stage_statuses: `external_user_id` → `account_id`; unique `(cohort_id, account_id, program_stage_id)`.
7. `…000100` audit_logs: `actor_external_user_id` → `actor_account_id`.

No `down()`/`up()` rename gymnastics and no data-backfill step — the migrations simply *are* the target schema. The code, call sites, and test seams (§5) all change in the same PR so a fresh `migrate` + the full suite are green together; there is no interim state referencing an old name.

## 5. Code changes

### Models
- Rename `ExternalUser` → `Account` (`app/Modules/Identity/Domain/Models/Account.php`), still `extends Authenticatable`, `HasUlids`. Add `hasMany(LinkedIdentity::class)`.
- New `LinkedIdentity` model (`app/Modules/Identity/Domain/Models/LinkedIdentity.php`): `belongsTo(Account::class)`, `hasOne(LinkedIdentityToken::class)`, a `projectFromClaims(array $claims): self` that upserts by `(provider, subject_id)` and writes the claim snapshot.
- Rename `ExternalUserToken` → `LinkedIdentityToken`, FK `linked_identity_id`.

### Auth flow
- `CompleteLogin::handle($code)`: after token exchange + id-token validation, resolve the `LinkedIdentity` by `(provider='startup_gate', subject_id=claims['sub'])`; if absent, create it **with a new `Account`**; if present, reuse its `Account`. Upsert claim snapshot onto the link, set `last_login_at`. Replace the link's token row. Capture profile snapshot (now keyed on `account_id`). `Auth::login($account)` + `session()->regenerate()`. Audit `auth.login` with `actor_account_id`.
- `AuthController` login/logout: logout revokes + deletes the link's tokens, `Auth::guard('web')->logout()`, session invalidate/regenerate (same as today; token query now via the link).
- `MeController`: reads the access token via the account's `startup_gate` link.

### Auth wiring
- `config/auth.php` `users` provider → `Account::class`. (Remove the vestigial `use App\Models\User;`.)

### Blast-radius call sites (mechanical `external_user_id`→`account_id` / model-name)
`ResolveTenant` (membership query), `CreateOrganization` (owner membership write), `MembershipController`, `OrganizationController`, `MembershipResource`, `StoreMembershipRequest` (`exists:accounts,id`), `AdvanceParticipantStage`, `CaptureProfileSnapshot`, `AuditLogger` (`actor_account_id`).

### Tests/seams
- `tests/TestCase.php`: `makeExternalUser` → `makeAccount(array $overrides=[])` (creates an `Account` + attaches a `startup_gate` `LinkedIdentity` with a random `subject_id`, mirroring today's `sub-<uuid>`); `bootUserWithOrg` / `actingAsTenant` updated to `Account`. `actingAs($account, 'web')` unchanged.

## 6. Session shape (unchanged in 1a)

`/auth/callback` and `/auth/session` keep returning `{ user: { id, startup_gate_subject_id, email, display_name } }`. `startup_gate_subject_id` is read from the account's `startup_gate` link (always present today). `frontend/src/schemas/session.ts` and all SPA code are untouched. (SP-1b evolves the shape to expose linked providers and native-account fields.)

## 7. Testing

- **Regression (must stay green):** the existing OIDC suite — `AuthFlowTest`, `AuthSecurityTest`, the projection test (renamed `…AccountProjectionTest`), the token-lifecycle test — passes against the new model and the unchanged session JSON.
- **New:**
  - `LinkedIdentity` projection + uniqueness: upsert by `(provider, subject_id)`; second login with same `sub` reuses the same account + link (no duplicate account); unique constraints enforced.
  - **Schema-shape test** (replaces the data-migration test, since there is no data migration): after a fresh migrate, assert `Schema::hasTable('accounts')` and `Schema::hasTable('linked_identities')` and `Schema::hasTable('linked_identity_tokens')`; assert `!Schema::hasTable('external_users')` / `!hasTable('external_user_tokens')`; assert `accounts` has no `startup_gate_subject_id` column; assert `organization_memberships`/`profile_snapshots`/`participant_stage_statuses` have `account_id` (not `external_user_id`) and `audit_logs` has `actor_account_id`.
  - **End-to-end projection** (the data path that used to be a backfill): a full OIDC login creates exactly one `accounts` row + one `startup_gate` `linked_identities` row + one `linked_identity_tokens` row; a second login with the same `sub` reuses the same account (no duplicate); the owner membership written by `CreateOrganization` lands on `account_id`.
  - **Tenant isolation (AR-6):** re-verify fail-closed isolation on the renamed `organization_memberships.account_id` (cross-tenant → 404).
  - Audit write uses `actor_account_id`.
- **Gates:** Pint, PHPStan, the full backend feature suite, and the frontend typecheck/lint (frontend unchanged, so it must remain green with no edits).

## 8. Out of scope (later cycles)

- `password` / `email_verified_at` columns, native registration, password login, email verification, password reset, login & register UI, evolved session shape → **SP-1b**.
- Link/unlink management UX, "sign in with Startup Gate → match an existing account by verified email" → **SP-2**.
- Consented field-level profile import, source tracking, conflict preview → **SP-4**.
- Folding token columns onto `linked_identities`, a generic multi-provider federation framework, a second provider → **YAGNI** (the `provider` column leaves room; we don't build for it now).

## 9. Standing constraints honored

`organization_id` server-set on every tenant row (unchanged); tenant queries fail-closed, cross-tenant → 404 (re-verified on the renamed FK); `withoutGlobalScope('tenant')` not introduced (tests use the existing `TenantContext` seams); no secrets committed; decimal/immutability kernels untouched. Account id (ULID) is the primary user key; `sub` now lives on the `linked_identities` row; email is not yet a credential in 1a (becomes one in SP-1b).
