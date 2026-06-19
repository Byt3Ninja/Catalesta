# Phase 2 — Programs, Cohorts & Stage Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement configurable Programs (CRUD, templates, cloning, policies, role requirements), Cohorts, and a Stage engine (per-stage versioning + published-immutability, ordering, entry/exit rules, parallel_group + conditional transitions, participant stage state) — plus the scoped shared kernel (Versioning concern + Rules expression evaluator) the stages depend on.

**Architecture:** Three tenant-owned modules (`app/Modules/{Programs,Cohorts,Stages}`) on top of the Phase-1 foundation (`TenantContext`, `BelongsToTenant`, `ResolveTenant`, RBAC, `AuditLogger`, error envelope). Stage entry/exit rules are structured expression trees (docs/07) evaluated only via a registered field-resolver registry — no PHP/SQL/JS/shell. Published stage versions are frozen by a shared `ImmutableWhenPublished` concern.

**Tech Stack:** PHP 8.3 / Laravel 13, PostgreSQL, `brick/math` (decimal comparisons), ULIDs, Sanctum SPA session + `tenant` middleware, PHPUnit, Pint, Larastan.

## Global Constraints

- `declare(strict_types=1);` in every PHP file. Final classes for services/models/controllers where Phase 1 did so.
- Every tenant-owned record has `organization_id` and uses `App\Shared\Tenancy\BelongsToTenant`; composite UNIQUE constraints include `organization_id`-scoped columns (e.g. unique(`program_id`,`key`)). `organizations`/global tables excepted.
- Published stage versions are immutable (rule 8) via `App\Shared\Versioning\ImmutableWhenPublished`.
- No arbitrary PHP/SQL/JS/shell in rules (rule 10): rules are JSON expression trees; fields read ONLY through registered `FieldResolver`s; validated by `ExpressionValidator` before persistence.
- Decimal arithmetic for numeric comparisons (rule 9) via `brick/math` `BigDecimal`.
- ULID PKs (`HasUlids`), UTC (`timestampsTz`), ISO 8601 in APIs, JSONB only for bounded config.
- Public APIs under `/api/v1`, `auth:sanctum` + `tenant` middleware (rule 12); no business logic in controllers (rule 14) — orchestration only, logic in `Application/` services; DB transactions around multi-record ops.
- Server-side authorization via policies → 403 (rule 17); audit sensitive actions via `AuditLogger`.
- Each task ends green: `php artisan test` (the relevant + full suite before commit), `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --no-progress --memory-limit=512M`. Then commit.
- Run all PHP from `backend/`. Tenant tests: reuse `tests/TestCase.php` helpers `bootUserWithOrg()`, `createBareOrg()`, `makeExternalUser()` and `$this->actingAs($user, 'web')` + `X-Organization-Id` header.
- Migrations use the existing prefix scheme; Phase-2 migrations are `2026_06_18_0020xx`–`0030xx` (after `2026_06_18_001400`).
- Code exploration uses the repo Graphify workflow (`graphify query/explain/path`) before broad source reads — include this instruction in any sub-dispatch.

---

## Phase-1 patterns to mirror (verified from the codebase)

- **Tenant-owned model:** `final class X extends Model { use HasUlids, BelongsToTenant; protected $guarded = []; protected $casts = [...]; }`. Migrations: `$t->ulid('id')->primary(); $t->ulid('organization_id')->index(); ...; $t->timestampsTz();`.
- **Transactional service with explicit org id** (creator path) — see `App\Modules\Organizations\Application\CreateOrganization`: wraps in `DB::transaction`, sets `organization_id` explicitly when no tenant is resolved, calls `AuditLogger::record($action,$type,$id,$before,$after)`.
- **Permission catalog:** `Database\Seeders\PermissionCatalogSeeder` (idempotent `firstOrCreate` by `key`); the owner role's grant list lives in `CreateOrganization::handle()` (Step 3). Adding a permission key requires updating BOTH.
- **Policy registration:** `App\Providers\AppServiceProvider::boot()` → `Gate::policy(Model::class, Policy::class)`. Policies call `app(TenantContext::class)->can('<key>')`.
- **Tenant middleware alias:** `tenant` → `App\Http\Middleware\ResolveTenant` (reads `X-Organization-Id`, sets `TenantContext`).
- **Routes:** `routes/api.php` inside `Route::prefix('v1')`; tenant-scoped routes wrapped in `->middleware(['auth:sanctum','tenant'])`.
- **Error envelope:** validation → 422, auth → 401, authorization (`$this->authorize`) → 403, all as `{error:{code,message,correlation_id,details?}}`.

## Shared interfaces (referenced across tasks — exact signatures)

