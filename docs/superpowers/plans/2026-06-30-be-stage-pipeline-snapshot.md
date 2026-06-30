# Stage-Pipeline Snapshot + Cohort Bind (ADR-0011 Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Snapshot a program's published stage graph into an immutable, content-addressed `StagePipelineVersion` and let a cohort bind it, wiring the Slice 2c preview + binding picker to real data — without touching the backend Stages engine or its participant runtime.

**Architecture:** New `StagePipeline` (one per program) + `StagePipelineVersion` (jsonb snapshot, immutable, content-addressed — mirrors `Form`/`FormVersion`). A `PublishStagePipeline` service captures the program's published `ProgramStage` graph (config + `StageRule`s + `StageTransition` topology + `StageDependency`) in the backend-native representation. A read resource translates the snapshot → the FE `StagePipelineVersion` shape. Cohort binding mirrors the shipped form-binding slice.

**Tech Stack:** PHP 8.3 / Laravel, Pest/PHPUnit, Eloquent (Postgres, ULID, JSONB), shared Versioning kernel (`VersionPublisher`, `ImmutableWhenPublished`, `VersionStatus`, `Versionable`), `dedoc/scramble`, `larastan`, `pint`, `deptrac`.

## Global Constraints

- Implements ADR-0011 Phase 1 only. Spec: `docs/superpowers/specs/2026-06-30-be-stage-pipeline-snapshot-design.md`.
- The backend Stages engine and participant runtime — `ProgramStage`, `StageVersion`, `StageTransition`, `StageRule`, `StageDependency`, `ParticipantStageState`, `StageInstance`, `AdvanceParticipantStage` — **must stay untouched**. This slice only READS them when snapshotting.
- One `StagePipeline` per program (`program_id` unique). Multi-pipeline/tracks deferred.
- `StagePipelineVersion` is immutable + content-addressed (sha256 of the canonical snapshot), idempotent republish, `ImmutableWhenPublished` — same machinery as `FormVersion`.
- `PublishStagePipeline` requires every program stage to have a published version (`current_published_version_id != null`); otherwise **422** (`StagePipelineNotPublishableException`) naming the offending stage keys. Empty program (no stages) also → 422.
- Snapshot `stage_id` = the captured `ProgramStage` ULID. Snapshot stores routing/rules in the backend-native representation.
- Read resource translates snapshot → FE shape (`frontend/src/schemas/stages.ts`): per stage `stage_id`, `name`, `type`, `order`, `next_stage_ids`, `depends_on_stage_ids`. **`type` maps to the FE 5-value vocabulary** (`review`→`review`, `interview`→`interview`, `evaluation`/`graduation`→`decision`; every other backend type → `task` default — display-only; the snapshot keeps the true backend type). **`entry_rule`/`exit_rule` are emitted as `null` in Phase 1** (structural preview only; full rule translation is Phase 2).
- Cohort bind mirrors `BindCohortForm`: draft-only cohort (else 409 `CohortStateException`); `StagePipelineVersion` scoped to tenant + `status=published` (else 404); same version idempotent 200; different version 409. `CohortStateException`→409 in the controller; `ModelNotFoundException`→404.
- Authorization deny-by-default: pipeline publish requires `stages.manage`; cohort bind requires `cohorts.manage` (both already granted to the owner role via `CreateOrganization` — no change needed). Org-scoped via `BelongsToTenant`; cross-tenant `{id}`→404.
- Audit actions come from the `AuditAction` enum (FR-052 registry). Add `StagePipelinePublished` (`'stage_pipeline.published'`) and `CohortStagePipelineBound` (`'cohort.stage_pipeline_bound'`) AND update the lockstep test `tests/Unit/Audit/AuditActionTest.php` **in the same task** (it asserts the exact enum set and is NOT in any per-task `--filter`).
- Commit author MUST be `274270+Byt3Ninja@users.noreply.github.com`; body ends with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Use `git -c commit.gpgsign=false`. `git add` only the task's files (never `-A`). Verify `git branch --show-current` is `feat/be-stage-pipeline-snapshot` before every commit.
- Feature-test hygiene (from prior slices): use `$this->actingAsTenantRequest($user, $org)` (in `backend/tests/TestCase.php`); `bootUserWithOrg()`/`actingAsTenant()` exist; owner role has `stages.manage` + `cohorts.manage`. Distinct `content_hash` per published-version fixture where applicable.
- Per-task gate (from `backend/`): `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress && php artisan test --filter=<relevant>`.

## Reference shapes (confirmed from source)

