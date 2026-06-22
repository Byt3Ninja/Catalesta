# SP-1b-ii — Native Auth UI (frontend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add native-credential auth UI (register, password login, forgot/reset, email-verified landing, unverified-account resend interstitial) to the existing React SPA, with the session-schema evolution and CSRF preflight those flows require.

**Architecture:** Frontend-only slice consuming the fixed SP-1b-i API. No router — `App.tsx` matches route regexes over `window.location.pathname`. Forms use `useState` + design-system primitives (`Field`/`Button`/`Banner`/`FormLayout`) + React Query `useMutation`. All mutations go through a new CSRF-aware fetch wrapper.

**Tech Stack:** React 19, TypeScript, `@tanstack/react-query` v5, `zod` v4, Vitest + `@testing-library/react`, Storybook (`@storybook/react-vite`). Run all commands from `frontend/`.

**Spec:** `docs/superpowers/specs/2026-06-22-sp1b-ii-native-auth-frontend-design.md`

## Global Constraints

- **No `react-router`** — add new routes as regexes in `src/app/App.tsx`, matching the existing pattern. No new runtime dependency.
- **Forms use `useState`**, not `react-hook-form` (the existing auth-adjacent forms do; YAGNI).
- **`Button` defaults to `type="button"`** — submit buttons MUST pass `type="submit"`.
- **Enumeration-safe login:** never branch on which 422 field failed and never reveal user existence — any login failure maps to one generic `INVALID_CREDENTIALS`.
- **Never persist** passwords or reset tokens to `localStorage`/`sessionStorage`; the reset token lives only in the URL and the POST body.
- **All mutations** use `credentials:'include'` and the CSRF wrapper.
- **Email** is a local login credential only — never used as a cross-system identifier. Session is keyed on Account `id` (ULID); `startup_gate_subject_id` is display-only and may be null.
- Backend base: `API_BASE_URL` = `import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1'`. CSRF cookie route is at the **app root** (`/sanctum/csrf-cookie`), not under `/api/v1`.
- Error envelope: `{ error: { code, message, correlation_id, details? } }`; helpers in `src/api/errors.ts` (`readValidationDetails`, `fieldMessage`, `firstValidationMessage`).
- Test helper: `jsonResponse(body, status=200)` from `src/tests/test-utils.ts`; mock fetch via `vi.spyOn(globalThis,'fetch')`.
- Gates per task: `npm run lint && npm run test`. Final task also `npm run build`.

---

### Task 1: Session-schema evolution

**Files:**
- Modify: `frontend/src/schemas/session.ts`
- Test: `frontend/src/api/session.test.ts` (update fixtures)

**Interfaces:**
- Produces: `sessionUserSchema` / `SessionUser` with shape `{ id: string; email: string|null; display_name: string|null; email_verified: boolean; startup_gate_subject_id: string|null; linked_providers: string[]; has_password: boolean }`. `sessionResponseSchema`, `loginUrlSchema`, `SessionError`, `SessionErrorCode` unchanged.

- [ ] **Step 1: Update the existing session test fixture to the new shape and add a native-account case**

In `frontend/src/api/session.test.ts`, replace the `USER` constant and add a native fixture:

```ts
const USER = {
  id: 'user-1',
  startup_gate_subject_id: 'sub-1',
  email: 'op@example.com',
  display_name: 'Operator',
  email_verified: true,
  linked_providers: ['startup_gate'],
  has_password: false,
}

const NATIVE_USER = {
  id: 'user-2',
  startup_gate_subject_id: null,
  email: 'native@example.com',
  display_name: 'Native',
  email_verified: false,
  linked_providers: [],
  has_password: true,
}
```

Add a test after the existing `getSession` 200 test:

```ts
test('getSession parses a native account (null sub, new fields)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ user: NATIVE_USER }))
  await expect(getSession()).resolves.toMatchObject({
    startup_gate_subject_id: null,
    email_verified: false,
    has_password: true,
    linked_providers: [],
  })
})
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd frontend && npm run test -- src/api/session.test.ts`
Expected: FAIL — the new test errors because `sessionUserSchema` rejects `startup_gate_subject_id: null` and is missing the new fields.

- [ ] **Step 3: Evolve the schema**

In `frontend/src/schemas/session.ts`, replace `sessionUserSchema`:

```ts
/**
 * Current-user projection (SP-1b-ii). Keyed on the Account id (ULID) — never email
 * (CLAUDE.md 4/5). `startup_gate_subject_id` is nullable: native accounts have no SG
 * link. `linked_providers`/`has_password` describe which sign-in methods apply.
 * [Source: backend AccountSessionResource]
 */
export const sessionUserSchema = z.object({
  id: z.string(),
  email: z.string().nullable(),
  display_name: z.string().nullable(),
  email_verified: z.boolean(),
  startup_gate_subject_id: z.string().nullable(),
  linked_providers: z.array(z.string()),
  has_password: z.boolean(),
})
```

Leave `sessionResponseSchema`, `loginUrlSchema`, `SessionError`, `SessionErrorCode` unchanged.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd frontend && npm run test -- src/api/session.test.ts`
Expected: PASS (all session tests).

- [ ] **Step 5: Lint and commit**

```bash
cd frontend && npm run lint
git add src/schemas/session.ts src/api/session.test.ts
git commit -m "feat(fe): evolve session schema for native accounts (SP-1b-ii task 1)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: CSRF fetch wrapper + retrofit

**Files:**
- Modify: `frontend/src/api/client.ts`
- Create: `frontend/src/api/csrf.ts`
- Create: `frontend/src/api/csrf.test.ts`
- Modify: `frontend/src/api/session.ts` (`completeLogin`)
- Modify: `frontend/src/api/organizations.ts` (`createOrganization`)

**Interfaces:**
- Consumes: `API_BASE_URL` from `client.ts`.
- Produces:
  - `APP_BASE_URL: string` in `client.ts` (API base with a trailing `/api/v1` and slash stripped).
  - `csrfFetch(path: string, init?: RequestInit): Promise<Response>` in `csrf.ts` — prepends `API_BASE_URL` to `path`, ensures the XSRF cookie (one preflight `GET ${APP_BASE_URL}/sanctum/csrf-cookie` when absent), sends `credentials:'include'`, `Content-Type: application/json`, and the decoded `X-XSRF-TOKEN` header.

- [ ] **Step 1: Write the failing CSRF test**

Create `frontend/src/api/csrf.test.ts`:

