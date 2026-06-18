# Phase 1 — Identity & Tenancy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement mock Startup Gate OIDC login with a `sub`-keyed external-user projection, immutable profile snapshots, organizations/memberships, org-scoped RBAC, tenant isolation, and audit logging — all behind adapter interfaces that the real Startup Gate replaces via config in Phase 12.

**Architecture:** One Laravel 13 codebase runs in two roles. The **platform** depends only on identity/profile adapter *interfaces* (bound to Startup Gate HTTP clients). The **Startup Gate mock** is an isolated `app/StartupGateMock/` namespace whose routes register only when `APP_ROLE=mock`; it issues RS256-signed JWTs with a JWKS endpoint and reproduces the docs/10 contract. Tenancy is enforced by an `X-Organization-Id` middleware that populates a request-scoped `TenantContext`, plus a `BelongsToTenant` Eloquent scope.

**Tech Stack:** PHP 8.3 (target) / Laravel 13, PostgreSQL, Redis, `laravel/sanctum` (SPA cookie session), `firebase/php-jwt ^7.0` (RS256 + JWKS; the 7.x line patches CVE-2025-45769), ULIDs via `HasUlids`, PHPUnit, Pint, Larastan.

## Global Constraints

- PHP `declare(strict_types=1);` in every PHP file; TypeScript strict (frontend untouched here).
- Use `sub` (`startup_gate_subject_id`) as the immutable user key. **Never** key users on email.
- Do not duplicate full Startup Gate profiles: only the projection (`external_users`) and immutable `profile_snapshots`.
- All profile reads are consent-aware (check consent/scope before returning fields).
- Every tenant-owned record has `organization_id`; every tenant query is scoped; composite uniques include `organization_id`.
- Published/immutable records cannot mutate (here: `profile_snapshots` are insert-only).
- No mock-specific logic in `app/Modules/*`; mock lives only in `app/StartupGateMock/`.
- ULID/string PKs (char 26), UTC storage, ISO 8601 in APIs, JSONB only for bounded config.
- Standard error object (docs/06) with `correlation_id` on every error response.
- Sensitive actions server-authorized; unauthorized → HTTP 403; invalid/expired token → HTTP 401.
- Each task ends green (tests + `pint --test` + `phpstan --memory-limit=512M`) and is committed.
- Run all PHP from `backend/`. Test command: `php artisan test --filter=<Name>`.

---

## Shared interfaces (referenced across tasks — exact signatures)

```php
// app/Modules/Identity/Domain/Contracts/IdentityProvider.php
namespace App\Modules\Identity\Domain\Contracts;

interface IdentityProvider
{
    /** @return array{authorization_url:string} built with state, nonce, PKCE S256 challenge */
    public function buildAuthorizationUrl(string $state, string $nonce, string $codeChallenge, array $scopes): string;

    /** @return array{id_token:string, access_token:string, refresh_token:?string, expires_in:int} */
    public function exchangeCode(string $code, string $codeVerifier): array;

    /** @return array<string,mixed> validated ID-token claims; throws InvalidTokenException on any failure */
    public function validateIdToken(string $idToken, string $expectedNonce): array;

    /** @return array{access_token:string, refresh_token:?string, expires_in:int} */
    public function refresh(string $refreshToken): array;

    public function revoke(string $token): void;

    /** @return array<string,mixed> userinfo claims */
    public function userinfo(string $accessToken): array;
}

// app/Modules/Identity/Domain/Contracts/ProfileProvider.php
interface ProfileProvider {
    /** @return array<string,mixed> */ public function generalProfile(string $accessToken): array;
}
// ConsentProvider::consents(string $accessToken): array<int,array{scope:string,granted:bool,reference:string}>
// RoleProfileProvider::roleProfiles(string $accessToken): array<int,array<string,mixed>>
// StartupMembershipProvider::startups(string $accessToken): array<int,array<string,mixed>>
// AchievementPublisher::publish(string $accessToken, array $achievement): array   // interface only in Phase 1
```

```php
// app/Shared/Tenancy/Contracts/TenantMembership.php
namespace App\Shared\Tenancy\Contracts;
interface TenantMembership {
    public function organizationId(): string;
    /** @return array<int,string> */ public function effectivePermissionKeys(): array;
}

// app/Shared/Tenancy/TenantContext.php  (request-scoped singleton)
// Depends only on the TenantMembership interface — NOT the concrete Eloquent
// model — so Tenancy has no compile-time dependency on the Organizations module.
final class TenantContext {
    public function setOrganization(string $organizationId, TenantMembership $membership, array $permissionKeys): void;
    public function organizationId(): ?string;           // null when no tenant resolved
    public function membership(): ?TenantMembership;
    public function has(): bool;                          // true when an org is resolved
    public function can(string $permissionKey): bool;     // platform admin always true
    public function actingAsPlatformAdmin(bool $is): void;
}
```

---

## Milestone M0 — Dependencies, config, dual-role gating

### Task 0.1: Install Sanctum + firebase/php-jwt; pin platform

**Files:**
- Modify: `backend/composer.json`, `backend/composer.lock`
- Modify: `backend/bootstrap/providers.php` (Sanctum auto-discovers; no manual add needed)

- [ ] **Step 1: Install deps (platform already pinned to php 8.3)**

Run:
```bash
cd backend
composer require laravel/sanctum:^4 firebase/php-jwt:^7.0 --no-interaction
```
Expected: both added; lock updated; `composer.lock` resolves on php 8.3.
Do NOT disable Composer's audit/advisory blocking — `^7.0` is already free of
the known php-jwt advisory (CVE-2025-45769), so `composer audit` stays clean.

- [ ] **Step 2: Publish Sanctum config + migration**

Run:
```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --no-interaction
```
Expected: `config/sanctum.php` and a `*_create_personal_access_tokens_table` migration appear.

- [ ] **Step 3: Verify suite still green, commit**

Run: `php artisan test && ./vendor/bin/pint --test`
Expected: PASS.
```bash
git add -A && git commit -m "chore: add sanctum + firebase/php-jwt for Phase 1"
```

### Task 0.2: Identity/tenancy config + `APP_ROLE` gate + `.env.example`

**Files:**
- Create: `backend/config/identity.php`
- Create: `backend/config/tenancy.php`
- Modify: `backend/.env.example`
- Modify: `backend/config/auth.php` (point `providers.users.model` at the projection — done in Task 2.1)

**Interfaces:**
- Produces: `config('identity.provider')`, `config('identity.oidc.*')`, `config('app.role')`.

- [ ] **Step 1: Add `role` to `config/app.php`**

Add to the returned array in `config/app.php`:
```php
    'role' => env('APP_ROLE', 'platform'), // 'platform' | 'mock'
```

- [ ] **Step 2: Create `config/identity.php`**

