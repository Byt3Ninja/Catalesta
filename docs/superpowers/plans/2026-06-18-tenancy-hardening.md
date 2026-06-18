# Tenancy Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make multi-tenant isolation fail-closed and structurally enforced (scope-validation C1) and stop mass-assignment of `organization_id` (C3), on the current ~6-module codebase.

**Architecture:** Add an explicit `TenantContext::runAsSystem()` escape hatch; flip `BelongsToTenant` from fail-open to fail-closed (no resolved tenant → reads return nothing, writes throw, request creates force `organization_id` from context); migrate legitimate `withoutGlobalScope('tenant')` call sites to `runAsSystem`; lock it with an architecture test; tighten tenant-owned models to explicit `$fillable`.

**Tech Stack:** PHP 8.3 / Laravel 13, PHPUnit, Pint, Larastan. Branch: `arch-hardening-tenancy-seams` (off `main` @ e57da17).

## Global Constraints

- `declare(strict_types=1);` in every PHP file.
- `organization_id` is NEVER mass-assignable: on request creates it is FORCED from `TenantContext`; on system/bootstrap creates it is set via direct attribute assignment / `forceFill`.
- Cross-tenant access (no specific resolved tenant) only via explicit `TenantContext::runAsSystem(callable)`. No new `withoutGlobalScope('tenant')` in `app/` outside `app/Shared/Tenancy/`.
- Fail CLOSED: with no resolved tenant and not in system context, tenant-owned reads return nothing and tenant-owned writes throw `TenantContextMissingException`.
- Do not weaken the scope to make a test pass — fix the call site (set context or `runAsSystem`).
- No schema migrations (behavioral + model changes only). Rollback = revert.
- Each task ends green: `php artisan test`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --no-progress --memory-limit=512M`. Run from `backend/`. Baseline on `main`: 194 tests.
- Tenant-owned models (need `$fillable`, Task 5): `Program`, `Cohort`, `ProgramPolicyRecord`, `ProgramRoleRequirement`, `ProgramStage`, `StageVersion`, `StageRule`, `StageTransition`, `ParticipantStageStatus`, `StageInstance`, `OrganizationRole`, `OrganizationMembership` (+ pivots). Global (NOT tenant-scoped, allowlist): `Organization`, `OrganizationPermission`, `ExternalUser`, `ExternalUserToken`, `ProfileSnapshot`, `AuditLog`.
- Code exploration uses Graphify first (repo rule); include that instruction in any sub-dispatch.

## Current code (verbatim — what we change)

`app/Shared/Tenancy/TenantContext.php`: `final class` with `setOrganization`, `organizationId(): ?string`, `membership()`, `has(): bool`, `actingAsPlatformAdmin(bool)`, `can(string): bool`. Properties `$organizationId`, `$membership`, `$permissions`, `$platformAdmin`.

`app/Shared/Tenancy/BelongsToTenant.php`:
```php
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
        if ($ctx->has() && empty($model->getAttribute('organization_id'))) {
            $model->setAttribute('organization_id', $ctx->organizationId());
        }
    });
}
```

---

## Task 1: `TenantContext::runAsSystem` + `isSystem`; scope honors system

**Files:**
- Modify: `backend/app/Shared/Tenancy/TenantContext.php`
- Modify: `backend/app/Shared/Tenancy/BelongsToTenant.php` (scope honors `isSystem` — additive, no behavior change since default false)
- Test: `backend/tests/Unit/Tenancy/TenantContextSystemTest.php`

**Interfaces:**
- Produces: `TenantContext::runAsSystem(callable $fn): mixed`, `TenantContext::isSystem(): bool`.

- [ ] **Step 1: Failing test**
```php
<?php
declare(strict_types=1);
namespace Tests\Unit\Tenancy;
use App\Shared\Tenancy\TenantContext;
use Tests\TestCase;