- `ProgramStage`: `id, program_id, organization_id, key, name, type (StageType), order_index, parallel_group, current_published_version_id`.
- `StageVersion`: fillable `program_stage_id, version_number, status, config (array), published_at` (no content_hash on stage versions).
- `StageRule`: `stage_version_id, type (entry|exit), expression (array)`.
- `StageTransition`: `program_id, from_program_stage_id, to_program_stage_id, condition (array), order_index`.
- `StageDependency`: `program_stage_id, depends_on_program_stage_id`.
- `StageType` (11): application, screening, interview, mentorship, training, assignment, review, evaluation, demo, graduation, custom.

## File Structure

**Create:**
- `backend/database/migrations/2026_06_30_000000_create_stage_pipelines.php` — `stage_pipelines`, `stage_pipeline_versions`, + `cohorts.stage_pipeline_version_id`.
- `backend/app/Modules/Stages/Domain/Models/StagePipeline.php`, `StagePipelineVersion.php`
- `backend/app/Modules/Stages/Domain/Exceptions/StagePipelineNotPublishableException.php`
- `backend/app/Modules/Stages/Application/PublishStagePipeline.php`
- `backend/app/Modules/Cohorts/Application/BindCohortStagePipeline.php` — (lives in **Cohorts**, mirroring `BindCohortForm`; references `StagePipelineVersion` from Stages — the established Cohorts→Stages edge, same shape as Cohorts→Forms)
- `backend/app/Modules/Stages/Http/StagePipelineController.php`, `StagePipelineVersionController.php`
- `backend/app/Modules/Stages/Http/Resources/StagePipelineResource.php`, `StagePipelineVersionResource.php`
- `backend/app/Modules/Stages/Policies/StagePipelinePolicy.php`
- `backend/tests/Feature/Stages/StagePipelineSnapshotTest.php`, `backend/tests/Feature/Stages/StagePipelineReadTest.php`, `backend/tests/Feature/Cohorts/CohortBindStagePipelineTest.php`, `backend/tests/Feature/Stages/StagePipelineFoundationsTest.php`

**Modify:**
- `backend/app/Shared/Audit/AuditAction.php` (+2 cases), `backend/tests/Unit/Audit/AuditActionTest.php` (lockstep)
- `backend/app/Modules/Cohorts/Http/CohortController.php` (+`bindStagePipeline`), `backend/app/Modules/Cohorts/Policies/CohortPolicy.php` (+`bindStagePipeline`), `backend/app/Modules/Cohorts/Http/Resources/CohortResource.php` (+`stage_pipeline_version_id`)
- `backend/routes/api.php` (+5 routes), `backend/app/Providers/AppServiceProvider.php` (register `StagePipelinePolicy`), `backend/app/Modules/Stages/StagesServiceProvider.php` if it registers policies/routes (check), `backend/openapi/openapi.json` (regen)

---

### Task 1: Schema, models, audit actions

**Files:**
- Create: the migration, `StagePipeline.php`, `StagePipelineVersion.php`
- Modify: `AuditAction.php`, `tests/Unit/Audit/AuditActionTest.php`
- Test: `backend/tests/Feature/Stages/StagePipelineFoundationsTest.php`

**Interfaces:**
- Produces: `stage_pipelines` + `stage_pipeline_versions` tables + `cohorts.stage_pipeline_version_id`; `StagePipeline` (relations `versions()`, `publishedVersions()`) and `StagePipelineVersion` (implements `Versionable`, `ImmutableWhenPublished`, `versionParentColumn()='stage_pipeline_id'`, `snapshot` cast array, `status` cast `VersionStatus`); `AuditAction::StagePipelinePublished`, `AuditAction::CohortStagePipelineBound`.

- [ ] **Step 1: Write the failing test**

`backend/tests/Feature/Stages/StagePipelineFoundationsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Stages\Domain\Models\StagePipeline;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Versioning\VersionStateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StagePipelineFoundationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_actions_exist(): void
    {
        $this->assertSame('stage_pipeline.published', AuditAction::StagePipelinePublished->value);
        $this->assertSame('cohort.stage_pipeline_bound', AuditAction::CohortStagePipelineBound->value);
    }

    public function test_pipeline_and_version_persist_and_freeze(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $pipeline = StagePipeline::create(['program_id' => (string) Str::ulid(), 'name' => 'Default']);
        $version = StagePipelineVersion::create([
            'stage_pipeline_id' => $pipeline->id,
            'status' => 'published',
            'version_number' => 1,
            'content_hash' => str_repeat('a', 64),
            'snapshot' => ['stages' => []],
            'published_at' => now(),
        ]);

        $this->assertSame($pipeline->id, $version->stage_pipeline_id);
        $this->assertSame(['stages' => []], $version->snapshot);

        $this->expectException(VersionStateException::class);
        $version->update(['snapshot' => ['stages' => [['x' => 1]]]]); // immutable once published
    }

    public function test_a_draft_version_persists_without_content_hash(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $pipeline = StagePipeline::create(['program_id' => (string) Str::ulid(), 'name' => 'Default']);
        $draft = StagePipelineVersion::create(['stage_pipeline_id' => $pipeline->id, 'snapshot' => ['stages' => []]]);

        $this->assertSame('draft', $draft->status->value);
        $this->assertSame(0, $draft->version_number);
        $this->assertNull($draft->content_hash);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && php artisan test --filter=StagePipelineFoundationsTest`
