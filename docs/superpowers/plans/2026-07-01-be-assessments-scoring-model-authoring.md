# Assessments Phase A — Scoring-Model Authoring + Versioning — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` (invoke before each task). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the already-built FE scoring-model authoring pages (`ScoringModelBuilderPage`, `ScoringModelPreviewPage`, `ScoringModelVersionsPage`) a real backend by creating a per-program `ScoringModel` + `ScoringModelVersion` aggregate that mirrors the Forms authoring slice exactly (Phase A of ADR-0012). The 8 FE-contract endpoints (list, get, getVersion, listVersions, create, saveDraft, publish, fork) replace MSW. No scoring math, no assignments, no scorecards, no decisions.

**Architecture:** Laravel modular monolith. The `Assessments` module (currently a `.gitkeep` scaffold) grows its Domain + Application + Http layers following the `Forms` module structure verbatim with substitutions. The shared `VersionPublisher`/`ImmutableWhenPublished`/`Versionable`/`VersionStatus` versioning kernel is consumed unchanged. A new `CriteriaValidator` handles structural criterion validation + canonical JSON (analogous to `FormDefinitionValidator`). New `assessments.manage` permission is wired into `PermissionCatalogSeeder` and `CreateOrganization`'s hardcoded owner-role grant. `AuditAction::ScoringModelPublished` is added with its FR-052 lockstep test updated atomically in T1.

**Tech Stack:** PHP 8.3 / Laravel 11, PHPUnit / Pest, Eloquent (PostgreSQL, ULID PKs, JSONB), `dedoc/scramble` (code-first OpenAPI), `larastan/larastan`, `laravel/pint`, `deptrac`.

## Global Constraints

- Branch: `feat/be-assessments-scoring-model-authoring`.
- Commit author: `274270+Byt3Ninja@users.noreply.github.com`. Use `git -c commit.gpgsign=false`. Sign-off: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. `git add` only the task's files (never `-A`, never `catalesta-ui/`). Verify `git branch --show-current` is the feature branch before every commit.
- Per-task gate (run from `backend/`):
  ```
  vendor/bin/pint --test \
  && vendor/bin/phpstan analyse --no-progress --memory-limit=512M \
  && vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress \
  && php artisan test --filter=ScoringModel
  ```
  T1 also runs `--filter=AuditActionTest` and `--filter=OrganizationApiTest`. T4 adds `--filter=OpenApiSpecTest`.
- The FE contract is fixed: `frontend/src/schemas/assessments.ts` + `frontend/src/api/assessments.ts`. Do not change the FE.
- All endpoints live under `/api/v1` inside `Route::middleware(['auth:sanctum', 'tenant'])` in `backend/routes/api.php`.
- Cross-tenant `{id}` → neutral **404** (ADR-0009), never 403.
- Authorization is deny-by-default via `ScoringModelPolicy`; `assessments.manage` gates create/draft/publish/fork.
- Published `ScoringModelVersion` rows are immutable (`ImmutableWhenPublished`). At most one draft per model.
- Publish is idempotent: republishing identical canonical criteria returns the existing version — no duplicate row.
- **No scoring math** in Phase A: criteria are definitions only.
- Known imperfect mappings (recorded, not silently fixed):
  - **Program scope**: Forms are org-scoped (flat `/forms`). ScoringModels are program-scoped: list + create nest under `/programs/{program}/scoring-models`. `ScoringModelController::index()` and `::store()` accept an implicit `Program $program` route-model-binding parameter; show/versions/draft/publish/fork remain flat by model id.
  - **Resource key renames**: `ScoringModelResource` emits `model_id` (not `id`) and includes `program_id`. `ScoringModelVersionResource` emits `version_id` (not `id`) and `model_id` (not `form_id`). Both differ from the Forms resources.
  - **Publish error codes**: Spec §6 lists only 409 for publish; spec §7 says 422 for empty criteria; FE `publishScoringModel` only handles 409. This plan implements: **409 for "no draft exists"** (NoDraftException), **422 for "draft has zero criteria"** (NoCriteriaException). The FE surfaces 422 as UNKNOWN — accepted per spec §9 test assertion. A follow-up FE slice may add a 422 handler.
  - **Fork response code**: Forms always returns 201. FE accepts 200|201. This plan returns **201 for a new draft**, **200 for an existing draft returned unchanged** (semantically correct).

## File Structure

| File | Responsibility | Task |
|------|----------------|------|
| `database/migrations/2026_07_01_000100_create_scoring_models_table.php` | scoring_models DDL | 1 |
| `database/migrations/2026_07_01_000200_create_scoring_model_versions_table.php` | scoring_model_versions DDL | 1 |
| `app/Modules/Assessments/Domain/Models/ScoringModel.php` | version-parent model | 1 |
| `app/Modules/Assessments/Domain/Models/ScoringModelVersion.php` | immutable versioned model | 1 |
| `app/Shared/Audit/AuditAction.php` | + ScoringModelPublished case | 1 |
| `tests/Unit/Audit/AuditActionTest.php` | FR-052 lockstep — updated | 1 |
| `database/seeders/PermissionCatalogSeeder.php` | + assessments.manage | 1 |
| `app/Modules/Organizations/Application/CreateOrganization.php` | + assessments.manage in whereIn | 1 |
| `tests/Feature/OrganizationApiTest.php` | + assertContains assessments.manage | 1 |
| `app/Modules/Assessments/Domain/CriteriaValidator.php` | structural validation + canonicalJson | 2 |
| `app/Modules/Assessments/Domain/Exceptions/InvalidCriteriaException.php` | 422 signal | 2 |
| `app/Modules/Assessments/Domain/Exceptions/NoDraftException.php` | 409 signal (no draft) | 2 |
| `app/Modules/Assessments/Domain/Exceptions/NoCriteriaException.php` | 422 signal (empty criteria at publish) | 2 |
| `app/Modules/Assessments/Application/CreateScoringModel.php` | create + seed empty draft | 2 |
| `app/Modules/Assessments/Application/SaveScoringModelDraft.php` | upsert criteria onto draft | 2 |
| `app/Modules/Assessments/Application/PublishScoringModel.php` | content-hash idempotent publish + audit | 2 |
| `app/Modules/Assessments/Application/ForkScoringModelDraft.php` | new draft from published version | 2 |
| `tests/Feature/Assessments/ScoringModelServiceTest.php` | service-level unit tests | 2 |
| `app/Modules/Assessments/Http/Requests/StoreScoringModelRequest.php` | name validation | 3 |
| `app/Modules/Assessments/Http/Requests/SaveScoringModelDraftRequest.php` | criteria structural rules | 3 |
| `app/Modules/Assessments/Http/Requests/ForkScoringModelDraftRequest.php` | from_version_id required | 3 |
| `app/Modules/Assessments/Http/Resources/ScoringModelResource.php` | model_id / program_id / derived fields | 3 |
| `app/Modules/Assessments/Http/Resources/ScoringModelVersionResource.php` | version_id / model_id / criteria | 3 |
| `app/Modules/Assessments/Policies/ScoringModelPolicy.php` | deny-by-default; assessments.manage gates | 3 |
| `app/Modules/Assessments/Http/ScoringModelController.php` | 7 controller actions | 3 |
| `app/Modules/Assessments/Http/ScoringModelVersionController.php` | show by version id | 3 |
| `routes/api.php` | 8 route registrations | 3 |
| `tests/Feature/Assessments/ScoringModelAuthoringTest.php` | HTTP surface tests | 3 |
| `tests/Feature/Assessments/ScoringModelPolicyTest.php` | policy unit tests | 3 |
| `tests/Feature/Assessments/ScoringModelResourceTest.php` | resource shape tests | 3 |
| `openapi/openapi.json` | regenerated by Scramble | 4 |

---

### Task 1: Migrations + Models + AuditAction + assessments.manage permission + owner grant

**Files:**
- Create: `backend/database/migrations/2026_07_01_000100_create_scoring_models_table.php`
- Create: `backend/database/migrations/2026_07_01_000200_create_scoring_model_versions_table.php`
- Create: `backend/app/Modules/Assessments/Domain/Models/ScoringModel.php`
- Create: `backend/app/Modules/Assessments/Domain/Models/ScoringModelVersion.php`
- Modify: `backend/app/Shared/Audit/AuditAction.php`
- Modify: `backend/tests/Unit/Audit/AuditActionTest.php`
- Modify: `backend/database/seeders/PermissionCatalogSeeder.php`
- Modify: `backend/app/Modules/Organizations/Application/CreateOrganization.php`
- Modify: `backend/tests/Feature/OrganizationApiTest.php`