```ts
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { csrfFetch } from './csrf'
import { jsonResponse } from '../tests/test-utils'

function setCookie(value: string) {
  Object.defineProperty(document, 'cookie', { value, writable: true, configurable: true })
}

beforeEach(() => setCookie(''))
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

test('preflights the csrf cookie when XSRF-TOKEN is absent, then sends the decoded header', async () => {
  const fetchMock = vi
    .spyOn(globalThis, 'fetch')
    .mockImplementationOnce(async () => {
      // Simulate Sanctum setting the (URL-encoded) cookie during preflight.
      setCookie('XSRF-TOKEN=tok%2Bvalue')
      return new Response(null, { status: 204 })
    })
    .mockResolvedValueOnce(jsonResponse({ ok: true }, 200))

  await csrfFetch('/auth/register', { method: 'POST', body: JSON.stringify({}) })

  expect(fetchMock.mock.calls[0][0]).toContain('/sanctum/csrf-cookie')
  const [, init] = fetchMock.mock.calls[1]
  const headers = new Headers(init?.headers)
  expect(headers.get('X-XSRF-TOKEN')).toBe('tok+value') // decoded
  expect(init?.credentials).toBe('include')
})

test('skips the preflight when the cookie is already present', async () => {
  setCookie('XSRF-TOKEN=already')
  const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ ok: true }))

  await csrfFetch('/auth/login', { method: 'POST', body: '{}' })

  expect(fetchMock).toHaveBeenCalledTimes(1)
  expect(fetchMock.mock.calls[0][0]).toContain('/auth/login')
})
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd frontend && npm run test -- src/api/csrf.test.ts`
Expected: FAIL — `csrf.ts` / `csrfFetch` does not exist.

- [ ] **Step 3: Add `APP_BASE_URL` to client.ts**

Append to `frontend/src/api/client.ts`:

```ts
/**
 * App-root base (no `/api/v1`). Sanctum's CSRF-cookie route lives at the app root,
 * not under the versioned API prefix. Overridable for environments where stripping
 * the suffix from the API base doesn't hold.
 */
export const APP_BASE_URL: string =
  import.meta.env.VITE_APP_BASE_URL ?? API_BASE_URL.replace(/\/api\/v1\/?$/, '')
```

- [ ] **Step 4: Implement `csrfFetch`**

Create `frontend/src/api/csrf.ts`:

```ts
import { API_BASE_URL, APP_BASE_URL } from './client'

/** Read a cookie value by name from document.cookie, or undefined. */
function readCookie(name: string): string | undefined {
  const match = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`))
  return match ? match.slice(name.length + 1) : undefined
}

/**
 * CSRF-aware fetch for state-changing API calls (SP-1b-ii). Sanctum's statefulApi()
 * enforces CSRF for cookie-authenticated requests, so every mutation must carry the
 * X-XSRF-TOKEN header. The preflight runs only when the cookie is absent (idempotent).
 * Laravel URL-encodes the cookie value, so it is decoded before being sent as a header.
 */
export async function csrfFetch(path: string, init: RequestInit = {}): Promise<Response> {
  if (readCookie('XSRF-TOKEN') === undefined) {
    await fetch(`${APP_BASE_URL}/sanctum/csrf-cookie`, { credentials: 'include' })
  }
  const token = readCookie('XSRF-TOKEN')
  const headers = new Headers(init.headers)
  headers.set('Content-Type', 'application/json')
  if (token !== undefined) {
    headers.set('X-XSRF-TOKEN', decodeURIComponent(token))
  }
  return fetch(`${API_BASE_URL}${path}`, { ...init, credentials: 'include', headers })
}
```

- [ ] **Step 5: Run the CSRF test to verify it passes**

Run: `cd frontend && npm run test -- src/api/csrf.test.ts`
Expected: PASS (both tests).

- [ ] **Step 6: Retrofit `completeLogin` to use `csrfFetch`**

In `frontend/src/api/session.ts`, change `completeLogin` to route through the wrapper (drop the manual `fetch`/headers/credentials):

```ts
import { csrfFetch } from './csrf'
```

```ts
export async function completeLogin(state: string, code: string): Promise<SessionUser> {
  const response = await csrfFetch('/auth/callback', {
    method: 'POST',
    body: JSON.stringify({ state, code }),
  })
  if (!response.ok) {
    throw new SessionError('UNKNOWN', `login callback failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return sessionResponseSchema.parse(json).user
}
```

Leave `getSession` and `beginLogin` (GET requests) unchanged.

- [ ] **Step 7: Retrofit `createOrganization` to use `csrfFetch`**

In `frontend/src/api/organizations.ts`, change the POST in `createOrganization`:

```ts
import { csrfFetch } from './csrf'
```

```ts
  const response = await csrfFetch('/organizations', {
    method: 'POST',
    body: JSON.stringify({ name }),
  })
```

Leave the status handling (201/401/422) and `listOrganizations` (GET) unchanged.

- [ ] **Step 8: Run the retrofitted clients' tests**

Run: `cd frontend && npm run test -- src/api/session.test.ts src/api/organizations.test.ts`
Expected: PASS. The existing `completeLogin` test asserts `init?.method === 'POST'` and the JSON body — still true through `csrfFetch`. If a test stubbed `document.cookie` is needed, the wrapper reads it via `readCookie`; the existing mocks return the POST response on the first `fetch` call because the tests set no cookie — **note:** the wrapper will preflight first. Update those two tests to set the cookie before calling so only one `fetch` is asserted:

In `session.test.ts` `completeLogin` test and `organizations.test.ts` create tests, add at the top of each affected test:

```ts
Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
```

Re-run until PASS.

- [ ] **Step 9: Lint and commit**

```bash
cd frontend && npm run lint
git add src/api/client.ts src/api/csrf.ts src/api/csrf.test.ts src/api/session.ts src/api/session.test.ts src/api/organizations.ts src/api/organizations.test.ts
git commit -m "feat(fe): CSRF preflight fetch wrapper + retrofit callback/create-org (SP-1b-ii task 2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Native-auth API client

**Files:**
- Create: `frontend/src/api/auth.ts`
- Create: `frontend/src/api/auth.test.ts`

**Interfaces:**
- Consumes: `csrfFetch` (Task 2), `sessionResponseSchema`/`SessionUser` (`schemas/session.ts`), `ApiError` (`api/errors.ts`), `readValidationDetails`/`fieldMessage`/`firstValidationMessage` (`api/errors.ts`).
- Produces:
  - `NativeAuthCode = 'INVALID_CREDENTIALS' | 'EMAIL_TAKEN' | 'INVALID_RESET_TOKEN' | 'RATE_LIMITED' | 'UNKNOWN'`
  - `class NativeAuthError extends ApiError<NativeAuthCode>` (`name = 'NativeAuthError'`)
  - `register(input: { email: string; password: string; displayName?: string }): Promise<SessionUser>`
  - `passwordLogin(input: { email: string; password: string }): Promise<SessionUser>`
  - `forgotPassword(email: string): Promise<void>`
  - `resetPassword(input: { token: string; email: string; password: string }): Promise<void>`
  - `resendVerification(): Promise<void>`

- [ ] **Step 1: Write the failing tests**

Create `frontend/src/api/auth.test.ts`:

```ts
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { register, passwordLogin, forgotPassword, resetPassword, resendVerification } from './auth'
import { jsonResponse } from '../tests/test-utils'

const USER = {
  id: 'u1', email: 'a@b.com', display_name: null,
  email_verified: false, startup_gate_subject_id: null,
  linked_providers: [], has_password: true,
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('register returns the user on 201', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ user: USER }, 201))
  await expect(register({ email: 'a@b.com', password: 'super-secret' })).resolves.toMatchObject({ id: 'u1' })
})