Expected: FAIL — tables/models/audit cases missing.

- [ ] **Step 3: Create the migration**

`backend/database/migrations/2026_06_30_000000_create_stage_pipelines.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_pipelines', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->unique(); // one pipeline per program
            $t->string('name');
            $t->ulid('current_published_version_id')->nullable();
            $t->timestampsTz();
        });

        Schema::create('stage_pipeline_versions', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('stage_pipeline_id')->index();
            $t->unsignedInteger('version_number');
            $t->string('status'); // draft | published | archived
            $t->string('content_hash', 64)->nullable();
            $t->jsonb('snapshot');
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();

            $t->unique(['stage_pipeline_id', 'content_hash']);
        });

        Schema::table('cohorts', function (Blueprint $t): void {
            $t->ulid('stage_pipeline_version_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $t): void {
            $t->dropColumn('stage_pipeline_version_id');
        });
        Schema::dropIfExists('stage_pipeline_versions');
        Schema::dropIfExists('stage_pipelines');
    }
};
```

- [ ] **Step 4: Create `StagePipeline`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One per program: the version parent for that program's immutable stage-graph snapshots. */
final class StagePipeline extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_id', 'name', 'current_published_version_id'];

    /** @return HasMany<StagePipelineVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(StagePipelineVersion::class);
    }

    /** @return HasMany<StagePipelineVersion, $this> */
    public function publishedVersions(): HasMany
    {
        return $this->hasMany(StagePipelineVersion::class)->where('status', 'published')->orderBy('version_number');
    }
}
```

- [ ] **Step 5: Create `StagePipelineVersion`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Versioning\ImmutableWhenPublished;
use App\Shared\Versioning\Versionable;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An immutable, content-addressed snapshot of a program's published stage graph (ADR-0011). */
final class StagePipelineVersion extends Model implements Versionable
{
    use BelongsToTenant;
    use HasUlids;
    use ImmutableWhenPublished;

    protected $fillable = ['stage_pipeline_id', 'version_number', 'status', 'content_hash', 'snapshot', 'published_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'version_number' => 0];

    /** @return array<string, string|class-string> */
    protected $casts = [
        'status' => VersionStatus::class,
        'snapshot' => 'array',
        'version_number' => 'integer',
        'published_at' => 'datetime',
    ];

    public function versionParentColumn(): string
    {
        return 'stage_pipeline_id';
    }

    public function validateForPublish(): void
    {
        // Snapshot is validated by PublishStagePipeline before the version is created.
    }

    /** @return BelongsTo<StagePipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(StagePipeline::class, 'stage_pipeline_id');
    }
}
```

- [ ] **Step 6: Add the audit cases + update the lockstep test**

In `backend/app/Shared/Audit/AuditAction.php`, add (near the cohort cases):

```php
    case StagePipelinePublished = 'stage_pipeline.published';
    case CohortStagePipelineBound = 'cohort.stage_pipeline_bound';
```

