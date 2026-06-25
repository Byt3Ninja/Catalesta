# FE-2.5: Tenant Header Foundation — Design

> Date: 2026-06-25 · Status: Approved (brainstorming) · Scope: foundation slice of
> the "complete the Phase-1a frontend end-to-end" initiative — inserted before
> FE-3 because it unblocks live operation of FE-1/FE-2 and all of FE-3.

## Problem

The backend tenant middleware `ResolveTenant`
(`backend/app/Http/Middleware/ResolveTenant.php`) requires an `X-Organization-Id`
header on every tenant-scoped route. Without it, a non-platform-admin request gets
**400 "Missing organization header."** (header name from `config/tenancy.php` →
`X-Organization-Id`).

The frontend sends **no** such header anywhere (verified: zero references in
`frontend/src`). Its API calls pass only `credentials: 'include'`. Consequently,
against the real API in a browser:

- `GET /auth/session` and `GET /organizations` succeed (both are `auth:sanctum`,
  NOT under the `tenant` middleware) → the console gate resolves the org and
  renders Home.
- The first tenant-scoped call — `GET /cohorts` from Home, or opening
  Programs/Cohorts — returns **400**, surfacing as the page's error state.

So FE-1 (programs) and FE-2 (cohorts) do not actually work end-to-end in a
browser; they have only ever been exercised with mocked `fetch` in unit tests.
FE-3 (members + org settings) is entirely tenant-scoped and is fully blocked.

## Which calls need the header (verified against `routes/api.php`)

**Tenant-scoped (need `X-Organization-Id`):**
- Reads (bare `fetch` today): `listPrograms` (`GET /programs`), `getProgram`
  (`GET /programs/{id}`), `listCohorts` (`GET /cohorts`), `getCohort`
  (`GET /cohorts/{id}`), and the three reads in `api/submissions.ts`
  (`GET /cohorts/{c}/submissions`, `GET /cohorts/{c}/submissions/{s}`).
- Mutations (via `csrfFetch`): program create/publish/update/clone, cohort
  create/update.

**NOT tenant-scoped (no header needed; `auth:sanctum`-only or public):**
- `GET /auth/session`, `GET /me`, `GET /me/profile`, `GET /organizations`
  (index), all `/auth/*`, `POST /organizations` (create — pre-tenant onboarding),
  and the public `/apply/*` + `/health`.

The header is **harmless on non-tenant routes** — the middleware only reads it
where mounted. The only calls that run before an org is resolved (login,
register, onboarding, apply) naturally carry no header because the active-org
holder is null at that point.

## Architecture

A module-level **active-organization holder**, set by the console gate, injected
by the API layer. Chosen over threading an `orgId` argument through every API
function, react-query `queryFn`, and page — that would touch dozens of call
sites; the holder is a single seam set once.

### New unit — `frontend/src/api/tenant.ts`
- `setActiveOrganizationId(id: string | null): void` — module-scoped `let`.
- `getActiveOrganizationId(): string | null`.
- `tenantHeaders(): Record<string, string>` — `{ 'X-Organization-Id': id }` when
  set, else `{}`.

(No persistence; it lives for the page session. The gate re-sets it on every
load, so a refresh re-establishes it.)

### Injection
- **`api/csrf.ts` (`csrfFetch`)**: spread `tenantHeaders()` into the constructed
  `Headers` (after the CSRF/JSON defaults). One change covers every mutation.
- **Tenant-scoped reads**: add a small `apiFetch(path: string, init?: RequestInit)`
  helper — `fetch(\`${API_BASE_URL}${path}\`, { ...init, credentials: 'include',
  headers: { ...init?.headers, ...tenantHeaders() } })` — and route the
  programs/cohorts/submissions reads through it. Non-tenant reads
  (session/organizations/profile/apply/health) are left unchanged.

`apiFetch` lives in `api/tenant.ts` alongside the holder; it depends only on
`API_BASE_URL` (imported from `api/client.ts`) and `tenantHeaders`.

### Setting the holder — `app/App.tsx` (`ConsoleGate`)
The gate already resolves `orgs[0]`. Add
`useEffect(() => { setActiveOrganizationId(org?.id ?? null) }, [org])` (where
`org = orgsQuery.data?.[0]`) so the active org is published the moment the gate
resolves it and cleared when it can't. This runs before any tenant-scoped child
page mounts (those pages only render through the resolved gate), so reads fire
with the header already set.

## Data flow

Unchanged. react-query keys and `queryFn`s are untouched; the header rides along
inside the shared request helpers. No new queries, no new endpoints.

## Error handling

If a tenant call somehow fires with no active org, the backend 400 surfaces
through each page's existing error state (loading → error → "Try again"). No new
error UI. The holder being null is the natural pre-login / onboarding state and
must not throw.

## Testing

- `api/tenant.test.ts`: `set`/`get`/clear (`null`) round-trip; `tenantHeaders()`
  returns `{ 'X-Organization-Id': id }` when set and `{}` when null.
- `api/csrf.test.ts` (extend): with an active org set, a `csrfFetch` mutation
  includes `X-Organization-Id`; with none, it does not. (Reset the holder in
  `afterEach` so tests don't leak the active org.)
- A reads test: `apiFetch`-backed `listPrograms`/`listCohorts` include the header
  when an org is active. (Assert via the `fetch` spy's `init.headers`.)
- `app/App.test.tsx` (extend): after the gate resolves an org (authenticated +
  one org), `getActiveOrganizationId()` returns that org's id; an unauthenticated
  gate leaves it null. Reset the holder between tests.
- **Live verification (the point of this slice):** with a seeded login
  (`alice@catalesta.test` / `Password123!`) in a real browser against the running
  stack, Home loads its cohorts and Programs/Cohorts render data — no 400s. This
  is manual/observed and reported as evidence, not an automated test.
- Gates: `npm run typecheck && npm run lint && npm run test && npm run build`
  green. Existing tests stay green (none assert header absence).

## Out of scope

- Multi-organization switching UI — the holder uses `orgs[0]`, matching today's
  gate. A real org switcher is a later concern (and pairs with FE-0.5's nav
  shell).
- FE-3 member/org-settings screens (this slice only unblocks them).
- Any backend change — the middleware is correct; the frontend was simply not
  sending the header.

## Risks

- **Holder leaking across tests** — module-level mutable state persists between
  Vitest tests in a file. Mitigated by resetting it in `afterEach` in the touched
  suites (and the holder is per-module, reset on import isolation).
- **A tenant read firing before the gate sets the holder** — not possible for the
  current pages (they render only inside the resolved gate), but noted: if a
  future surface reads tenant data outside the gate, it must ensure the holder is
  set first.
- **Stale org after switching accounts** — the gate's `useEffect` re-runs on org
  change and clears to null when the gate can't resolve an org, so logout/login
  re-establishes correctly.