```php
<?php

declare(strict_types=1);

return [
    'provider' => env('IDENTITY_PROVIDER', 'mock'),
    'oidc' => [
        'issuer' => env('OIDC_ISSUER', 'http://startup-gate-mock:8080'),
        'client_id' => env('OIDC_CLIENT_ID', 'program-platform'),
        'client_secret' => env('OIDC_CLIENT_SECRET', 'local-secret'),
        'redirect_uri' => env('OIDC_REDIRECT_URI', 'http://localhost:3000/auth/callback'),
        'scopes' => [
            'openid', 'profile.basic.read', 'profile.professional.read',
            'profile.founder.read', 'profile.mentor.read',
            'profile.service_provider.read', 'profile.startups.read',
            'profile.documents.read',
        ],
    ],
    'profile_api_base_url' => env('PROFILE_API_BASE_URL', 'http://startup-gate-mock:8080/api/v1'),
    // Mock signing keys (mock role only). In testing a fixed pair is injected.
    'mock' => [
        'private_key' => env('SG_MOCK_PRIVATE_KEY'),
        'public_key' => env('SG_MOCK_PUBLIC_KEY'),
        'kid' => env('SG_MOCK_KID', 'sg-mock-key-1'),
        'webhook_secret' => env('SG_MOCK_WEBHOOK_SECRET', 'mock-webhook-secret'),
    ],
];
```

- [ ] **Step 3: Create `config/tenancy.php`**

```php
<?php

declare(strict_types=1);

return [
    'header' => 'X-Organization-Id',
];
```

- [ ] **Step 4: Append Phase-1 vars to `backend/.env.example`**

Append:
```env

# --- Dual role: platform (default) or mock ---
APP_ROLE=platform

# Mock signing keys are generated by `php artisan sg-mock:keys` for local dev.
SG_MOCK_KID=sg-mock-key-1
SG_MOCK_WEBHOOK_SECRET=mock-webhook-secret

# Sanctum SPA
SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DOMAIN=localhost
```

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: identity/tenancy config + APP_ROLE gating"
```

---

## Milestone M1 — Tenancy primitives, audit, error envelope

### Task 1.1: Standard error envelope + correlation id

**Files:**
- Create: `backend/app/Shared/Support/CorrelationId.php`
- Create: `backend/app/Http/Middleware/AssignCorrelationId.php`
- Modify: `backend/bootstrap/app.php` (append middleware to `api` group; register alias later)
- Modify: `backend/app/Http/Controllers/HealthController.php` (none) ; error rendering in `withExceptions`
- Test: `backend/tests/Feature/ErrorEnvelopeTest.php`

**Interfaces:**
- Produces: every JSON error has shape `{"error":{"code","message","details"?,"correlation_id"}}`.

- [ ] **Step 1: Write failing test**

```php
<?php
declare(strict_types=1);
namespace Tests\Feature;
use Tests\TestCase;

final class ErrorEnvelopeTest extends TestCase
{
    public function test_validation_errors_use_standard_envelope(): void
    {
        // /api/v1/organizations requires auth; unauthenticated => 401 envelope
        $res = $this->getJson('/api/v1/organizations');
        $res->assertStatus(401)
            ->assertJsonStructure(['error' => ['code', 'message', 'correlation_id']]);
    }
}
```

- [ ] **Step 2: Run, expect FAIL** (route/middleware not present yet)

Run: `php artisan test --filter=ErrorEnvelopeTest`
Expected: FAIL.

- [ ] **Step 3: Implement correlation id + exception rendering**

`app/Shared/Support/CorrelationId.php`:
```php
<?php
declare(strict_types=1);
namespace App\Shared\Support;
use Illuminate\Support\Str;

final class CorrelationId
{
    private static ?string $value = null;
    public static function set(string $id): void { self::$value = $id; }
    public static function get(): string { return self::$value ??= 'corr_'.Str::ulid(); }
}
```

`app/Http/Middleware/AssignCorrelationId.php`:
```php
<?php
declare(strict_types=1);
namespace App\Http\Middleware;
use App\Shared\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;