**Interfaces:**
- Consumes: `App\Shared\Tenancy\BelongsToTenant`, `App\Shared\Versioning\{ImmutableWhenPublished, Versionable, VersionStatus}`, `App\Shared\Audit\AuditAction`.
- Produces: `scoring_models` table, `scoring_model_versions` table; Eloquent models `ScoringModel`/`ScoringModelVersion`; `AuditAction::ScoringModelPublished`; permission key `assessments.manage` in catalog + owner role.

**Steps:**

- [ ] **Step 1.1 — Write the failing schema test**

Create `backend/tests/Feature/Assessments/ScoringModelSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScoringModelSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoring_model_can_be_created_without_a_published_version(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $model = ScoringModel::create(['program_id' => 'prog-01', 'name' => 'Evaluation']);

        $this->assertNull($model->current_published_version_id);
        $this->assertDatabaseHas('scoring_models', ['id' => $model->id, 'name' => 'Evaluation']);
    }

    public function test_scoring_model_version_draft_can_have_null_content_hash(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $model = ScoringModel::create(['program_id' => 'prog-01', 'name' => 'Evaluation']);
        $version = ScoringModelVersion::create([
            'scoring_model_id' => $model->id,
            'status' => 'draft',
            'version_number' => 0,
            'criteria' => [],
        ]);

        $this->assertNull($version->content_hash);
        $this->assertSame([], $version->criteria);
    }
}
```

Run: `php artisan test --filter=ScoringModelSchemaTest` → **red** (table does not exist).

- [ ] **Step 1.2 — Write migration for scoring_models**

Create `backend/database/migrations/2026_07_01_000100_create_scoring_models_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A scoring model — the version parent, program-scoped, org-scoped.
    // Immutable, content-addressed versions live in scoring_model_versions.
    public function up(): void
    {
        Schema::create('scoring_models', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->index();
            $t->string('name');
            $t->ulid('current_published_version_id')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_models');
    }
};
```

- [ ] **Step 1.3 — Write migration for scoring_model_versions**

Create `backend/database/migrations/2026_07_01_000200_create_scoring_model_versions_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A published, immutable scoring-model version. content_hash is the
    // content-addressed version id (sha256 of canonical criteria JSON).
    // UNIQUE(scoring_model_id, content_hash) makes identical republish idempotent.
    // content_hash is nullable until publish (draft may exist without a hash).
    public function up(): void
    {
        Schema::create('scoring_model_versions', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('scoring_model_id')->index();
            $t->unsignedInteger('version_number');
            $t->string('status');        // draft | published
            $t->string('content_hash', 64)->nullable();
            $t->jsonb('criteria');       // array of {criterion_id, label, max_points, descriptors}
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();

            $t->unique(['scoring_model_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_model_versions');
    }
};
```

- [ ] **Step 1.4 — Create ScoringModel model**

Create `backend/app/Modules/Assessments/Domain/Models/ScoringModel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scoring model — the version parent, program-scoped and org-scoped.
 * Immutable, content-addressed versions live in scoring_model_versions.
 */
final class ScoringModel extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_id', 'name', 'current_published_version_id'];

    /** @return HasMany<ScoringModelVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ScoringModelVersion::class);
    }

    /** @return HasMany<ScoringModelVersion, $this> */
    public function publishedVersions(): HasMany
    {
        return $this->hasMany(ScoringModelVersion::class)
            ->where('status', 'published')
            ->orderBy('version_number');
    }

    public function draftVersion(): ?ScoringModelVersion
    {
        return $this->versions()->where('status', 'draft')->first();
    }
}
```

- [ ] **Step 1.5 — Create ScoringModelVersion model**

Create `backend/app/Modules/Assessments/Domain/Models/ScoringModelVersion.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Versioning\ImmutableWhenPublished;
use App\Shared\Versioning\Versionable;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A published, immutable scoring-model version. Once published the row is
 * frozen (ImmutableWhenPublished); each published version keeps its ULID
 * resolvable for historical binding (ADR-0012 immutability invariant).
 */
final class ScoringModelVersion extends Model implements Versionable
{
    use BelongsToTenant;
    use HasUlids;
    use ImmutableWhenPublished;

    protected $fillable = [
        'scoring_model_id', 'version_number', 'status',
        'content_hash', 'criteria', 'published_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'version_number' => 0,
    ];

    /** @return array<string, string|class-string> */
    protected $casts = [
        'status'         => VersionStatus::class,
        'criteria'       => 'array',
        'version_number' => 'integer',
        'published_at'   => 'datetime',
    ];

    public function versionParentColumn(): string
    {
        return 'scoring_model_id';
    }

    public function validateForPublish(): void
    {
        // Structural criterion validation happens in PublishScoringModel
        // (via CriteriaValidator) before VersionPublisher::publish is called;
        // nothing further to assert here.
    }

    /** @return BelongsTo<ScoringModel, $this> */
    public function scoringModel(): BelongsTo
    {
        return $this->belongsTo(ScoringModel::class);
    }
}
```

- [ ] **Step 1.6 — Run schema test to green**

Run: `php artisan migrate && php artisan test --filter=ScoringModelSchemaTest` → **green**.

- [ ] **Step 1.7 — Write the failing AuditAction lockstep test (update expected set)**

Open `backend/tests/Unit/Audit/AuditActionTest.php`. Add `'scoring_model.published'` to the `$expected` array:

```php
$expected = [
    'program.published',
    'cohort.opened',
    'cohort.form_bound',
    'cohort.closed',
    'application.submitted',
    'submission.scored',
    'decision.recorded',
    'decision.reopened',
    'decisions.exported',
    'stage_pipeline.published',
    'cohort.stage_pipeline_bound',
    'scoring_model.published',  // ← ADD
];
```

Run: `php artisan test --filter=AuditActionTest` → **red** (enum case missing).

- [ ] **Step 1.8 — Add AuditAction::ScoringModelPublished**

Open `backend/app/Shared/Audit/AuditAction.php`. Add the case after `CohortStagePipelineBound`:

```php
case ScoringModelPublished = 'scoring_model.published';
```

Run: `php artisan test --filter=AuditActionTest` → **green**.

- [ ] **Step 1.9 — Write failing owner-grant regression test**

Open `backend/tests/Feature/OrganizationApiTest.php`. Find the assertion block that checks `forms.manage` and add immediately after it:

```php
$this->assertContains('assessments.manage', $effectivePermissions);
```

Run: `php artisan test --filter=OrganizationApiTest` → **red** (assessments.manage not yet in catalog or grant).

- [ ] **Step 1.10 — Add assessments.manage to PermissionCatalogSeeder**

Open `backend/database/seeders/PermissionCatalogSeeder.php`. Append to the `$permissions` array:

```php
['key' => 'assessments.manage', 'description' => 'Manage scoring models and evaluations'],
```

- [ ] **Step 1.11 — Add assessments.manage to CreateOrganization owner-role grant**

Open `backend/app/Modules/Organizations/Application/CreateOrganization.php`. In the `whereIn('key', [...])` call, append `'assessments.manage'` to the list:

```php
$permissionIds = OrganizationPermission::whereIn('key', [
    'organizations.manage',
    'members.manage',
    'members.invite',
    'roles.manage',
    'programs.manage',
    'programs.publish',
    'cohorts.manage',
    'stages.manage',
    'forms.manage',
    'assessments.manage',   // ← ADD
])->pluck('id')->toArray();
```

Run: `php artisan test --filter=AuditActionTest` && `php artisan test --filter=OrganizationApiTest` → **both green**.

- [ ] **Step 1.12 — Run pint / phpstan / deptrac / full gate**

```bash
cd backend \
  && vendor/bin/pint --test \
  && vendor/bin/phpstan analyse --no-progress --memory-limit=512M \
  && vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress \
  && php artisan test --filter=ScoringModel \
  && php artisan test --filter=AuditActionTest \
  && php artisan test --filter=OrganizationApiTest
```

All green. Fix any pint/phpstan issues before continuing.

- [ ] **Step 1.13 — Commit T1**

