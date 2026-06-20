---
baseline_commit: eb9d7bf
---

# Story 1.1: Sign up and create an organization

Status: review

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As a **prospective program operator**,
I want to sign in via Startup Gate and create my organization,
so that I have a tenant workspace where I become the admin and can reach the operator console.

## Acceptance Criteria

1. **Create org → admin → Home.** Given a user authenticated via the Startup Gate OIDC mock with **no** organization, when they complete the create-organization form (organization name), then an Organization is created with a server-set `organization_id` (ULID), the creator is assigned the **owner** role with the full org permission set and an **active** membership (FR-002, FR-005), the event is audited as `organization.created`, and they land on the operator Home. *(Backend already does this — see Dev Notes; this AC is about wiring the UI to it and verifying end-to-end.)*
2. **Not skippable.** An authenticated user with no organization **cannot reach any console surface** (any page wrapped in `AppShell`). The SPA forces the create-org route and offers no skip/dismiss/back-out until an org exists.
3. **No session → login.** An unauthenticated visitor to any console/onboarding route is sent through the Startup Gate OIDC mock login (initiate → IdP → callback → session); no console surface renders without a session.
4. **Duplicate name rejected cleanly.** Submitting an organization name that derives to an already-used slug is rejected **server-side with a clear 422 validation message** (not a 500), and the UI preserves the entered name and shows the message inline.
5. **Cross-tenant isolation (AR-6).** Accessing an organization the user is not a member of returns a **neutral 404** (FR-004) — not 403 — proven by an explicit cross-tenant isolation test. *(Decided 2026-06-20: harden the org access path to 404 and update the 6 existing 403 assertions.)*

## Tasks / Subtasks