final class AssignCorrelationId
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->header('X-Correlation-Id') ?: CorrelationId::get();
        CorrelationId::set($id);
        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $id);
        return $response;
    }
}
```

In `bootstrap/app.php` `withMiddleware`:
```php
$middleware->api(prepend: [\App\Http\Middleware\AssignCorrelationId::class]);
```

In `bootstrap/app.php` `withExceptions`, render the envelope for `api/*`:
```php
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use App\Shared\Support\CorrelationId;
// ...
$exceptions->render(function (\Throwable $e, Request $request) {
    if (! $request->is('api/*')) { return null; }
    [$status, $code] = match (true) {
        $e instanceof ValidationException => [422, 'VALIDATION_ERROR'],
        $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED'],
        $e instanceof \Illuminate\Auth\Access\AuthorizationException => [403, 'FORBIDDEN'],
        $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface =>
            [$e->getStatusCode(), 'HTTP_'.$e->getStatusCode()],
        default => [500, 'SERVER_ERROR'],
    };
    $payload = ['error' => [
        'code' => $code,
        'message' => $status === 500 ? 'Server error.' : $e->getMessage(),
        'correlation_id' => CorrelationId::get(),
    ]];
    if ($e instanceof ValidationException) { $payload['error']['details'] = $e->errors(); }
    return response()->json($payload, $status);
});
```

- [ ] **Step 4: Add a temporary protected route to exercise 401**

In `routes/api.php` add inside `v1` group (will host real resources later):
```php
Route::middleware('auth:sanctum')->get('/organizations', fn () => response()->json([]));
```

- [ ] **Step 5: Run, expect PASS; pint; phpstan; commit**

```bash
php artisan test --filter=ErrorEnvelopeTest
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
git add -A && git commit -m "feat: standard API error envelope + correlation id"
```

### Task 1.2: Audit log (table, model, writer)

**Files:**
- Create: `backend/database/migrations/2026_06_18_000100_create_audit_logs_table.php`
- Create: `backend/app/Shared/Audit/AuditLog.php` (model)
- Create: `backend/app/Shared/Audit/AuditLogger.php`
- Test: `backend/tests/Unit/AuditLoggerTest.php`

**Interfaces:**
- Produces: `AuditLogger::record(string $action, ?string $targetType, ?string $targetId, array $before, array $after, string $result='success'): AuditLog`.

- [ ] **Step 1: Migration**

```php
<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('actor_external_user_id')->nullable()->index();
            $t->ulid('organization_id')->nullable()->index();
            $t->string('action');
            $t->string('target_type')->nullable();
            $t->string('target_id')->nullable();
            $t->jsonb('before')->nullable();
            $t->jsonb('after')->nullable();
            $t->string('ip_address')->nullable();
            $t->string('correlation_id')->nullable();
            $t->string('result')->default('success');
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['target_type', 'target_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('audit_logs'); }
};
```

- [ ] **Step 2: Failing test**

```php
<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;
    public function test_records_an_audit_entry(): void
    {
        app(AuditLogger::class)->record('organization.created', 'organization', '01ABC', [], ['name' => 'Acme']);
        $this->assertDatabaseCount('audit_logs', 1);
        $log = AuditLog::first();
        $this->assertSame('organization.created', $log->action);
        $this->assertSame(['name' => 'Acme'], $log->after);
    }
}
```

- [ ] **Step 3: Run, expect FAIL.** `php artisan test --filter=AuditLoggerTest`

- [ ] **Step 4: Implement model + logger**

`AuditLog.php`:
```php
<?php
declare(strict_types=1);
namespace App\Shared\Audit;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AuditLog extends Model
{
    use HasUlids;
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
}
```

`AuditLogger.php`:
```php
<?php
declare(strict_types=1);
namespace App\Shared\Audit;
use App\Shared\Support\CorrelationId;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function __construct(private TenantContext $tenant, private Request $request) {}

    public function record(string $action, ?string $targetType, ?string $targetId, array $before = [], array $after = [], string $result = 'success'): AuditLog
    {
        return AuditLog::create([
            'actor_external_user_id' => optional($this->request->user())->id,
            'organization_id' => $this->tenant->organizationId(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip_address' => $this->request->ip(),
            'correlation_id' => CorrelationId::get(),
            'result' => $result,
        ]);
    }
}
```
> `TenantContext` is created in Task 1.3; sequence 1.3 before running this test, or stub the binding. (Plan order: do 1.3 then 1.2's Step 5.)

- [ ] **Step 5: Run PASS; pint; phpstan; commit**

```bash
git add -A && git commit -m "feat: audit log table, model, writer"
```

### Task 1.3: TenantMembership interface, TenantContext, BelongsToTenant trait

> The `ResolveTenant` middleware is intentionally NOT built here — it needs the
> `OrganizationMembership` model + `effectivePermissionKeys()`, which are created
> in Task 6.2. Building it now would leave PHPStan referencing an unknown class.
> `TenantContext` depends only on the `TenantMembership` interface, so Tenancy has
> zero compile-time dependency on the Organizations module.

**Files:**
- Create: `backend/app/Shared/Tenancy/Contracts/TenantMembership.php` (interface)
- Create: `backend/app/Shared/Tenancy/TenantContext.php`
- Create: `backend/app/Shared/Tenancy/BelongsToTenant.php` (trait)
- Modify: `backend/app/Providers/AppServiceProvider.php` (bind `TenantContext` scoped singleton)
- Test: `backend/tests/Unit/TenantContextTest.php` (unit), tenant-isolation feature test added in M7.

**Interfaces:**
- Produces: `TenantMembership` interface (`organizationId(): string`, `effectivePermissionKeys(): array`); `TenantContext` (signatures in the Shared interfaces block above); `BelongsToTenant` trait adds the global scope + `creating` stamp using `organization_id`.

- [ ] **Step 1: Failing unit test**

```php
<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Shared\Tenancy\TenantContext;
use Tests\TestCase;

final class TenantContextTest extends TestCase
{
    public function test_permission_checks_against_resolved_set(): void
    {
        $ctx = new TenantContext();
        $this->assertFalse($ctx->has());
        $this->assertFalse($ctx->can('members.manage'));
    }

    public function test_platform_admin_bypasses_permission_checks(): void
    {
        $ctx = new TenantContext();
        $ctx->actingAsPlatformAdmin(true);
        $this->assertTrue($ctx->can('anything'));
    }
}
```

- [ ] **Step 2: Run, expect FAIL.** `php artisan test --filter=TenantContextTest`

- [ ] **Step 3: Implement TenantMembership interface + TenantContext**

`app/Shared/Tenancy/Contracts/TenantMembership.php`:
```php
<?php
declare(strict_types=1);
namespace App\Shared\Tenancy\Contracts;

interface TenantMembership
{
    public function organizationId(): string;

    /** @return array<int,string> */
    public function effectivePermissionKeys(): array;
}
```

`app/Shared/Tenancy/TenantContext.php`:
```php
<?php
declare(strict_types=1);
namespace App\Shared\Tenancy;
use App\Shared\Tenancy\Contracts\TenantMembership;

final class TenantContext
{
    private ?string $organizationId = null;
    private ?TenantMembership $membership = null;
    /** @var array<int,string> */ private array $permissions = [];
    private bool $platformAdmin = false;

    public function setOrganization(string $organizationId, TenantMembership $membership, array $permissionKeys): void
    { $this->organizationId = $organizationId; $this->membership = $membership; $this->permissions = $permissionKeys; }

    public function organizationId(): ?string { return $this->organizationId; }
    public function membership(): ?TenantMembership { return $this->membership; }
    public function has(): bool { return $this->organizationId !== null; }
    public function actingAsPlatformAdmin(bool $is): void { $this->platformAdmin = $is; }
    public function can(string $permissionKey): bool
    { return $this->platformAdmin || in_array($permissionKey, $this->permissions, true); }
}
```

- [ ] **Step 4: Implement BelongsToTenant trait**

```php
<?php
declare(strict_types=1);
namespace App\Shared\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $ctx = app(TenantContext::class);
            if ($ctx->has()) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $ctx->organizationId());
            }
        });
        static::creating(function (Model $model): void {
            $ctx = app(TenantContext::class);
            if ($ctx->has() && empty($model->organization_id)) {
                $model->organization_id = $ctx->organizationId();
            }
        });
    }
}
```

- [ ] **Step 5: Bind TenantContext as a scoped singleton**

In `app/Providers/AppServiceProvider.php` `register()`:
```php
$this->app->scoped(\App\Shared\Tenancy\TenantContext::class);
```
> The `ResolveTenant` middleware + its `tenant` alias are built in Task 6.2 (after `OrganizationMembership` exists). Do not add them here.

- [ ] **Step 6: Run PASS; pint; phpstan; commit**

```bash
php artisan test --filter=TenantContextTest
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
git add -A && git commit -m "feat: tenant context + membership interface + belongs-to-tenant scope"
```

---

## Milestone M2 — External-user projection, tokens, snapshots

### Task 2.1: `external_users` projection + auth wiring

**Files:**
- Create: `backend/database/migrations/2026_06_18_000200_create_external_users_table.php`
- Create: `backend/app/Modules/Identity/Domain/Models/ExternalUser.php`
- Modify: `backend/config/auth.php` (`providers.users.model` => ExternalUser)
- Test: `backend/tests/Feature/ExternalUserProjectionTest.php`

**Interfaces:**
- Produces: `ExternalUser` (Authenticatable), unique `startup_gate_subject_id`, `is_platform_admin`.
  `ExternalUser::projectFromClaims(array $claims): self` upserts by `sub`.

- [ ] **Step 1: Migration**

```php
<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('external_users', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('startup_gate_subject_id')->unique();   // immutable 'sub'
            $t->string('email')->nullable();                   // NOT a linkage key
            $t->string('display_name')->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('locale', 16)->nullable();
            $t->unsignedBigInteger('profile_version')->default(0);
            $t->string('synchronization_status')->default('pending');
            $t->timestampTz('synchronized_at')->nullable();
            $t->boolean('is_platform_admin')->default(false);
            $t->rememberToken();
            $t->timestampsTz();
        });
    }
    public function down(): void { Schema::dropIfExists('external_users'); }
};
```

- [ ] **Step 2: Failing test**

```php
<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Modules\Identity\Domain\Models\ExternalUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExternalUserProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_is_keyed_on_sub_not_email(): void
    {
        $claims = ['sub' => 'sg_user_01', 'email' => 'a@example.com', 'name' => 'A', 'locale' => 'en', 'profile_updated_at' => 1781712000];
        $u1 = ExternalUser::projectFromClaims($claims);

        // same sub, new email -> SAME projection updated, not a new row
        $u2 = ExternalUser::projectFromClaims([...$claims, 'email' => 'changed@example.com']);

        $this->assertSame($u1->id, $u2->id);
        $this->assertSame('changed@example.com', $u2->email);
        $this->assertDatabaseCount('external_users', 1);
    }

    public function test_different_sub_creates_distinct_user_even_with_same_email(): void
    {
        ExternalUser::projectFromClaims(['sub' => 'sg_user_01', 'email' => 'same@example.com']);
        ExternalUser::projectFromClaims(['sub' => 'sg_user_02', 'email' => 'same@example.com']);
        $this->assertDatabaseCount('external_users', 2);
    }
}
```

- [ ] **Step 3: Run, expect FAIL.**

- [ ] **Step 4: Implement model**

```php
<?php
declare(strict_types=1);
namespace App\Modules\Identity\Domain\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class ExternalUser extends Authenticatable
{
    use HasUlids;
    protected $guarded = [];
    protected $casts = [
        'is_platform_admin' => 'boolean',
        'synchronized_at' => 'datetime',
        'profile_version' => 'integer',
    ];

