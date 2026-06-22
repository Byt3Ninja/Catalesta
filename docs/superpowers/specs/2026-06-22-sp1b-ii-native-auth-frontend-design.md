# SP-1b-ii — Native Auth UI (frontend) — Design

- **Date:** 2026-06-22
- **Status:** Approved (design); pending implementation plan
- **Parent:** Epic 4 "Standalone Identity, Accounts & Profiles" → SP-1 → SP-1b. SP-1b split into **SP-1b-i (backend native-auth API, merged)** and **SP-1b-ii (this spec, frontend native-auth UI)**.
- **Builds on:** SP-1b-i (merged, `origin/main` @ `ecd9fa8`) — native-auth endpoints + the evolved `AccountSessionResource`. Identity model is `Account` + `linked_identities`; Sanctum SPA cookie-session; OIDC login intact.
- **Implements:** FR-007 (native registration + email verification + password reset + login) — the frontend half.

## 1. Purpose, scope & invariant

Add the **native credential UI** to the existing React SPA: register, password login (alongside the existing Startup Gate SSO), forgot/reset password, an email-verified landing page, and an unverified-account "check your email / resend" interstitial. Wire the **session-schema evolution** (consume the new `AccountSessionResource` fields) and the **CSRF preflight** the SPA has been missing. **Frontend only** — no backend changes; the SP-1b-i API is the fixed contract.

**Invariant:** the existing OIDC sign-in path keeps working end-to-end. The session-schema change is backward-compatible at runtime because every existing user is OIDC-linked (`startup_gate_subject_id` non-null), and the new fields are always present in the SP-1b-i response.

## 2. Current state (verified)

- **No router.** `src/app/App.tsx` matches routes with regexes over `window.location.pathname` (`LOGIN_ROUTE`, `CALLBACK_ROUTE`, …) and renders the matched page. `ConsoleGate` drives a `['session']` + `['organizations']` query and decides unauthenticated → `LoginPage`, authed+no-org → `OnboardingPage`, authed+org → `HomePage`.
- **Form pattern** (`OnboardingPage`): `useState` + `Field` + `Button` (has `loading`) + `Banner` (`variant="error"`) + `FormLayout`, with a React Query `useMutation` that invalidates the relevant query on success. `Field` passes through `InputHTMLAttributes` (so `type="password"` works) and wires accessible error association (`aria-invalid`/`aria-describedby`).
- **API clients** (`src/api/*.ts`): `fetch` with `credentials:'include'`; map `{ error: { code, message, correlation_id, details } }` via `readValidationDetails`/`fieldMessage`/`firstValidationMessage` (`src/api/errors.ts`); throw typed `ApiError` subclasses (e.g. `CreateOrgError`, `SessionError`).
- **Session client** (`src/api/session.ts`): `getSession` (GET `/auth/session`, 401→`SessionError('UNAUTHENTICATED')`), `beginLogin` (GET `/auth/login` → redirect; stashes `postLoginRedirect` in `sessionStorage`), `completeLogin` (POST `/auth/callback`). `AuthCallbackPage` invalidates `['session']` then redirects to the captured path with a same-origin open-redirect guard (`startsWith('/') && !startsWith('//')`).
- **Session schema** (`src/schemas/session.ts`): `sessionUserSchema = { id, startup_gate_subject_id: string, email: nullable, display_name: nullable }` — `startup_gate_subject_id` is **required**, and the three new SP-1b-i fields are absent.
- **CSRF:** the SPA sends mutating POSTs (`/auth/callback`, `/organizations`) with **no** CSRF token. Backend uses `statefulApi()` (Sanctum `EnsureFrontendRequestsAreStateful`), which enforces CSRF for cookie-authenticated requests → real-browser mutations would 419. Latent gap; in scope here.
- `API_BASE_URL` (`src/api/client.ts`) = `import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1'`. The Sanctum CSRF-cookie route is at the **app root** (`/sanctum/csrf-cookie`), not under `/api/v1`.
- **Conventions:** every page has a sibling `*.test.tsx` and `*.stories.tsx`; design-system classes (`ds-*`); a11y is tested (`src/tests/a11y.test.tsx`).
- Deps available: `@tanstack/react-query`, `zod`, `react-hook-form` (+ `@hookform/resolvers`). Existing auth-adjacent forms use plain `useState`, **not** RHF; this slice follows the existing `useState` pattern (YAGNI — do not introduce RHF for these forms).

## 3. Backend contract (fixed — SP-1b-i, as merged)

All under `/api/v1`. JSON in/out, Sanctum SPA session, throttled (429 → standard error envelope).