- [x] **Task 1 — Backend hardening (consume, don't rebuild)** (AC: #4, #5)
  - [x] 1.1 Added a derived-slug uniqueness closure rule to `StoreOrganizationRequest` → duplicate name now returns a clean 422 (`error.code=VALIDATION_ERROR`, `error.details.name`) instead of a 500 on the DB unique index. `CreateOrganization` untouched.
  - [x] 1.2 Cross-tenant org access is now a **neutral 404**: `ResolveTenant` non-member abort changed `AccessDeniedHttpException`(403)→`NotFoundHttpException`(404); `OrganizationController::show/update` gained `assertResolvedOrg()` to 404 the second leak vector (own valid header + foreign org id in URL, which previously returned **200**). Permission-failure 403s (member lacking `organizations.manage`) and the membership route/header guard 403 are unchanged. Reconciled assertions: `OrganizationApiTest` non-member-view→404 (+ new vector-2 and dup-name tests), `TenantIsolationTest` two cross-tenant cases→404, `ProgramConfigApiTest` two cross-tenant key-leak tests now accept `[403,404]`. No `withoutGlobalScope` added outside `app/Shared/Tenancy/`.
  - [x] 1.3 Ran `php artisan scramble:export` — no diff to `openapi/openapi.json` (success schemas unchanged).
- [x] **Task 2 — Frontend auth entry (SPA OIDC wiring)** (AC: #1, #3)
  - [x] 2.1 `frontend/src/api/session.ts`: `getSession()`, `beginLogin()` (redirects to `authorization_url`), `completeLogin(state, code)`; `credentials:'include'`, zod-parse, typed `SessionError`.
  - [x] 2.2 `App.tsx` routing: `/login`, `/auth/callback`, `/health` branches + root `ConsoleGate` (session React Query). The `<Link href="/login">` in `ApplyPage` now resolves to a real page.
  - [x] 2.3 Tests (`session.test.ts`, `App.test.tsx`, fetch-mock 200/401) + LoginPage added to the axe a11y gate.
- [x] **Task 3 — No-org gate + onboarding + Home landing** (AC: #1, #2)
  - [x] 3.1 `frontend/src/api/organizations.ts`: `createOrganization(name)` (422 → typed `CreateOrgError('DUPLICATE_NAME', message)`), `listOrganizations()`; schemas in `frontend/src/schemas/`.
  - [x] 3.2 `ConsoleGate` at the root route: unauth → LoginPage; authed + no org → forced OnboardingPage; authed + org → HomePage. Console surfaces render only through the gate, only when an org exists.
  - [x] 3.3 `OnboardingPage`: `Field` (org name) in `FormLayout`, `Button` loading, `Banner` for the 422 duplicate-name (entered name preserved). Success invalidates `['organizations']` → gate lands on Home. No skip control.
  - [x] 3.4 Minimal `HomePage` in `AppShell` with a `// Story 1.5:` marker; real Home content deferred to Story 1.5.
  - [x] 3.5 Tests (gate routing unauth/no-org/has-org; create→Home; 422 preserves input; not-skippable) + OnboardingPage/HomePage in the axe gate + Storybook stories (incl. Arabic/RTL).

## Dev Notes

### ⛔ Anti-reinvention: the backend org-creation flow ALREADY EXISTS and is tested
Do **not** rebuild any of this. Wire the UI to it and harden the two gaps in Task 1.
- **Endpoint:** `POST /api/v1/organizations` → `OrganizationController::store` → `CreateOrganization::handle(ExternalUser $creator, string $name, array $branding = [])` [Source: backend/app/Modules/Organizations/Application/CreateOrganization.php]. In one `DB::transaction` it: creates the `Organization` (ULID id, slug auto-derived in the model `creating` hook), creates an `owner` system role and syncs the full permission catalog (`organizations.manage, members.manage, members.invite, roles.manage, programs.manage, programs.publish, cohorts.manage, stages.manage`), creates an **active** `OrganizationMembership` for the creator, attaches the owner role, and audits `organization.created`.
- **Auth flow exists end-to-end:** `GET /api/v1/auth/login`, `POST /api/v1/auth/callback`, `GET /api/v1/auth/session`, `POST /api/v1/auth/logout` [Source: backend/routes/api.php#L110-L115; backend/app/Modules/Identity/Http/AuthController.php]. `CompleteLogin` runs OIDC code+PKCE, projects `ExternalUser` from claims (key = `startup_gate_subject_id` = OIDC `sub`; **email is never the key** — CLAUDE.md rules 4/5, NFR-002), then `Auth::login($user); session()->regenerate()` (Sanctum SPA session).
- **Auth model:** `App\Modules\Identity\Domain\Models\ExternalUser` (the `web`/`users` guard model). `app/Models/User.php` is **dead/unwired** — do not reference it.
- **Existing tests to read first:** `backend/tests/Feature/OrganizationApiTest.php` (create→201 with owner role + active membership + effective perms; member/non-member matrix) and `backend/tests/Feature/TenantIsolationTest.php`.

### Server-set `organization_id` (FR-003)
`organization_id` is **excluded from `$fillable`** on tenant-owned models and set by direct assignment on bootstrap paths. `Organization` is the **tenant root** — it has no `organization_id` of its own; its ULID `id` *is* the tenant id. Do not make `organization_id` client-supplied anywhere.

### Tenancy rules (read before touching any query) [Source: app/Shared/Tenancy/*]
- `BelongsToTenant` adds a global `tenant` scope (fail-closed: no tenant + not system ⇒ `whereRaw('1=0')`) and a `creating` hook that forces `organization_id` from `TenantContext` when resolved.
- `TenantContext` API is **`setOrganization(...)`** + **`runAsSystem(fn)`** only. **There is no `runAsTenant`** — do not invent it.
- **Architecture test forbids `withoutGlobalScope('tenant')` outside `app/Shared/Tenancy/`** [Source: tests/Architecture/TenantIsolationArchTest.php]. Use `runAsSystem` for sanctioned cross-tenant reads.
- A freshly signed-up user has **no membership** → `TenantContext::has()` false → tenant-scoped queries return zero rows. They can still hit the **non-tenant** routes `POST /organizations` and `GET /organizations` (the bootstrap path this story relies on). Tenant-scoped routes require the `X-Organization-Id` header + active membership (resolved by `ResolveTenant`).

### AC-4 duplicate-name detail
`organizations.slug` has a **unique index** [Source: database/migrations/2026_06_18_000500_create_organizations_table.php#L16]; slug is auto-derived from name. `StoreOrganizationRequest` today validates only `name` (`required|string|max:255`, `branding` nullable array) with `authorize()=true` — **no uniqueness**, so a dup name hits the DB index and 500s. Fix in the FormRequest (validate derived-slug uniqueness) so it’s a clean 422 before the transaction.

### AC-5 / AR-6 — neutral 404 for the org access path (DECIDED 2026-06-20)
FR-004: *"cross-tenant access returns 404"*; flows.md specifies a **neutral 404** (no existence leak). Resources using `BelongsToTenant` already 404 cross-tenant (`Phase2TenantIsolationTest`). The **organizations** path is the exception: because `Organization` is the tenant root (not `BelongsToTenant`-scoped), `ResolveTenant` 403s a non-member before the controller. Honoring AC-5 means making `GET/PATCH /organizations/{id}` 404 for non-members and flipping the 6 existing 403 assertions. Treat this as a deliberate, cited change — not a silent test edit.

### Frontend — reuse the Story 1.0 foundation (do NOT re-implement) [Source: frontend/src/components/*]
- Primitives: `Button` (`variant`, `loading`), **`Field`** (the only labelled-input primitive — `useId` + `<label htmlFor>` + `aria-invalid`/`aria-describedby` + `dir="auto"`; use for the org-name input), `FormLayout` (field-group wrapper), `Banner` (`info|error|success`, `role=alert` on error), `Link`, `Spinner`/`Skeleton`, `StateBlock`, **`AppShell`** (the console-surface wrapper, rail+main, RTL-aware).
- No `Select`/`Checkbox`/`Textarea`/`Form` primitive exists — not needed here (name is text).
- Routing: no router lib — `App.tsx` regex-matches `window.location.pathname` in `resolveRoute()` and returns a page; `QueryClientProvider` (module-level client) + `DirectionProvider` already wrap the app. Console pages render their own `<AppShell>`; public pages (ApplyPage) don't.
- API/error conventions: `API_BASE_URL` from `src/api/client.ts`; `credentials:'include'` for authed calls; zod-parse responses; typed error class pattern = `SubmitError` in `schemas/apply.ts` (status → `code` mapping, 401 → `UNAUTHENTICATED`).
- RTL/lang: `useDirection()` from `src/app/direction-context.ts`; reuse the ApplyPage language-toggle button pattern if a toggle is needed. Never re-decide RTL per feature.
- **Must be built new (none exists today):** `/login` + `/auth/callback` routes, session/current-user query, the no-org gate, `api/session.ts`, `api/organizations.ts`, `OnboardingPage`, minimal Home. `react-hook-form`+`zod` are installed but unused so far — fine to introduce `zodResolver` here, or follow ApplyPage’s controlled-`Field` + `useState` pattern; either is acceptable.

### Testing standards
- **Backend:** feature tests authenticate with `actingAs($user, 'web')` (only guard). Helpers in `backend/tests/TestCase.php`: `makeExternalUser()`, `bootUserWithOrg($name)` → `[user, org]` (creator owns org), `createBareOrg($name)` → foreign org for isolation tests, `withoutTenantContext(fn)` (sanctioned wrapper to call `CreateOrganization`), `actingAsTenant($user,$org)`. Tenant-scoped HTTP calls add header `X-Organization-Id: $org->id`. Assert membership/role via `assertDatabaseHas('organization_roles', ['key'=>'owner','is_system'=>1, ...])`, `organization_memberships` `status=active`, and `effectivePermissionKeys()`.
- **Frontend:** Vitest + Testing Library (jsdom). Render wrapper = `<DirectionProvider><QueryClientProvider client={new QueryClient({defaultOptions:{queries:{retry:false}}})}>…`. Mock fetch via `vi.spyOn(globalThis,'fetch').mockResolvedValueOnce(jsonResponse(body,status))`; 401 = `new Response(null,{status:401})`. In-memory `localStorage` via `vi.stubGlobal` if needed. Add new pages to the axe gate (`src/tests/a11y.test.tsx`); new shared components need a `*.stories.tsx` (incl. an Arabic/RTL story).
- **Gates (run after coding):** Backend — `./vendor/bin/pint --dirty --test`, `./vendor/bin/phpstan analyse --memory-limit=1G` (PHPStan L6), `php artisan test`, OpenAPI regen if shapes changed. Frontend — typecheck, lint, `vitest`.

### Project Structure Notes
- Backend: `app/Modules/Organizations/{Application,Domain/Models,Http,Policies}` (touch only `Http/Requests/StoreOrganizationRequest.php` and possibly `Http/OrganizationController.php` + tests for Task 1). Audit kernel `App\Shared\Audit`, tenancy kernel `App\Shared\Tenancy`.
- Frontend: pages in `frontend/src/pages/`, api in `frontend/src/api/`, schemas in `frontend/src/schemas/`, routing in `frontend/src/app/App.tsx`. Reuse `frontend/src/components/*`.
- **Entitlements:** org-creation is **not** an enumerated `EntitlementService` call site in P1a (only `program.publish`, `cohort.open`, `application.submit`) — do not add an entitlement gate here (FR-060).
- **Audit org_id is null on creation** (no tenant resolved yet) — accepted by existing code; optionally backfill via `AuditLogger::record(..., organizationId: $org->id)`, not required by an AC.

### References
- [Source: _bmad-output/planning-artifacts/epics.md#L165-L178] — Story 1.1 ACs
- [Source: _bmad-output/planning-artifacts/prd.md#L98-L102, #L135, #L178] — FR-001/002/003/004/005, FR-060, NFR-002
- [Source: _bmad-output/planning-artifacts/epics.md#L96] — AR-6 (per-table isolation test, ADR-3)
- [Source: docs/product/flows.md#L143, #L148-L161] — neutral 404; first-run sign-up → not-skippable create-org → Home sequence
- [Source: backend/app/Modules/Organizations/Application/CreateOrganization.php] — existing create flow + `organization.created` audit
- [Source: backend/app/Modules/Identity/Http/AuthController.php; backend/app/Modules/Identity/Application/CompleteLogin.php; backend/routes/api.php#L110-L115] — OIDC auth endpoints
- [Source: backend/database/migrations/2026_06_18_000500_create_organizations_table.php] — `slug` unique index
- [Source: backend/tests/TestCase.php; backend/tests/Feature/OrganizationApiTest.php; backend/tests/Feature/TenantIsolationTest.php; backend/tests/Feature/Phase2TenantIsolationTest.php; backend/tests/Architecture/TenantIsolationArchTest.php] — test helpers, 403/404 patterns, arch constraint
- [Source: frontend/src/app/App.tsx; frontend/src/api/client.ts; frontend/src/api/apply.ts; frontend/src/schemas/apply.ts; frontend/src/components/*; frontend/src/app/direction-context.ts; frontend/src/tests/a11y.test.tsx] — frontend foundation & conventions

## Dev Agent Record

### Agent Model Used

Claude Opus 4.8 (1M context) — Task 1 (backend) inline; Tasks 2 & 3 (frontend) via a delegated agent, gates independently re-verified.

### Debug Log References

- A/B isolation (`git stash` on `ResolveTenant.php`) confirmed the two `ProgramConfigApiTest` cross-tenant tests were hitting the middleware's non-member abort (not the controller `authorize()` the comment assumed); reconciled to accept the neutral `[403,404]`.

### Completion Notes List

- **Task 1 (backend):** dup-name → clean 422 (derived-slug rule in `StoreOrganizationRequest`); cross-tenant org access → neutral 404 via `ResolveTenant` non-member abort (403→404) **and** a new `OrganizationController::assertResolvedOrg()` guard closing a second leak vector (own valid header + foreign org id had returned **200**). Permission/route-guard 403s left intact. Backend suite: 350 tests, 349 pass, 1 pgsql-skip; Pint + PHPStan L6 clean; OpenAPI unchanged.
- **Tasks 2 & 3 (frontend):** SPA OIDC entry (`session.ts` + `/login` + `/auth/callback`), a root `ConsoleGate` (session+orgs React Query) enforcing the non-skippable no-org flow, `OnboardingPage` (create-org, 422 duplicate-name preserves input), and a minimal `HomePage` (real content deferred to Story 1.5). Gates independently re-run: typecheck clean, eslint clean, vitest 58/58 across 16 files (incl. a11y + contrast).
- **Deviations (frontend agent, accepted):** (1) `HealthPage` moved `/`→`/health` so the gate owns root; its test updated. (2) onboarding success invalidates `['organizations']` so the gate re-evaluates to Home (no hard redirect — stays in the no-router model). (3) `/auth/callback` success → `window.location.assign('/')` so the gate decides onboarding-vs-Home. (4) actual 422 body is `{error:{code,message,correlation_id,details:{name:[...]}}}`; UI reads `error.details.name[0]`.

### File List

Task 1 (backend) — modified:
- backend/app/Modules/Organizations/Http/Requests/StoreOrganizationRequest.php (derived-slug uniqueness rule → 422)
- backend/app/Http/Middleware/ResolveTenant.php (non-member abort 403 → neutral 404)
- backend/app/Modules/Organizations/Http/OrganizationController.php (assertResolvedOrg guard on show/update)
- backend/tests/Feature/OrganizationApiTest.php (non-member-view→404; +vector-2 and dup-name tests)
- backend/tests/Feature/TenantIsolationTest.php (two cross-tenant cases→404, renamed)
- backend/tests/Feature/Programs/ProgramConfigApiTest.php (two cross-tenant key-leak tests accept [403,404])

Tasks 2 & 3 (frontend) — new:
- frontend/src/schemas/session.ts
- frontend/src/schemas/organizations.ts
- frontend/src/api/session.ts
- frontend/src/api/organizations.ts
- frontend/src/pages/LoginPage.tsx
- frontend/src/pages/AuthCallbackPage.tsx
- frontend/src/pages/OnboardingPage.tsx
- frontend/src/pages/HomePage.tsx
- frontend/src/api/session.test.ts
- frontend/src/api/organizations.test.ts
- frontend/src/app/App.test.tsx
- frontend/src/pages/OnboardingPage.stories.tsx
- frontend/src/pages/LoginPage.stories.tsx

Tasks 2 & 3 (frontend) — modified:
- frontend/src/app/App.tsx (added /login, /auth/callback, /health routes + ConsoleGate no-org gate at root)
- frontend/src/tests/a11y.test.tsx (added LoginPage, OnboardingPage, HomePage to the axe gate)
- frontend/src/tests/HealthPage.test.tsx (HealthPage moved from root to /health; test navigates there)

### Code-review fixes (1–10) — files

New: `frontend/src/api/errors.ts` (shared `ApiError` base), `frontend/src/tests/test-utils.ts` (shared `jsonResponse`), `frontend/src/app/queryClient.ts` (extracted client).
Modified — frontend: `app/App.tsx` (flash fix, render-prop gate, dead-branch collapse, staleTime), `api/session.ts` + `pages/AuthCallbackPage.tsx` (redirect-after-login), `api/organizations.ts` (name-only DUPLICATE_NAME), `schemas/session.ts` + `schemas/organizations.ts` (extend ApiError), `api/session.test.ts` + `api/organizations.test.ts` + `app/App.test.tsx` (shared helper + new tests + cache isolation).
Modified — backend: `Organizations/Http/MembershipController.php` (route/header mismatch 403→404), `Organizations/Application/CreateOrganization.php` (unique-violation race → 422), `tests/Feature/TenantIsolationTest.php` + `tests/Feature/OrganizationApiTest.php` + `tests/Feature/Programs/ProgramConfigApiTest.php` (assertions/tests).

## Change Log

| Date | Change |
|------|--------|
| 2026-06-20 | Story drafted from epics + exhaustive code/docs analysis; status → ready-for-dev |
| 2026-06-20 | Resolved AC-5 403→404: org access path hardened to neutral 404 (FR-004); dev gate removed |
| 2026-06-20 | Implemented: Task 1 backend (422 dup-name, neutral 404 incl. second leak vector); Tasks 2&3 frontend (OIDC entry, no-org gate, onboarding, minimal Home). Backend 349 pass/1 skip + Pint/PHPStan; frontend typecheck/lint + 58 tests. Status → review |
| 2026-06-20 | Applied 10 code-review fixes (8 candidates refuted at verify): flash gate, AR-6 404 on membership path, structural gate-wrapper, dup-name race→422, redirect-after-login, precise 422 mapping, dead-branch, de-dup, staleTime, tightened assertions. All 6 gates re-verified green (backend 350/1-skip, frontend 61). |

## Open Questions (for PM/architect before or during dev)

1. ~~403 vs 404 for the organizations access path (AC-5).~~ **RESOLVED 2026-06-20:** harden the org access path to a neutral **404** and update the 6 existing 403 assertions (matches FR-004, flows.md, and the rest of the system). Captured in AC-5 + Task 1.2.
2. **Home in 1.1 vs 1.5.** This story lands on a *minimal* AppShell Home and defers real content to Story 1.5. Confirm that split (recommended) rather than building Home content here. *(Still open — low risk; the default split stands unless you say otherwise.)*