test('register maps a taken email (422 details.email) to EMAIL_TAKEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['The email has already been taken.'] } } }, 422),
  )
  await expect(register({ email: 'a@b.com', password: 'x' })).rejects.toMatchObject({
    name: 'NativeAuthError', code: 'EMAIL_TAKEN',
  })
})

test('passwordLogin maps any 422 to a generic INVALID_CREDENTIALS', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['These credentials do not match our records.'] } } }, 422),
  )
  await expect(passwordLogin({ email: 'a@b.com', password: 'nope' })).rejects.toMatchObject({
    name: 'NativeAuthError', code: 'INVALID_CREDENTIALS',
  })
})

test('passwordLogin returns the user on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ user: USER }))
  await expect(passwordLogin({ email: 'a@b.com', password: 'super-secret' })).resolves.toMatchObject({ id: 'u1' })
})

test('forgotPassword resolves on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ message: 'ok' }))
  await expect(forgotPassword('a@b.com')).resolves.toBeUndefined()
})

test('forgotPassword maps 429 to RATE_LIMITED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ error: { code: 'HTTP_429' } }, 429))
  await expect(forgotPassword('a@b.com')).rejects.toMatchObject({ code: 'RATE_LIMITED' })
})

test('resetPassword maps 422 to INVALID_RESET_TOKEN', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['This password reset token is invalid or has expired.'] } } }, 422),
  )
  await expect(resetPassword({ token: 't', email: 'a@b.com', password: 'super-secret' })).rejects.toMatchObject({
    code: 'INVALID_RESET_TOKEN',
  })
})

test('resetPassword resolves on 200', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ message: 'ok' }))
  await expect(resetPassword({ token: 't', email: 'a@b.com', password: 'super-secret' })).resolves.toBeUndefined()
})

test('resendVerification resolves on 204', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 }))
  await expect(resendVerification()).resolves.toBeUndefined()
})
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd frontend && npm run test -- src/api/auth.test.ts`
Expected: FAIL — `auth.ts` does not exist.

- [ ] **Step 3: Implement the client**

Create `frontend/src/api/auth.ts`:

```ts
import { csrfFetch } from './csrf'
import { ApiError, fieldMessage, firstValidationMessage, readValidationDetails } from './errors'
import { sessionResponseSchema, type SessionUser } from '../schemas/session'

export type NativeAuthCode =
  | 'INVALID_CREDENTIALS'
  | 'EMAIL_TAKEN'
  | 'INVALID_RESET_TOKEN'
  | 'RATE_LIMITED'
  | 'UNKNOWN'

/** Typed native-auth error. Login failures are deliberately collapsed to one code. */
export class NativeAuthError extends ApiError<NativeAuthCode> {
  constructor(code: NativeAuthCode, message?: string) {
    super(code, message)
    this.name = 'NativeAuthError'
  }
}

async function parseUser(response: Response): Promise<SessionUser> {
  const json: unknown = await response.json()
  return sessionResponseSchema.parse(json).user
}

export async function register(input: {
  email: string
  password: string
  displayName?: string
}): Promise<SessionUser> {
  const response = await csrfFetch('/auth/register', {
    method: 'POST',
    body: JSON.stringify({
      email: input.email,
      password: input.password,
      display_name: input.displayName,
    }),
  })
  if (response.status === 201) return parseUser(response)
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  if (response.status === 422) {
    const details = await readValidationDetails(response)
    if (details?.email !== undefined) {
      throw new NativeAuthError('EMAIL_TAKEN', fieldMessage(details.email))
    }
    throw new NativeAuthError('UNKNOWN', firstValidationMessage(details))
  }
  throw new NativeAuthError('UNKNOWN', `register failed: ${response.status}`)
}

export async function passwordLogin(input: {
  email: string
  password: string
}): Promise<SessionUser> {
  const response = await csrfFetch('/auth/password/login', {
    method: 'POST',
    body: JSON.stringify(input),
  })
  if (response.ok) return parseUser(response)
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  // Any other failure (notably 422) is collapsed to one generic code — never
  // inspect the field or reveal user existence (enumeration guard).
  throw new NativeAuthError('INVALID_CREDENTIALS')
}

export async function forgotPassword(email: string): Promise<void> {
  const response = await csrfFetch('/auth/password/forgot', {
    method: 'POST',
    body: JSON.stringify({ email }),
  })
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  // Any non-429 is treated as success-shaped (the endpoint always 200s; no enumeration).
}

export async function resetPassword(input: {
  token: string
  email: string
  password: string
}): Promise<void> {
  const response = await csrfFetch('/auth/password/reset', {
    method: 'POST',
    body: JSON.stringify(input),
  })
  if (response.ok) return
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  if (response.status === 422) {
    const details = await readValidationDetails(response)
    throw new NativeAuthError('INVALID_RESET_TOKEN', firstValidationMessage(details))
  }
  throw new NativeAuthError('UNKNOWN', `reset failed: ${response.status}`)
}