```bash
cd backend && git add \
  database/migrations/2026_07_01_000100_create_scoring_models_table.php \
  database/migrations/2026_07_01_000200_create_scoring_model_versions_table.php \
  app/Modules/Assessments/Domain/Models/ScoringModel.php \
  app/Modules/Assessments/Domain/Models/ScoringModelVersion.php \
  app/Shared/Audit/AuditAction.php \
  tests/Unit/Audit/AuditActionTest.php \
  database/seeders/PermissionCatalogSeeder.php \
  app/Modules/Organizations/Application/CreateOrganization.php \
  tests/Feature/OrganizationApiTest.php \
  tests/Feature/Assessments/ScoringModelSchemaTest.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
Assessments Phase A — T1: migrations + models + audit case + assessments.manage perm

Tables scoring_models and scoring_model_versions mirror forms/form_versions.
AuditAction::ScoringModelPublished added; FR-052 lockstep test updated.
assessments.manage added to PermissionCatalogSeeder and CreateOrganization
owner-role grant; OrganizationApiTest regression assertion extended.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Application Services (CreateScoringModel / SaveScoringModelDraft / PublishScoringModel / ForkScoringModelDraft)

**Files:**
- Create: `backend/app/Modules/Assessments/Domain/CriteriaValidator.php`
- Create: `backend/app/Modules/Assessments/Domain/Exceptions/InvalidCriteriaException.php`
- Create: `backend/app/Modules/Assessments/Domain/Exceptions/NoDraftException.php`
- Create: `backend/app/Modules/Assessments/Domain/Exceptions/NoCriteriaException.php`
- Create: `backend/app/Modules/Assessments/Application/CreateScoringModel.php`
- Create: `backend/app/Modules/Assessments/Application/SaveScoringModelDraft.php`
- Create: `backend/app/Modules/Assessments/Application/PublishScoringModel.php`
- Create: `backend/app/Modules/Assessments/Application/ForkScoringModelDraft.php`
- Create: `backend/tests/Feature/Assessments/ScoringModelServiceTest.php`

**Interfaces:**
- Consumes: T1 models, `App\Shared\Versioning\VersionPublisher`, `App\Shared\Audit\AuditLogger`.
- Produces: 4 application services; 1 domain validator; 3 exception classes.

**Steps:**

- [ ] **Step 2.1 — Write the failing service test**

Create `backend/tests/Feature/Assessments/ScoringModelServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Application\CreateScoringModel;
use App\Modules\Assessments\Application\ForkScoringModelDraft;
use App\Modules\Assessments\Application\PublishScoringModel;
use App\Modules\Assessments\Application\SaveScoringModelDraft;
use App\Modules\Assessments\Domain\Exceptions\InvalidCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoDraftException;
use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Versioning\VersionStateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScoringModelServiceTest extends TestCase
{
    use RefreshDatabase;

    private function validCriteria(): array
    {
        return [
            [
                'criterion_id' => 'c1',
                'label'        => 'Innovation',
                'max_points'   => 30,
                'descriptors'  => ['Highly innovative', 'Somewhat innovative', 'Not innovative'],
            ],
            [
                'criterion_id' => 'c2',
                'label'        => 'Market Fit',
                'max_points'   => 20,
                'descriptors'  => null,
            ],
        ];
    }

    private function makeModel(): ScoringModel
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();

        return $this->app->make(CreateScoringModel::class)->handle($program, 'Evaluation Model');
    }

    // ── CreateScoringModel ─────────────────────────────────────────────

    public function test_create_scoring_model_creates_model_and_empty_draft(): void
    {
        $model = $this->makeModel();

        $this->assertSame('Evaluation Model', $model->name);
        $this->assertNull($model->current_published_version_id);
        $this->assertNotNull($model->draftVersion());
        $this->assertSame([], $model->draftVersion()->criteria);
        $this->assertSame(0, $model->draftVersion()->version_number);
        $this->assertSame('draft', $model->draftVersion()->status->value);
    }

    // ── SaveScoringModelDraft ──────────────────────────────────────────

    public function test_save_draft_stores_criteria_on_the_draft(): void
    {
        $model = $this->makeModel();

        $draft = $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());

        $this->assertSame('draft', $draft->status->value);
        $this->assertCount(2, $draft->criteria);
        $this->assertSame('Innovation', $draft->criteria[0]['label']);
    }

    public function test_save_draft_throws_noDraftException_when_no_draft_exists(): void
    {
        $model = $this->makeModel();
        // promote the only draft to published state via direct update (bypassing ImmutableWhenPublished)
        ScoringModelVersion::withoutGlobalScopes()
            ->where('scoring_model_id', $model->id)
            ->update(['status' => 'published', 'version_number' => 1, 'content_hash' => str_repeat('a', 64), 'published_at' => now()]);

        $this->expectException(NoDraftException::class);
        $this->app->make(SaveScoringModelDraft::class)->handle($model->refresh(), $this->validCriteria());
    }

    public function test_save_draft_rejects_invalid_criterion_max_points_zero(): void
    {
        $model = $this->makeModel();
        $bad = [['criterion_id' => 'c1', 'label' => 'Score', 'max_points' => 0, 'descriptors' => null]];

        $this->expectException(InvalidCriteriaException::class);
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $bad);
    }

    public function test_save_draft_rejects_criterion_without_label(): void
    {
        $model = $this->makeModel();
        $bad = [['criterion_id' => 'c1', 'label' => '', 'max_points' => 10, 'descriptors' => null]];

        $this->expectException(InvalidCriteriaException::class);
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $bad);
    }

    // ── PublishScoringModel ────────────────────────────────────────────

    public function test_publish_promotes_draft_to_immutable_published_version(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());

        $version = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $this->assertSame('published', $version->status->value);
        $this->assertSame(1, $version->version_number);
        $this->assertNotNull($version->published_at);
        $this->assertSame(64, strlen((string) $version->content_hash));
        $this->assertSame($version->id, $model->fresh()->current_published_version_id);
        $this->assertNull($model->fresh()->draftVersion());

        $this->expectException(VersionStateException::class);
        $version->update(['criteria' => []]);
    }

    public function test_publish_throws_noDraftException_when_no_draft_exists(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $this->app->make(PublishScoringModel::class)->handle($model->refresh()); // consumes draft

        $this->expectException(NoDraftException::class);
        $this->app->make(PublishScoringModel::class)->handle($model->refresh());
    }

    public function test_publish_throws_noCriteriaException_when_draft_is_empty(): void
    {
        $model = $this->makeModel(); // draft has criteria = []

        $this->expectException(NoCriteriaException::class);
        $this->app->make(PublishScoringModel::class)->handle($model);
    }

    public function test_identical_republish_is_idempotent(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $v1 = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        // fork same content, republish → same version row
        $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $v1->id);
        $v2 = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $this->assertSame($v1->id, $v2->id);
        $this->assertDatabaseCount('scoring_model_versions', 1);
    }

    public function test_fork_then_different_criteria_publish_creates_version_2(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $v1 = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $v1->id);

        $different = [['criterion_id' => 'c99', 'label' => 'Changed', 'max_points' => 50, 'descriptors' => null]];
        $this->app->make(SaveScoringModelDraft::class)->handle($model->refresh(), $different);

        $v2 = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $this->assertSame(2, $v2->version_number);
        $this->assertNotSame($v1->id, $v2->id);
        $this->assertNotNull(ScoringModelVersion::find($v1->id));
        $this->assertDatabaseCount('scoring_model_versions', 2);
    }

    // ── ForkScoringModelDraft ──────────────────────────────────────────

    public function test_fork_creates_new_draft_seeded_from_published_version(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $published = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $draft = $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $published->id);

        $this->assertSame('draft', $draft->status->value);
        $this->assertCount(2, $draft->criteria);
        $this->assertSame('Innovation', $draft->criteria[0]['label']);
        $this->assertSame(2, ScoringModelVersion::where('scoring_model_id', $model->id)->count());
    }

    public function test_fork_with_non_published_version_throws(): void
    {
        $model = $this->makeModel();
        $draft = $model->draftVersion();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->app->make(ForkScoringModelDraft::class)->handle($model, $draft->id);
    }

    public function test_fork_with_existing_draft_returns_existing_draft(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $published = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $draft1 = $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $published->id);
        $draft2 = $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $published->id);

        $this->assertSame($draft1->id, $draft2->id);
        $this->assertSame(1, ScoringModelVersion::where('scoring_model_id', $model->id)->where('status', 'draft')->count());
    }
}
```

Run: `php artisan test --filter=ScoringModelServiceTest` → **red** (classes do not exist).

- [ ] **Step 2.2 — Create exception classes**

`backend/app/Modules/Assessments/Domain/Exceptions/InvalidCriteriaException.php`:
```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Domain\Exceptions;

use RuntimeException;

final class InvalidCriteriaException extends RuntimeException {}
```

`backend/app/Modules/Assessments/Domain/Exceptions/NoDraftException.php`:
```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Domain\Exceptions;