```php
// app/Shared/Rules/Operator.php
enum Operator: string {
    case Equals='equals'; case NotEquals='not_equals';
    case GreaterThan='greater_than'; case GreaterThanOrEqual='greater_than_or_equal';
    case LessThan='less_than'; case LessThanOrEqual='less_than_or_equal';
    case In='in'; case NotIn='not_in'; case Contains='contains'; case ContainsAny='contains_any';
    case IsNull='is_null'; case IsNotNull='is_not_null';
    public function apply(mixed $left, mixed $right): bool; // decimal-safe for the comparison ops
}

// app/Shared/Rules/FieldResolver.php
interface FieldResolver {
    public function supports(string $field): bool;
    /** @param array<string,mixed> $context */
    public function resolve(string $field, array $context): mixed;
    /** @return array<int,string> field namespaces this resolver owns, e.g. ['cohort','participant'] */
    public function namespaces(): array;
}

// app/Shared/Rules/FieldResolverRegistry.php  (singleton)
final class FieldResolverRegistry {
    public function register(FieldResolver $r): void;
    /** @param array<string,mixed> $context */ public function resolve(string $field, array $context): mixed; // throws UnknownFieldException
    public function knows(string $field): bool;
    /** @return array<int,string> */ public function namespaces(): array;
}

// app/Shared/Rules/ExpressionValidator.php
final class ExpressionValidator {
    public function __construct(private FieldResolverRegistry $registry) {}
    /** @param array<string,mixed> $tree  throws InvalidExpressionException */
    public function validate(array $tree): void;
}

// app/Shared/Rules/ExpressionEvaluator.php
final class ExpressionEvaluator {
    public function __construct(private FieldResolverRegistry $registry) {}
    /** @param array<string,mixed> $tree @param array<string,mixed> $context */
    public function evaluate(array $tree, array $context): bool;
}

// app/Shared/Versioning/VersionStatus.php
enum VersionStatus: string { case Draft='draft'; case Published='published'; case Archived='archived'; }

// app/Shared/Versioning/Versionable.php
interface Versionable {
    public function versionParentColumn(): string;  // e.g. 'program_stage_id'
    public function validateForPublish(): void;      // throws on invalid; no-op if fine
}

// app/Shared/Versioning/VersionPublisher.php
final class VersionPublisher {
    /** Publishes a draft version: assigns next version_number for its parent, sets status+published_at, in a transaction. @param \Illuminate\Database\Eloquent\Model&Versionable $version */
    public function publish(object $version): void;  // throws if not draft
}
```

---

## Milestone M0 — Shared kernel (Rules + Versioning)

### Task 0.1: Operator enum, FieldResolver contract, registry, ExpressionValidator

**Files:**
- Create: `backend/app/Shared/Rules/Operator.php`, `FieldResolver.php`, `FieldResolverRegistry.php`, `ExpressionValidator.php`, `Exceptions/InvalidExpressionException.php`, `Exceptions/UnknownFieldException.php`
- Test: `backend/tests/Unit/Rules/ExpressionValidatorTest.php`

**Interfaces:** Produces the signatures in the shared block (Operator, FieldResolver, FieldResolverRegistry, ExpressionValidator, the two exceptions extending `\RuntimeException`).

- [ ] **Step 1: Failing test** `tests/Unit/Rules/ExpressionValidatorTest.php`

```php
<?php
declare(strict_types=1);
namespace Tests\Unit\Rules;
use App\Shared\Rules\ExpressionValidator;
use App\Shared\Rules\FieldResolver;
use App\Shared\Rules\FieldResolverRegistry;
use App\Shared\Rules\Exceptions\InvalidExpressionException;
use Tests\TestCase;

final class ExpressionValidatorTest extends TestCase
{
    private function validator(): ExpressionValidator
    {
        $registry = new FieldResolverRegistry();
        $registry->register(new class implements FieldResolver {
            public function supports(string $field): bool { return str_starts_with($field, 'cohort.'); }
            public function resolve(string $field, array $context): mixed { return $context[$field] ?? null; }
            public function namespaces(): array { return ['cohort']; }
        });
        return new ExpressionValidator($registry);
    }

    public function test_accepts_a_valid_tree(): void
    {
        $this->validator()->validate([
            'all' => [
                ['field' => 'cohort.is_open', 'operator' => 'equals', 'value' => true],
            ],
        ]);
        $this->addToAssertionCount(1); // no exception
    }

    public function test_rejects_unknown_operator(): void
    {
        $this->expectException(InvalidExpressionException::class);
        $this->validator()->validate(['all' => [['field' => 'cohort.x', 'operator' => 'eval', 'value' => 1]]]);
    }

    public function test_rejects_unknown_field_namespace(): void
    {
        $this->expectException(InvalidExpressionException::class);
        $this->validator()->validate(['all' => [['field' => 'system.exec', 'operator' => 'equals', 'value' => 1]]]);
    }

    public function test_rejects_non_structured_node(): void
    {
        $this->expectException(InvalidExpressionException::class);
        $this->validator()->validate(['php' => 'system("rm -rf /")']);
    }
}
```