| Method · Path | Body | Success | Failure |
|---|---|---|---|
| `POST /auth/register` | `{email, password, display_name?}` | **201** + session-user (§4); session issued (unverified); `VerifyEmail` queued | 422 `error.details.email` (taken / invalid); 422 password rules |
| `POST /auth/password/login` | `{email, password}` | **200** + session-user | **422** generic `error.details.email = ["These credentials do not match our records."]` — identical for unknown email, wrong password, and SSO-only (null-password) accounts (enumeration-safe) |
| `POST /auth/password/forgot` | `{email}` | **200** `{message}` always (no enumeration) | — |
| `POST /auth/password/reset` | `{token, email, password}` | **200** `{message}` | 422 `error.details.email` (invalid/expired token; password rules `min:8`) |
| `GET /auth/email/verify/{id}/{hash}` *(signed)* | — | backend verifies server-side then **HTTP-redirects** to `FRONTEND_URL/auth/email-verified` | tampered/expired → 403 |
| `POST /auth/email/resend` *(auth:sanctum)* | — | **204** | — |

- The **verify email link points at the backend** signed route; the user never lands on the frontend with a token — only on `/auth/email-verified` after the redirect. The frontend verify page is therefore **informational only**.
- The **reset email link** points at the frontend: `FRONTEND_URL/auth/reset-password?token=<token>&email=<urlencoded>`. The reset page reads both from the query string and submits them.
- Org-create/join is gated by `EnsureEmailVerified` → **403 `error.code = "EMAIL_NOT_VERIFIED"`** for unverified native accounts (SG accounts auto-verified).

## 4. Session-user contract (`AccountSessionResource`)

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
Returned by `register`, `password/login`, `callback`, and `session`. The frontend schema must make `startup_gate_subject_id` **nullable** and add `email_verified` (bool), `linked_providers` (string[]), `has_password` (bool).

## 5. Components & files

### 5.1 Plumbing (cross-cutting)

- **`src/api/client.ts`** — add `APP_BASE_URL` = `API_BASE_URL` with a trailing `/api/v1` (and any trailing slash) stripped, so `${APP_BASE_URL}/sanctum/csrf-cookie` resolves at the app root. Overridable via `VITE_APP_BASE_URL` for environments where the derivation doesn't hold.
- **`src/api/csrf.ts`** *(new)* — `csrfFetch(path: string, init: RequestInit): Promise<Response>`:
  1. If no `XSRF-TOKEN` cookie is present, `await fetch(\`${APP_BASE_URL}/sanctum/csrf-cookie\`, { credentials:'include' })`.
  2. Read the `XSRF-TOKEN` cookie, `decodeURIComponent` it (Laravel URL-encodes the value), and set header `X-XSRF-TOKEN`.
  3. Call `fetch(\`${API_BASE_URL}${path}\`, { ...init, credentials:'include', headers: { 'Content-Type':'application/json', 'X-XSRF-TOKEN': token, ...init.headers } })`.
  - Idempotent preflight: only fetches the cookie when absent. Cookie read is a small `document.cookie` parse helper.
  - **Retrofit:** `completeLogin` (`/auth/callback`) and `createOrganization` (`/organizations`) switch to `csrfFetch` so the existing latent CSRF gap closes. Their response handling is otherwise unchanged.
- **`src/schemas/session.ts`** — evolve `sessionUserSchema` per §4: `startup_gate_subject_id: z.string().nullable()`, add `email_verified: z.boolean()`, `linked_providers: z.array(z.string())`, `has_password: z.boolean()`. Update `session.test.ts` fixtures to the new shape.
- **`src/api/auth.ts`** *(new)* — native-auth client. `NativeAuthError extends ApiError<NativeAuthCode>` with `NativeAuthCode = 'INVALID_CREDENTIALS' | 'EMAIL_TAKEN' | 'INVALID_RESET_TOKEN' | 'RATE_LIMITED' | 'UNKNOWN'`.
  - `register({email, password, displayName?})` → `csrfFetch` POST; 201 → `sessionResponseSchema.parse(json).user`; 422 with `details.email` → `EMAIL_TAKEN` (carry server message); other 422 → `UNKNOWN` with first field message; 429 → `RATE_LIMITED`.
  - `passwordLogin({email, password})` → POST; 200 → user; 422 → `INVALID_CREDENTIALS` (generic — never inspect which field, never branch on user existence); 429 → `RATE_LIMITED`.
  - `forgotPassword(email)` → POST; resolves on 200 (and treats any non-429 as success-shaped to avoid enumeration); 429 → `RATE_LIMITED`.
  - `resetPassword({token, email, password})` → POST; 200 → void; 422 → `INVALID_RESET_TOKEN` (carry message); 429 → `RATE_LIMITED`.
  - `resendVerification()` → POST `/auth/email/resend`; 204 → void; 429 → `RATE_LIMITED`.

### 5.2 Pages (each with sibling `*.test.tsx` + `*.stories.tsx`)

