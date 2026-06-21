# SP-1b-i — Native Auth API (backend) — Design

- **Date:** 2026-06-22
- **Status:** Approved (design); pending implementation plan
- **Parent:** Epic 4 "Standalone Identity, Accounts & Profiles" → SP-1 → SP-1b. SP-1b is split into **SP-1b-i (this spec, backend native-auth API)** and **SP-1b-ii (frontend native-auth UI)**.
- **Builds on:** SP-1a (merged, PR #25) — the identity model is `Account` + `linked_identities`, Sanctum SPA cookie-session, OIDC login intact.
- **Implements:** FR-007 (native registration + email verification + password reset + session) — the backend half.

## 1. Purpose, scope & invariant

Add **native credential authentication** (register, password login, email verification, password reset) as **new** API endpoints on the existing `Account` model. The OIDC flow is untouched and stays green. **Backend only** — no frontend changes (SP-1b-ii). Because no native UI exists yet, no native-only accounts exist during this slice, so evolving the session JSON is safe for the live OIDC SPA: every existing user is OIDC-linked and keeps a non-null `startup_gate_subject_id`.

**Invariant:** the OIDC regression suite stays green; existing session/callback responses remain parseable by the current frontend (additive change, `sub` becomes nullable but is non-null for all existing users).

## 2. Current state (verified)

- `accounts` (ULID PK, `Authenticatable`, `HasUlids`): has `email` **nullable, NOT unique**; **no `password`, no `email_verified_at`**; does **not** implement `MustVerifyEmail`; does **not** use `Notifiable`.
- `password_reset_tokens` and `sessions` tables already exist (Laravel stub migration `0001_01_01_000000`). `password_reset_tokens` keys on `email`. (`SESSION_DRIVER=redis`, so the `sessions` table's bigint `user_id` is latent/unused — out of scope.)
- Mail is wired (Mailpit, `MAIL_MAILER=smtp`/`mailpit:1025`); `QUEUE_CONNECTION=redis`. No `Notification`/`Mailable` exists in `app/` yet — SP-1b-i introduces the first ones.
- Auth routes (`routes/api.php`): `GET /auth/login` (OIDC initiator → `{authorization_url}`), `POST /auth/callback`, `GET /auth/session` (auth:sanctum), `POST /auth/logout` (auth:sanctum). **No throttle anywhere in the app.**
- 422 error shape: `{ error: { code, message, correlation_id, details } }`; 401 `UNAUTHENTICATED`; 403 `FORBIDDEN`. FormRequest pattern: `authorize(): true` + array rules, policy checked in controller.
- `AuditLogger::record(action, targetType?, targetId?, before=[], after=[], result='success', organizationId=null)`; resolves `actor_account_id` from `$request->user()`.
- Session/callback return `{ user: { id, startup_gate_subject_id, email, display_name } }`; `/me` returns a richer `{ data: { …, avatar_url, locale } }`.
- Tests: sqlite `:memory:`, `RefreshDatabase`; OIDC harness via `Http::fake` + `MockKeys`.

## 3. Schema — one migration

Add to `accounts`:
- `password` (string, **nullable** — SSO-only accounts have none).
- `email_verified_at` (timestamptz, nullable).
- Make `email` **unique** (a partial/standard unique index; Postgres and SQLite both allow multiple NULLs, so SSO accounts without an email remain valid).

Data step (same migration or a paired one): **backfill `email_verified_at = now()` for every account that has a `startup_gate` linked identity** (the SG email is trusted). Reuse the existing `password_reset_tokens` table as-is.

Email is stored **normalized to lowercase** on every write path (register, and SG projection) so the unique index and login lookups are case-insensitive without relying on `citext` (keeps SQLite parity).

## 4. Account model changes

- `implements MustVerifyEmail`, `use Notifiable`.
- Casts: `password => 'hashed'`, `email_verified_at => 'datetime'`.
- `LinkedIdentity::projectFromClaims`: when creating/refreshing the account from SG claims, set `email_verified_at = now()` (SG-trusted), so OIDC accounts uniformly pass the org gate (§7). This is the only change to SP-1a's OIDC path and is behavior-preserving for the session JSON (verification was implicitly "trusted" before; now it's explicit).

## 5. Endpoints

All under `/api/v1/auth`, JSON in/out, Sanctum SPA session, throttled (§6). Notifications are queued (Redis).

| Method · Path | Body | Behavior |
|---|---|---|
| `POST /auth/register` | `{email, password, display_name?}` | Validate (email format + unique; password min length); create `Account` (hashed pw, lowercased email); send queued `VerifyEmail`; `Auth::login($account)` + `session()->regenerate()` (session issued, **unverified**); audit `auth.register` (target = new account id); return the session-user JSON (§8). |
| `POST /auth/password/login` | `{email, password}` | Look up by lowercased email; verify password (constant-time via the hasher); on success regenerate session + `Auth::login` + audit `auth.login`; **enumeration-safe** — unknown email and wrong password return the **same** generic 422 `{code: INVALID_CREDENTIALS}` (no user-existence leak). |
| `POST /auth/email/verify` | `{id, hash, expires, signature}` | Validate the Laravel signed payload (60-min expiry) against the account; if valid and unverified, set `email_verified_at`, fire `Verified`; audit `auth.email_verified`; idempotent (already-verified → 200). Tampered/expired → 403. |
| `POST /auth/email/resend` | — (auth:sanctum) | If the authed account is unverified, resend `VerifyEmail` (throttled); 204. |
| `POST /auth/password/forgot` | `{email}` | `Password::sendResetLink` (queued `ResetPassword` notification with a **frontend** URL carrying token+email); **always 200** regardless of existence (no enumeration); audit `auth.password_reset_requested`. |
| `POST /auth/password/reset` | `{email, token, password}` | `Password::reset` → set new hashed password; on success audit `auth.password_reset_completed`; invalid/expired token → 422 `{code: INVALID_RESET_TOKEN}`. |

`logout` / `session` / `me` keep their existing handlers; their response uses the evolved resource (§8). Native login lives at `/auth/password/login` because `GET /auth/login` is the OIDC initiator.

## 6. Email, throttle, audit

- **Notifications** (first in the codebase): queued `VerifyEmail` and `ResetPassword` classes whose URLs target the **frontend** (built from a `FRONTEND_URL` config): `…/auth/verify-email?id=…&hash=…&expires=…&signature=…` (Laravel-signed; the `/auth/email/verify` endpoint re-validates) and `…/auth/reset-password?token=…&email=…`. Mailpit in dev; the queue connection is Redis (sync in tests).
- **Rate limiting** (none exists today): named limiters registered for `register`, `password-login`, `email-resend`, `password-forgot`, keyed by `IP + email` (default **6/min**, tunable via config), applied as `throttle:` middleware on those routes → 429 with the standard error envelope.
- **Audit** actions: `auth.register`, `auth.login`, `auth.email_verified`, `auth.password_reset_requested`, `auth.password_reset_completed`. These are org-less (no tenant yet); `actor_account_id` is the account where a session exists (register/login/verify/resend) and null for the pre-session forgot/reset, with `targetId` = the account id where known.

## 7. Org-create email-verified gate

A small API middleware `EnsureEmailVerified` returning **403 `{code: EMAIL_NOT_VERIFIED}`** when `! $request->user()->hasVerifiedEmail()`. Applied to the org-onboarding writes: `POST /organizations` (create) and the membership-join path. The check is uniform: verified native accounts pass; **all SG-linked accounts pass** (auto-verified in §4). Unverified native accounts are blocked from creating/joining an org but retain their session and can resend verification.

## 8. Evolved session JSON (contract for SP-1b-ii)

A single `AccountSessionResource` used by `register`, `password/login`, `callback`, and `session`:
```json
{ "user": {
  "id": "<ulid>",
  "email": "<string|null>",
  "display_name": "<string|null>",
  "email_verified": true,
  "startup_gate_subject_id": "<string|null>",
  "linked_providers": ["startup_gate"],
  "has_password": false
}}
```
- `email_verified` = `hasVerifiedEmail()`. `linked_providers` = the account's `linked_identities.provider` list. `has_password` = `password !== null` (can use native login).
- **`startup_gate_subject_id` becomes nullable** but is non-null for every existing (OIDC) user, so the current frontend keeps working; the schema fix + new-field consumption is SP-1b-ii. `/me` keeps its `data` wrapper + extra fields, also extended with `email_verified`/`linked_providers`/`has_password` for consistency.

## 9. Testing (sqlite, RefreshDatabase, `Notification::fake`, queue sync)

- **register:** creates the account (hashed pw, lowercased email), issues a session (unverified), queues exactly one `VerifyEmail`; duplicate email → 422; weak password → 422.
- **password/login:** correct creds → session + `auth.login` audit; wrong password and unknown email return the **identical** generic 422 (assert byte-equal bodies — the enumeration guard); a SSO-only account (null password) cannot native-login.
- **email/verify:** valid signed payload → verified + `Verified` fired + audit; tampered signature / expired / wrong account → 403; already-verified → 200 idempotent.
- **email/resend:** unverified authed account resends; throttle returns 429 after the limit.
- **password/forgot:** known + unknown email both → 200 (no enumeration); a `ResetPassword` notification is queued only for the known email (asserted via `Notification::fake`).
- **password/reset:** valid token sets new password (old fails, new logs in); invalid/expired token → 422.
- **org-create gate:** unverified native → 403 `EMAIL_NOT_VERIFIED`; verified native → 201; OIDC-linked account → 201.
- **throttle:** the four limited endpoints return 429 past the configured rate.
- **session shape:** `AccountSessionResource` returns the §8 fields for both a native and an OIDC account; OIDC regression suite (`AuthFlowTest`/`AuthSecurityTest`) stays green.
- **Gates:** `php artisan test`, `vendor/bin/pint`, `vendor/bin/phpstan analyse`.

## 10. Out of scope (later cycles)

- **All frontend** — register/login/verify/reset pages, the session-schema fix (`startup_gate_subject_id` nullable + new fields), CSRF preflight / fetch wrapper → **SP-1b-ii**.
- Link/unlink an SG identity to an existing native account, and "sign in with Startup Gate that matches an existing native account by verified email" → **SP-2**.
- Consented field-level profile import → **SP-4**.
- 2FA, passkeys/WebAuthn, "remember me", social providers beyond Startup Gate, account lockout policies beyond rate-limiting → **YAGNI**.

## 11. Standing constraints honored

`organization_id` server-set; tenant queries fail-closed, cross-tenant → 404 (unchanged); no new `withoutGlobalScope('tenant')`; no secrets committed (mail/queue via env); decimal/immutability kernels untouched. Passwords are hashed (`'hashed'` cast, bcrypt); no raw credentials logged or audited (audit records actions + ids only, never the password or reset token). Email is a **local** login credential only — never a cross-system identifier (CLAUDE rule 5); the Account id (ULID) remains the primary key and `sub` stays on the link.