    public static function projectFromClaims(array $claims): self
    {
        return self::updateOrCreate(
            ['startup_gate_subject_id' => $claims['sub']],
            [
                'email' => $claims['email'] ?? null,
                'display_name' => $claims['name'] ?? null,
                'avatar_url' => $claims['picture'] ?? null,
                'locale' => $claims['locale'] ?? null,
                'profile_version' => isset($claims['profile_updated_at']) ? (int) $claims['profile_updated_at'] : 0,
                'synchronization_status' => 'synced',
                'synchronized_at' => now(),
            ],
        );
    }
}
```

- [ ] **Step 5: Point auth provider at the projection**

In `config/auth.php` set:
```php
'providers' => [
    'users' => ['driver' => 'eloquent', 'model' => \App\Modules\Identity\Domain\Models\ExternalUser::class],
],
```

- [ ] **Step 6: Run PASS; pint; phpstan; commit**

```bash
git add -A && git commit -m "feat: external_users projection keyed on sub"
```

### Task 2.2: Encrypted token store

**Files:**
- Create: `backend/database/migrations/2026_06_18_000300_create_external_user_tokens_table.php`
- Create: `backend/app/Modules/Identity/Domain/Models/ExternalUserToken.php`
- Test: `backend/tests/Feature/ExternalUserTokenTest.php`

- [ ] **Step 1: Migration** — columns: `id` ulid, `external_user_id` (fk index), `access_token` text, `refresh_token` text nullable, `scopes` jsonb nullable, `expires_at` timestamptz nullable, timestamps.

```php
<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('external_user_tokens', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('external_user_id')->index();
            $t->text('access_token');
            $t->text('refresh_token')->nullable();
            $t->jsonb('scopes')->nullable();
            $t->timestampTz('expires_at')->nullable();
            $t->timestampsTz();
        });
    }
    public function down(): void { Schema::dropIfExists('external_user_tokens'); }
};
```

- [ ] **Step 2: Failing test** — assert tokens are encrypted at rest (raw DB value != plaintext) and decrypt via cast.

```php
<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Modules\Identity\Domain\Models\ExternalUserToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ExternalUserTokenTest extends TestCase
{
    use RefreshDatabase;
    public function test_tokens_are_encrypted_at_rest(): void
    {
        $row = ExternalUserToken::create([
            'external_user_id' => '01HZZZEXTERNALUSERID00000001',
            'access_token' => 'plain-access',
            'refresh_token' => 'plain-refresh',
        ]);
        $raw = DB::table('external_user_tokens')->where('id', $row->id)->value('access_token');
        $this->assertNotSame('plain-access', $raw);
        $this->assertSame('plain-access', $row->fresh()->access_token);
    }
}
```

- [ ] **Step 3: Run FAIL.**

- [ ] **Step 4: Implement model with encrypted casts**

```php
<?php
declare(strict_types=1);
namespace App\Modules\Identity\Domain\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class ExternalUserToken extends Model
{
    use HasUlids;
    protected $guarded = [];
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'scopes' => 'array',
        'expires_at' => 'datetime',
    ];
}
```

- [ ] **Step 5: Run PASS; pint; phpstan; commit** `git commit -m "feat: encrypted external user token store"`

### Task 2.3: Immutable profile snapshots

**Files:**
- Create: `backend/database/migrations/2026_06_18_000400_create_profile_snapshots_table.php`
- Create: `backend/app/Modules/Identity/Domain/Models/ProfileSnapshot.php`
- Create: `backend/app/Modules/Identity/Application/CaptureProfileSnapshot.php`
- Test: `backend/tests/Feature/ProfileSnapshotTest.php`

**Interfaces:**
- Produces: `CaptureProfileSnapshot::capture(ExternalUser $u, string $contextType, ?string $contextId, array $payload, string $consentReference): ProfileSnapshot`. Hash = `hash('sha256', canonical_json(payload))`. Updates throw.

- [ ] **Step 1: Migration** — `id` ulid, `external_user_id` index, `context_type`, `context_id` nullable, `profile_version` int, `payload_json` jsonb, `consent_reference` nullable, `hash` char(64), `captured_at` timestamptz. No `updated_at`.

- [ ] **Step 2: Failing test**

```php
<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Modules\Identity\Application\CaptureProfileSnapshot;
use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Identity\Domain\Models\ProfileSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProfileSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_writes_immutable_snapshot_with_hash(): void
    {
        $u = ExternalUser::projectFromClaims(['sub' => 'sg_user_01', 'email' => 'a@b.c']);
        $snap = app(CaptureProfileSnapshot::class)->capture($u, 'identity', null, ['biography' => 'hi'], 'profile.basic.read');
        $this->assertSame(64, strlen($snap->hash));
        $this->assertSame(['biography' => 'hi'], $snap->payload_json);
    }

    public function test_snapshot_cannot_be_updated(): void
    {
        $u = ExternalUser::projectFromClaims(['sub' => 'sg_user_01']);
        $snap = app(CaptureProfileSnapshot::class)->capture($u, 'identity', null, ['x' => 1], 'profile.basic.read');
        $this->expectException(\RuntimeException::class);
        $snap->payload_json = ['x' => 2];
        $snap->save();
    }
}
```

- [ ] **Step 3: Run FAIL.**

- [ ] **Step 4: Implement model (immutability) + capture service**

```php
<?php
declare(strict_types=1);
namespace App\Modules\Identity\Domain\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class ProfileSnapshot extends Model
{
    use HasUlids;
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['payload_json' => 'array', 'captured_at' => 'datetime', 'profile_version' => 'integer'];