use RuntimeException;

final class NoDraftException extends RuntimeException {}
```

`backend/app/Modules/Assessments/Domain/Exceptions/NoCriteriaException.php`:
```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Domain\Exceptions;

use RuntimeException;

final class NoCriteriaException extends RuntimeException {}
```

- [ ] **Step 2.3 — Create CriteriaValidator**

Create `backend/app/Modules/Assessments/Domain/CriteriaValidator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Domain;

use App\Modules\Assessments\Domain\Exceptions\InvalidCriteriaException;

/**
 * Validates that criteria are structural data only — no code injection.
 * Each criterion: criterion_id (string, non-empty), label (string, non-empty),
 * max_points (numeric, > 0), descriptors (array<string>|null).
 * Also provides canonical JSON for content-addressed versioning.
 */
final class CriteriaValidator
{
    /**
     * @param  array<int, mixed>  $criteria
     *
     * @throws InvalidCriteriaException
     */
    public function validate(array $criteria): void
    {
        foreach ($criteria as $index => $criterion) {
            if (! is_array($criterion)) {
                throw new InvalidCriteriaException("Criterion at index {$index} must be an object.");
            }

            $id = $criterion['criterion_id'] ?? '';
            if (! is_string($id) || trim($id) === '') {
                throw new InvalidCriteriaException("Criterion at index {$index} requires a non-empty criterion_id string.");
            }

            $label = $criterion['label'] ?? '';
            if (! is_string($label) || trim($label) === '') {
                throw new InvalidCriteriaException("Criterion at index {$index} requires a non-empty label string.");
            }

            $maxPoints = $criterion['max_points'] ?? null;
            if (! is_numeric($maxPoints) || (float) $maxPoints <= 0) {
                throw new InvalidCriteriaException("Criterion at index {$index}: max_points must be a positive number.");
            }

            $descriptors = $criterion['descriptors'] ?? null;
            if ($descriptors !== null) {
                if (! is_array($descriptors)) {
                    throw new InvalidCriteriaException("Criterion at index {$index}: descriptors must be an array or null.");
                }
                foreach ($descriptors as $d) {
                    if (! is_string($d)) {
                        throw new InvalidCriteriaException("Criterion at index {$index}: each descriptor must be a string.");
                    }
                }
            }
        }
    }

    /**
     * Stable canonical serialization: recursively key-sorted JSON.
     * Used to compute content_hash for content-addressed publish idempotency.
     *
     * @param  array<array-key, mixed>  $criteria
     */
    public function canonicalJson(array $criteria): string
    {
        $sorted = $this->ksortRecursive($criteria);

        return json_encode($sorted, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function ksortRecursive(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->ksortRecursive($v);
            }
        }

        return $value;
    }
}
```

- [ ] **Step 2.4 — Create CreateScoringModel service**

Create `backend/app/Modules/Assessments/Application/CreateScoringModel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Application;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Support\Facades\DB;

/**
 * Creates a program-scoped scoring model and seeds its single empty draft
 * version. The empty draft is valid until publish (criteria can be added
 * incrementally via SaveScoringModelDraft).
 */
final class CreateScoringModel
{
    public function handle(Program $program, string $name): ScoringModel
    {
        return DB::transaction(function () use ($program, $name): ScoringModel {
            $model = ScoringModel::create(['program_id' => $program->id, 'name' => $name]);
            ScoringModelVersion::create([
                'scoring_model_id' => $model->id,
                'status'           => 'draft',
                'version_number'   => 0,
                'criteria'         => [],
            ]);

            return $model->load('versions');
        });
    }
}
```

- [ ] **Step 2.5 — Create SaveScoringModelDraft service**

Create `backend/app/Modules/Assessments/Application/SaveScoringModelDraft.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Application;

use App\Modules\Assessments\Domain\CriteriaValidator;
use App\Modules\Assessments\Domain\Exceptions\InvalidCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoDraftException;
use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;

final class SaveScoringModelDraft
{
    public function __construct(private readonly CriteriaValidator $validator) {}

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     *
     * @throws NoDraftException when the model has no draft version
     * @throws InvalidCriteriaException when any criterion is structurally invalid
     */
    public function handle(ScoringModel $model, array $criteria): ScoringModelVersion
    {
        /** @var ScoringModelVersion|null $draft */
        $draft = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->where('status', 'draft')
            ->first();

        if ($draft === null) {
            throw new NoDraftException('This scoring model has no draft version to edit.');
        }

        if ($criteria !== []) {
            $this->validator->validate($criteria);
        }

        $draft->criteria = $criteria;
        $draft->save();

        return $draft;
    }
}
```

- [ ] **Step 2.6 — Create PublishScoringModel service**

Create `backend/app/Modules/Assessments/Application/PublishScoringModel.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Application;

use App\Modules\Assessments\Domain\CriteriaValidator;
use App\Modules\Assessments\Domain\Exceptions\NoCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoDraftException;
use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Versioning\VersionPublisher;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the scoring model's single draft version as an immutable,
 * content-addressed version. Republishing criteria identical to an existing
 * published version returns that version and discards the redundant draft
 * (idempotent — no duplicate row, no UNIQUE collision).
 */