export async function resendVerification(): Promise<void> {
  const response = await csrfFetch('/auth/email/resend', { method: 'POST' })
  if (response.status === 204) return
  if (response.status === 429) throw new NativeAuthError('RATE_LIMITED')
  throw new NativeAuthError('UNKNOWN', `resend failed: ${response.status}`)
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `cd frontend && npm run test -- src/api/auth.test.ts`
Expected: PASS (all tests).

- [ ] **Step 5: Lint and commit**

```bash
cd frontend && npm run lint
git add src/api/auth.ts src/api/auth.test.ts
git commit -m "feat(fe): native-auth API client (SP-1b-ii task 3)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Post-login redirect helper, RegisterPage, LoginPage native form

**Files:**
- Create: `frontend/src/api/postLoginRedirect.ts`
- Modify: `frontend/src/pages/AuthCallbackPage.tsx` (use the helper — behavior-preserving)
- Create: `frontend/src/pages/RegisterPage.tsx`, `RegisterPage.test.tsx`, `RegisterPage.stories.tsx`
- Modify: `frontend/src/pages/LoginPage.tsx`
- Create: `frontend/src/pages/LoginPage.test.tsx`

**Interfaces:**
- Consumes: `register`, `passwordLogin`, `NativeAuthError` (Task 3); `beginLogin` (`api/session.ts`); `Field`, `Button`, `Banner`, `FormLayout`, `Link`; `useMutation`, `useQueryClient`.
- Produces:
  - `consumePostLoginRedirect(): string` — reads `sessionStorage.postLoginRedirect`, removes it, returns it only if same-origin (`startsWith('/') && !startsWith('//')`), else `'/'`.
  - `RegisterPage()`, extended `LoginPage()`.

- [ ] **Step 1: Write the redirect-helper test**

Create `frontend/src/api/postLoginRedirect.test.ts`:

```ts
import { afterEach, expect, test, vi } from 'vitest'
import { consumePostLoginRedirect } from './postLoginRedirect'

afterEach(() => vi.unstubAllGlobals())

test('returns and clears a same-origin path', () => {
  const removeItem = vi.fn()
  vi.stubGlobal('sessionStorage', { getItem: () => '/programs?tab=stages', removeItem, setItem: vi.fn() })
  expect(consumePostLoginRedirect()).toBe('/programs?tab=stages')
  expect(removeItem).toHaveBeenCalledWith('postLoginRedirect')
})

test('rejects protocol-relative and absolute URLs, falling back to /', () => {
  vi.stubGlobal('sessionStorage', { getItem: () => '//evil.example/x', removeItem: vi.fn(), setItem: vi.fn() })
  expect(consumePostLoginRedirect()).toBe('/')
})

test('falls back to / when nothing is stored', () => {
  vi.stubGlobal('sessionStorage', { getItem: () => null, removeItem: vi.fn(), setItem: vi.fn() })
  expect(consumePostLoginRedirect()).toBe('/')
})
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd frontend && npm run test -- src/api/postLoginRedirect.test.ts`
Expected: FAIL — module does not exist.

- [ ] **Step 3: Implement the helper**

Create `frontend/src/api/postLoginRedirect.ts`:

```ts
/**
 * Read the captured pre-login destination, clear it, and return it only if it is a
 * same-origin absolute path. Guards against open redirects (protocol-relative `//host`
 * or absolute URLs). Falls back to '/'. Shared by callback, login, and register.
 */
export function consumePostLoginRedirect(): string {
  const dest = sessionStorage.getItem('postLoginRedirect')
  sessionStorage.removeItem('postLoginRedirect')
  return dest && dest.startsWith('/') && !dest.startsWith('//') ? dest : '/'
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `cd frontend && npm run test -- src/api/postLoginRedirect.test.ts`
Expected: PASS.

- [ ] **Step 5: Refactor AuthCallbackPage to use the helper (behavior-preserving)**

In `frontend/src/pages/AuthCallbackPage.tsx`, replace the inline redirect block in `onSuccess` with the helper:

```ts
import { consumePostLoginRedirect } from '../api/postLoginRedirect'
```

```ts
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['session'] })
      window.location.assign(consumePostLoginRedirect())
    },
```

Run: `cd frontend && npm run test -- src/pages` (existing callback behavior stays green).

- [ ] **Step 6: Write the RegisterPage test**

Create `frontend/src/pages/RegisterPage.test.tsx`:

```tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { RegisterPage } from './RegisterPage'
import { jsonResponse } from '../tests/test-utils'

const USER = {
  id: 'u1', email: 'a@b.com', display_name: null, email_verified: false,
  startup_gate_subject_id: null, linked_providers: [], has_password: true,
}

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <RegisterPage />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

test('successful registration redirects to the captured path', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ user: USER }, 201))
  const assign = vi.fn()
  vi.stubGlobal('location', { ...window.location, assign })
  vi.stubGlobal('sessionStorage', { getItem: () => '/', removeItem: vi.fn(), setItem: vi.fn() })

  renderPage()
  fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@b.com' } })
  fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'super-secret' } })
  fireEvent.click(screen.getByRole('button', { name: /create account/i }))

  await waitFor(() => expect(assign).toHaveBeenCalledWith('/'))
})

test('a taken email shows a field-level message', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['The email has already been taken.'] } } }, 422),
  )
  renderPage()
  fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@b.com' } })
  fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'super-secret' } })
  fireEvent.click(screen.getByRole('button', { name: /create account/i }))

  expect(await screen.findByText(/already been taken/i)).toBeInTheDocument()
})
```

- [ ] **Step 7: Run to verify it fails**

Run: `cd frontend && npm run test -- src/pages/RegisterPage.test.tsx`
Expected: FAIL — `RegisterPage` does not exist.

- [ ] **Step 8: Implement RegisterPage**

Create `frontend/src/pages/RegisterPage.tsx`:

```tsx
import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { register, NativeAuthError } from '../api/auth'
import { consumePostLoginRedirect } from '../api/postLoginRedirect'

/**
 * Native registration (FR-007). Creates an account, issues an (unverified) session,
 * then lands via the no-org gate — which shows the verify-email notice until the user
 * confirms their address.
 */
export function RegisterPage() {
  const queryClient = useQueryClient()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [displayName, setDisplayName] = useState('')

  const mutation = useMutation({
    mutationFn: () => register({ email, password, displayName: displayName || undefined }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['session'] })
      window.location.assign(consumePostLoginRedirect())
    },
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (!email.trim() || !password) return
    mutation.mutate()
  }

  const error = mutation.error
  const emailError =
    error instanceof NativeAuthError && error.code === 'EMAIL_TAKEN'
      ? error.message || 'That email is already registered.'
      : undefined
  const bannerError =
    error && !emailError
      ? error instanceof NativeAuthError && error.code === 'RATE_LIMITED'
        ? 'Too many attempts. Please try again shortly.'
        : 'We could not create your account. Please try again.'
      : undefined

  return (
    <section aria-labelledby="register-heading">
      <h1 id="register-heading">Create your account</h1>
      <p>Register with your email and a password.</p>
      {bannerError ? <Banner variant="error">{bannerError}</Banner> : null}
      <form onSubmit={onSubmit} noValidate>
        <FormLayout>
          <Field
            label="Email"
            type="email"
            name="email"
            autoComplete="email"
            required
            value={email}
            error={emailError}
            onChange={(e) => setEmail(e.target.value)}
          />
          <Field
            label="Password"
            type="password"
            name="password"
            autoComplete="new-password"
            required
            value={password}
            help="At least 8 characters."
            onChange={(e) => setPassword(e.target.value)}
          />
          <Field
            label="Display name (optional)"
            name="display_name"
            value={displayName}
            onChange={(e) => setDisplayName(e.target.value)}
          />
        </FormLayout>
        <Button type="submit" loading={mutation.isPending} disabled={!email.trim() || !password}>
          Create account
        </Button>
      </form>
      <p>
        Already have an account? <Link href="/login">Sign in</Link>
      </p>
    </section>
  )
}
```

- [ ] **Step 9: Run to verify RegisterPage passes**

Run: `cd frontend && npm run test -- src/pages/RegisterPage.test.tsx`
Expected: PASS.

- [ ] **Step 10: Write the LoginPage native-form test**

Create `frontend/src/pages/LoginPage.test.tsx`:

```tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { LoginPage } from './LoginPage'
import { jsonResponse } from '../tests/test-utils'

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <LoginPage />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

test('wrong credentials show a single generic banner (no enumeration)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['These credentials do not match our records.'] } } }, 422),
  )
  renderPage()
  fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@b.com' } })
  fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'nope' } })
  fireEvent.click(screen.getByRole('button', { name: /^sign in$/i }))

  expect(await screen.findByText(/these details don't match our records/i)).toBeInTheDocument()
})

test('the Startup Gate SSO button is still present', () => {
  renderPage()
  expect(screen.getByRole('button', { name: /startup gate/i })).toBeInTheDocument()
})
```

- [ ] **Step 11: Run to verify it fails**

Run: `cd frontend && npm run test -- src/pages/LoginPage.test.tsx`
Expected: FAIL — no native form / no email field yet.

- [ ] **Step 12: Extend LoginPage with the native form**

Replace `frontend/src/pages/LoginPage.tsx` with:

```tsx
import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { beginLogin } from '../api/session'
import { passwordLogin, NativeAuthError } from '../api/auth'
import { consumePostLoginRedirect } from '../api/postLoginRedirect'

/**
 * Sign-in (FR-007). Native email/password is primary; Startup Gate SSO is offered
 * below. Login failures are deliberately collapsed into one generic message — the
 * UI never reveals whether an email exists (enumeration guard).
 */
export function LoginPage() {
  const queryClient = useQueryClient()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [ssoError, setSsoError] = useState(false)
  const [ssoPending, setSsoPending] = useState(false)

  const mutation = useMutation({
    mutationFn: () => passwordLogin({ email, password }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['session'] })
      window.location.assign(consumePostLoginRedirect())
    },
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (!email.trim() || !password) return
    mutation.mutate()
  }

  const onSso = () => {
    setSsoError(false)
    setSsoPending(true)
    beginLogin().catch(() => {
      setSsoError(true)
      setSsoPending(false)
    })
  }

  const rateLimited =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'RATE_LIMITED'
  const loginError = mutation.error
    ? rateLimited
      ? 'Too many attempts. Please try again shortly.'
      : "These details don't match our records."
    : undefined

  return (
    <section aria-labelledby="login-heading">
      <h1 id="login-heading">Sign in</h1>
      {loginError ? <Banner variant="error">{loginError}</Banner> : null}
      <form onSubmit={onSubmit} noValidate>
        <FormLayout>
          <Field
            label="Email"
            type="email"
            name="email"
            autoComplete="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          <Field
            label="Password"
            type="password"
            name="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
        </FormLayout>
        <Button type="submit" loading={mutation.isPending} disabled={!email.trim() || !password}>
          Sign in
        </Button>
      </form>
      <p>
        <Link href="/forgot-password">Forgot password?</Link> ·{' '}
        <Link href="/register">Create an account</Link>
      </p>

      <hr />
      <p>Or sign in with Startup Gate.</p>
      {ssoError ? (
        <Banner variant="error">We could not start sign-in. Please try again.</Banner>
      ) : null}
      <Button variant="secondary" onClick={onSso} loading={ssoPending}>
        Sign in with Startup Gate
      </Button>
    </section>
  )
}
```

- [ ] **Step 13: Run both page tests to verify they pass**

Run: `cd frontend && npm run test -- src/pages/LoginPage.test.tsx src/pages/RegisterPage.test.tsx`
Expected: PASS.

- [ ] **Step 14: Add Storybook stories**

Create `frontend/src/pages/RegisterPage.stories.tsx`:

```tsx
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '../app/queryClient'
import { RegisterPage } from './RegisterPage'

const meta = {
  title: 'Pages/RegisterPage',
  component: RegisterPage,
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <Story />
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof RegisterPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
```

Replace `frontend/src/pages/LoginPage.stories.tsx` with the same decorator pattern (LoginPage now needs a QueryClient):

```tsx
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '../app/queryClient'
import { LoginPage } from './LoginPage'

const meta = {
  title: 'Pages/LoginPage',
  component: LoginPage,
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <Story />
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof LoginPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
```

- [ ] **Step 15: Lint and commit**

```bash
cd frontend && npm run lint && npm run test
git add src/api/postLoginRedirect.ts src/api/postLoginRedirect.test.ts src/pages/AuthCallbackPage.tsx \
  src/pages/RegisterPage.tsx src/pages/RegisterPage.test.tsx src/pages/RegisterPage.stories.tsx \
  src/pages/LoginPage.tsx src/pages/LoginPage.test.tsx src/pages/LoginPage.stories.tsx
git commit -m "feat(fe): register page + native login + redirect helper (SP-1b-ii task 4)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: ForgotPasswordPage + ResetPasswordPage

**Files:**
- Create: `frontend/src/pages/ForgotPasswordPage.tsx`, `ForgotPasswordPage.test.tsx`, `ForgotPasswordPage.stories.tsx`
- Create: `frontend/src/pages/ResetPasswordPage.tsx`, `ResetPasswordPage.test.tsx`, `ResetPasswordPage.stories.tsx`

**Interfaces:**
- Consumes: `forgotPassword`, `resetPassword`, `NativeAuthError` (Task 3); `Field`, `Button`, `Banner`, `FormLayout`, `Link`; `useMutation`.
- Produces: `ForgotPasswordPage()`, `ResetPasswordPage()`.

- [ ] **Step 1: Write the ForgotPasswordPage test**

Create `frontend/src/pages/ForgotPasswordPage.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ForgotPasswordPage } from './ForgotPasswordPage'
import { jsonResponse } from '../tests/test-utils'

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ForgotPasswordPage />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('submitting shows a neutral confirmation (no enumeration)', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ message: 'ok' }))
  renderPage()
  fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'a@b.com' } })
  fireEvent.click(screen.getByRole('button', { name: /send reset link/i }))

  expect(await screen.findByText(/if that email is registered/i)).toBeInTheDocument()
})
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd frontend && npm run test -- src/pages/ForgotPasswordPage.test.tsx`
Expected: FAIL — page does not exist.

- [ ] **Step 3: Implement ForgotPasswordPage**

Create `frontend/src/pages/ForgotPasswordPage.tsx`:

```tsx
import { useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { forgotPassword, NativeAuthError } from '../api/auth'

/**
 * Request a password-reset link (FR-007). The endpoint always 200s, so on success we
 * show a neutral confirmation that never reveals whether the email is registered.
 */
export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')

  const mutation = useMutation({
    mutationFn: () => forgotPassword(email),
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (!email.trim()) return
    mutation.mutate()
  }

  if (mutation.isSuccess) {
    return (
      <section aria-labelledby="forgot-heading">
        <h1 id="forgot-heading">Check your email</h1>
        <p>If that email is registered, we've sent a link to reset your password.</p>
        <p>
          <Link href="/login">Back to sign in</Link>
        </p>
      </section>
    )
  }

  const rateLimited =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'RATE_LIMITED'

  return (
    <section aria-labelledby="forgot-heading">
      <h1 id="forgot-heading">Reset your password</h1>
      <p>Enter your email and we'll send a reset link.</p>
      {rateLimited ? (
        <Banner variant="error">Too many attempts. Please try again shortly.</Banner>
      ) : null}
      <form onSubmit={onSubmit} noValidate>
        <FormLayout>
          <Field
            label="Email"
            type="email"
            name="email"
            autoComplete="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </FormLayout>
        <Button type="submit" loading={mutation.isPending} disabled={!email.trim()}>
          Send reset link
        </Button>
      </form>
      <p>
        <Link href="/login">Back to sign in</Link>
      </p>
    </section>
  )
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `cd frontend && npm run test -- src/pages/ForgotPasswordPage.test.tsx`
Expected: PASS.

- [ ] **Step 5: Write the ResetPasswordPage tests**

Create `frontend/src/pages/ResetPasswordPage.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { ResetPasswordPage } from './ResetPasswordPage'
import { jsonResponse } from '../tests/test-utils'

function renderPage(search: string): void {
  vi.stubGlobal('location', { ...window.location, search })
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <ResetPasswordPage />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

test('missing token/email shows an error with a forgot-password link', () => {
  renderPage('')
  expect(screen.getByText(/link is invalid or incomplete/i)).toBeInTheDocument()
})

test('a valid reset shows a success confirmation', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ message: 'ok' }))
  renderPage('?token=abc&email=a%40b.com')
  fireEvent.change(screen.getByLabelText(/new password/i), { target: { value: 'super-secret' } })
  fireEvent.click(screen.getByRole('button', { name: /reset password/i }))

  expect(await screen.findByText(/your password has been reset/i)).toBeInTheDocument()
})

test('an invalid token shows an error banner', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    jsonResponse({ error: { code: 'VALIDATION_ERROR', details: { email: ['This password reset token is invalid or has expired.'] } } }, 422),
  )
  renderPage('?token=bad&email=a%40b.com')
  fireEvent.change(screen.getByLabelText(/new password/i), { target: { value: 'super-secret' } })
  fireEvent.click(screen.getByRole('button', { name: /reset password/i }))

  expect(await screen.findByText(/invalid or has expired/i)).toBeInTheDocument()
})
```

- [ ] **Step 6: Run to verify it fails**

Run: `cd frontend && npm run test -- src/pages/ResetPasswordPage.test.tsx`
Expected: FAIL — page does not exist.

- [ ] **Step 7: Implement ResetPasswordPage**

Create `frontend/src/pages/ResetPasswordPage.tsx`:

```tsx
import { useMemo, useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { resetPassword, NativeAuthError } from '../api/auth'

/**
 * Choose a new password from a reset link (FR-007). The token+email arrive in the URL
 * (from the emailed link); they are submitted with the new password and never stored.
 */
export function ResetPasswordPage() {
  const { token, email } = useMemo(() => {
    const params = new URLSearchParams(window.location.search)
    return { token: params.get('token'), email: params.get('email') }
  }, [])

  const [password, setPassword] = useState('')

  const mutation = useMutation({
    mutationFn: () => resetPassword({ token: token ?? '', email: email ?? '', password }),
  })

  if (!token || !email) {
    return (
      <section aria-labelledby="reset-heading">
        <h1 id="reset-heading">Reset your password</h1>
        <Banner variant="error">
          This reset link is invalid or incomplete. <Link href="/forgot-password">Request a new one</Link>.
        </Banner>
      </section>
    )
  }

  if (mutation.isSuccess) {
    return (
      <section aria-labelledby="reset-heading">
        <h1 id="reset-heading">Password reset</h1>
        <p>Your password has been reset. You can now sign in.</p>
        <p>
          <Link href="/login">Go to sign in</Link>
        </p>
      </section>
    )
  }

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (password.length < 8) return
    mutation.mutate()
  }

  const rateLimited =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'RATE_LIMITED'
  const invalidToken =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'INVALID_RESET_TOKEN'
  const bannerError = mutation.error
    ? rateLimited
      ? 'Too many attempts. Please try again shortly.'
      : invalidToken
        ? mutation.error.message || 'This password reset token is invalid or has expired.'
        : 'We could not reset your password. Please try again.'
    : undefined

  return (
    <section aria-labelledby="reset-heading">
      <h1 id="reset-heading">Choose a new password</h1>
      {bannerError ? (
        <Banner variant="error">
          {bannerError}
          {invalidToken ? (
            <>
              {' '}
              <Link href="/forgot-password">Request a new link</Link>.
            </>
          ) : null}
        </Banner>
      ) : null}
      <form onSubmit={onSubmit} noValidate>
        <FormLayout>
          <Field
            label="New password"
            type="password"
            name="password"
            autoComplete="new-password"
            required
            value={password}
            help="At least 8 characters."
            onChange={(e) => setPassword(e.target.value)}
          />
        </FormLayout>
        <Button type="submit" loading={mutation.isPending} disabled={password.length < 8}>
          Reset password
        </Button>
      </form>
    </section>
  )
}
```

- [ ] **Step 8: Run to verify it passes**

Run: `cd frontend && npm run test -- src/pages/ResetPasswordPage.test.tsx`
Expected: PASS (all three tests).

- [ ] **Step 9: Add Storybook stories**

Create `frontend/src/pages/ForgotPasswordPage.stories.tsx`:

```tsx
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '../app/queryClient'
import { ForgotPasswordPage } from './ForgotPasswordPage'

const meta = {
  title: 'Pages/ForgotPasswordPage',
  component: ForgotPasswordPage,
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <Story />
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof ForgotPasswordPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
```

Create `frontend/src/pages/ResetPasswordPage.stories.tsx`:

```tsx
import type { Meta, StoryObj } from '@storybook/react-vite'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '../app/queryClient'
import { ResetPasswordPage } from './ResetPasswordPage'

const meta = {
  title: 'Pages/ResetPasswordPage',
  component: ResetPasswordPage,
  decorators: [
    (Story) => (
      <QueryClientProvider client={queryClient}>
        <Story />
      </QueryClientProvider>
    ),
  ],
} satisfies Meta<typeof ResetPasswordPage>

export default meta
type Story = StoryObj<typeof meta>

export const Default: Story = {}
```

- [ ] **Step 10: Lint and commit**

```bash
cd frontend && npm run lint && npm run test
git add src/pages/ForgotPasswordPage.tsx src/pages/ForgotPasswordPage.test.tsx src/pages/ForgotPasswordPage.stories.tsx \
  src/pages/ResetPasswordPage.tsx src/pages/ResetPasswordPage.test.tsx src/pages/ResetPasswordPage.stories.tsx
git commit -m "feat(fe): forgot + reset password pages (SP-1b-ii task 5)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: EmailVerifiedPage, verify interstitial, routing wire-up

**Files:**
- Create: `frontend/src/pages/EmailVerifiedPage.tsx`, `EmailVerifiedPage.test.tsx`, `EmailVerifiedPage.stories.tsx`
- Create: `frontend/src/pages/VerifyEmailNotice.tsx`, `VerifyEmailNotice.test.tsx`, `VerifyEmailNotice.stories.tsx`
- Modify: `frontend/src/app/App.tsx` (routes + gate interstitial)

**Interfaces:**
- Consumes: `resendVerification`, `NativeAuthError` (Task 3); `getSession`/`SessionUser` (gate already wires `['session']`); `Button`, `Banner`, `Link`, `Spinner`; `useMutation`, `useQueryClient`.
- Produces: `EmailVerifiedPage()`, `VerifyEmailNotice()`; new routes `/register`, `/forgot-password`, `/auth/reset-password`, `/auth/email-verified`; gate renders `VerifyEmailNotice` when `sessionQuery.data.email_verified === false`.

- [ ] **Step 1: Write the EmailVerifiedPage test**

Create `frontend/src/pages/EmailVerifiedPage.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { EmailVerifiedPage } from './EmailVerifiedPage'

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <EmailVerifiedPage />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

test('shows confirmation and Continue navigates to /', () => {
  const assign = vi.fn()
  vi.stubGlobal('location', { ...window.location, assign })
  renderPage()
  expect(screen.getByText(/your email is verified/i)).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: /continue/i }))
  expect(assign).toHaveBeenCalledWith('/')
})
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd frontend && npm run test -- src/pages/EmailVerifiedPage.test.tsx`
Expected: FAIL — page does not exist.

- [ ] **Step 3: Implement EmailVerifiedPage**

Create `frontend/src/pages/EmailVerifiedPage.tsx`:

```tsx
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '../components/Button'