In `backend/tests/Unit/Audit/AuditActionTest.php`, add both values to the `$expected` array (the test sorts before comparing, so order doesn't matter, but add them):

```php
            'stage_pipeline.published',
            'cohort.stage_pipeline_bound',
```

- [ ] **Step 7: Run to verify pass**

Run: `cd backend && php artisan test --filter=StagePipelineFoundationsTest && php artisan test --filter=AuditActionTest`
Expected: PASS (foundations 3, AuditAction 1).

- [ ] **Step 8: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/database/migrations/2026_06_30_000000_create_stage_pipelines.php backend/app/Modules/Stages/Domain/Models/StagePipeline.php backend/app/Modules/Stages/Domain/Models/StagePipelineVersion.php backend/app/Shared/Audit/AuditAction.php backend/tests/Unit/Audit/AuditActionTest.php backend/tests/Feature/Stages/StagePipelineFoundationsTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(stages): stage-pipeline snapshot schema, models, audit actions

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: `PublishStagePipeline` service

**Files:**
- Create: `backend/app/Modules/Stages/Domain/Exceptions/StagePipelineNotPublishableException.php`, `backend/app/Modules/Stages/Application/PublishStagePipeline.php`, `backend/tests/Feature/Stages/StagePipelineSnapshotTest.php`

**Interfaces:**
- Consumes: `StagePipeline`, `StagePipelineVersion`, `ProgramStage`, `StageVersion`, `StageRule`, `StageTransition`, `StageDependency`, `Program`, `VersionPublisher`, `AuditLogger`, `AuditAction::StagePipelinePublished`.
- Produces: `StagePipelineNotPublishableException extends \RuntimeException` (carries the offending stage keys; →422); `PublishStagePipeline::handle(Program $program): StagePipelineVersion` (idempotent, immutable, content-addressed). Snapshot shape per Step 3.

- [ ] **Step 1: Write the failing test**

`backend/tests/Feature/Stages/StagePipelineSnapshotTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Application\PublishStagePipeline;
use App\Modules\Stages\Domain\Exceptions\StagePipelineNotPublishableException;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StagePipelineSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PublishStagePipeline
    {
        return $this->app->make(PublishStagePipeline::class);
    }

    /** A program with one fully-published stage, under tenant context. */
    private function programWithPublishedStage(): Program
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Accelerator', 'organization_id' => $org->id]);
        $stage = ProgramStage::create([
            'program_id' => $program->id, 'organization_id' => $org->id,
            'key' => 'screen', 'name' => 'Screening', 'type' => StageType::Screening, 'order_index' => 0,
        ]);
        $v = StageVersion::create([
            'program_stage_id' => $stage->id, 'organization_id' => $org->id,
            'status' => 'published', 'version_number' => 1, 'config' => ['k' => 'v'], 'published_at' => now(),
        ]);
        $stage->update(['current_published_version_id' => $v->id]);

        return $program->refresh();
    }

    public function test_publishes_an_immutable_content_addressed_snapshot(): void
    {
        $program = $this->programWithPublishedStage();

        $version = $this->service()->handle($program);

        $this->assertSame('published', $version->status->value);
        $this->assertSame(1, $version->version_number);
        $this->assertSame(64, strlen((string) $version->content_hash));
        $this->assertCount(1, $version->snapshot['stages']);
        $this->assertSame('screen', $version->snapshot['stages'][0]['key']);
        $this->assertArrayHasKey('stage_id', $version->snapshot['stages'][0]);
    }

    public function test_republish_of_identical_graph_is_idempotent(): void
    {
        $program = $this->programWithPublishedStage();
        $a = $this->service()->handle($program);
        $b = $this->service()->handle($program->refresh());

        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('stage_pipeline_versions', 1);
    }

    public function test_422_when_a_stage_is_unpublished(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Accelerator', 'organization_id' => $org->id]);
        ProgramStage::create([
            'program_id' => $program->id, 'organization_id' => $org->id,
            'key' => 'screen', 'name' => 'Screening', 'type' => StageType::Screening, 'order_index' => 0,
        ]); // no published version

        $this->expectException(StagePipelineNotPublishableException::class);
        $this->service()->handle($program->refresh());
    }

    public function test_422_when_program_has_no_stages(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Empty', 'organization_id' => $org->id]);

        $this->expectException(StagePipelineNotPublishableException::class);
        $this->service()->handle($program->refresh());
    }
}
```

Confirm `Program::create` field names against the existing Programs tests/model (e.g. whether `organization_id` is auto-stamped by `BelongsToTenant` — if so, omit it). Match the existing pattern; adjust the fixture if the Program model differs.

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && php artisan test --filter=StagePipelineSnapshotTest`
Expected: FAIL — `PublishStagePipeline` / exception missing.

- [ ] **Step 3: Create the exception + service**

`StagePipelineNotPublishableException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Exceptions;

use RuntimeException;

/** Raised when a program's stage graph cannot be snapshotted (a stage lacks a published version, or no stages). →422. */
final class StagePipelineNotPublishableException extends RuntimeException {}
```

`PublishStagePipeline.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Stages\Application;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Domain\Exceptions\StagePipelineNotPublishableException;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageDependency;
use App\Modules\Stages\Domain\Models\StagePipeline;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Modules\Stages\Domain\Models\StageRule;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Versioning\VersionPublisher;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots a program's PUBLISHED stage graph into an immutable, content-addressed
 * StagePipelineVersion (ADR-0011 Phase 1). Reads the Stages engine only — never mutates it.
 */
final class PublishStagePipeline
{
    public function __construct(
        private readonly VersionPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws StagePipelineNotPublishableException when a stage lacks a published version, or there are no stages
     */
    public function handle(Program $program): StagePipelineVersion
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ProgramStage> $stages */
        $stages = ProgramStage::query()
            ->where('program_id', $program->id)
            ->orderBy('order_index')
            ->get();

        if ($stages->isEmpty()) {
            throw new StagePipelineNotPublishableException('The program has no stages to publish.');
        }

        $unpublished = $stages->filter(fn (ProgramStage $s) => $s->current_published_version_id === null)
            ->map(fn (ProgramStage $s) => $s->key)->values()->all();
        if ($unpublished !== []) {
            throw new StagePipelineNotPublishableException(
                'Every stage must have a published version before the pipeline can be published. Unpublished: '.implode(', ', $unpublished)
            );
        }

        $snapshot = $this->buildSnapshot($program, $stages);
        $hash = hash('sha256', $this->canonicalJson($snapshot));

        $pipeline = StagePipeline::query()->firstOrCreate(
            ['program_id' => $program->id],
            ['name' => $program->name],
        );

        $version = DB::transaction(function () use ($pipeline, $snapshot, $hash): StagePipelineVersion {
            /** @var StagePipelineVersion|null $existing */
            $existing = StagePipelineVersion::query()
                ->where('stage_pipeline_id', $pipeline->id)
                ->where('status', 'published')
                ->where('content_hash', $hash)
                ->first();
            if ($existing !== null) {
                return $existing; // idempotent republish — no duplicate row
            }

            $version = StagePipelineVersion::create([
                'stage_pipeline_id' => $pipeline->id,
                'content_hash' => $hash,
                'snapshot' => $snapshot,
            ]);
            $this->publisher->publish($version); // version_number, Published, published_at
            $pipeline->update(['current_published_version_id' => $version->id]);

            return $version->refresh();
        });

        $this->audit->record(AuditAction::StagePipelinePublished->value, 'stage_pipeline_version', $version->id, [], [
            'content_hash' => $hash,
            'version_number' => $version->version_number,
        ]);

        return $version;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ProgramStage>  $stages
     * @return array<string, mixed>
     */
    private function buildSnapshot(Program $program, $stages): array
    {
        $transitions = StageTransition::query()->where('program_id', $program->id)->get();
        $stageIds = $stages->pluck('id')->all();
        $deps = StageDependency::query()->whereIn('program_stage_id', $stageIds)->get();

        $stageNodes = $stages->map(function (ProgramStage $stage) use ($transitions, $deps): array {
            /** @var StageVersion $pv */
            $pv = StageVersion::query()->findOrFail($stage->current_published_version_id);
            $rules = StageRule::query()->where('stage_version_id', $pv->id)
                ->get(['type', 'expression'])
                ->map(fn (StageRule $r) => ['type' => $r->type, 'expression' => $r->expression])->all();

            return [
                'stage_id' => $stage->id,
                'key' => $stage->key,
                'name' => $stage->name,
                'type' => $stage->type->value,            // backend-native (11-value)
                'order_index' => $stage->order_index,
                'stage_version_id' => $pv->id,
                'config' => $pv->config,
                'rules' => $rules,                         // native StageRule expressions
                'next_stage_ids' => $transitions->where('from_program_stage_id', $stage->id)
                    ->sortBy('order_index')->pluck('to_program_stage_id')->values()->all(),
                'depends_on_stage_ids' => $deps->where('program_stage_id', $stage->id)
                    ->pluck('depends_on_program_stage_id')->values()->all(),
            ];
        })->all();

        return ['program_id' => $program->id, 'stages' => $stageNodes];
    }

    /** Stable canonical serialization (recursively key-sorted) for the content hash. */
    private function canonicalJson(array $value): string
    {
        return json_encode($this->ksortRecursive($value), JSON_THROW_ON_ERROR);
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

(`StageRule.type` is a string `entry|exit`; if it is cast to an enum on the model, use `->value` — confirm and adjust.)

- [ ] **Step 4: Run to verify pass**

Run: `cd backend && php artisan test --filter=StagePipelineSnapshotTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Confirm the engine is untouched + gate + commit**

Run: `cd backend && php artisan test --filter=Stages` (the existing engine + participant-runtime suites must stay green; this slice only reads them).
Then the gate and commit:

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Stages/Domain/Exceptions/StagePipelineNotPublishableException.php backend/app/Modules/Stages/Application/PublishStagePipeline.php backend/tests/Feature/Stages/StagePipelineSnapshotTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(stages): PublishStagePipeline snapshots the published stage graph

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Resources, read endpoints, publish endpoint, policy

**Files:**
- Create: `StagePipelineResource.php`, `StagePipelineVersionResource.php`, `StagePipelineController.php`, `StagePipelineVersionController.php`, `StagePipelinePolicy.php`, `backend/tests/Feature/Stages/StagePipelineReadTest.php`
- Modify: `backend/routes/api.php`, `backend/app/Providers/AppServiceProvider.php`

**Interfaces:**
- Consumes: `PublishStagePipeline`, `StagePipelineNotPublishableException`, `StagePipeline`, `StagePipelineVersion`, `Program`.
- Produces: read endpoints + `POST /programs/{program}/stage-pipelines/publish`; resources translating the snapshot → FE shape.

- [ ] **Step 1: Write the failing tests**

`backend/tests/Feature/Stages/StagePipelineReadTest.php` — covers: publish endpoint returns 201/200 with a version; 422 when unpublished; GET pipeline lists the program's pipeline; GET version returns the FE-shaped `stages[]` (`stage_id` = ProgramStage ULID, `type` mapped to FE vocab, `entry_rule`/`exit_rule` null, `next_stage_ids`/`depends_on_stage_ids` present); cross-tenant 404; 403 publishing without `stages.manage`. Build fixtures with a fully-published 2-stage program (use the `StagePipelineSnapshotTest` fixture pattern) and assert the resource shape. Use `$this->actingAsTenantRequest($user, $org)`. For the 403 test, construct a same-org member without `stages.manage` (mirror the cohort/forms no-permission-member test pattern). Decide the publish success code: **200** (idempotent snapshot-now, not a create-by-id); assert 200.

(Write the full test bodies following the established feature-test patterns in `backend/tests/Feature/Stages/` and `backend/tests/Feature/Cohorts/CohortOpenBindTest.php`.)

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && php artisan test --filter=StagePipelineReadTest`
Expected: FAIL — routes 404 / resources missing.

- [ ] **Step 3: Create `StagePipelineVersionResource`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http\Resources;

use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $stage_pipeline_id
 * @property int $version_number
 * @property \App\Shared\Versioning\VersionStatus $status
 * @property array<string, mixed> $snapshot
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $published_at
 */
final class StagePipelineVersionResource extends JsonResource
{
    /** Backend StageType (11) → FE vocabulary (5). Display-only; snapshot keeps the true type. */
    private const TYPE_MAP = [
        'review' => 'review', 'interview' => 'interview',
        'evaluation' => 'decision', 'graduation' => 'decision',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<int, array<string, mixed>> $stages */
        $stages = $this->snapshot['stages'] ?? [];

        return [
            'id' => $this->id,
            'pipeline_id' => $this->stage_pipeline_id,
            'version' => $this->version_number,
            'status' => $this->status->value,
            'stages' => array_map(fn (array $s): array => [
                'stage_id' => $s['stage_id'],
                'name' => $s['name'],
                'type' => self::TYPE_MAP[$s['type']] ?? 'task',   // documented default
                'order' => $s['order_index'],
                'next_stage_ids' => $s['next_stage_ids'] ?? [],
                'depends_on_stage_ids' => $s['depends_on_stage_ids'] ?? [],
                'entry_rule' => null,  // Phase 1: structural preview only (ADR-0011)
                'exit_rule' => null,
            ], $stages),
            'created_at' => $this->created_at->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
```

Verify the exact FE `stageSchema` / `stagePipelineVersionSchema` field names in `frontend/src/schemas/stages.ts` and match them precisely (the keys above — `pipeline_id`, `version`, `stages[].order`, `entry_rule`, etc. — must equal the Zod shape; adjust if the FE differs).

- [ ] **Step 4: Create `StagePipelineResource`**

Maps a `StagePipeline` (+ loaded `versions`) → FE `StagePipeline` shape: `id`, `program_id`, `name`, `latest_version` (max published `version_number`, 0 if none), `published_version_ids` (published ids in version order), `current_draft_version_id` (`null` — authoring is Phase 2). Mirror `FormResource`'s derived-field approach (closure filter on `status`).

- [ ] **Step 5: Create `StagePipelinePolicy` + register it**

`viewAny`/`view` return true (tenant members); `publish(Account)` returns `app(TenantContext::class)->can('stages.manage')`. Register `StagePipeline::class => StagePipelinePolicy::class` in `AppServiceProvider` the same way existing policies are registered.

- [ ] **Step 6: Create the controllers**

`StagePipelineController`: `index(string $program)` (the program's pipelines, 0/1), `show(string $id)`, `versions(string $pipeline)`, `publish(PublishStagePipeline $service, string $program)` — `$this->authorize('publish', StagePipeline::class)`, resolve the `Program` (tenant-scoped `findOrFail`), `try { $v = $service->handle($program); } catch (StagePipelineNotPublishableException $e) { return response()->json(['message' => $e->getMessage(), 'errors' => ['stages' => [$e->getMessage()]]], 422); }`, return `StagePipelineVersionResource` 200. `StagePipelineVersionController@show(string $id)` returns one version (tenant-scoped `findOrFail` → 404).

- [ ] **Step 7: Register routes**

In the `['auth:sanctum','tenant']` group:

```php
        Route::get('/programs/{program}/stage-pipelines', [StagePipelineController::class, 'index'])->name('programs.stage-pipelines.index');
        Route::post('/programs/{program}/stage-pipelines/publish', [StagePipelineController::class, 'publish'])->name('programs.stage-pipelines.publish');
        Route::get('/stage-pipelines/{id}', [StagePipelineController::class, 'show'])->name('stage-pipelines.show');
        Route::get('/stage-pipelines/{pipeline}/versions', [StagePipelineController::class, 'versions'])->name('stage-pipelines.versions.index');
        Route::get('/stage-pipeline-versions/{id}', [StagePipelineVersionController::class, 'show'])->name('stage-pipeline-versions.show');
```
with the imports. Order `/stage-pipelines/{pipeline}/versions` and the literal `publish` path so they don't shadow `/stage-pipelines/{id}`.

- [ ] **Step 8: Run to verify pass + gate + commit**

Run: `cd backend && php artisan test --filter=StagePipelineReadTest`; then the gate; then commit only the task's files with message `feat(stages): stage-pipeline read + publish endpoints + resources`.

---

### Task 4: `BindCohortStagePipeline` + `POST /cohorts/{id}/bind-stage-pipeline`

**Files:**
- Create: `backend/app/Modules/Cohorts/Application/BindCohortStagePipeline.php`, `backend/tests/Feature/Cohorts/CohortBindStagePipelineTest.php`
- Modify: `backend/app/Modules/Cohorts/Http/CohortController.php`, `backend/app/Modules/Cohorts/Policies/CohortPolicy.php`, `backend/app/Modules/Cohorts/Http/Resources/CohortResource.php`, `backend/routes/api.php`

**Interfaces:**
- Consumes: `Cohort`, `CohortStatus`, `CohortStateException`, `StagePipelineVersion`, `AuditAction::CohortStagePipelineBound`, `AuditLogger`, `CohortResource`, `CohortPolicy@bindStagePipeline`.
- Produces: `BindCohortStagePipeline::handle(Cohort, string $versionId): Cohort`; `CohortController@bindStagePipeline` (POST `/cohorts/{id}/bind-stage-pipeline`, route `cohorts.bind-stage-pipeline`). `CohortResource` emits `stage_pipeline_version_id`.

- [ ] **Step 1: Write the failing tests**

`CohortBindStagePipelineTest.php` mirrors `CohortOpenBindTest`'s bind-form cases exactly, swapping form→pipeline: 200 first bind; idempotent same version; 409 different version; 409 non-draft cohort; 404 missing cohort; 404 non-published/foreign `StagePipelineVersion`; cross-tenant 404; 403 without `cohorts.manage`. Build a published `StagePipelineVersion` fixture (create a `StagePipeline` + a published version directly, distinct `content_hash`). Use `$this->actingAsTenantRequest($user, $org)`.

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && php artisan test --filter=CohortBindStagePipelineTest` → FAIL (route/service missing).

- [ ] **Step 3: Create `BindCohortStagePipeline`** (mirror `BindCohortForm` exactly)

```php
<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Application;

use App\Modules\Cohorts\Domain\Exceptions\CohortStateException;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final class BindCohortStagePipeline
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws CohortStateException on a non-draft cohort or a conflicting bound version
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when no published version with that id exists in the tenant
     */
    public function handle(Cohort $cohort, string $versionId): Cohort
    {
        if ($cohort->status !== CohortStatus::Draft) {
            throw new CohortStateException('A stage pipeline can only be bound while the cohort is a draft.');
        }

        $version = StagePipelineVersion::query()->where('status', 'published')->findOrFail($versionId);

        if ($cohort->stage_pipeline_version_id === $version->id) {
            return $cohort;
        }
        if ($cohort->stage_pipeline_version_id !== null) {
            throw new CohortStateException('A different stage pipeline version is already bound to this cohort.');
        }

        $cohort = DB::transaction(function () use ($cohort, $version): Cohort {
            $cohort->update(['stage_pipeline_version_id' => $version->id]);

            return $cohort->refresh();
        });

        $this->audit->record(AuditAction::CohortStagePipelineBound->value, 'cohort', $cohort->id, [], [
            'stage_pipeline_version_id' => $version->id,
        ]);

        return $cohort;
    }
}
```

Add `stage_pipeline_version_id` to `Cohort::$fillable` (confirm it is fillable, else add it) so the `update` persists.

- [ ] **Step 4: Wire the controller, policy, resource, route**

- `CohortController@bindStagePipeline` mirrors `bindForm` (a `BindStagePipelineRequest` validating `stage_pipeline_version_id` required, or reuse an inline `$request->validate`); `findOrFail`→`authorize('bindStagePipeline', $cohort)`→service; `CohortStateException`→409; `StagePipelineVersionResource` not needed — return `CohortResource` 200.
- `CohortPolicy@bindStagePipeline(Account, Cohort)` → `cohorts.manage`.
- `CohortResource`: add `'stage_pipeline_version_id' => $this->stage_pipeline_version_id` (+ `@property-read string|null $stage_pipeline_version_id`).
- Route: `Route::post('/cohorts/{id}/bind-stage-pipeline', [CohortController::class, 'bindStagePipeline'])->name('cohorts.bind-stage-pipeline');`

- [ ] **Step 5: Run + gate + commit**

Run: `cd backend && php artisan test --filter=CohortBindStagePipelineTest && php artisan test --filter=CohortOpenBindTest` (the latter must stay green). Then the gate; commit the task's files with message `feat(cohorts): bind-stage-pipeline endpoint`.

---

### Task 5: Regenerate OpenAPI + full-suite sweep

**Files:** Modify `backend/openapi/openapi.json` (regenerated)

- [ ] **Step 1: Regenerate** — `cd backend && php artisan scramble:export --path=openapi/openapi.json`; `git diff --stat openapi/openapi.json`. Expected: adds `/v1/programs/{program}/stage-pipelines`, `/v1/programs/{program}/stage-pipelines/publish`, `/v1/stage-pipelines/{id}`, `/v1/stage-pipelines/{pipeline}/versions`, `/v1/stage-pipeline-versions/{id}`, `/v1/cohorts/{id}/bind-stage-pipeline`, and `stage_pipeline_version_id` on the cohort schema.
- [ ] **Step 2: Contract + Spectral** — `php artisan test --filter=OpenApiSpecTest` (PASS); from repo root `npx --yes @stoplight/spectral-cli lint backend/openapi/openapi.json --ruleset .spectral.yaml --fail-severity=error` (0 errors). If Spectral flags the new operations, add minimal Scramble PHPDoc mirroring existing controllers, regenerate.
- [ ] **Step 3: Full gate** — `cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress && php artisan test` (full suite green; engine + participant-runtime tests included). Resolve any deptrac edge only if it matches an existing allowed pattern (`BindCohortStagePipeline` references the Cohorts module from Stages — confirm this direction is allowed; `OpenCohort`→`FormVersion` already crosses Cohorts→Forms, so Stages→Cohorts may be a new edge — if deptrac flags it, place `BindCohortStagePipeline` to respect the allowed layering, e.g. keep the cross-module reference consistent with how `OpenCohort` imports `FormVersion`).
- [ ] **Step 4: Commit** the regenerated `openapi.json` (+ any controller doc tweaks) with message `chore(stages): regenerate OpenAPI for stage-pipeline + bind endpoints`.

---

## Self-Review

**Spec coverage:** §4 schema → Task 1; §5 PublishStagePipeline (snapshot, 422, idempotent) → Task 2; §6 read endpoints + resource translation (type map, null rules, stage_id=ProgramStage ULID) → Task 3; §6 program-scoped publish endpoint divergence note → Task 3 Step 7; §7 cohort bind → Task 4; §8 authz/tenancy/audit → Tasks 1 (audit) + 3 (policy) + 4 (cohort policy); §9 testing incl. engine-untouched → Tasks 2 (Step 5) + 5; OpenAPI → Task 5. ✓

**Placeholder scan:** Tasks 3 and 4 describe some test bodies as "mirror the established pattern" rather than full inline code — these are deliberate references to concrete, in-repo patterns (`CohortOpenBindTest`, `FormResource`) the implementer copies, with the exact cases enumerated; not vague placeholders. The "confirm against FE schema / Program model / StageRule cast" notes are explicit verification points (names differ per repo and must be matched, not guessed).

**Type consistency:** `PublishStagePipeline::handle(Program): StagePipelineVersion`, `BindCohortStagePipeline::handle(Cohort, string): Cohort`, `StagePipelineNotPublishableException`, `CohortStateException` (reused), `AuditAction::StagePipelinePublished`/`CohortStagePipelineBound`, snapshot keys (`stage_id`/`key`/`type`/`order_index`/`next_stage_ids`/`depends_on_stage_ids`) consistent between the snapshot builder (Task 2) and the resource translation (Task 3). Cohort binding mirrors `BindCohortForm` field-for-field.

**Cross-module note (deptrac):** `BindCohortStagePipeline` lives in **Cohorts** and references `StagePipelineVersion` (Cohorts→Stages — same edge shape as `BindCohortForm`→`FormVersion` Cohorts→Forms, already allowed). `PublishStagePipeline` lives in **Stages** and references `Program` (Stages→Programs — `StageController` already imports `Program`, so this edge exists). No new reverse edges are introduced; confirm deptrac stays at 0 violations (Task 5 Step 3).