final class PublishScoringModel
{
    public function __construct(
        private readonly CriteriaValidator $validator,
        private readonly VersionPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws NoDraftException     when there is no draft version (→ 409)
     * @throws NoCriteriaException  when the draft has no criteria (→ 422)
     */
    public function handle(ScoringModel $model): ScoringModelVersion
    {
        /** @var ScoringModelVersion|null $draft */
        $draft = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->where('status', 'draft')
            ->first();

        if ($draft === null) {
            throw new NoDraftException('This scoring model has no draft to publish.');
        }

        if ($draft->criteria === [] || $draft->criteria === null) {
            throw new NoCriteriaException('A scoring model must have at least one criterion before it can be published.');
        }

        $hash = hash('sha256', $this->validator->canonicalJson($draft->criteria));

        $version = DB::transaction(function () use ($model, $draft, $hash): ScoringModelVersion {
            /** @var ScoringModelVersion|null $existing */
            $existing = ScoringModelVersion::query()
                ->where('scoring_model_id', $model->id)
                ->where('status', 'published')
                ->where('content_hash', $hash)
                ->first();

            if ($existing !== null) {
                $draft->delete();  // discard redundant draft (avoids UNIQUE collision)
                $model->update(['current_published_version_id' => $existing->id]);

                return $existing;
            }

            $draft->content_hash = $hash; // still draft — mutation allowed
            $draft->save();
            $this->publisher->publish($draft); // sets version_number, Published, published_at
            $model->update(['current_published_version_id' => $draft->id]);

            return $draft->refresh();
        });

        $this->audit->record(
            AuditAction::ScoringModelPublished->value,
            'scoring_model_version',
            $version->id,
            [],
            ['content_hash' => $hash, 'version_number' => $version->version_number],
        );

        return $version;
    }
}
```

- [ ] **Step 2.7 — Create ForkScoringModelDraft service**

Create `backend/app/Modules/Assessments/Application/ForkScoringModelDraft.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Application;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ForkScoringModelDraft
{
    /**
     * @throws ModelNotFoundException when $fromVersionId is not a published
     *                                version of $model (→ 404)
     */
    public function handle(ScoringModel $model, string $fromVersionId): ScoringModelVersion
    {
        // Validate source FIRST — even when a draft exists — so invalid
        // version ids still return 404 rather than silently succeeding.
        /** @var ScoringModelVersion $source */
        $source = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->where('status', 'published')
            ->findOrFail($fromVersionId);

        // Invariant: at most one draft per scoring model. Return existing unchanged.
        /** @var ScoringModelVersion|null $existingDraft */
        $existingDraft = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->where('status', 'draft')
            ->first();

        if ($existingDraft !== null) {
            return $existingDraft;
        }

        return ScoringModelVersion::create([
            'scoring_model_id' => $model->id,
            'criteria'         => json_decode(json_encode($source->criteria), true), // deep copy
        ]);
    }
}
```

- [ ] **Step 2.8 — Run service tests to green**

Run: `php artisan test --filter=ScoringModelServiceTest` → **green**.

- [ ] **Step 2.9 — Run full per-task gate; fix any pint/phpstan/deptrac issues**

- [ ] **Step 2.10 — Commit T2**

```bash
cd backend && git add \
  app/Modules/Assessments/Domain/CriteriaValidator.php \
  app/Modules/Assessments/Domain/Exceptions/InvalidCriteriaException.php \
  app/Modules/Assessments/Domain/Exceptions/NoDraftException.php \
  app/Modules/Assessments/Domain/Exceptions/NoCriteriaException.php \
  app/Modules/Assessments/Application/CreateScoringModel.php \
  app/Modules/Assessments/Application/SaveScoringModelDraft.php \
  app/Modules/Assessments/Application/PublishScoringModel.php \
  app/Modules/Assessments/Application/ForkScoringModelDraft.php \
  tests/Feature/Assessments/ScoringModelServiceTest.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
Assessments Phase A — T2: application services (Create/SaveDraft/Publish/Fork)

CriteriaValidator (structural validation + canonicalJson), 3 exception classes,
4 application services mirroring the Forms authoring slice. Content-hash
idempotent publish, at-most-one-draft invariant enforced by ForkScoringModelDraft.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Controllers + Resources + Requests + Routes + Policy

**Files:**
- Create: `backend/app/Modules/Assessments/Http/Requests/StoreScoringModelRequest.php`
- Create: `backend/app/Modules/Assessments/Http/Requests/SaveScoringModelDraftRequest.php`
- Create: `backend/app/Modules/Assessments/Http/Requests/ForkScoringModelDraftRequest.php`
- Create: `backend/app/Modules/Assessments/Http/Resources/ScoringModelResource.php`
- Create: `backend/app/Modules/Assessments/Http/Resources/ScoringModelVersionResource.php`
- Create: `backend/app/Modules/Assessments/Policies/ScoringModelPolicy.php`
- Create: `backend/app/Modules/Assessments/Http/ScoringModelController.php`
- Create: `backend/app/Modules/Assessments/Http/ScoringModelVersionController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Providers/AppServiceProvider.php` (register ScoringModelPolicy)
- Create: `backend/tests/Feature/Assessments/ScoringModelAuthoringTest.php`
- Create: `backend/tests/Feature/Assessments/ScoringModelPolicyTest.php`
- Create: `backend/tests/Feature/Assessments/ScoringModelResourceTest.php`

**Interfaces:**
- Consumes: T1 models, T2 services + exceptions; `App\Modules\Programs\Domain\Models\Program` (route model binding); `App\Shared\Tenancy\TenantContext`.
- Produces: 8 HTTP endpoints satisfying `frontend/src/api/assessments.ts` authoring functions; `ScoringModelResource` emitting `model_id`/`program_id`/`name`/`latest_version`/`published_version_ids`/`current_draft_version_id`/`created_at`; `ScoringModelVersionResource` emitting `version_id`/`model_id`/`version`/`status`/`criteria`/`created_at`/`published_at`.

**Steps:**

- [ ] **Step 3.1 — Write the failing HTTP + resource + policy tests**

Create `backend/tests/Feature/Assessments/ScoringModelAuthoringTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScoringModelAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private function criteria(): array
    {
        return [
            ['criterion_id' => 'c1', 'label' => 'Innovation', 'max_points' => 30, 'descriptors' => null],
            ['criterion_id' => 'c2', 'label' => 'Market Fit', 'max_points' => 20, 'descriptors' => ['Strong', 'Weak']],
        ];
    }

    // ── list + create (program-nested) ────────────────────────────────

    public function test_list_scoring_models_returns_200_for_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/programs/{$program->id}/scoring-models");

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Eval', $res->json('data.0.name'));
        $this->assertArrayHasKey('model_id', $res->json('data.0'));
        $this->assertArrayHasKey('program_id', $res->json('data.0'));
    }

    public function test_create_scoring_model_returns_201_with_empty_draft(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $program = Program::factory()->create();

        $res = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/scoring-models", ['name' => 'Evaluation']);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Evaluation')
            ->assertJsonPath('data.latest_version', 0)
            ->assertJsonPath('data.published_version_ids', []);

        $this->assertNotNull($res->json('data.current_draft_version_id'));
        $this->assertSame('Evaluation', $res->json('data.name'));
        $modelId = $res->json('data.model_id');
        $this->assertDatabaseHas('scoring_models', ['id' => $modelId]);
        $this->assertDatabaseHas('scoring_model_versions', [
            'scoring_model_id' => $modelId,
            'status' => 'draft',
            'version_number' => 0,
        ]);
    }

    public function test_create_requires_name(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $program = Program::factory()->create();

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/scoring-models", ['name' => ''])
            ->assertStatus(422);
    }

    public function test_create_requires_authentication(): void
    {
        $program = Program::factory()->create();
        $this->postJson("/api/v1/programs/{$program->id}/scoring-models", ['name' => 'X'])
            ->assertStatus(401);
    }

    // ── show ──────────────────────────────────────────────────────────

    public function test_show_returns_200_with_model_id_key(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/scoring-models/{$model->id}");

        $res->assertStatus(200)
            ->assertJsonPath('data.model_id', $model->id)
            ->assertJsonPath('data.program_id', $program->id)
            ->assertJsonPath('data.name', 'Eval');
    }

    public function test_show_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Mine']);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/scoring-models/{$model->id}")
            ->assertStatus(404);
    }

    // ── versions list ─────────────────────────────────────────────────

    public function test_versions_index_lists_by_version_desc(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]); // draft

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/scoring-models/{$model->id}/versions");

        $res->assertStatus(200);
        $this->assertCount(2, $res->json('data'));
        $this->assertSame([1, 0], array_column($res->json('data'), 'version'));
        $this->assertArrayHasKey('version_id', $res->json('data.0'));
        $this->assertArrayHasKey('model_id', $res->json('data.0'));
    }

    // ── version show ──────────────────────────────────────────────────

    public function test_version_show_returns_200_with_version_id_key(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $v = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'criteria' => $this->criteria(),
        ]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/scoring-model-versions/{$v->id}");

        $res->assertStatus(200)
            ->assertJsonPath('data.version_id', $v->id)
            ->assertJsonPath('data.model_id', $model->id)
            ->assertJsonPath('data.version', 0)
            ->assertJsonPath('data.status', 'draft');

        $this->assertCount(2, $res->json('data.criteria'));
    }

    public function test_version_show_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Mine']);
        $v = ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/scoring-model-versions/{$v->id}")
            ->assertStatus(404);
    }

    // ── saveDraft ─────────────────────────────────────────────────────

    public function test_save_draft_replaces_criteria(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/scoring-models/{$model->id}/draft", ['criteria' => $this->criteria()]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');
        $this->assertCount(2, $res->json('data.criteria'));
        $this->assertSame('Innovation', $res->json('data.criteria.0.label'));
    }

    public function test_save_draft_returns_409_when_no_draft_exists(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        // fully published, no draft
        ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);

        $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/scoring-models/{$model->id}/draft", ['criteria' => $this->criteria()])
            ->assertStatus(409);
    }

    public function test_save_draft_returns_422_for_invalid_criterion(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/scoring-models/{$model->id}/draft", [
                'criteria' => [['criterion_id' => 'c1', 'label' => 'Score', 'max_points' => 0, 'descriptors' => null]],
            ])
            ->assertStatus(422);
    }

    public function test_save_draft_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Mine']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->patchJson("/api/v1/scoring-models/{$model->id}/draft", ['criteria' => []])
            ->assertStatus(404);
    }

    // ── publish ───────────────────────────────────────────────────────

    public function test_publish_promotes_draft_and_returns_200(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'criteria' => $this->criteria(),
        ]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/publish");

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 1);
    }

    public function test_publish_returns_409_when_no_draft(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/publish")
            ->assertStatus(409);
    }

    public function test_publish_returns_422_when_draft_has_no_criteria(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]); // empty

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/publish")
            ->assertStatus(422);
    }

    public function test_publish_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Mine']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => $this->criteria()]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->postJson("/api/v1/scoring-models/{$model->id}/publish")
            ->assertStatus(404);
    }

    // ── fork ──────────────────────────────────────────────────────────

    public function test_fork_creates_new_draft_from_published_version_returns_201(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $published = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/fork", ['from_version_id' => $published->id]);

        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
        $this->assertCount(2, $res->json('data.criteria'));
        $this->assertSame(2, ScoringModelVersion::where('scoring_model_id', $model->id)->count());
    }

    public function test_fork_with_existing_draft_returns_same_draft_with_200(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $published = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);

        $res1 = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/fork", ['from_version_id' => $published->id]);
        $res1->assertStatus(201);
        $draftId = $res1->json('data.version_id');

        $res2 = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/fork", ['from_version_id' => $published->id]);
        $res2->assertStatus(200)->assertJsonPath('data.version_id', $draftId);

        $this->assertSame(1, ScoringModelVersion::where('scoring_model_id', $model->id)->where('status', 'draft')->count());
    }

    public function test_fork_with_unpublished_version_returns_404(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $draft = ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/fork", ['from_version_id' => $draft->id])
            ->assertStatus(404);
    }

    // ── member without assessments.manage ────────────────────────────

    public function test_member_without_assessments_manage_cannot_create(): void
    {
        [, $org] = $this->bootUserWithOrg();
        $program = Program::factory()->create();

        $member = $this->makeAccount();
        $m = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
        $m->organization_id = $org->id;
        $m->save();

        $this->resetTenantContext();

        $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/scoring-models", ['name' => 'Blocked'])
            ->assertStatus(403);
    }
}
```

Run: `php artisan test --filter=ScoringModelAuthoringTest` → **red** (routes not registered).

- [ ] **Step 3.2 — Create the policy**

Create `backend/app/Modules/Assessments/Policies/ScoringModelPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Policies;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for ScoringModel.
 *
 * viewAny/view: any authenticated tenant member may read scoring models.
 *   BelongsToTenant global scope + ResolveTenant middleware ensure only the
 *   correct tenant's records are visible; no extra permission required.
 *
 * create/update/publish: require `assessments.manage` permission.
 * Deny-by-default for everything else.
 */
final class ScoringModelPolicy
{
    public function viewAny(Account $user): bool
    {
        return true;
    }

    public function view(Account $user, ScoringModel $model): bool
    {
        return true;
    }

    public function create(Account $user): bool
    {
        return app(TenantContext::class)->can('assessments.manage');
    }

    public function update(Account $user, ScoringModel $model): bool
    {
        return app(TenantContext::class)->can('assessments.manage');
    }

    public function publish(Account $user, ScoringModel $model): bool
    {
        return app(TenantContext::class)->can('assessments.manage');
    }
}
```

- [ ] **Step 3.3 — Register policy in AppServiceProvider**

Open `backend/app/Providers/AppServiceProvider.php`. In the `$policies` array (or `boot()` with `Gate::policy()`), add:

```php
\App\Modules\Assessments\Domain\Models\ScoringModel::class =>
    \App\Modules\Assessments\Policies\ScoringModelPolicy::class,
```

- [ ] **Step 3.4 — Create request classes**

`backend/app/Modules/Assessments/Http/Requests/StoreScoringModelRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreScoringModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize('create', ScoringModel::class)
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
```

`backend/app/Modules/Assessments/Http/Requests/SaveScoringModelDraftRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveScoringModelDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes 'update'
    }

    /**
     * Structural rules only — exact-shape validation (max_points > 0, label required)
     * happens in CriteriaValidator inside the service. Controller reads criteria via
     * input('criteria', []) to avoid nested-key stripping by validated().
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'criteria'                 => ['present', 'array'],
            'criteria.*.criterion_id'  => ['required', 'string'],
            'criteria.*.label'         => ['required', 'string'],
            'criteria.*.max_points'    => ['required', 'numeric', 'gt:0'],
            'criteria.*.descriptors'   => ['nullable', 'array'],
            'criteria.*.descriptors.*' => ['string'],
        ];
    }
}
```

`backend/app/Modules/Assessments/Http/Requests/ForkScoringModelDraftRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForkScoringModelDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['from_version_id' => ['required', 'string']];
    }
}
```

- [ ] **Step 3.5 — Create resource classes**

`backend/app/Modules/Assessments/Http/Resources/ScoringModelResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Resources;

use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $program_id
 * @property string $name
 * @property Collection<int, ScoringModelVersion> $versions
 * @property \Illuminate\Support\Carbon $created_at
 */
final class ScoringModelResource extends JsonResource
{
    /**
     * @return array{
     *     model_id: string,
     *     program_id: string,
     *     name: string,
     *     latest_version: int,
     *     published_version_ids: list<string>,
     *     current_draft_version_id: string|null,
     *     created_at: string,
     * }
     */
    public function toArray(Request $request): array
    {
        $versions = $this->versions; // requires ->load('versions')
        $published = $versions
            ->filter(fn (ScoringModelVersion $v) => $v->status->value === 'published')
            ->sortBy('version_number')
            ->values();
        $draft = $versions->first(fn (ScoringModelVersion $v) => $v->status->value === 'draft');

        return [
            'model_id'                => $this->id,
            'program_id'              => $this->program_id,
            'name'                    => $this->name,
            'latest_version'          => (int) ($published->max('version_number') ?? 0),
            'published_version_ids'   => array_values(
                $published->pluck('id')->map(fn (mixed $id) => (string) $id)->all()
            ),
            'current_draft_version_id' => $draft?->id,
            'created_at'              => $this->created_at->toIso8601String(),
        ];
    }
}
```

`backend/app/Modules/Assessments/Http/Resources/ScoringModelVersionResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Resources;

use App\Shared\Versioning\VersionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $scoring_model_id
 * @property int $version_number
 * @property VersionStatus $status
 * @property array<int, array<string, mixed>> $criteria
 * @property Carbon $created_at
 * @property Carbon|null $published_at
 */
final class ScoringModelVersionResource extends JsonResource
{
    /**
     * @return array{
     *     version_id: string,
     *     model_id: string,
     *     version: int,
     *     status: string,
     *     criteria: list<array<string, mixed>>,
     *     created_at: string,
     *     published_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'version_id'  => $this->id,
            'model_id'    => $this->scoring_model_id,
            'version'     => (int) $this->version_number,
            'status'      => $this->status->value,
            'criteria'    => array_values((array) ($this->criteria ?? [])),
            'created_at'  => $this->created_at->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 3.6 — Create ScoringModelVersionController**

Create `backend/app/Modules/Assessments/Http/ScoringModelVersionController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http;

use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Assessments\Http\Resources\ScoringModelVersionResource;
use Illuminate\Routing\Controller;

final class ScoringModelVersionController extends Controller
{
    public function show(string $id): ScoringModelVersionResource
    {
        $version = ScoringModelVersion::query()->findOrFail($id);

        return new ScoringModelVersionResource($version);
    }
}
```

- [ ] **Step 3.7 — Create ScoringModelController**

Create `backend/app/Modules/Assessments/Http/ScoringModelController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http;

use App\Modules\Assessments\Application\CreateScoringModel;
use App\Modules\Assessments\Application\ForkScoringModelDraft;
use App\Modules\Assessments\Application\PublishScoringModel;
use App\Modules\Assessments\Application\SaveScoringModelDraft;
use App\Modules\Assessments\Domain\Exceptions\InvalidCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoDraftException;
use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Assessments\Http\Requests\ForkScoringModelDraftRequest;
use App\Modules\Assessments\Http\Requests\SaveScoringModelDraftRequest;
use App\Modules\Assessments\Http\Requests\StoreScoringModelRequest;
use App\Modules\Assessments\Http\Resources\ScoringModelResource;
use App\Modules\Assessments\Http\Resources\ScoringModelVersionResource;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class ScoringModelController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /programs/{program}/scoring-models
     * Lists all scoring models for the given program (tenant-scoped by BelongsToTenant).
     */
    public function index(Program $program): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ScoringModel::class);

        $models = ScoringModel::query()
            ->where('program_id', $program->id)
            ->with('versions')
            ->orderByDesc('created_at')
            ->get();

        return ScoringModelResource::collection($models);
    }

    /**
     * POST /programs/{program}/scoring-models
     * Creates a scoring model for the program + seeds an empty draft version.
     */
    public function store(
        StoreScoringModelRequest $request,
        CreateScoringModel $service,
        Program $program,
    ): JsonResponse {
        $this->authorize('create', ScoringModel::class);

        /** @var array{name: string} $data */
        $data = $request->validated();
        $model = $service->handle($program, $data['name']);

        return (new ScoringModelResource($model))->response()->setStatusCode(201);
    }

    /**
     * GET /scoring-models/{id}
     */
    public function show(string $id): ScoringModelResource
    {
        $model = ScoringModel::query()->with('versions')->findOrFail($id);
        $this->authorize('view', $model);

        return new ScoringModelResource($model);
    }

    /**
     * GET /scoring-models/{id}/versions
     */
    public function versions(string $id): AnonymousResourceCollection
    {
        $model = ScoringModel::query()->findOrFail($id);
        $this->authorize('view', $model);

        $versions = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->orderByDesc('version_number')
            ->get();

        return ScoringModelVersionResource::collection($versions);
    }

    /**
     * PATCH /scoring-models/{id}/draft
     * Upserts criteria onto the current draft version.
     * Reads criteria via input() NOT validated() to avoid nested-key stripping.
     */
    public function saveDraft(
        SaveScoringModelDraftRequest $request,
        SaveScoringModelDraft $service,
        string $id,
    ): JsonResponse {
        $model = ScoringModel::query()->findOrFail($id);
        $this->authorize('update', $model);

        /** @var array<int, array<string, mixed>> $criteria */
        $criteria = $request->input('criteria', []);

        try {
            $version = $service->handle($model, $criteria);
        } catch (NoDraftException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (InvalidCriteriaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => ['criteria' => [$e->getMessage()]],
            ], 422);
        }

        return (new ScoringModelVersionResource($version))->response()->setStatusCode(200);
    }

    /**
     * POST /scoring-models/{id}/publish
     * Publishes the draft (idempotent; content-hash deduplicates).
     * 409 → no draft; 422 → draft has no criteria.
     */
    public function publish(PublishScoringModel $service, string $id): JsonResponse
    {
        $model = ScoringModel::query()->findOrFail($id);
        $this->authorize('publish', $model);

        try {
            $version = $service->handle($model);
        } catch (NoDraftException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (NoCriteriaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => ['criteria' => [$e->getMessage()]],
            ], 422);
        }

        return (new ScoringModelVersionResource($version))->response()->setStatusCode(200);
    }

    /**
     * POST /scoring-models/{id}/fork
     * Creates a new draft seeded from a published version.
     * Returns 201 for a new draft; 200 for an existing draft returned unchanged.
     * ModelNotFoundException (invalid/unpublished from_version_id) → 404.
     */
    public function fork(
        ForkScoringModelDraftRequest $request,
        ForkScoringModelDraft $service,
        string $id,
    ): JsonResponse {
        $model = ScoringModel::query()->findOrFail($id);
        $this->authorize('update', $model);

        /** @var array{from_version_id: string} $data */
        $data = $request->validated();

        $isNew = $model->draftVersion() === null;
        $draft = $service->handle($model, $data['from_version_id']); // ModelNotFoundException → 404

        $status = $isNew ? 201 : 200;

        return (new ScoringModelVersionResource($draft))->response()->setStatusCode($status);
    }
}
```

- [ ] **Step 3.8 — Register routes in api.php**

Open `backend/routes/api.php`. Add the controller imports at the top (with the other use statements):

```php
use App\Modules\Assessments\Http\ScoringModelController;
use App\Modules\Assessments\Http\ScoringModelVersionController;
```

Inside the `Route::middleware(['auth:sanctum', 'tenant'])->group(...)` block, append after the forms block:

```php
// Scoring-model authoring (Assessments Phase A — per-program) — ADR-0012 Phase A
// Program-nested create + list (program_id is authoritative from the route).
Route::get('/programs/{program}/scoring-models', [ScoringModelController::class, 'index'])->name('programs.scoring-models.index');
Route::post('/programs/{program}/scoring-models', [ScoringModelController::class, 'store'])->name('programs.scoring-models.store');
// More-specific /scoring-models/{id}/versions before catch-all {id} — same ordering rule as forms.
Route::get('/scoring-models/{id}/versions', [ScoringModelController::class, 'versions'])->name('scoring-models.versions.index');
Route::patch('/scoring-models/{id}/draft', [ScoringModelController::class, 'saveDraft'])->name('scoring-models.draft.update');
Route::post('/scoring-models/{id}/publish', [ScoringModelController::class, 'publish'])->name('scoring-models.publish');
Route::post('/scoring-models/{id}/fork', [ScoringModelController::class, 'fork'])->name('scoring-models.fork');
Route::get('/scoring-models/{id}', [ScoringModelController::class, 'show'])->name('scoring-models.show');
Route::get('/scoring-model-versions/{id}', [ScoringModelVersionController::class, 'show'])->name('scoring-model-versions.show');
```

- [ ] **Step 3.9 — Write and run the policy unit test**

Create `backend/tests/Feature/Assessments/ScoringModelPolicyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Tenancy\Contracts\TenantMembership;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class ScoringModelPolicyTest extends TestCase
{
    private function makeContext(string ...$permKeys): TenantContext
    {
        $membership = new class('org-001', $permKeys) implements TenantMembership
        {
            /** @param array<int,string> $keys */
            public function __construct(
                private readonly string $orgId,
                private readonly array $keys,
            ) {}

            public function organizationId(): string { return $this->orgId; }

            /** @return array<int,string> */
            public function effectivePermissionKeys(): array { return $this->keys; }
        };

        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $ctx->setOrganization('org-001', $membership, $permKeys);

        return $ctx;
    }

    private function makeUser(): Account
    {
        return new Account(['id' => 'user-001']);
    }

    public function test_member_with_assessments_manage_may_create(): void
    {
        $this->makeContext('assessments.manage');
        $this->assertTrue(Gate::forUser($this->makeUser())->allows('create', ScoringModel::class));
    }

    public function test_member_without_assessments_manage_may_not_create(): void
    {
        $this->makeContext();
        $this->assertFalse(Gate::forUser($this->makeUser())->allows('create', ScoringModel::class));
    }

    public function test_any_member_may_view_any(): void
    {
        $this->makeContext();
        $this->assertTrue(Gate::forUser($this->makeUser())->allows('viewAny', ScoringModel::class));
    }

    public function test_member_without_assessments_manage_may_not_update(): void
    {
        $this->makeContext();
        $model = new ScoringModel(['id' => 'sm-001']);
        $this->assertFalse(Gate::forUser($this->makeUser())->allows('update', $model));
    }

    public function test_member_without_assessments_manage_may_not_publish(): void
    {
        $this->makeContext();
        $model = new ScoringModel(['id' => 'sm-001']);
        $this->assertFalse(Gate::forUser($this->makeUser())->allows('publish', $model));
    }
}
```

- [ ] **Step 3.10 — Write and run the resource shape test**

Create `backend/tests/Feature/Assessments/ScoringModelResourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Assessments\Http\Resources\ScoringModelResource;
use App\Modules\Assessments\Http\Resources\ScoringModelVersionResource;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ScoringModelResourceTest extends TestCase
{
    use RefreshDatabase;

    private function criteria(): array
    {
        return [
            ['criterion_id' => 'c1', 'label' => 'Innovation', 'max_points' => 30, 'descriptors' => null],
        ];
    }

    public function test_version_resource_emits_version_id_and_model_id(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $draft = ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => $this->criteria()]);

        $out = (new ScoringModelVersionResource($draft))->toArray(Request::create('/'));

        $this->assertSame($draft->id, $out['version_id']);
        $this->assertSame($model->id, $out['model_id']);
        $this->assertSame(0, $out['version']);
        $this->assertSame('draft', $out['status']);
        $this->assertSame($this->criteria(), $out['criteria']);
        $this->assertNull($out['published_at']);
        $this->assertStringContainsString('T', $out['created_at']);
        $this->assertArrayNotHasKey('id', $out);
        $this->assertArrayNotHasKey('form_id', $out);
    }

    public function test_model_resource_emits_model_id_program_id_and_derived_fields(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);

        $v3 = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 3, 'content_hash' => str_repeat('c', 64),
            'criteria' => [], 'published_at' => now(),
        ]);
        $v1 = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => [], 'published_at' => now(),
        ]);
        $v2 = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 2, 'content_hash' => str_repeat('b', 64),
            'criteria' => [], 'published_at' => now(),
        ]);
        $draft = ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        $out = (new ScoringModelResource($model->load('versions')))->toArray(Request::create('/'));

        $this->assertSame($model->id, $out['model_id']);
        $this->assertSame($program->id, $out['program_id']);
        $this->assertSame('Eval', $out['name']);
        $this->assertSame(3, $out['latest_version']);
        $this->assertSame(
            [$v1->id, $v2->id, $v3->id],
            $out['published_version_ids'],
            'published_version_ids must be ordered by version_number ascending'
        );
        $this->assertSame($draft->id, $out['current_draft_version_id']);
        $this->assertStringContainsString('T', $out['created_at']);
        $this->assertArrayNotHasKey('id', $out);
    }
}
```

- [ ] **Step 3.11 — Run all three new test classes**

```bash
cd backend && php artisan test --filter=ScoringModelAuthoringTest \
  && php artisan test --filter=ScoringModelPolicyTest \
  && php artisan test --filter=ScoringModelResourceTest