    protected static function booted(): void
    {
        static::updating(function (): void { throw new \RuntimeException('Profile snapshots are immutable.'); });
        static::deleting(function (): void { throw new \RuntimeException('Profile snapshots are immutable.'); });
    }
}
```

```php
<?php
declare(strict_types=1);
namespace App\Modules\Identity\Application;
use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Identity\Domain\Models\ProfileSnapshot;

final class CaptureProfileSnapshot
{
    public function capture(ExternalUser $user, string $contextType, ?string $contextId, array $payload, string $consentReference): ProfileSnapshot
    {
        $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return ProfileSnapshot::create([
            'external_user_id' => $user->id,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'profile_version' => $user->profile_version,
            'payload_json' => $payload,
            'consent_reference' => $consentReference,
            'hash' => hash('sha256', $canonical),
            'captured_at' => now(),
        ]);
    }
}
```

- [ ] **Step 5: Run PASS; pint; phpstan; commit** `git commit -m "feat: immutable profile snapshots with content hash"`

---

## Milestone M3 — Adapter interfaces + Startup Gate HTTP client + fakes

### Task 3.1: Define the six adapter interfaces

**Files:** Create the contracts in `app/Modules/Identity/Domain/Contracts/` exactly as in the Shared-interfaces block, one file each: `IdentityProvider`, `ProfileProvider`, `ConsentProvider`, `RoleProfileProvider`, `StartupMembershipProvider`, `AchievementPublisher`. Plus `InvalidTokenException` in `Domain/Exceptions/`.

- [ ] **Step 1:** Write the six interface files + `InvalidTokenException extends \RuntimeException`.
- [ ] **Step 2:** `./vendor/bin/phpstan analyse` (no test yet; interfaces only).
- [ ] **Step 3:** Commit `git commit -m "feat: identity adapter interfaces"`.

### Task 3.2: Startup Gate HTTP adapters + provider binding

**Files:**
- Create: `backend/app/Modules/Identity/Infrastructure/StartupGate/StartupGateIdentityProvider.php` (implements IdentityProvider; uses `Http::` client to `config('identity.oidc.issuer')`, validates ID token with `firebase/php-jwt` using JWKS from `/.well-known/jwks.json`).
- Create: `.../StartupGateProfileProvider.php`, `.../StartupGateConsentProvider.php`, `.../StartupGateRoleProfileProvider.php`, `.../StartupGateStartupMembershipProvider.php`, `.../StartupGateAchievementPublisher.php` (HTTP GET/POST against `config('identity.profile_api_base_url')` with bearer token).
- Create: `backend/app/Modules/Identity/IdentityServiceProvider.php` (binds interfaces → Startup Gate impls; registered in `bootstrap/providers.php`).
- Test: `backend/tests/Feature/IdentityProviderValidationTest.php` (uses the mock keys to sign tokens and asserts validation outcomes).

**Interfaces:**
- Produces: bindings so `app(IdentityProvider::class)` resolves the HTTP adapter under `IDENTITY_PROVIDER=mock` (same HTTP client; real Startup Gate differs only by config in Phase 12).

- [ ] **Step 1: Failing test** (sign a token with a known RSA key, expose JWKS via `Http::fake`, assert valid claims returned; assert wrong-iss/wrong-aud/expired/bad-nonce throw `InvalidTokenException`).

```php
<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Modules\Identity\Domain\Contracts\IdentityProvider;
use App\Modules\Identity\Domain\Exceptions\InvalidTokenException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class IdentityProviderValidationTest extends TestCase
{
    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keys = TestRsa::generate(); // helper in tests/Support; returns ['private','public','jwks']
        config(['identity.oidc.issuer' => 'https://issuer.test', 'identity.oidc.client_id' => 'program-platform']);
        Http::fake(['https://issuer.test/.well-known/jwks.json' => Http::response($this->keys['jwks'])]);
    }

    private function token(array $overrides = []): string
    {
        $claims = array_merge([
            'iss' => 'https://issuer.test', 'aud' => 'program-platform',
            'sub' => 'sg_user_01', 'nonce' => 'N1', 'exp' => time() + 300, 'iat' => time(),
        ], $overrides);
        return JWT::encode($claims, $this->keys['private'], 'RS256', 'sg-mock-key-1');
    }

    public function test_valid_token_returns_claims(): void
    {
        $claims = app(IdentityProvider::class)->validateIdToken($this->token(), 'N1');
        $this->assertSame('sg_user_01', $claims['sub']);
    }

    public function test_expired_token_rejected(): void
    {
        $this->expectException(InvalidTokenException::class);
        app(IdentityProvider::class)->validateIdToken($this->token(['exp' => time() - 10]), 'N1');
    }

    public function test_wrong_issuer_rejected(): void
    {
        $this->expectException(InvalidTokenException::class);
        app(IdentityProvider::class)->validateIdToken($this->token(['iss' => 'https://evil.test']), 'N1');
    }

    public function test_wrong_audience_rejected(): void
    {
        $this->expectException(InvalidTokenException::class);
        app(IdentityProvider::class)->validateIdToken($this->token(['aud' => 'someone-else']), 'N1');
    }

    public function test_nonce_mismatch_rejected(): void
    {
        $this->expectException(InvalidTokenException::class);
        app(IdentityProvider::class)->validateIdToken($this->token(['nonce' => 'OTHER']), 'N1');
    }
}
```

- [ ] **Step 2:** Add `tests/Support/TestRsa.php` generating an RSA-2048 keypair and a JWKS document (`kid=sg-mock-key-1`, `alg=RS256`, `use=sig`, `n`/`e` from the public key). Use `openssl_pkey_new`.

- [ ] **Step 3: Run FAIL.**

- [ ] **Step 4: Implement `StartupGateIdentityProvider::validateIdToken`** using `Firebase\JWT\JWK::parseKeySet($jwks)` + `JWT::decode($idToken, $keySet)`, then assert `iss === config issuer`, `aud === client_id`, `nonce === expected`; wrap all failures in `InvalidTokenException`. Implement `buildAuthorizationUrl/exchangeCode/refresh/revoke/userinfo` via `Http::`.

- [ ] **Step 5: Bind in `IdentityServiceProvider`** and register it in `bootstrap/providers.php`.

- [ ] **Step 6: Run PASS; pint; phpstan; commit** `git commit -m "feat: Startup Gate HTTP identity adapter + JWKS token validation"`

---

## Milestone M4 — Startup Gate mock service (`app/StartupGateMock/`)

### Task 4.1: Mock keys command + JWKS + discovery

**Files:**
- Create: `backend/app/StartupGateMock/StartupGateMockServiceProvider.php` (registers routes only when `config('app.role')==='mock'` OR in `testing`).
- Create: `backend/app/StartupGateMock/Support/MockKeys.php` (loads RSA keypair from `config('identity.mock')`, falls back to a generated test pair in `testing`).
- Create: `backend/app/StartupGateMock/Http/OidcDiscoveryController.php`, `JwksController.php`
- Create: `backend/routes/startup-gate-mock.php`
- Create: `backend/app/Console/Commands/GenerateMockKeys.php` (`sg-mock:keys` writes a keypair to `.env` lines for dev)
- Register provider in `bootstrap/providers.php`.
- Test: `backend/tests/Contract/OidcDiscoveryContractTest.php`

**Interfaces:**
- Produces: `GET /.well-known/openid-configuration`, `GET /.well-known/jwks.json` (served by the mock role / in tests).

- [ ] **Step 1: Failing contract test**

```php
<?php
declare(strict_types=1);
namespace Tests\Contract;
use Tests\TestCase;

