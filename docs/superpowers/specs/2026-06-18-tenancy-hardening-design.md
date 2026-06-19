# Tenancy Hardening — Design (sub-project)

Status: Approved (2026-06-18)
Branch: `arch-hardening-tenancy-seams`
Driver: `docs/superpowers/specs/2026-06-18-scope-validation-design.md` §6 (C1/C2/C3) + §7 corrections 1.
Governing rules: `CLAUDE.md` rules 6 (every tenant-owned record has `organization_id`), 7 (every tenant query enforces isolation).

## 1. Goal

Make multi-tenant isolation **fail-closed** and **structurally enforced**, and tighten
mass-assignment on tenant-owned models. This closes the architecture review's CRITICAL
finding C1 ("tenant isolation is opt-in and fails OPEN") and IMPORTANT finding C3
(`$guarded = []` full mass-assignment), on the current ~6-module codebase where the fix is
cheap — before more modules are built on the fragile foundation.

## 2. Scope

**In scope:**
- C1: fail-closed `BelongsToTenant`; explicit, audited cross-tenant system context; an
  architecture test enforcing the trait on every tenant-owned model; migrate legitimate
  `withoutGlobalScope('tenant')` call sites to the new system-context API.
- C3: `$guarded = []` → explicit `$fillable` on tenant-owned models.

**Out of scope (separate sub-projects):**
- C2 hostname/subdomain tenant resolution + unknown-host rejection → custom-domains sub-project (docs/34).
- Reliability seam (transactional outbox + inbound idempotency) and entitlement seam
  (`EntitlementService`/`UsageMeter`/`SubscriptionGuard`) → a following hardening sub-project.
- Inter-module contract style (typed events vs service calls) → a following decision.
- No new domain features (no Programs/Cohorts/Stages feature work).

## 3. Current state (what we're changing)

`app/Shared/Tenancy/BelongsToTenant.php` applies the global scope only `if ($ctx->has())`,
and the `creating` hook stamps `organization_id` only `if ($ctx->has())`. Consequences
(verified by the scope-validation architecture review):
- Queue jobs / console commands / platform-admin requests (no resolved tenant) run
  tenant-owned queries **unfiltered across all tenants**.
- Any model that omits the trait has **zero** isolation, silently.
- Several call sites use `withoutGlobalScope('tenant')` directly (e.g. `ResolveTenant`
  membership lookup, `CreateOrganization`, org/membership `index`) — ungreppable intent.

Tenant-owned models today: `Program`, `Cohort`, `ProgramPolicyRecord`,
`ProgramRoleRequirement`, `ProgramStage`, `StageVersion`, `StageRule`, `StageTransition`,
`ParticipantStageStatus`, `StageInstance`, `OrganizationRole`, `OrganizationMembership`
(+ pivots). Global (NOT tenant-scoped): `Organization`, `OrganizationPermission`,
`ExternalUser`, `ExternalUserToken`, `ProfileSnapshot`, `AuditLog`.

## 4. Design

### 4.1 `TenantContext` — explicit system context
Add an `isSystem` flag and a scoped runner:
- `public function runAsSystem(callable $fn): mixed` — sets `isSystem = true`, runs `$fn`,
  restores the prior value in a `finally` (re-entrant-safe). Returns `$fn`'s result.
- `public function isSystem(): bool`.
This is the ONLY sanctioned way to span tenants where no specific tenant is resolved
(global maintenance jobs, the org-bootstrap path, cross-tenant admin reads). It is explicit,
greppable, and auditable — unlike scattered `withoutGlobalScope`.

### 4.2 `BelongsToTenant` — fail-closed
- **Read (global scope):**
  - `$ctx->has()` → `where(table.organization_id, $ctx->organizationId())` (unchanged).
  - `$ctx->isSystem()` → no constraint (explicit cross-tenant).
  - otherwise → `$builder->whereRaw('1 = 0')` (**fail closed** — return nothing, never all tenants).
- **Create (`creating` hook):**
  - `$ctx->has()` → **force** `organization_id = $ctx->organizationId()` (always overwrite;
    a request can never set/spoof the org because the value comes from the resolved tenant,
    not the payload).
  - else `organization_id` already present on the model (set in code via direct attribute
    assignment / `forceFill` — see below) → allow (the bootstrap / system path).
  - else → throw `App\Shared\Tenancy\Exceptions\TenantContextMissingException`
    (no silent orphan/null-org writes).
- **`organization_id` is never mass-assignable** (kept out of `$fillable`, §4.6). System code
  that must create a tenant-owned record without a resolved tenant (e.g. `CreateOrganization`)
  sets it via **direct attribute assignment** (`$model->organization_id = $org->id; …; $model->save();`)
  or `forceFill`, which bypasses `$fillable`. `CreateOrganization`'s creates are therefore
  updated from `Model::create(['organization_id' => …, …])` to set `organization_id` outside
  the mass-assigned array (and may run inside `runAsSystem`). This is the seam that makes C3
  safe: the org binding always comes from server state, never the request body.