final class TenantContextSystemTest extends TestCase
{
    public function test_is_system_defaults_false_and_runAsSystem_toggles_then_restores(): void
    {
        $ctx = new TenantContext();
        $this->assertFalse($ctx->isSystem());
        $seen = $ctx->runAsSystem(function () use ($ctx) {
            return $ctx->isSystem();
        });
        $this->assertTrue($seen);
        $this->assertFalse($ctx->isSystem(), 'flag restored after the closure');
    }

    public function test_runAsSystem_is_reentrant_and_restores_on_exception(): void
    {
        $ctx = new TenantContext();
        try {
            $ctx->runAsSystem(function () use ($ctx) {
                $ctx->runAsSystem(fn () => null);
                $this->assertTrue($ctx->isSystem(), 'still system inside outer after nested returns');
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }
        $this->assertFalse($ctx->isSystem(), 'flag restored even when the closure throws');
    }
}
```

- [ ] **Step 2: Run, expect FAIL** — `php artisan test --filter=TenantContextSystemTest`

- [ ] **Step 3: Implement** in `TenantContext`:
```php
private bool $system = false;

public function isSystem(): bool
{
    return $this->system;
}

/**
 * Run $fn with cross-tenant (system) access. The ONLY sanctioned way to span
 * tenants when no specific tenant is resolved. Re-entrant; restores prior state.
 *
 * @template T
 * @param callable():T $fn
 * @return T
 */
public function runAsSystem(callable $fn): mixed
{
    $previous = $this->system;
    $this->system = true;
    try {
        return $fn();
    } finally {
        $this->system = $previous;
    }
}
```
And in `BelongsToTenant::bootBelongsToTenant` global scope, make system bypass explicit (no behavior change while `isSystem` is false):
```php
static::addGlobalScope('tenant', function (Builder $builder): void {
    $ctx = app(TenantContext::class);
    if ($ctx->has()) {
        $builder->where($builder->getModel()->getTable().'.organization_id', $ctx->organizationId());
        return;
    }
    if ($ctx->isSystem()) {
        return; // explicit cross-tenant access
    }
    // (fail-closed branch added in Task 3)
});
```

- [ ] **Step 4: Run PASS; full suite green (194); pint; phpstan; commit**
```bash
php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
git add -A && git commit -m "feat(tenancy): TenantContext::runAsSystem + isSystem; scope honors system context"
```

---

## Task 2: Migrate `withoutGlobalScope('tenant')` call sites → `runAsSystem`

**Files (modify — exact set found via grep):**
- `backend/app/Http/Middleware/ResolveTenant.php` (membership lookup before context exists)
- `backend/app/Modules/Organizations/Application/CreateOrganization.php` (bootstrap reads, if any)
- `backend/app/Modules/Organizations/Domain/Models/OrganizationMembership.php` (`effectivePermissionKeys()` role query, if it uses `withoutGlobalScope`)
- `backend/app/Modules/Organizations/Http/OrganizationController.php`, `MembershipController.php` (index listings, if any)
- Any other `app/` hit from the grep below.

**Interfaces:** Consumes `TenantContext::runAsSystem` from Task 1. No new public API.

- [ ] **Step 1: Find every usage**
```bash
grep -rn "withoutGlobalScope('tenant')\|withoutGlobalScope(\"tenant\")" backend/app
```
Expected: a finite list (ResolveTenant, effectivePermissionKeys, possibly CreateOrganization/controllers).

- [ ] **Step 2: Replace each** `X::withoutGlobalScope('tenant')->…` with
  `app(\App\Shared\Tenancy\TenantContext::class)->runAsSystem(fn () => X::…)`
  (inject `TenantContext` via the constructor where the class already has one, e.g. `ResolveTenant`; otherwise resolve via `app()`). Behavior is unchanged at this point (both bypass the scope, which is still fail-open), so the full suite stays green. Example for `ResolveTenant` (it already injects `TenantContext $tenant`):
```php
$membership = $user
    ? $this->tenant->runAsSystem(fn () => OrganizationMembership::query()
        ->where('organization_id', $orgId)
        ->where('external_user_id', $user->id)
        ->where('status', 'active')
        ->first())
    : null;
```

- [ ] **Step 3: Verify no `withoutGlobalScope('tenant')` remains in `app/`**
```bash
grep -rn "withoutGlobalScope('tenant')\|withoutGlobalScope(\"tenant\")" backend/app || echo "CLEAN"
```
Expected: `CLEAN`.

- [ ] **Step 4: Full suite green (194); pint; phpstan; commit**
```bash
git add -A && git commit -m "refactor(tenancy): replace withoutGlobalScope('tenant') with runAsSystem in app code"
```

---

## Task 3: Flip `BelongsToTenant` to fail-closed

**Files:**
- Modify: `backend/app/Shared/Tenancy/BelongsToTenant.php`
- Create: `backend/app/Shared/Tenancy/Exceptions/TenantContextMissingException.php` (extends `\RuntimeException`)
- Test: `backend/tests/Feature/Tenancy/FailClosedTenancyTest.php`
- Modify: any test/call site surfaced by the flip (set context or `runAsSystem`).

**Interfaces:** Consumes `TenantContext::isSystem/has/organizationId`. Produces `TenantContextMissingException`.

- [ ] **Step 1: Failing test** (uses a tenant-owned model, e.g. `Program`; set up two orgs)
```php
<?php
declare(strict_types=1);
namespace Tests\Feature\Tenancy;
use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Tenancy\Exceptions\TenantContextMissingException;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FailClosedTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_without_tenant_returns_nothing(): void
    {
        // Seed a program under a real tenant (system context bypasses the create-guard).
        $ctx = app(TenantContext::class);
        [$user, $org] = $this->bootUserWithOrg();
        $ctx->runAsSystem(fn () => Program::query()->create(['name' => 'A', 'organization_id' => $org->id]));

        // Fresh request-less context: no tenant resolved, not system.
        app()->forgetInstance(TenantContext::class); // ensure a clean scoped context
        $this->assertCount(0, Program::all(), 'fail-closed: no tenant => no rows');
    }

    public function test_create_without_tenant_throws(): void
    {
        $this->expectException(TenantContextMissingException::class);
        Program::query()->create(['name' => 'Orphan']); // no ctx, no org, not system
    }

    public function test_runAsSystem_sees_all_tenants(): void
    {
        $ctx = app(TenantContext::class);
        [$ua, $a] = $this->bootUserWithOrg('Org A');
        $ctx->runAsSystem(fn () => Program::query()->create(['name' => 'PA', 'organization_id' => $a->id]));
        $b = $this->createBareOrg('Org B');
        $ctx->runAsSystem(fn () => Program::query()->create(['name' => 'PB', 'organization_id' => $b->id]));

        $count = $ctx->runAsSystem(fn () => Program::query()->count());
        $this->assertSame(2, $count);
    }
}
```
> Note: the exact mechanics of obtaining a "no tenant resolved" context in a test depend on how `TenantContext` is bound (scoped singleton). If `forgetInstance` is awkward, construct assertions around a freshly-bound context or assert via a model query inside vs. outside `runAsSystem`. The implementer adapts to the binding while preserving the three behaviors (read→empty, write→throw, system→all).

- [ ] **Step 2: Run, expect FAIL** (reads currently return all; writes currently succeed)

- [ ] **Step 3: Implement fail-closed** — final `BelongsToTenant`:
```php
public static function bootBelongsToTenant(): void
{
    static::addGlobalScope('tenant', function (Builder $builder): void {
        $ctx = app(TenantContext::class);
        if ($ctx->has()) {
            $builder->where($builder->getModel()->getTable().'.organization_id', $ctx->organizationId());
            return;
        }
        if ($ctx->isSystem()) {
            return;
        }
        $builder->whereRaw('1 = 0'); // fail closed: no tenant, not system => no rows
    });

    static::creating(function (Model $model): void {
        $ctx = app(TenantContext::class);
        if ($ctx->has()) {
            $model->setAttribute('organization_id', $ctx->organizationId()); // FORCE from context
            return;
        }
        if (! empty($model->getAttribute('organization_id'))) {
            return; // explicit org set in code (system/bootstrap path)
        }
        throw new TenantContextMissingException(sprintf(
            'Cannot persist %s without a resolved tenant or explicit organization_id.',
            $model::class,
        ));
    });
}
```

- [ ] **Step 4: Fix surfaced call sites/tests.** Run the full suite; for each failure caused by a tenant-model read/write with no resolved context, fix by setting `TenantContext` (the natural request/seed path) or wrapping the legitimately-cross-tenant access in `runAsSystem` — NEVER by weakening the scope. Re-run until green.

- [ ] **Step 5: Full suite green; pint; phpstan; commit**
```bash
git add -A && git commit -m "feat(tenancy): fail-closed BelongsToTenant (no tenant => empty reads, throwing writes); force org from context"
```

---

## Task 4: Architecture test (structural enforcement)

**Files:**
- Create: `backend/tests/Architecture/TenantIsolationArchTest.php`
- Modify: `backend/phpunit.xml` (add an `Architecture` testsuite if test discovery needs it)

**Interfaces:** none (test-only).

- [ ] **Step 1: Write the test**
```php
<?php
declare(strict_types=1);
namespace Tests\Architecture;
use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

final class TenantIsolationArchTest extends TestCase
{
    use RefreshDatabase;