final class OidcDiscoveryContractTest extends TestCase
{
    public function test_discovery_document_shape(): void
    {
        $this->getJson('/.well-known/openid-configuration')
            ->assertOk()
            ->assertJsonStructure(['issuer','authorization_endpoint','token_endpoint','userinfo_endpoint','jwks_uri','response_types_supported','subject_types_supported','id_token_signing_alg_values_supported']);
    }

    public function test_jwks_exposes_rs256_signing_key(): void
    {
        $this->getJson('/.well-known/jwks.json')
            ->assertOk()
            ->assertJsonPath('keys.0.kty', 'RSA')
            ->assertJsonPath('keys.0.alg', 'RS256')
            ->assertJsonPath('keys.0.use', 'sig');
    }
}
```

- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** the provider (route registration gated on role/testing), `MockKeys`, discovery + JWKS controllers, routes file.
- [ ] **Step 4: Run PASS; commit** `git commit -m "feat: mock OIDC discovery + JWKS"`

### Task 4.2: Seed personas + authorize + token + userinfo

**Files:**
- Create: `backend/app/StartupGateMock/Support/SeedPersonas.php` (the 10 personas from docs/10, each: `sub`, `email`, `name`, `locale`, granted scopes/consents, role profiles, startups, profile payload, flags like `consent_revoked`, `incomplete`, `role_verification_expired`).
- Create: `backend/app/StartupGateMock/Http/AuthorizeController.php` (issues a one-time `code` bound to a chosen `sub` + `state` + `code_challenge` + `nonce`, stored in cache TTL 300s; supports `login_hint=<sub>` for automated tests).
- Create: `backend/app/StartupGateMock/Http/TokenController.php` (validates PKCE `code_verifier` against stored `code_challenge`, returns RS256 `id_token` with docs/10 claims + `access_token` (opaque, cached → sub/scopes) + `refresh_token`).
- Create: `backend/app/StartupGateMock/Http/UserInfoController.php`
- Create: `backend/app/StartupGateMock/Http/RevokeController.php`, `LogoutController.php`
- Test: `backend/tests/Contract/OidcTokenContractTest.php`

**Interfaces:**
- Produces: `/oauth/authorize`, `/oauth/token`, `/oauth/userinfo`, `/oauth/revoke`, `/oauth/logout`.
  Token response: `{token_type:"Bearer", id_token, access_token, refresh_token, expires_in:3600, scope}`.

- [ ] **Step 1: Failing contract test** (drive authorize→token with PKCE; decode id_token via JWKS; assert claims `sub,iss,aud,email,email_verified,name,locale,profile_updated_at`; assert userinfo matches).

```php
<?php
declare(strict_types=1);
namespace Tests\Contract;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Tests\TestCase;

final class OidcTokenContractTest extends TestCase
{
    public function test_authorization_code_with_pkce_returns_valid_id_token(): void
    {
        $verifier = str_repeat('a', 64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $authorize = $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => 'program-platform',
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'scope' => 'openid profile.basic.read', 'state' => 'S1', 'nonce' => 'N1',
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
            'login_hint' => 'sg_user_01',
        ]));
        $authorize->assertRedirect();
        parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('S1', $q['state']);

        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'code' => $q['code'],
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'client_id' => 'program-platform', 'code_verifier' => $verifier,
        ])->assertOk()->json();

        $this->assertSame('Bearer', $token['token_type']);
        $jwks = $this->getJson('/.well-known/jwks.json')->json();
        $claims = (array) JWT::decode($token['id_token'], JWK::parseKeySet($jwks));
        $this->assertSame('sg_user_01', $claims['sub']);
        $this->assertArrayHasKey('email_verified', $claims);
        $this->assertArrayHasKey('profile_updated_at', $claims);
    }
}
```

- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** personas + authorize/token/userinfo/revoke/logout with cache-backed codes/tokens and RS256 signing via `MockKeys`.
- [ ] **Step 4: Run PASS; commit** `git commit -m "feat: mock OAuth authorize/token/userinfo with PKCE + personas"`

### Task 4.3: Mock profile/consent/role/startup endpoints + achievements + proposals

**Files:**
- Create: `backend/app/StartupGateMock/Http/ProfileController.php` with actions for `/api/v1/me`, `/me/profile`, `/me/role-profiles`, `/me/startups`, `/me/consents`.
- Create: `backend/app/StartupGateMock/Http/ProfileUpdateProposalController.php` (`POST /api/v1/profile-update-proposals` → 202 + proposal id).
- Create: `backend/app/StartupGateMock/Http/ProgramAchievementController.php` (`POST /api/v1/program-achievements` → 201 + achievement id).
- All resolve the persona from the bearer access token (cache lookup); enforce scope/consent.
- Test: `backend/tests/Contract/ProfileApiContractTest.php`

**Interfaces:**
- Produces: profile payload shapes; consent list `[{scope,granted,reference}]`; revoked-consent persona returns `granted:false` and `/me/profile` omits gated sections.

- [ ] **Step 1: Failing contract test** (obtain a token for `sg_user_01`, call each endpoint, assert documented shapes; obtain token for the revoked-consent persona, assert consent gating).
- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** controllers reading personas; consent-aware field redaction.
- [ ] **Step 4: Run PASS; commit** `git commit -m "feat: mock profile/consent/role/startup/achievement endpoints"`

### Task 4.4: Mock webhook payload builders + HMAC signing (contract only)

**Files:**
- Create: `backend/app/StartupGateMock/Webhooks/WebhookPayloadFactory.php` (builds versioned payloads for `ProfileUpdated`, `ConsentRevoked`, `RoleProfileApproved`, `AchievementPublished`; each `{id, type, version, occurred_at, data}`).
- Create: `backend/app/StartupGateMock/Webhooks/WebhookSigner.php` (`sign(string $body): string` → `sha256=` HMAC using `config('identity.mock.webhook_secret')`).
- Test: `backend/tests/Contract/WebhookPayloadContractTest.php`

- [ ] **Step 1: Failing test** — assert each payload has `{id,type,version,occurred_at,data}` and the signature verifies with the shared secret.
- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** factory + signer (no HTTP delivery — deferred).
- [ ] **Step 4: Run PASS; commit** `git commit -m "feat: mock webhook payload builders + HMAC signing (contract)"`

---

## Milestone M5 — Platform auth flow (login/callback/session/logout)

### Task 5.1: Sanctum SPA wiring + stateful api group

**Files:**
- Modify: `bootstrap/app.php` (`$middleware->statefulApi();`)
- Modify: `config/cors.php` (publish if needed; allow `http://localhost:3000`, `supports_credentials=true`)
- Test: `backend/tests/Feature/CsrfCookieTest.php` (asserts `GET /sanctum/csrf-cookie` returns 204 and sets `XSRF-TOKEN`).