- [ ] **Step 2: Run, expect FAIL** `php artisan test --filter=ExpressionValidatorTest`

- [ ] **Step 3: Implement.** `Operator` enum (cases per shared block; `apply()` — see Task 0.2 where it's exercised; in 0.1 you may implement `apply` fully or leave the comparison logic for 0.2, but define the enum + a `tryFrom` lookup). `FieldResolver` interface; `FieldResolverRegistry` (array of resolvers; `resolve` finds first `supports`, else throws `UnknownFieldException`; `knows`; `namespaces` aggregates). `ExpressionValidator::validate`: recursively walk; a node must be exactly one of: `['all'=>array]`, `['any'=>array]` (recurse each child), or a leaf with keys `field`(string)+`operator`(string)+`value`; operator must be `Operator::tryFrom(...)` non-null; for `is_null`/`is_not_null` `value` is optional; the leaf `field` must be `registry->knows($field)` OR its namespace (substring before first `.`) be in `registry->namespaces()`; any other shape → `InvalidExpressionException`.

- [ ] **Step 4: Run PASS; pint; phpstan; commit**
```bash
git add -A && git commit -m "feat(rules): operator enum, field-resolver registry, expression validator"
```

### Task 0.2: ExpressionEvaluator (all operators, nesting, decimal-safe)

**Files:**
- Create: `backend/app/Shared/Rules/ExpressionEvaluator.php` (+ flesh out `Operator::apply`)
- Test: `backend/tests/Unit/Rules/ExpressionEvaluatorTest.php`

**Interfaces:** Consumes `FieldResolverRegistry`, `Operator`. Produces `ExpressionEvaluator::evaluate(array,array): bool`.

- [ ] **Step 1: Failing test** — cover each operator + nested all/any + decimal:

```php
<?php
declare(strict_types=1);
namespace Tests\Unit\Rules;
use App\Shared\Rules\ExpressionEvaluator;
use App\Shared\Rules\FieldResolver;
use App\Shared\Rules\FieldResolverRegistry;
use Tests\TestCase;

final class ExpressionEvaluatorTest extends TestCase
{
    private function evaluator(): ExpressionEvaluator
    {
        $registry = new FieldResolverRegistry();
        $registry->register(new class implements FieldResolver {
            public function supports(string $field): bool { return str_starts_with($field, 'ctx.'); }
            public function resolve(string $field, array $context): mixed { return $context[$field] ?? null; }
            public function namespaces(): array { return ['ctx']; }
        });
        return new ExpressionEvaluator($registry);
    }

    public function test_all_and_any_nesting(): void
    {
        $tree = ['all' => [
            ['field' => 'ctx.score', 'operator' => 'greater_than_or_equal', 'value' => 70],
            ['any' => [
                ['field' => 'ctx.role', 'operator' => 'in', 'value' => ['founder', 'mentor']],
                ['field' => 'ctx.flag', 'operator' => 'equals', 'value' => true],
            ]],
        ]];
        $this->assertTrue($this->evaluator()->evaluate($tree, ['ctx.score' => 71, 'ctx.role' => 'mentor', 'ctx.flag' => false]));
        $this->assertFalse($this->evaluator()->evaluate($tree, ['ctx.score' => 69, 'ctx.role' => 'mentor', 'ctx.flag' => false]));
    }

    public function test_decimal_comparison_is_exact(): void
    {
        $tree = ['all' => [['field' => 'ctx.total', 'operator' => 'greater_than', 'value' => '0.1']]];
        // 0.1 + 0.2 = 0.3 exactly under decimal; float would be 0.30000000000000004
        $this->assertTrue($this->evaluator()->evaluate($tree, ['ctx.total' => '0.3']));
    }

    public function test_is_null_and_contains(): void
    {
        $e = $this->evaluator();
        $this->assertTrue($e->evaluate(['all'=>[['field'=>'ctx.x','operator'=>'is_null']]], ['ctx.x'=>null]));
        $this->assertTrue($e->evaluate(['all'=>[['field'=>'ctx.tags','operator'=>'contains','value'=>'a']]], ['ctx.tags'=>['a','b']]));
    }
}
```

- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** `ExpressionEvaluator::evaluate` (group `all` = every child true; `any` = at least one; leaf resolves field via registry then `Operator::from($op)->apply($resolved,$value)`). Implement `Operator::apply`: numeric ops (`greater_than`…`less_than_or_equal`) compare via `BrickMath\BigDecimal::of((string)$left)->compareTo(BigDecimal::of((string)$value))`; `equals/not_equals` loose-but-typed (use `==`/`!=` after normalizing scalars; for numerics compare via BigDecimal); `in/not_in` (`in_array` strict-ish on array `$value`); `contains` (`in_array` on array left OR `str_contains` on string left); `contains_any` (intersection non-empty); `is_null`/`is_not_null` (presence of resolved value).
- [ ] **Step 4: Run PASS; pint; phpstan; commit** `git commit -m "feat(rules): expression evaluator (decimal-safe, nested all/any)"`

### Task 0.3: Versioning concern + publisher

**Files:**
- Create: `backend/app/Shared/Versioning/VersionStatus.php`, `Versionable.php`, `ImmutableWhenPublished.php` (trait), `VersionPublisher.php`, `Exceptions/VersionStateException.php`
- Test: `backend/tests/Unit/Versioning/VersioningTest.php` (uses a throwaway anonymous/in-memory model OR a tiny test model + an on-the-fly table via a migration in the test).

**Interfaces:** Produces `VersionStatus`, `Versionable`, `ImmutableWhenPublished` (booted: throw `VersionStateException` on `updating`/`deleting` when `status===published`, EXCEPT permit an update whose only dirty column is `status` changing published→archived), `VersionPublisher::publish($version)`.

- [ ] **Step 1: Failing test** — create a temp table + a model using the trait + Versionable; assert: publish assigns version_number 1 then 2 for the same parent; published row update throws; published→archived allowed.

```php
<?php
declare(strict_types=1);
namespace Tests\Unit\Versioning;
use App\Shared\Versioning\ImmutableWhenPublished;
use App\Shared\Versioning\Versionable;
use App\Shared\Versioning\VersionPublisher;
use App\Shared\Versioning\VersionStateException;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FakeVersion extends Model implements Versionable {
    use HasUlids, ImmutableWhenPublished;
    protected $table = 'fake_versions';
    public $timestamps = true;
    protected $guarded = [];
    protected $casts = ['status' => VersionStatus::class];
    public function versionParentColumn(): string { return 'parent_id'; }
    public function validateForPublish(): void {}
}

final class VersioningTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('fake_versions', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('parent_id')->index();
            $t->unsignedInteger('version_number')->default(0);
            $t->string('status')->default('draft');
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();
        });
    }

    public function test_publish_assigns_incrementing_version_numbers_per_parent(): void
    {
        $publisher = new VersionPublisher();
        $v1 = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        $v2 = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        $publisher->publish($v1);
        $publisher->publish($v2);
        $this->assertSame(1, $v1->fresh()->version_number);
        $this->assertSame(2, $v2->fresh()->version_number);
        $this->assertSame(VersionStatus::Published, $v1->fresh()->status);
    }

    public function test_published_version_cannot_be_edited(): void
    {
        $v = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        (new VersionPublisher())->publish($v);
        $this->expectException(VersionStateException::class);
        $v->refresh();
        $v->parent_id = 'p2';
        $v->save();
    }
}
```

- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** the enum, contract, trait (guard via `static::updating`/`static::deleting`; allow the published→archived status-only change by inspecting `$model->getDirty()` === `['status'=>...]` with new value `archived`), and `VersionPublisher::publish` (assert `status===Draft` else throw; `DB::transaction`: `version_number = (max for parent)+1`, `status=Published`, `published_at=now()`, save via a path that the guard allows — publishing transitions draft→published so the guard (which only blocks when CURRENT status is published) permits it).
- [ ] **Step 4: Run PASS; pint; phpstan; commit** `git commit -m "feat(versioning): immutable-when-published concern + version publisher"`

---

## Milestone M1 — Programs (core)

### Task 1.1: programs table + model + new permission keys

**Files:**
- Create: `backend/database/migrations/2026_06_18_002000_create_programs_table.php`, `backend/app/Modules/Programs/Domain/Models/Program.php`
- Modify: `backend/database/seeders/PermissionCatalogSeeder.php` (add keys), `backend/app/Modules/Organizations/Application/CreateOrganization.php` (add keys to owner grant)
- Test: `backend/tests/Feature/Programs/ProgramModelTest.php`

**Interfaces:** Produces `Program` (tenant-owned; `status` cast to a `ProgramStatus` enum OR string; slug auto-derived from name; `settings` array cast). New permission keys: `programs.manage`, `programs.publish`, `cohorts.manage`, `stages.manage`.

- [ ] **Step 1: Migration** — `programs`: `id` ulid pk, `organization_id` ulid index, `name`, `slug`, `status` default `draft`, `description` nullable, `settings` jsonb nullable, `template_id` ulid nullable index, `timestampsTz`; `unique(['organization_id','slug'])`.
- [ ] **Step 2: Failing test** — create a Program (acting under a tenant via `bootUserWithOrg`), assert ULID id, slug derived, settings array cast, `status='draft'`; assert the seeder includes the 4 new keys.

```php
<?php
declare(strict_types=1);
namespace Tests\Feature\Programs;
use App\Modules\Organizations\Domain\Models\OrganizationPermission;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProgramModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_persists_with_ulid_slug_and_settings_cast(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->withHeader('X-Organization-Id', $org->id);
        $p = Program::create(['name' => 'Accelerator 2026', 'settings' => ['cohort_cap' => 20]]);
        $this->assertSame(26, strlen($p->id));
        $this->assertSame('accelerator-2026', $p->slug);
        $this->assertSame(['cohort_cap' => 20], $p->fresh()->settings);
        $this->assertSame($org->id, $p->organization_id); // auto-stamped by BelongsToTenant
    }

    public function test_catalog_seeds_phase2_permissions(): void
    {
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
        foreach (['programs.manage','programs.publish','cohorts.manage','stages.manage'] as $key) {
            $this->assertTrue(OrganizationPermission::where('key',$key)->exists(), $key);
        }
    }
}
```

- [ ] **Step 3: Run FAIL.**
- [ ] **Step 4: Implement** `Program` model (`HasUlids`, `BelongsToTenant`, `$guarded=[]`, `casts['settings'=>'array']`, slug auto-derive on `creating` via `Str::slug` if absent); add the 4 keys to `PermissionCatalogSeeder`; add the same 4 keys to the owner-grant list in `CreateOrganization::handle()` (so org owners gain them — required for the feature tests' `bootUserWithOrg` owner to manage programs).
- [ ] **Step 5: Run PASS; pint; phpstan; commit** `git commit -m "feat(programs): programs table + model; Phase-2 permission keys"`

### Task 1.2: Program policy + CRUD API + publish

**Files:**
- Create: `backend/app/Modules/Programs/Policies/ProgramPolicy.php`, `Http/ProgramController.php`, `Http/Requests/StoreProgramRequest.php`, `Http/Requests/UpdateProgramRequest.php`, `Http/Resources/ProgramResource.php`, `Application/PublishProgram.php`
- Modify: `backend/app/Providers/AppServiceProvider.php` (register `ProgramPolicy`), `backend/routes/api.php` (program routes)
- Test: `backend/tests/Feature/Programs/ProgramApiTest.php`

**Interfaces:** Produces routes `GET/POST /api/v1/programs`, `GET/PATCH /api/v1/programs/{id}`, `POST /api/v1/programs/{id}/publish`. Policy: `viewAny/view` → tenant resolved; `create/update` → `programs.manage`; `publish` → `programs.publish`.

- [ ] **Step 1: Failing feature test** — owner (`bootUserWithOrg`, has all perms) with `X-Organization-Id`: POST /programs {name} → 201 + draft; PATCH → 200; POST publish → 200 status=published; GET index lists it; a member WITHOUT `programs.manage` POST → 403; cross-tenant GET of another org's program → 403/404.
- [ ] **Step 2: Run FAIL.**
- [ ] **Step 3: Implement** controller (thin; `$this->authorize`), requests (`name` required on store; name/description/settings optional on update; `settings` array), resource, `PublishProgram` service (draft→published, audit `program.published`), policy, route group under `['auth:sanctum','tenant']`, register policy. Audit `program.created/updated`.
- [ ] **Step 4: Run PASS; pint; phpstan; commit** `git commit -m "feat(programs): program CRUD + publish API with policy"`

### Task 1.3: program_policies + program_role_requirements

**Files:**
- Create migrations `2026_06_18_002200_create_program_policies_table.php`, `2026_06_18_002300_create_program_role_requirements_table.php`; models `ProgramPolicyRecord.php` (table `program_policies`), `ProgramRoleRequirement.php`; nested endpoints on the program (or sub-resources) + requests/resources.
- Test: `backend/tests/Feature/Programs/ProgramConfigApiTest.php`

**Interfaces:** Produces `program_policies` (unique(program_id,key)) + `program_role_requirements` (unique(program_id,role_key)) models + endpoints `POST/GET /api/v1/programs/{program}/policies`, `POST/GET /api/v1/programs/{program}/role-requirements` (auth+tenant, `programs.manage`).

- [ ] **Step 1: Migrations** per §5 columns. **Step 2: Failing test** — set a policy + a role requirement on a program (owner), assert persisted + scoped + unique enforced (duplicate key → 422 or DB unique). **Step 3: Implement** models (BelongsToTenant), thin controllers/services (validate role_requirement: min_count>=0, max_count null or >=min_count). **Step 4: PASS; pint; phpstan; commit** `git commit -m "feat(programs): policies + role requirements"`

---

## Milestone M2 — Cohorts

### Task 2.1: cohorts table + model + policy + API

**Files:**
- Create: migration `2026_06_18_002400_create_cohorts_table.php`, `app/Modules/Cohorts/Domain/Models/Cohort.php`, `Policies/CohortPolicy.php`, `Http/CohortController.php`, `Http/Requests/StoreCohortRequest.php`, `UpdateCohortRequest.php`, `Http/Resources/CohortResource.php`
- Modify: `AppServiceProvider` (register `CohortPolicy`), `routes/api.php`
- Test: `backend/tests/Feature/Cohorts/CohortApiTest.php`

**Interfaces:** Produces `cohorts` (per §6: program_id, name, slug, status `draft|open|closed|completed`, enrollment_opens_at?, enrollment_closes_at?, starts_at?, ends_at?, capacity?, timeline jsonb; unique(program_id,slug)). Routes `POST /api/v1/programs/{program}/cohorts`, `GET/PATCH /api/v1/cohorts/{id}`. Policy `create/update` → `cohorts.manage`.

- [ ] **Step 1: Migration + model.** **Step 2: Failing test** — owner POST /programs/{p}/cohorts {name, capacity:20} → 201 draft; PATCH status transitions draft→open → 200; window validation: enrollment_opens_at after enrollment_closes_at → 422; member lacking `cohorts.manage` → 403; cross-tenant cohort GET → 403/404. **Step 3: Implement** model (BelongsToTenant; date casts), policy, requests (validate `enrollment_opens_at <= enrollment_closes_at <= starts_at <= ends_at` when present; `capacity` integer >=1 nullable; `status` in enum), controller (thin, authorize, audit `cohort.created/updated`), routes. **Step 4: PASS; pint; phpstan; commit** `git commit -m "feat(cohorts): cohorts table, model, API"`

---

## Milestone M3 — Stages

### Task 3.1: program_stages + stage_versions + models

**Files:**
- Create: migrations `2026_06_18_002500_create_program_stages_table.php`, `2026_06_18_002600_create_stage_versions_table.php`; models `app/Modules/Stages/Domain/Models/ProgramStage.php`, `StageVersion.php` (uses `ImmutableWhenPublished` + implements `Versionable`)
- Test: `backend/tests/Feature/Stages/StageModelTest.php`

**Interfaces:** Produces `ProgramStage` (per §7: key,name,type,order_index,parallel_group?,current_published_version_id?; unique(program_id,key)) + `StageVersion` (version_number,status,config jsonb,published_at; `versionParentColumn()='program_stage_id'`; `validateForPublish()` validates each attached rule's expression via `ExpressionValidator`).

- [ ] **Step 1: Migrations** per §7. **Step 2: Failing test** — create a ProgramStage + draft StageVersion; assert ULID, type enum stored, order_index; publishing via `VersionPublisher` sets version_number=1 + status=published + then editing the published version throws. **Step 3: Implement** models (BelongsToTenant; StageVersion casts `config`=>array, `status`=>VersionStatus, uses ImmutableWhenPublished, implements Versionable; relations: ProgramStage hasMany versions; StageVersion hasMany rules). **Step 4: PASS; pint; phpstan; commit** `git commit -m "feat(stages): program_stages + versioned stage_versions"`

### Task 3.2: stage_rules + stage_transitions (validated expressions)

**Files:**
- Create: migrations `2026_06_18_002700_create_stage_rules_table.php`, `2026_06_18_002800_create_stage_transitions_table.php`; models `StageRule.php`, `StageTransition.php`; register a minimal `FieldResolverRegistry` binding + Phase-2 resolvers in a new `app/Modules/Stages/StagesServiceProvider.php` (register in `bootstrap/providers.php`).
- Test: `backend/tests/Feature/Stages/StageRuleValidationTest.php`, `backend/tests/Unit/Stages/StageFieldResolversTest.php`

**Interfaces:** Produces `StageRule` (stage_version_id, type entry|exit, expression jsonb — validated via `ExpressionValidator` on save, reject invalid → exception/422), `StageTransition` (program_id, from_program_stage_id?, to_program_stage_id, condition jsonb? validated, order_index). Registers resolvers for namespaces `participant`, `cohort`, `context` (e.g. `participant.current_stage_status`, `cohort.is_open`).

- [ ] **Step 1: Failing tests** — saving a StageRule with an invalid expression (unknown operator/field) is rejected; a valid entry rule persists; the registry resolves `cohort.is_open` from context. **Step 2: Run FAIL.** **Step 3: Implement** models with a `saving` hook (or service) that calls `app(ExpressionValidator::class)->validate($expression)` and throws `InvalidExpressionException` on failure; `StagesServiceProvider` binds `FieldResolverRegistry` as a singleton and registers the Phase-2 resolvers. **Step 4: PASS; pint; phpstan; commit** `git commit -m "feat(stages): validated entry/exit rules + transitions + field resolvers"`

### Task 3.3: Stage API — list/add/reorder/publish + policy

**Files:**
- Create: `app/Modules/Stages/Policies/StagePolicy.php`, `Http/StageController.php` (index, store, update, publish, reorder), requests, `Http/Resources/StageResource.php`, `Application/ReorderStages.php`, `Application/PublishStageVersion.php`
- Modify: `AppServiceProvider` (register `StagePolicy` on `ProgramStage`), `routes/api.php`
- Test: `backend/tests/Feature/Stages/StageApiTest.php`

**Interfaces:** Routes `GET/POST /api/v1/programs/{program}/stages`, `PATCH /api/v1/stages/{id}`, `POST /api/v1/stages/{id}/publish`, `POST /api/v1/stages/reorder`. Policy `create/update/publish/reorder` → `stages.manage`.

- [ ] **Step 1: Failing feature test** — owner: POST stage (creates stage + draft v1) → 201; POST a second stage; POST /stages/reorder with new order → 200 + order_index updated; POST /stages/{id}/publish → 200 + current_published_version_id set; PATCH a PUBLISHED stage version's config → rejected (422/409 — published immutable); member without `stages.manage` → 403; cross-tenant → 403/404; create two stages with the same `parallel_group` and a transition between stages → persisted (represents parallel + conditional). **Step 2: Run FAIL.** **Step 3: Implement** controllers (thin, authorize, audit `stage.created/reordered/published`), `ReorderStages` (transactional bulk order_index update, validates all ids belong to the program/tenant), `PublishStageVersion` (uses `VersionPublisher`, sets `program_stages.current_published_version_id`), `StageResource`. Editing a published version must surface as a clean 422/409 (catch `VersionStateException`). **Step 4: PASS; pint; phpstan; commit** `git commit -m "feat(stages): stage API — add, reorder, publish, transitions"`

### Task 3.4: participant_stage_statuses + stage_instances + advance service

**Files:**
- Create: migrations `2026_06_18_002900_create_participant_stage_statuses_table.php`, `2026_06_18_003000_create_stage_instances_table.php`; models `ParticipantStageStatus.php`, `StageInstance.php`; `Application/AdvanceParticipantStage.php`
- Test: `backend/tests/Feature/Stages/ParticipantStageStateTest.php`

**Interfaces:** Produces `ParticipantStageStatus` (cohort_id, external_user_id, program_stage_id, status, entered_at?, completed_at?; unique triple) + `StageInstance` (participant_stage_status_id, stage_version_id, started_at). `AdvanceParticipantStage::enter($cohort,$externalUser,$stage)` (evaluates entry rule via `ExpressionEvaluator`; on pass sets status=in_progress, entered_at, creates `StageInstance` bound to `current_published_version_id`); `complete(...)` (evaluates exit rule; sets completed).

- [ ] **Step 1: Failing test** — enter a participant into a published stage → status in_progress + a stage_instance bound to the published version id; an entry rule that fails (via synthetic context) blocks entry (status stays/blocked); completing sets completed_at. **Step 2: Run FAIL.** **Step 3: Implement** models (BelongsToTenant) + service (transactional; uses ExpressionEvaluator + the stage's published version's entry/exit StageRule; builds context from participant/cohort). **Step 4: PASS; pint; phpstan; commit** `git commit -m "feat(stages): participant stage state + instances bound to published version"`

---

## Milestone M4 — Templates & Cloning

### Task 4.1: Program cloning

**Files:**
- Create: `app/Modules/Programs/Application/CloneProgram.php`; add `clone` to `ProgramController` + route `POST /api/v1/programs/{id}/clone`
- Test: `backend/tests/Feature/Programs/CloneProgramTest.php`

**Interfaces:** Produces `CloneProgram::handle(Program $source, string $newName): Program` — transactional deep copy → new `draft` program + copied program_policies + program_role_requirements + program_stages (each with a fresh **draft** stage_version copying config + stage_rules) + stage_transitions (remapped to the new stage ids). Does NOT copy cohorts or participant state. New slug uniqued.

- [ ] **Step 1: Failing test** — owner creates a program with 2 stages (one published), policies, role reqs, a transition; POST /programs/{id}/clone {name} → 201; assert the clone is draft, has 2 stages each with a DRAFT version (not published), copied rules, copied policies/role-reqs, a transition between the cloned stages, and a distinct slug; assert NO cohorts copied. **Step 2: Run FAIL.** **Step 3: Implement** `CloneProgram` (transactional; id remap map old→new stage ids for transitions; copy rules from the source's current/draft version into the new draft version), controller `clone` (authorize `programs.manage`, audit `program.cloned`). **Step 4: PASS; pint; phpstan; commit** `git commit -m "feat(programs): deep clone program into a new draft"`

### Task 4.2: Program templates

**Files:**
- Create: migration `2026_06_18_002100_create_program_templates_table.php`; model `ProgramTemplate.php`; `Application/CreateProgramFromTemplate.php`; `Application/SaveProgramAsTemplate.php`; endpoints `POST /api/v1/program-templates` (save a program as template), `POST /api/v1/program-templates/{id}/instantiate` (create program from template).
- Test: `backend/tests/Feature/Programs/ProgramTemplateTest.php`

**Interfaces:** Produces `program_templates` (org_id, name, slug, description?, blueprint jsonb; unique(org_id,slug)). `SaveProgramAsTemplate::handle($program,$name)` serializes the program definition (stages/rules/policies/role-reqs/transitions) into `blueprint`. `CreateProgramFromTemplate::handle($template,$name)` materializes a new draft program from the blueprint (reusing the clone/deep-copy mechanics).

- [ ] **Step 1: Failing test** — save a program as a template (blueprint captured); instantiate → a new draft program with the template's stages/rules/policies. **Step 2: Run FAIL.** **Step 3: Implement** model + the two services (DRY with CloneProgram's copy mechanics — extract a shared `ProgramDefinitionCopier` if it reduces duplication) + thin controller + routes (authorize `programs.manage`). **Step 4: PASS; pint; phpstan; commit** `git commit -m "feat(programs): templates — save-as-template + instantiate"`

---

## Milestone M5 — Security suites + gate + docs

### Task 5.1: Tenant isolation + authorization suite (Phase 2)

**Files:** Create `backend/tests/Feature/Phase2TenantIsolationTest.php`.

- [ ] **Step 1: Write tests** (must pass against the implemented stack; if any reveals a real leak, STOP/report rather than weaken):
  - Member of Org A (header OrgA) cannot create/read/patch a program/cohort/stage in Org B via route param mismatch or foreign id → 403 (mirror the Phase-1 membership fix: any `{program}`/`{id}` route under `tenant` must reconcile against `TenantContext->organizationId()` or be tenant-scoped so a foreign id 404s; **verify each Phase-2 controller does this** — fix the controller if a leak is found).
  - A program/stage/cohort created under Org A is invisible to a query under Org B (global scope).
  - A member lacking `programs.manage`/`stages.manage`/`cohorts.manage` is 403 on the respective mutations.
- [ ] **Step 2: Run; fix any controller that leaks (route-param vs tenant reconciliation, as in Phase-1 MembershipController).** **Step 3: Commit** `git commit -m "test: Phase 2 tenant isolation + authorization suite"`

### Task 5.2: Full gate + docs

**Files:** Modify `docs/04-data-model.md` (mark Phase-2 tables implemented), `docs/05-modules.md` (note Programs/Cohorts/Stages implemented), `docs/phase-1-notes.md` or new `docs/phase-2-notes.md` (rule expression format + field-resolver registry + how to add resolvers; versioning/immutability; clone/template behavior).

- [ ] **Step 1:** Run the whole gate: `php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M`. All green.
- [ ] **Step 2:** Update docs (rule 19).
- [ ] **Step 3: Commit** `git commit -m "docs: Phase 2 data-model/module status + notes"`

---

## Self-Review (against the spec)

**Spec coverage:** kernel §4 → M0; programs/templates/cloning/policies/role-reqs §5 → M1.1-1.3 + M4; cohorts §6 → M2; stages/versioning/ordering/rules/parallel/transitions/participant-state §7 → M3; APIs §8 → M1.2/M2.1/M3.3/M4; authz §9 → permission keys (1.1) + policies (1.2/2.1/3.3); testing §10 → per-task + M5; deferrals §2 honored (no forms/applications/assessments; rich resolvers + workflow engine deferred). Acceptance map §12 → M1.2 (create), M3.3 (add/reorder/publish-immutable), M3.2/M3.3 (conditional+parallel), M5.1 (tenant isolation), feature suites (API tests).

**Placeholder scan:** kernel + versioning + clone tasks carry complete code; CRUD/migration tasks specify exact columns, routes, validation rules, response codes, and assertions (not "add validation"). No "similar to Task N".

**Type consistency:** `Operator`, `FieldResolver`, `FieldResolverRegistry`, `ExpressionValidator`, `ExpressionEvaluator`, `VersionStatus`, `Versionable`, `VersionPublisher::publish`, `ImmutableWhenPublished`, `ProgramStage`/`StageVersion` (`versionParentColumn()='program_stage_id'`), `CloneProgram::handle` used identically where referenced. Migration prefixes ordered for FK safety (programs 002000 → templates 002100 → policies 002200 → role-reqs 002300 → cohorts 002400 → program_stages 002500 → stage_versions 002600 → stage_rules 002700 → stage_transitions 002800 → participant_stage_statuses 002900 → stage_instances 003000).

**Integration note:** New permission keys MUST be added to BOTH `PermissionCatalogSeeder` AND the owner-grant list in `CreateOrganization::handle()` (Task 1.1) so `bootUserWithOrg()` owners can exercise the new endpoints; otherwise every Phase-2 feature test 403s.