    /** Models that legitimately are NOT tenant-scoped (global / identity / audit). */
    private const GLOBAL_ALLOWLIST = [
        \App\Modules\Organizations\Domain\Models\Organization::class,
        \App\Modules\Organizations\Domain\Models\OrganizationPermission::class,
        \App\Modules\Identity\Domain\Models\ExternalUser::class,
        \App\Modules\Identity\Domain\Models\ExternalUserToken::class,
        \App\Modules\Identity\Domain\Models\ProfileSnapshot::class,
        \App\Shared\Audit\AuditLog::class,
    ];

    public function test_every_model_with_organization_id_uses_belongs_to_tenant(): void
    {
        foreach ($this->modelClasses() as $class) {
            $model = new $class();
            $table = $model->getTable();
            if (! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }
            $this->assertContains(
                BelongsToTenant::class,
                class_uses_recursive($class),
                "$class has an organization_id column but does not use BelongsToTenant (tenant isolation gap).",
            );
        }
    }

    public function test_global_allowlist_models_do_not_use_belongs_to_tenant(): void
    {
        foreach (self::GLOBAL_ALLOWLIST as $class) {
            $this->assertNotContains(
                BelongsToTenant::class,
                class_uses_recursive($class),
                "$class is allowlisted as global but uses BelongsToTenant.",
            );
        }
    }