- [ ] Steps: failing test → `statefulApi()` + CORS creds → PASS → commit `git commit -m "feat: sanctum SPA stateful api + cors"`.

### Task 5.2: Login + callback (the core flow)

**Files:**
- Create: `backend/app/Modules/Identity/Http/AuthController.php` (`login`, `callback`, `session`, `logout`).
- Create: `backend/app/Modules/Identity/Application/CompleteLogin.php` (validates ID token via `IdentityProvider`, projects user, stores tokens, captures an identity snapshot, logs the user in, writes audit).
- Modify: `routes/api.php` (auth routes).
- Test: `backend/tests/Feature/AuthFlowTest.php`

**Interfaces:**
- Consumes: `IdentityProvider`, `ExternalUser::projectFromClaims`, `CaptureProfileSnapshot`, `AuditLogger`.
- Produces: `GET /api/v1/auth/login` → `{authorization_url}`; `POST /api/v1/auth/callback {code,state}` → session + `{user}`; `GET /api/v1/auth/session`; `POST /api/v1/auth/logout`.

- [ ] **Step 1: Failing feature test** — full flow against the in-process mock:

```php
<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Modules\Identity\Domain\Models\ExternalUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_logs_in_through_mock_and_projection_uses_sub(): void
    {
        // login -> get authorization_url
        $login = $this->getJson('/api/v1/auth/login')->assertOk()->json();
        $this->assertArrayHasKey('authorization_url', $login);

        // follow authorize with a chosen persona (login_hint), get code
        $authorize = $this->get($login['authorization_url'].'&login_hint=sg_user_01');
        parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY), $q);

        // callback completes login
        $this->postJson('/api/v1/auth/callback', ['code' => $q['code'], 'state' => $q['state']])
            ->assertOk()
            ->assertJsonPath('user.startup_gate_subject_id', 'sg_user_01');

        $this->assertDatabaseHas('external_users', ['startup_gate_subject_id' => 'sg_user_01']);
        $this->assertDatabaseCount('profile_snapshots', 1);
        $this->getJson('/api/v1/auth/session')->assertOk();
    }

    public function test_invalid_state_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/login');
        $this->postJson('/api/v1/auth/callback', ['code' => 'x', 'state' => 'tampered'])
            ->assertStatus(401);
    }
}
```

> Note: `login` stores `state`, `nonce`, and PKCE `code_verifier` in the session; the test shares the session across requests. `callback` reads them back, exchanges the code, validates the id_token, and projects.

- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** `AuthController` + `CompleteLogin` (PKCE generation in `login`; state/nonce verification in `callback`; capture an `identity` snapshot from `ProfileProvider` under the basic consent reference; audit `auth.login`).
- [ ] **Step 4: Run PASS; pint; phpstan; commit** `git commit -m "feat: platform OIDC login/callback/session/logout"`

### Task 5.3: `/me` + profile passthrough (consent-aware)

**Files:**
- Create: `backend/app/Modules/Identity/Http/MeController.php` (`me`, `profile`, `roleProfiles`, `startups`) reading via adapters with the stored access token; refresh on expiry.
- Modify: `routes/api.php`.
- Test: `backend/tests/Feature/MeEndpointsTest.php` (authenticated; asserts shapes; revoked-consent persona omits gated sections).

- [ ] Steps: failing test → implement → PASS → commit `git commit -m "feat: /me profile passthrough endpoints (consent-aware)"`.

---

## Milestone M6 — Organizations, memberships, RBAC

### Task 6.1: Organizations table + model + tenant root

**Files:**
- Create migration `..._create_organizations_table.php` (`id` ulid, `name`, `slug` unique, `branding` jsonb nullable, timestampsTz).
- Create: `backend/app/Modules/Organizations/Domain/Models/Organization.php` (NO `BelongsToTenant`; it is the tenant root).
- Test: `backend/tests/Feature/OrganizationCrudTest.php` (created with M6.4 controllers; here just model + migration unit check).

- [ ] Steps: migration → model (`HasUlids`, slug auto from name) → trivial test asserting create → commit `git commit -m "feat: organizations table + model"`.

### Task 6.2: Permissions catalog + roles + assignments + membership

**Files:**
- Migrations: `organization_permissions` (global catalog: `id`,`key` unique,`description`), `organization_roles` (`id`,`organization_id`,`key`,`name`,`is_system`; unique(org,key)), `role_permission_assignments` (`organization_role_id`,`organization_permission_id`; unique pair), `organization_memberships` (`id`,`organization_id`,`external_user_id`,`status`; unique(org,user)), `organization_membership_roles` (`membership_id`,`organization_role_id`; unique pair).
- Models in `app/Modules/Organizations/Domain/Models/`: `OrganizationPermission`, `OrganizationRole` (uses `BelongsToTenant`), `OrganizationMembership` (uses `BelongsToTenant`; **implements `App\Shared\Tenancy\Contracts\TenantMembership`**; methods `organizationId(): string` and `effectivePermissionKeys(): array`).
- Create: `backend/app/Http/Middleware/ResolveTenant.php` + register the `tenant` middleware alias in `bootstrap/app.php` (deferred here from Task 1.3 because it needs `OrganizationMembership`).
- Seeder: `backend/database/seeders/PermissionCatalogSeeder.php` (keys: `organizations.manage`, `members.manage`, `members.invite`, `roles.manage`).
- Test: `backend/tests/Unit/EffectivePermissionsTest.php`