- **`src/pages/RegisterPage.tsx`** (`/register`) — `Field`s for email, password (`type="password"`), optional display name; mutation → `register`. On success: invalidate `['session']`, then navigate to the captured post-login path (reuse the `postLoginRedirect` + open-redirect guard, factored into a shared helper — see §5.3). `EMAIL_TAKEN` renders under the email field; other errors → `Banner`. Link to `/login`.
- **`src/pages/LoginPage.tsx`** (extend `/login`) — native email/password form **first** (mutation → `passwordLogin`; success → invalidate `['session']` + navigate to captured path); a divider; the existing "Sign in with Startup Gate" `Button` (→ `beginLogin`) below; links to `/register` and `/forgot-password`. `INVALID_CREDENTIALS` and any other failure render **one** generic error banner ("These details don't match our records.") — no field-level leak.
- **`src/pages/ForgotPasswordPage.tsx`** (`/forgot-password`) — email `Field` → `forgotPassword`; on resolve, replace the form with a neutral confirmation ("If that email is registered, we've sent a reset link."). Link back to `/login`.
- **`src/pages/ResetPasswordPage.tsx`** (`/auth/reset-password`) — read `token` + `email` from `window.location.search`; if either missing → error state with a link to `/forgot-password`. New-password `Field` (min 8, enforced client-side for UX + server-authoritative) → `resetPassword`. Success → confirmation + link to `/login`. `INVALID_RESET_TOKEN` → error banner with a `/forgot-password` link.
- **`src/pages/EmailVerifiedPage.tsx`** (`/auth/email-verified`) — informational ("Your email is verified."); a "Continue" `Button` that invalidates `['session']` (so the verified flag refreshes) and navigates to `/`.

### 5.3 Routing, redirect helper & gate

- **`src/app/App.tsx`** — add route regexes/branches for `/register`, `/forgot-password`, `/auth/reset-password`, `/auth/email-verified` (all public — render without `ConsoleGate`). `/login` stays public.
- **Redirect helper** — extract the existing `AuthCallbackPage` logic (`read postLoginRedirect → same-origin guard → assign`) into a small shared function (e.g. `src/api/postLoginRedirect.ts` with `consumePostLoginRedirect(): string`) reused by callback, login, and register. No behavior change to callback.
- **Unverified interstitial** — in `ConsoleGate`, after `sessionQuery.isSuccess`, if `sessionQuery.data.email_verified === false`: render a `VerifyEmailNotice` ("Check your inbox to verify your email" + a **Resend** `Button` → `resendVerification`, showing a confirmation on 204) **before** the orgs/onboarding branch. SG-linked accounts are auto-verified (`email_verified === true`) and never see it. This makes the org-create `EMAIL_NOT_VERIFIED` 403 effectively unreachable from the UI, but the typed code is still mapped defensively.

## 6. Error handling (summary)

| Surface | Condition | UX |
|---|---|---|
| Register | `EMAIL_TAKEN` | message under email field |
| Login | any failure | one generic banner (no enumeration) |
| Forgot | any non-429 | neutral success confirmation |
| Reset | `INVALID_RESET_TOKEN` / missing query | error banner + link to `/forgot-password` |
| Any | `RATE_LIMITED` (429) | "Too many attempts. Please try again shortly." |
| Any | network/unknown | generic "Something went wrong. Please try again." |

## 7. Testing

- **`csrf.test.ts`** — fetch-mocked: asserts the cookie preflight fires when `XSRF-TOKEN` is absent, is skipped when present, and the decoded `X-XSRF-TOKEN` header is sent on the mutation.
- **`auth.test.ts`** — each call's success + mapped-error paths; specifically the login mapping returns the **same** generic `INVALID_CREDENTIALS` regardless of 422 detail (enumeration guard), and register maps `details.email` → `EMAIL_TAKEN`.
- **`session.test.ts`** — updated for the §4 shape (nullable `startup_gate_subject_id` + the three new fields), incl. a native-account fixture (`startup_gate_subject_id: null`).
- **Page tests** — render + submit happy path and the primary error path for each page; Login asserts a single generic banner; Reset asserts the missing-query and invalid-token states; the gate test asserts an unverified session renders `VerifyEmailNotice` and a verified one does not.
- **a11y** — extend `src/tests/a11y.test.tsx` coverage to the new pages (labelled inputs, error association via `Field`).
- **Stories** — a `*.stories.tsx` per new page (default + error/confirmation states) per repo convention.
- **Gates:** `npm run lint`, `npm run test`, `npm run build`.

## 8. Out of scope (later cycles)

- SG link/unlink to an existing native account; "sign in with SG that matches a native account by verified email" → **SP-2**.
- Consented field-level profile import → **SP-4**.
- 2FA, passkeys/WebAuthn, "remember me", social providers beyond SG, client-side lockout → **YAGNI**.
- Adopting `react-router` — keep the regex matcher (no new dependency for 4 routes).
- Any backend change — the SP-1b-i contract is fixed.

## 9. Standing constraints honored

Email is shown/collected as a **local** login credential only — never used as a cross-system identifier; the session is keyed on the Account `id` (ULID), and `startup_gate_subject_id` is display-only and may be null. Passwords and reset tokens are never logged or persisted client-side (no `localStorage`); the reset token lives only in the URL the user arrived with and in the single POST body. Mutations are CSRF-protected and cookie-scoped (`credentials:'include'`). The open-redirect guard on the post-login destination is preserved. No secrets in the bundle (only public base URLs via `VITE_*`).