```

All green. Fix any failures before continuing.

- [ ] **Step 3.12 — Run full per-task gate**

```bash
cd backend && vendor/bin/pint --test \
  && vendor/bin/phpstan analyse --no-progress --memory-limit=512M \
  && vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress \
  && php artisan test --filter=ScoringModel
```

- [ ] **Step 3.13 — Commit T3**

```bash
cd backend && git add \
  app/Modules/Assessments/Http/Requests/StoreScoringModelRequest.php \
  app/Modules/Assessments/Http/Requests/SaveScoringModelDraftRequest.php \
  app/Modules/Assessments/Http/Requests/ForkScoringModelDraftRequest.php \
  app/Modules/Assessments/Http/Resources/ScoringModelResource.php \
  app/Modules/Assessments/Http/Resources/ScoringModelVersionResource.php \
  app/Modules/Assessments/Policies/ScoringModelPolicy.php \
  app/Modules/Assessments/Http/ScoringModelController.php \
  app/Modules/Assessments/Http/ScoringModelVersionController.php \
  app/Providers/AppServiceProvider.php \
  routes/api.php \
  tests/Feature/Assessments/ScoringModelAuthoringTest.php \
  tests/Feature/Assessments/ScoringModelPolicyTest.php \
  tests/Feature/Assessments/ScoringModelResourceTest.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