**Interfaces:**
- Produces: `OrganizationMembership::effectivePermissionKeys(): array<int,string>` (distinct permission keys via the member's roles); `OrganizationMembership::organizationId(): string`; the `tenant` route-middleware alias.

- [ ] **Step 1: Failing test** — build org + role (with `members.manage`) + membership assigned that role; assert `effectivePermissionKeys()` contains `members.manage` and not others.
- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** migrations, models (OrganizationMembership implements `TenantMembership`), relationships, `effectivePermissionKeys()`, seeder.
  - **Also remove** the temporary `ignoreErrors: trait.unused` entry for `app/Shared/Tenancy/BelongsToTenant.php` from `phpstan.neon` — once a model uses the trait the notice disappears and PHPStan must run clean without the suppression.
- [ ] **Step 4: Implement `ResolveTenant` middleware** (exact body below) and register the alias:

```php
<?php
declare(strict_types=1);
namespace App\Http\Middleware;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ResolveTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $isPlatformAdmin = (bool) ($user?->is_platform_admin);
        if ($isPlatformAdmin) { $this->tenant->actingAsPlatformAdmin(true); }

        $orgId = $request->header((string) config('tenancy.header'));
        if (! $orgId) {
            if ($isPlatformAdmin) { return $next($request); }
            throw new BadRequestHttpException('Missing organization header.');
        }

        $membership = $user
            ? OrganizationMembership::withoutGlobalScope('tenant')
                ->where('organization_id', $orgId)
                ->where('external_user_id', $user->id)
                ->where('status', 'active')
                ->first()
            : null;

        if (! $membership) {
            if ($isPlatformAdmin) { return $next($request); }
            throw new AccessDeniedHttpException('Not a member of this organization.');
        }

        $this->tenant->setOrganization($orgId, $membership, $membership->effectivePermissionKeys());
        return $next($request);
    }
}
```
In `bootstrap/app.php` `withMiddleware`: `$middleware->alias(['tenant' => \App\Http\Middleware\ResolveTenant::class]);`

- [ ] **Step 5: Run PASS; pint; phpstan; commit** `git commit -m "feat: org roles, permissions, memberships, effective permissions + resolve-tenant middleware"`

### Task 6.3: Organization policy + membership policy (server authorization)

**Files:**
- Create: `backend/app/Modules/Organizations/Policies/OrganizationPolicy.php` (`update` requires `organizations.manage`), `MembershipPolicy.php` (`create` requires `members.invite`/`members.manage`).
- Register policies in `AppServiceProvider::boot` via `Gate::policy(...)`.
- Test: covered by feature tests in 6.4 + M7.

- [ ] Steps: implement policies checking `app(TenantContext::class)->can(...)`; commit `git commit -m "feat: organization + membership policies"`.

### Task 6.4: Organization + membership controllers/endpoints

**Files:**
- Create: `backend/app/Modules/Organizations/Http/OrganizationController.php` (`index`,`store`,`show`,`update`), `MembershipController.php` (`store`,`index`).
- Form requests: `StoreOrganizationRequest`, `UpdateOrganizationRequest`, `StoreMembershipRequest`.
- API Resources: `OrganizationResource`, `MembershipResource`.
- Modify `routes/api.php`: replace the temporary `/organizations` stub with the real resourceful routes under `auth:sanctum`; membership + org-scoped routes also use `tenant` middleware.
- Test: `backend/tests/Feature/OrganizationCrudTest.php`

**Interfaces:**
- Produces: `GET/POST /api/v1/organizations`, `GET/PATCH /api/v1/organizations/{id}`, `POST /api/v1/organizations/{org}/memberships`, `GET /api/v1/organizations/{org}/memberships`.
  `index` lists only orgs the user is a member of (or all if platform admin).

- [ ] **Step 1: Failing test** — authenticated user creates org (becomes owner-admin member with a system role granting `organizations.manage`+`members.manage`); can `PATCH` own org; listing returns it.
- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** controllers/requests/resources; on `store`, create org + a system `owner` role with full Phase-1 permissions + an active membership for the creator (audited).
- [ ] **Step 4: Run PASS; pint; phpstan; commit** `git commit -m "feat: organization + membership API"`

---

## Milestone M7 — Security & contract test suites (mandatory, docs/12)

### Task 7.1: Tenant isolation suite

**Files:** Create `backend/tests/Feature/TenantIsolationTest.php`.

- [ ] **Step 1: Write tests** (then run; they must pass against the implemented stack):
  - User A (member of Org 1 only) sets `X-Organization-Id: Org2` → **403**.
  - User A reads `/organizations/{org2_id}` they don't belong to → **403/404** (assert not 200).
  - A membership row created under Org 1 is invisible when querying under Org 2 (global scope).
  - User without `organizations.manage` PATCHing an org → **403**.

```php
<?php
declare(strict_types=1);
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_member_org_header_is_forbidden(): void
    {
        // helpers: $this->loginPersona('sg_user_01'); $this->createOrgFor($user) ...
        [$user, $org1] = $this->bootUserWithOrg('sg_user_01');
        $otherOrg = $this->createBareOrg();
        $this->withHeader('X-Organization-Id', $otherOrg->id)
            ->getJson('/api/v1/organizations/'.$otherOrg->id)
            ->assertStatus(403);
    }
}
```
> Add reusable helpers (`bootUserWithOrg`, `createBareOrg`, `loginPersona`) to `tests/TestCase.php`.

- [ ] **Step 2: Run PASS** (`php artisan test --filter=TenantIsolationTest`). Fix scoping/policy bugs surfaced here.
- [ ] **Step 3: Commit** `git commit -m "test: tenant isolation suite"`

### Task 7.2: Mandatory OIDC/security tests

**Files:** Create `backend/tests/Feature/AuthSecurityTest.php`.

- [ ] **Step 1: Write tests:** expired token rejected (401); invalid issuer rejected; invalid audience rejected; tampered state rejected; revoked-consent persona → gated profile sections withheld; unauthenticated `/me` → 401.
- [ ] **Step 2: Run PASS.**
- [ ] **Step 3: Commit** `git commit -m "test: mandatory OIDC + consent security tests"`

### Task 7.3: Full-suite gate + docs update

**Files:** Modify `docs/04-data-model.md` (mark Phase-1 tables implemented), `docs/superpowers/specs/2026-06-18-identity-tenancy-design.md` (dependency note → firebase/php-jwt), `BOOTSTRAP.md` or a new `docs/phase-1-notes.md` (how to run mock, `sg-mock:keys`).

- [ ] **Step 1:** Run the entire suite + quality gates:
```bash
php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```
Expected: all PASS.
- [ ] **Step 2:** Update docs (rule 19).
- [ ] **Step 3:** Bring up the stack and verify the mock container serves discovery:
```bash
docker compose up -d --build && curl -sf http://localhost:8081/.well-known/openid-configuration
```
(Confirm the `startup-gate-mock` container now runs the Laravel image with `APP_ROLE=mock` — update `docker-compose.yml` `startup-gate-mock` service to `build: ./backend` + `APP_ROLE=mock` as part of this task, replacing the Node placeholder.)
- [ ] **Step 4: Commit** `git commit -m "docs: Phase 1 identity/tenancy notes + data-model status; wire mock container"`

---

## Self-Review (performed against the spec)

**Spec coverage:** topology §3 → M0/M4/7.3; adapters §4 → M3; auth flow §5 → M5; data model §6 → M2/M6/1.2; tenant isolation §7 → M1.3/7.1; RBAC §8 → M6; mock surface §9 → M4; snapshots/consent §10 → M2.3/5.3; endpoints §11 → M5/M6; tests §12 → M3.2/M4/7.1/7.2; deps §13 → M0; deferrals §14 honored (no achievement/proposal consumption, no inbound webhook pipeline, no RLS); acceptance map §15 → M5/M6/M7. All covered.

**Placeholder scan:** mechanical/parallel tasks (4.2/4.3/5.3/6.x) specify exact files, routes, response shapes, and assertions rather than vague directives; representative full code is given for every novel/risky behavior (token validation, immutability, tenant scope, effective permissions, auth flow). No "add validation/error handling" left abstract — validation rules and error envelope are specified in 1.1/6.4.

**Type consistency:** `TenantContext`, `IdentityProvider`, `ExternalUser::projectFromClaims`, `CaptureProfileSnapshot::capture`, `OrganizationMembership::effectivePermissionKeys`, `AuditLogger::record` signatures are used identically wherever referenced. `kid=sg-mock-key-1` consistent between mock signing and test JWKS.

**Ordering note:** Task 1.2 (AuditLogger) depends on `TenantContext` from 1.3 — implement 1.3 before running 1.2 Step 5 (called out inline).