    public function test_no_without_global_scope_tenant_in_app_outside_tenancy(): void
    {
        $finder = (new Finder())->files()->in(app_path())->name('*.php')
            ->notPath('Shared/Tenancy');
        $offenders = [];
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $contents = (string) file_get_contents($file->getRealPath());
            if (Str::contains($contents, ["withoutGlobalScope('tenant')", 'withoutGlobalScope("tenant")'])) {
                $offenders[] = $file->getRelativePathname();
            }
        }
        $this->assertSame([], $offenders, 'Use TenantContext::runAsSystem instead of withoutGlobalScope(\'tenant\') in app code.');
    }

    /** @return list<class-string<\Illuminate\Database\Eloquent\Model>> */
    private function modelClasses(): array
    {
        $finder = (new Finder())->files()->in(app_path())->name('*.php')->path('Domain/Models');
        $classes = [];
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $class = $this->classFromFile($file->getRealPath());
            if ($class !== null && is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)
                && ! (new \ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }
        return $classes;
    }

    private function classFromFile(string $path): ?string
    {
        $src = (string) file_get_contents($path);
        if (! preg_match('/namespace\s+([^;]+);/', $src, $ns) || ! preg_match('/(?:final\s+)?class\s+(\w+)/', $src, $cls)) {
            return null;
        }
        $fqcn = trim($ns[1]).'\\'.$cls[1];
        return class_exists($fqcn) ? $fqcn : null;
    }
}
```

- [ ] **Step 2: Run** — `php artisan test --filter=TenantIsolationArchTest`. Expected PASS (all tenant models already use the trait; no `withoutGlobalScope` after Task 2; allowlist correct). If it FAILS, it has found a real gap — fix the offending model (add the trait) or correct the allowlist; do not weaken the assertion.

- [ ] **Step 3: Full suite green; pint; phpstan; commit**
```bash
git add -A && git commit -m "test(tenancy): architecture test enforcing BelongsToTenant on tenant-owned models"
```

---

## Task 5: C3 — explicit `$fillable`; `CreateOrganization` direct-assigns org

**Files:**
- Modify: the 12 tenant-owned models (see Global Constraints list) — `$guarded = []` → explicit `$fillable` (excluding `organization_id` and `id`).
- Modify: `backend/app/Modules/Organizations/Application/CreateOrganization.php` (set `organization_id` outside the mass-assigned array).
- Test: `backend/tests/Feature/Tenancy/MassAssignmentGuardTest.php`

**Interfaces:** none new.

- [ ] **Step 1: Failing test**
```php
<?php
declare(strict_types=1);
namespace Tests\Feature\Tenancy;
use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MassAssignmentGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_id_cannot_be_mass_assigned_and_is_forced_from_context(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $other = $this->createBareOrg('Other');
        $ctx = app(TenantContext::class);
        // Simulate a resolved-tenant request creating a Program while trying to spoof org.
        $ctx->runAsSystem(function () { /* noop to ensure helper available */ });
        $this->actingAsTenant($user, $org); // helper that sets TenantContext to $org (add if absent)
        $program = Program::query()->create([
            'name' => 'Legit',
            'organization_id' => $other->id, // spoof attempt
        ]);
        $this->assertSame($org->id, $program->fresh()->organization_id, 'org forced from context, spoof ignored');
    }
}
```
> If a `actingAsTenant` helper does not exist in `tests/TestCase.php`, add a small one that resolves the user's membership and calls `TenantContext::setOrganization(...)` (mirrors the pattern used in ProgramModelTest). The key assertion: the spoofed `organization_id` is overridden by the context org.

- [ ] **Step 2: Run, expect FAIL** if `$guarded = []` lets the spoof through before the hook forces it. (With the Task-3 force-from-context hook, the spoof is already overridden on request creates — so this test may already pass for the request path; its purpose is to lock that behavior AND drive the `$fillable` change. If it passes pre-change, still proceed to make `$fillable` explicit per the spec and keep the test as a regression guard.)

- [ ] **Step 3: Implement** — for each tenant-owned model replace `protected $guarded = [];` with an explicit `protected $fillable = [...];` listing the caller-supplied attributes only (NOT `organization_id`, NOT `id`). Examples:
  - `Program`: `['name', 'slug', 'status', 'description', 'settings', 'template_id']`
  - `Cohort`: `['program_id', 'name', 'slug', 'status', 'enrollment_opens_at', 'enrollment_closes_at', 'starts_at', 'ends_at', 'capacity', 'timeline']`
  - `ProgramStage`: `['program_id', 'key', 'name', 'type', 'order_index', 'parallel_group', 'current_published_version_id']`
  - `StageVersion`: `['program_stage_id', 'version_number', 'status', 'config', 'published_at']`
  - `StageRule`: `['stage_version_id', 'type', 'expression']`
  - `StageTransition`: `['program_id', 'from_program_stage_id', 'to_program_stage_id', 'condition', 'order_index']`
  - `ParticipantStageStatus`: `['cohort_id', 'external_user_id', 'program_stage_id', 'status', 'entered_at', 'completed_at']`
  - `StageInstance`: `['participant_stage_status_id', 'stage_version_id', 'started_at']`
  - `ProgramPolicyRecord`: `['program_id', 'key', 'value']`
  - `ProgramRoleRequirement`: `['program_id', 'role_key', 'min_count', 'max_count', 'is_required']`
  - `OrganizationRole`: `['key', 'name', 'is_system']`
  - `OrganizationMembership`: `['external_user_id', 'status']`
  Match each list to what controllers/services actually pass (read each model's call sites if unsure). Update `CreateOrganization` so org-scoped creates set `organization_id` via direct assignment, e.g.:
```php
$role = new OrganizationRole(['key' => 'owner', 'name' => 'Owner', 'is_system' => true]);
$role->organization_id = $org->id;
$role->save();
// ...same for OrganizationMembership
```

- [ ] **Step 4: Full suite green** (fix any create that relied on `organization_id`/other now-unfillable keys in a mass array → move to direct assignment or add the key to `$fillable` if it is legitimately caller-supplied and not `organization_id`/`id`). pint; phpstan; commit.
```bash
git add -A && git commit -m "fix(tenancy): explicit \$fillable on tenant-owned models; org_id never mass-assigned (C3)"
```

---

## Task 6: Full gate + docs

**Files:** Modify `docs/04-security-baseline.md` (note fail-closed tenancy + the `runAsSystem` pattern + arch test) and `docs/superpowers/specs/2026-06-18-scope-validation-design.md` (mark C1/C3 resolved). Add a short `docs/tenancy.md` describing the rule (fail-closed, `runAsSystem`, `organization_id` is server-set, the arch test).

- [ ] **Step 1:** Whole gate: `php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M`. All green.
- [ ] **Step 2:** Update docs (rule: docs updated when behavior changes).
- [ ] **Step 3: Commit** `git commit -m "docs: fail-closed tenancy + runAsSystem; mark scope-validation C1/C3 resolved"`.

---

## Self-Review (against the spec)

**Spec coverage:** §4.1 runAsSystem/isSystem → Task 1; §4.2 fail-closed read+create → Task 3; §4.3 TenantModel — *note:* the spec's optional light base class is YAGNI given the arch test is the real guard; deferred (the arch test, Task 4, fully enforces isolation, so a base class adds no enforcement) — flagged for the user; §4.4 arch test → Task 4; §4.5 call-site migration → Task 2; §4.6 $fillable + CreateOrganization direct-assign → Task 5; §6 testing → per-task + Task 6; acceptance map §7 → Tasks 1–5.

**Deviation flagged:** I dropped §4.3's optional abstract `TenantModel` base class — it was explicitly "light convention, not the enforcement," and the architecture test (Task 4) is the actual guard. Adding an unused base class new models *might* extend is YAGNI. If you want it anyway, it's a trivial add. (Surface to the human at execution per the conflict rule.)

**Placeholder scan:** complete code for Tasks 1, 3, 4; Task 5 lists exact `$fillable` per model + the CreateOrganization change; Task 2 gives the grep + exact replacement pattern. The two tests with binding caveats (Task 3 "no-tenant context", Task 5 `actingAsTenant` helper) name the exact behavior to preserve and how to adapt — not vague.

**Type consistency:** `runAsSystem(callable): mixed`, `isSystem(): bool`, `TenantContextMissingException`, `BelongsToTenant` scope/hook, `class_uses_recursive` enforcement, the global allowlist, and the `$fillable` lists are referenced consistently across tasks.