Assessments Phase A — T3: HTTP layer (controllers + resources + routes + policy)

8 endpoints satisfying frontend/src/api/assessments.ts authoring contract.
ScoringModelResource emits model_id/program_id; ScoringModelVersionResource
emits version_id/model_id. ScoringModelPolicy gates on assessments.manage.
Routes nested under /programs/{program} for list+create; flat by id for rest.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: OpenAPI regen + full sweep

**Files:**
- Modify: `backend/openapi/openapi.json` (regenerated by Scramble)

**Interfaces:**
- Consumes: all T3 routes + resources.
- Produces: updated `openapi/openapi.json` with 8 new scoring-model paths; `OpenApiSpecTest` passes; `spectral lint` 0 errors.

**Steps:**

- [ ] **Step 4.1 — Regenerate the OpenAPI baseline**

```bash
cd backend && php artisan scramble:export
```

Verify the output includes the 8 new paths:
- `/api/v1/programs/{program}/scoring-models` (GET, POST)
- `/api/v1/scoring-models/{id}` (GET)
- `/api/v1/scoring-models/{id}/versions` (GET)
- `/api/v1/scoring-models/{id}/draft` (PATCH)
- `/api/v1/scoring-models/{id}/publish` (POST)
- `/api/v1/scoring-models/{id}/fork` (POST)
- `/api/v1/scoring-model-versions/{id}` (GET)