/**
 * Landing page after the backend verifies an email (FR-007). The signed verify link
 * hits the backend, which marks the email verified and redirects here — so this page
 * is purely informational. Continue refreshes the session (now verified) and proceeds.
 */
export function EmailVerifiedPage() {
  const queryClient = useQueryClient()

  const onContinue = () => {
    void queryClient.invalidateQueries({ queryKey: ['session'] })
    window.location.assign('/')
  }

  return (
    <section aria-labelledby="verified-heading">
      <h1 id="verified-heading">Email verified</h1>
      <p>Your email is verified. You can now continue to your workspace.</p>
      <Button onClick={onContinue}>Continue</Button>
    </section>
  )
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `cd frontend && npm run test -- src/pages/EmailVerifiedPage.test.tsx`
Expected: PASS.

- [ ] **Step 5: Write the VerifyEmailNotice test**

Create `frontend/src/pages/VerifyEmailNotice.test.tsx`:

```tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { VerifyEmailNotice } from './VerifyEmailNotice'

function renderPage(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = (
    <DirectionProvider>
      <QueryClientProvider client={client}>
        <VerifyEmailNotice />
      </QueryClientProvider>
    </DirectionProvider>
  )
  render(ui)
}

beforeEach(() => {
  Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true })
})
afterEach(() => vi.restoreAllMocks())

test('Resend posts and shows a confirmation', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 204 }))
  renderPage()
  fireEvent.click(screen.getByRole('button', { name: /resend/i }))
  expect(await screen.findByText(/we've sent another/i)).toBeInTheDocument()
})
```

- [ ] **Step 6: Run to verify it fails**

Run: `cd frontend && npm run test -- src/pages/VerifyEmailNotice.test.tsx`
Expected: FAIL — component does not exist.

- [ ] **Step 7: Implement VerifyEmailNotice**

Create `frontend/src/pages/VerifyEmailNotice.tsx`:

```tsx
import { useMutation } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { resendVerification, NativeAuthError } from '../api/auth'

/**
 * Interstitial for an unverified native account (FR-007). The no-org gate renders this
 * before onboarding when the session reports email_verified === false. Startup Gate
 * accounts are auto-verified and never see it.
 */
export function VerifyEmailNotice() {
  const mutation = useMutation({ mutationFn: () => resendVerification() })

  const rateLimited =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'RATE_LIMITED'

  return (
    <section aria-labelledby="verify-heading">
      <h1 id="verify-heading">Verify your email</h1>
      <p>We've sent a verification link to your email. Click it to finish setting up your account.</p>
      {mutation.isSuccess ? (
        <Banner variant="info">We've sent another verification email.</Banner>
      ) : null}
      {rateLimited ? (
        <Banner variant="error">Too many attempts. Please try again shortly.</Banner>
      ) : null}
      <Button onClick={() => mutation.mutate()} loading={mutation.isPending}>
        Resend verification email
      </Button>
    </section>
  )
}
```

Note: `Banner` supports `variant="info" | "error" | "success"` (verified), so `variant="info"` is valid here.

- [ ] **Step 8: Run to verify it passes**

Run: `cd frontend && npm run test -- src/pages/VerifyEmailNotice.test.tsx`
Expected: PASS.

- [ ] **Step 9: Wire routing + the gate interstitial in App.tsx**

In `frontend/src/app/App.tsx`:

Add imports:

```ts
import { RegisterPage } from '../pages/RegisterPage'
import { ForgotPasswordPage } from '../pages/ForgotPasswordPage'
import { ResetPasswordPage } from '../pages/ResetPasswordPage'
import { EmailVerifiedPage } from '../pages/EmailVerifiedPage'
import { VerifyEmailNotice } from '../pages/VerifyEmailNotice'
```

Add route constants near the existing ones:

```ts
const REGISTER_ROUTE = /^\/register\/?$/
const FORGOT_ROUTE = /^\/forgot-password\/?$/
const RESET_ROUTE = /^\/auth\/reset-password\/?$/
const EMAIL_VERIFIED_ROUTE = /^\/auth\/email-verified\/?$/
```

In `resolveRoute()`, add these branches alongside the existing public routes (after `CALLBACK_ROUTE`):

```ts
  if (REGISTER_ROUTE.test(path)) {
    return <RegisterPage />
  }
  if (FORGOT_ROUTE.test(path)) {
    return <ForgotPasswordPage />
  }
  if (RESET_ROUTE.test(path)) {
    return <ResetPasswordPage />
  }
  if (EMAIL_VERIFIED_ROUTE.test(path)) {
    return <EmailVerifiedPage />
  }
```

In `ConsoleGate`, add the interstitial immediately after the `sessionQuery.isError` check and before the `orgsQuery` checks:

```ts
  // Unverified native account → block console/onboarding behind the verify notice.
  // SG-linked accounts are auto-verified and skip this.
  if (sessionQuery.data.email_verified === false) {
    return <VerifyEmailNotice />
  }
```

(`sessionQuery.data` is defined here because `sessionQuery.isError` returned above and `isLoading` was handled earlier.)

- [ ] **Step 10: Update App.test.tsx for the interstitial**

In `frontend/src/app/App.test.tsx`, any test that mocks a successful session MUST include the new session fields. Find the session fixture(s) used and ensure they include `email_verified: true` (so existing authed-Home tests still route to Home, not the notice), plus `linked_providers`, `has_password`, and `startup_gate_subject_id`. Add one new test asserting an unverified session renders the notice:

```tsx
test('an unverified session shows the verify-email notice', async () => {
  // Arrange a session with email_verified:false and a successful (empty) org list is irrelevant —
  // the notice short-circuits before orgs. Mock GET /auth/session → unverified user.
  // (Follow the file's existing fetch-mock pattern for routing by URL.)
})
```

Implement the test body following the file's existing mocking pattern (match on the request URL containing `/auth/session`, return an unverified user via `jsonResponse({ user: { …, email_verified: false } })`), and assert `await screen.findByText(/verify your email/i)`.

- [ ] **Step 11: Run the full suite + build**

Run: `cd frontend && npm run lint && npm run test && npm run build`
Expected: PASS — all tests green, type-check + production build succeed.

- [ ] **Step 12: Add Storybook stories**

Create `frontend/src/pages/EmailVerifiedPage.stories.tsx` and `frontend/src/pages/VerifyEmailNotice.stories.tsx`, each using the QueryClient decorator pattern from Task 4 Step 14 (swap the component name and `title`: `Pages/EmailVerifiedPage`, `Pages/VerifyEmailNotice`).

- [ ] **Step 13: Lint and commit**

```bash
cd frontend && npm run lint && npm run test
git add src/pages/EmailVerifiedPage.tsx src/pages/EmailVerifiedPage.test.tsx src/pages/EmailVerifiedPage.stories.tsx \
  src/pages/VerifyEmailNotice.tsx src/pages/VerifyEmailNotice.test.tsx src/pages/VerifyEmailNotice.stories.tsx \
  src/app/App.tsx src/app/App.test.tsx
git commit -m "feat(fe): email-verified landing + verify interstitial + routes (SP-1b-ii task 6)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Plan Self-Review

**Spec coverage:**
- §4 session schema → Task 1. ✅
- §5.1 `APP_BASE_URL` + `csrfFetch` + retrofit callback/create-org → Task 2. ✅
- §5.1 `auth.ts` client + `NativeAuthError` → Task 3. ✅
- §5.2 RegisterPage → Task 4; LoginPage extension → Task 4; ForgotPasswordPage + ResetPasswordPage → Task 5; EmailVerifiedPage → Task 6. ✅
- §5.3 routing + `consumePostLoginRedirect` helper + verify interstitial → Tasks 4 & 6. ✅
- §6 error mapping → enforced in Task 3 client + per-page banners (Tasks 4-6). ✅
- §7 tests (csrf, auth incl. enumeration guard, session, page tests, gate test) → distributed across all tasks; build gate in Task 6. ✅ a11y is covered by `Field`'s built-in label/error association exercised in the page tests (no separate `a11y.test.tsx` edit required; the existing suite already renders the app).

**Placeholder scan:** No TBD/TODO. Task 6 Step 10 and Step 12 reference the file's "existing pattern" — that is a deliberate instruction to match an established convention the implementer can read in-file, not a missing-code placeholder; the assertion text and fields to add are given explicitly.

**Type consistency:** `SessionUser` shape identical across Tasks 1/3/4. `csrfFetch(path, init)` signature consistent (Task 2 def → Tasks 2/3 use). `NativeAuthError`/`NativeAuthCode` consistent (Task 3 def → Tasks 4/5/6 use). `consumePostLoginRedirect()` consistent (Task 4 def → Task 4 uses). Route-constant naming matches the existing `*_ROUTE` convention.

**Resolved before handoff:** `Banner` variants are `info | error | success` (verified) — Task 6's `variant="info"` is valid; no open checks remain.