### 4.3 `TenantModel` (light convention)
`abstract class App\Shared\Tenancy\TenantModel extends Model { use HasUlids, BelongsToTenant; }`
New tenant-owned models SHOULD extend it. Existing models keep the trait directly (no churn).
The architecture test (4.4), not inheritance, is the enforcement.

### 4.4 Architecture test (enforcement)
`tests/Architecture/TenantIsolationArchTest.php`:
1. For every Eloquent model class under `app/Modules/**/Domain/Models` and `app/Shared/**`:
   resolve its table; if the table has an `organization_id` column, assert the model's class
   uses the `BelongsToTenant` trait (recursively, incl. via `TenantModel`). Fail with the
   offending class name.
2. An explicit **global allowlist** (`Organization`, `OrganizationPermission`, `ExternalUser`,
   `ExternalUserToken`, `ProfileSnapshot`, `AuditLog`, pivot models without `organization_id`)
   documents the models that legitimately are not tenant-scoped.
3. Assert `withoutGlobalScope('tenant')` does not appear in `app/` outside
   `app/Shared/Tenancy/` (a static grep test) — forcing `runAsSystem` for cross-tenant access.
Implementation reads the schema via `Schema::hasColumn($table,'organization_id')` against the
migrated test DB and reflects traits via `class_uses_recursive`.

### 4.5 Migrate call sites to `runAsSystem`
Replace legitimate `withoutGlobalScope('tenant')` usages in production code with
`app(TenantContext::class)->runAsSystem(fn () => …)`:
- `ResolveTenant` membership lookup (resolves the tenant before context exists).
- `CreateOrganization` (bootstrap — also already sets explicit org_id on creates).
- `OrganizationController::index` / `MembershipController::index` cross-membership listings.
- `effectivePermissionKeys()` role query if it relies on unscoped access.
Test-only `withoutGlobalScope` in factories/seeders is acceptable but PREFER `runAsSystem`
or setting `TenantContext` for clarity; the arch test (4.4.3) scopes its grep to `app/` only.

### 4.6 C3 — explicit `$fillable`
Replace `$guarded = []` with an explicit `$fillable` array on each tenant-owned model,
listing exactly the attributes controllers/services assign (names, slugs, status, config,
JSONB fields, FKs that are legitimately caller-supplied) — and deliberately **excluding
`organization_id`** (and `id`), which are never mass-assigned: `organization_id` is forced by
the trait from `TenantContext` on request creates and set via direct assignment/`forceFill`
on the system/bootstrap path (§4.2). Casts unchanged. Global models may keep their current
pattern unless trivially tightened. (Note: existing system creates that pass `organization_id`
in a `create([...])` array — `CreateOrganization` — must move it out of the mass-assigned
array per §4.2, since it is no longer fillable.)

## 5. Migration / call-site impact

Fail-closed will surface code and tests that quietly relied on fail-open (querying tenant
models with no resolved `TenantContext`). Each is fixed by setting the context or wrapping in
`runAsSystem` — never by weakening the scope. Expected churn: a handful of unit/feature tests
and the index/bootstrap call sites in §4.5. No schema migrations (behavioral + model changes
only). Rollback: revert the trait/`TenantContext`/model changes; no data migration.

## 6. Testing

- **Architecture:** trait-on-every-tenant-model; global allowlist; no `withoutGlobalScope`
  in `app/` outside the tenancy layer.
- **Fail-closed behavior (unit/feature):**
  - No resolved tenant → reading a tenant-owned model returns empty (not cross-tenant rows).
  - No resolved tenant, no explicit `organization_id` → creating a tenant-owned model throws
    `TenantContextMissingException`.
  - `runAsSystem(fn)` → the same read returns cross-tenant rows; flag restored afterward
    (nested/re-entrant safe).
  - Resolved tenant → only that org's rows; another org's rows invisible (regression).
- **C3:** mass-assigning a non-fillable attribute (e.g. spoofed `organization_id` or `id`) is
  ignored; the trait still stamps the correct `organization_id`.
- **Full suite green** after migrating affected call sites (was 194 on `main`).
- pint + phpstan(level 6, `--memory-limit=512M`) clean.

## 7. Acceptance criteria

| Criterion | Covered by |
|---|---|
| Tenant reads fail closed without a resolved tenant | §4.2, tests §6 |
| Cross-tenant access only via explicit `runAsSystem` | §4.1/§4.5, arch test §4.4.3 |
| Every tenant-owned model is isolation-enforced | §4.4 arch test |
| No silent null-org writes | §4.2 creating hook + test |
| Mass-assignment tightened (C3) | §4.6 + test |
| Existing behavior preserved (resolved-tenant isolation, org bootstrap) | §4.2/§4.5, full suite green |