- [ ] **Step 4.2 — Run OpenApiSpecTest**

```bash
cd backend && php artisan test --filter=OpenApiSpecTest
```

Should pass (baseline is now in sync). If it fails, re-run `php artisan scramble:export`.

- [ ] **Step 4.3 — Run Spectral (0 errors)**

If Spectral is available (check `package.json` or `npx spectral`):

```bash
cd backend && npx @stoplight/spectral-cli lint openapi/openapi.json --ruleset .spectral.yaml
```

Fix any validation errors before continuing. If Spectral is not installed in this project, note as `Not verified`.

- [ ] **Step 4.4 — Run the complete backend gate**

```bash
cd backend \
  && vendor/bin/pint --test \
  && vendor/bin/phpstan analyse --no-progress --memory-limit=512M \
  && vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress \
  && php artisan test --filter=ScoringModel \
  && php artisan test --filter=AuditActionTest \
  && php artisan test --filter=OrganizationApiTest \
  && php artisan test --filter=OpenApiSpecTest
```

All green before commit.

- [ ] **Step 4.5 — Commit T4**

```bash
cd backend && git add openapi/openapi.json
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
Assessments Phase A — T4: regenerate OpenAPI baseline

Scramble-exported openapi.json now includes all 8 scoring-model authoring
paths. OpenApiSpecTest and Spectral pass with 0 errors.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review

### Spec Coverage Map

| Spec Section | Requirement | Task | Verified By |
|---|---|---|---|
| §4 scoring_models table | id ULID, organization_id, program_id, name, current_published_version_id nullable | T1 | ScoringModelSchemaTest |
| §4 scoring_model_versions table | id ULID, organization_id, scoring_model_id, version_number, status, content_hash nullable, criteria jsonb, published_at; UNIQUE(scoring_model_id, content_hash) | T1 | ScoringModelSchemaTest; migration |
| §4 Versionable + ImmutableWhenPublished + BelongsToTenant | ScoringModelVersion implements all three | T1 | ScoringModelServiceTest::test_publish_promotes_draft_to_immutable (VersionStateException) |
| §5 CreateScoringModel | creates model + empty draft | T2 | ScoringModelServiceTest |
| §5 SaveScoringModelDraft | upserts criteria; 409 no draft; validates criteria | T2 | ScoringModelServiceTest |
| §5 PublishScoringModel | content-hash idempotent; audits scoring_model.published | T2 | ScoringModelServiceTest; AuditActionTest (T1) |
| §5 ForkScoringModelDraft | new draft from published; at-most-one-draft invariant | T2 | ScoringModelServiceTest |
| §6 GET /programs/{program}/scoring-models | 200 | T3 | ScoringModelAuthoringTest |
| §6 POST /programs/{program}/scoring-models → 201 | 201 with empty draft | T3 | ScoringModelAuthoringTest |
| §6 GET /scoring-models/{id} | 200/404 | T3 | ScoringModelAuthoringTest |
| §6 GET /scoring-models/{id}/versions | 200 | T3 | ScoringModelAuthoringTest |
| §6 GET /scoring-model-versions/{id} | 200/404 | T3 | ScoringModelAuthoringTest |
| §6 PATCH /scoring-models/{id}/draft → 200/404/409 | covered | T3 | ScoringModelAuthoringTest |
| §6 POST /scoring-models/{id}/publish → 200/404/409 | 409 for no draft; 422 for no criteria (see imperfect mappings) | T3 | ScoringModelAuthoringTest |
| §6 POST /scoring-models/{id}/fork → 200/201 | 201 new / 200 existing | T3 | ScoringModelAuthoringTest |
| §7 criterion validation | criterion_id/label required; max_points > 0; descriptors array<string>|null | T2+T3 | ScoringModelServiceTest; ScoringModelAuthoringTest |
| §7 publish ≥1 criterion | NoCriteriaException → 422 | T2+T3 | ScoringModelServiceTest + ScoringModelAuthoringTest |
| §7 input('criteria', []) not validated() | controller reads via input() | T3 | Code review; ScoringModelAuthoringTest |
| §8 ScoringModelPolicy | viewAny/view open; create/update/publish need assessments.manage | T3 | ScoringModelPolicyTest; ScoringModelAuthoringTest |
| §8 assessments.manage in catalog | PermissionCatalogSeeder | T1 | OrganizationApiTest |
| §8 assessments.manage in owner-role grant | CreateOrganization.whereIn | T1 | OrganizationApiTest |
| §8 AuditAction::ScoringModelPublished | FR-052 lockstep | T1 | AuditActionTest |
| §8 cross-tenant → 404 | BelongsToTenant global scope | T3 | ScoringModelAuthoringTest (4 cross-tenant cases) |
| §9 OpenAPI regen | openapi.json updated; Spectral 0 errors | T4 | OpenApiSpecTest |
| Resource field renames | model_id, version_id, model_id, program_id | T3 | ScoringModelResourceTest |

### Placeholder Scan

- No `// TODO` or `// FIXME` may remain in created files.
- No placeholder return values (empty arrays, hardcoded strings).
- The `validateForPublish()` method on `ScoringModelVersion` intentionally does nothing (validation is pre-empted in `PublishScoringModel`) — add a doc comment explaining this, as `FormVersion::validateForPublish()` does.

### Type and Name Consistency Check

- `ScoringModel::$fillable` includes `program_id` — matches migration column.
- `ScoringModelVersion::versionParentColumn()` returns `'scoring_model_id'` — matches migration FK column name.
- `ScoringModelVersionResource` emits `model_id` (= `$this->scoring_model_id`) — matches FE schema `model_id`.
- `ScoringModelResource` emits `model_id` (= `$this->id`) — matches FE schema `model_id`.
- `criteria` jsonb cast to `'array'` — Eloquent returns PHP array; resource wraps with `array_values()` — matches FE `criteria: ScoringCriterion[]`.
- Route names follow `scoring-models.*` convention, matching the `forms.*` pattern one-to-one.
- `AuditAction::ScoringModelPublished` value is `'scoring_model.published'` — matches the string used in `PublishScoringModel::handle()`.
