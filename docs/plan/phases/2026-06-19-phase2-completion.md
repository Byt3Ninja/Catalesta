# Phase 2 Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the three unimplemented Phase-2 capabilities — program **tracks** (stage-filtering pathways + enrollment), stage **dependencies** (prerequisite edges enforced at entry), and program **archival** (archive action + read-only + listing filter).

**Architecture:** Three feature slices on top of merged Phase 2 (Programs/Cohorts/Stages) + fail-closed tenancy. New tenant-owned tables/models, `Application/` services, thin controllers, and three new guards inside `AdvanceParticipantStage::enter`. Reuses existing permission keys. Spec: `docs/superpowers/specs/2026-06-19-phase2-completion-design.md`.

**Tech Stack:** PHP 8.3 / Laravel 13, PostgreSQL, ULIDs (`HasUlids`), `BelongsToTenant`, Sanctum + `tenant` middleware, PHPUnit, Pint, Larastan (level 6).

## Global Constraints

- `declare(strict_types=1);` in every PHP file; `final` classes for models/services/controllers/policies (as the codebase does).
- Every new tenant-owned record has `organization_id` and uses `App\Shared\Tenancy\BelongsToTenant`; explicit `$fillable` that NEVER includes `organization_id` or `id` (org is forced from `TenantContext` on create inside a resolved-tenant request).
- Migrations timestamped AFTER `2026_06_18_080000` — use `2026_06_18_0810xx`. Column idiom: `$t->ulid('id')->primary(); $t->ulid('organization_id')->index(); …; $t->timestampsTz();` (see `database/migrations/2026_06_18_002800_create_stage_transitions_table.php`).
- **Reuse permission keys** `programs.manage`, `stages.manage`, `cohorts.manage` — add NO new keys (would force a `PermissionCatalogSeeder` + `CreateOrganization` dual-update).
- No business logic in controllers (orchestration only; logic in `Application/` services). `DB::transaction` around multi-row ops. Audit via `App\Shared\Audit\AuditLogger::record($action, $targetType, $targetId, $before, $after)`.
- Resolve route ids/parents **tenant-scoped** (`Model::query()->findOrFail($id)` under the global scope) so a foreign-org id 404s; or use the FormRequest `authorize()` tenant-scoped `find()→null→false` (403) pattern (see `app/Modules/Cohorts/Http/Requests/UpdateCohortRequest.php`). Do NOT use implicit route-model binding (it resolves before `ResolveTenant`).
- Published stages remain immutable (rule 8); never weaken the tenant scope/trait to pass a test.
- Each task ends green: from `backend/` run `php artisan test` (full suite, was **244**), `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --no-progress --memory-limit=512M`. Then commit.
- Code exploration uses Graphify first (`graphify query "…"`) before broad reads — include this instruction in any sub-dispatch.

## Reference patterns (read these before mirroring)

- Migration: `database/migrations/2026_06_18_002800_create_stage_transitions_table.php`.
- Model: `app/Modules/Stages/Domain/Models/StageTransition.php` (tenant-owned, `$fillable`).
- Controller: `app/Modules/Programs/Http/ProgramController.php` (tenant-scoped `findOrFail` → `authorize` → service → `Resource`, 201/200) and `ProgramTemplateController.php` (explicit-id resolution).
- FormRequest: `app/Modules/Cohorts/Http/Requests/UpdateCohortRequest.php` (tenant-scoped `authorize()` → 403).
- Policy: `app/Modules/Stages/Policies/StagePolicy.php` (`viewAny/view` → true; mutations → `app(TenantContext::class)->can('<key>')`). Policies registered in `app/Providers/AppServiceProvider::boot()` via `Gate::policy(Model::class, Policy::class)`.
- Service + audit: `app/Modules/Programs/Application/CloneProgram.php`, `PublishProgram.php`.
- Routes: `routes/api.php` inside the `auth:sanctum`+`tenant` group under `Route::prefix('v1')`.
- Advance service (the guard target): `app/Modules/Stages/Application/AdvanceParticipantStage.php`. `ParticipantStageState` cases: `not_started,in_progress,completed,skipped,blocked`.

---

## Milestone M1 — Stage Dependencies

### Task 1: stage_dependencies — model, validated CRUD service, API

**Files:**
- Create: `database/migrations/2026_06_18_081300_create_stage_dependencies_table.php`
- Create: `app/Modules/Stages/Domain/Models/StageDependency.php`
- Create: `app/Modules/Stages/Domain/Exceptions/InvalidStageDependencyException.php`
- Create: `app/Modules/Stages/Application/AddStageDependency.php`, `RemoveStageDependency.php`
- Create: `app/Modules/Stages/Http/StageDependencyController.php`, `Http/Requests/StoreStageDependencyRequest.php`, `Http/Resources/StageDependencyResource.php`
- Modify: `app/Modules/Stages/Policies/StagePolicy.php` (add `manageDependencies`), `routes/api.php`
- Test: `tests/Feature/Stages/StageDependencyApiTest.php`

**Interfaces:**
- Produces: `AddStageDependency::handle(ProgramStage $stage, string $dependsOnStageId): StageDependency` (validates + persists); `RemoveStageDependency::handle(StageDependency $dep): void`; `StageDependency` model with `program_stage_id`, `depends_on_program_stage_id`. Consumed by Task 2 (`enter` guard).

- [ ] **Step 1: Migration**
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
        Schema::create('stage_dependencies', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_stage_id')->index();          // dependent stage
            $t->ulid('depends_on_program_stage_id')->index(); // prerequisite
            $t->timestampsTz();
            $t->unique(['program_stage_id', 'depends_on_program_stage_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('stage_dependencies'); }
};
```

- [ ] **Step 2: Model** (`StageDependency.php`) — mirror `StageTransition`:
```php
<?php
declare(strict_types=1);
namespace App\Modules\Stages\Domain\Models;
use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
final class StageDependency extends Model
{
    use BelongsToTenant, HasUlids;
    protected $fillable = ['program_stage_id', 'depends_on_program_stage_id'];
}
```

- [ ] **Step 3: Exception** (`InvalidStageDependencyException.php`) extends `\RuntimeException`, `declare(strict_types=1)`. Add static factories `selfDependency()`, `crossProgram()`, `cycle()` each with a clear message.

- [ ] **Step 4: Failing test** (`StageDependencyApiTest`) — owner (via `bootUserWithOrg()` + `actingAs($user,'web')` + `X-Organization-Id` header) creates a program with 3 stages A,B,C. Assert:
  - `POST /api/v1/programs/{program}/stages/{B}/dependencies {depends_on_program_stage_id: A}` → 201; dependency persisted.
  - `GET /api/v1/programs/{program}/stages/{B}/dependencies` → lists A.
  - `DELETE /api/v1/stage-dependencies/{id}` → 200/204; removed.
  - self-edge (B depends on B) → 422; cross-program (B depends on a stage in another program) → 422; cycle (A→B then B→A) → 422.
  - member without `stages.manage` → 403; cross-tenant `{program}`/`{stage}` id → 404.

- [ ] **Step 5: Implement `AddStageDependency`** (cycle detection via DFS over existing edges):
```php
<?php
declare(strict_types=1);
namespace App\Modules\Stages\Application;
use App\Modules\Stages\Domain\Exceptions\InvalidStageDependencyException;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageDependency;
use Illuminate\Support\Facades\DB;
final class AddStageDependency
{
    public function handle(ProgramStage $stage, string $dependsOnStageId): StageDependency
    {
        if ($stage->id === $dependsOnStageId) {
            throw InvalidStageDependencyException::selfDependency();
        }
        // prerequisite must exist in the SAME program (tenant-scoped find)
        $prereq = ProgramStage::query()->find($dependsOnStageId);
        if ($prereq === null || $prereq->program_id !== $stage->program_id) {
            throw InvalidStageDependencyException::crossProgram();
        }
        // cycle check: would adding stage->prereq create a path prereq ->* stage?
        if ($this->reaches($dependsOnStageId, $stage->id, $stage->program_id)) {
            throw InvalidStageDependencyException::cycle();
        }
        return StageDependency::query()->firstOrCreate([
            'program_stage_id' => $stage->id,
            'depends_on_program_stage_id' => $dependsOnStageId,
        ]);
    }

    /** Does $from reach $target by following depends_on edges (within $programId)? */
    private function reaches(string $from, string $target, string $programId): bool
    {
        $seen = [];
        $stack = [$from];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node === $target) {
                return true;
            }
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            $edges = StageDependency::query()
                ->where('program_stage_id', $node)
                ->pluck('depends_on_program_stage_id');
            foreach ($edges as $next) {
                $stack[] = $next;
            }
        }
        return false;
    }
}
```
`RemoveStageDependency::handle(StageDependency $dep): void` → `$dep->delete();` (no audit needed; or audit `stage_dependency.removed`).

- [ ] **Step 6: Controller + request + resource + routes + policy.** `StageDependencyController` mirrors `ProgramController`: `index($programId,$stageId)` (resolve program + stage tenant-scoped → 404; `authorize('manageDependencies', ProgramStage::class)`; return resource collection); `store(...)` (validate body `{depends_on_program_stage_id: required|string}` via `StoreStageDependencyRequest`; catch `InvalidStageDependencyException` → 422 envelope; audit `stage_dependency.added`; 201); `destroy($id)` (resolve `StageDependency` tenant-scoped → 404; authorize; delete; 200/204). Add `StagePolicy::manageDependencies(ExternalUser $user): bool => app(TenantContext::class)->can('stages.manage');`. Routes:
```php
Route::get('/programs/{program}/stages/{stage}/dependencies', [StageDependencyController::class, 'index']);
Route::post('/programs/{program}/stages/{stage}/dependencies', [StageDependencyController::class, 'store']);
Route::delete('/stage-dependencies/{id}', [StageDependencyController::class, 'destroy']);
```
Map `InvalidStageDependencyException` → 422 in the controller (try/catch → `response()->json(['error'=>['code'=>'invalid_stage_dependency','message'=>$e->getMessage(),'correlation_id'=>CorrelationId::get()]], 422)`), matching the project error envelope.

- [ ] **Step 7: Green** — `php artisan test --filter=StageDependencyApiTest` PASS; full suite green; pint; phpstan; commit `feat(stages): stage dependencies — validated prerequisite edges + API`.

---

### Task 2: enforce prerequisites in `AdvanceParticipantStage::enter`

**Files:**
- Create: `app/Modules/Stages/Domain/Exceptions/StagePrerequisiteNotMetException.php`
- Modify: `app/Modules/Stages/Application/AdvanceParticipantStage.php`
- Test: `tests/Feature/Stages/AdvancePrerequisiteTest.php`

**Interfaces:** Consumes `StageDependency` (Task 1). Produces the guard behavior.

- [ ] **Step 1: Exception** — `StagePrerequisiteNotMetException` extends `\RuntimeException`; `declare(strict_types=1)`; static `forStage(string $stageId)`.

- [ ] **Step 2: Failing test** — program with stages A,B; B depends on A. Participant (an `ExternalUser`) in a cohort. Calling `app(AdvanceParticipantStage::class)->enter($cohort,$participant,$stageB)` when A is NOT completed → throws `StagePrerequisiteNotMetException` and writes NO `ParticipantStageStatus` for B. After completing A (a `ParticipantStageStatus` with `status=completed`), `enter(...,$stageB)` succeeds (status `in_progress`). A stage with NO dependencies behaves exactly as today (regression).

- [ ] **Step 3: Implement guard** — in `enter()`, immediately after the method opens (before the `current_published_version_id` check), add:
```php
$unmet = \App\Modules\Stages\Domain\Models\StageDependency::query()
    ->where('program_stage_id', $stage->id)
    ->pluck('depends_on_program_stage_id');
if ($unmet->isNotEmpty()) {
    $completed = ParticipantStageStatus::query()
        ->where('cohort_id', $cohort->id)
        ->where('external_user_id', $participant->id)
        ->whereIn('program_stage_id', $unmet)
        ->where('status', ParticipantStageState::Completed->value)
        ->pluck('program_stage_id');
    if ($completed->count() < $unmet->unique()->count()) {
        throw \App\Modules\Stages\Domain\Exceptions\StagePrerequisiteNotMetException::forStage($stage->id);
    }
}
```
(Guard order: prerequisites → existing published-version check. Track guard from Task 6 will be inserted before this.)

- [ ] **Step 4: Green** — full suite green; pint; phpstan; commit `feat(stages): enforce stage prerequisites on participant entry (AND-semantics)`.

---

## Milestone M2 — Tracks + Enrollment

### Task 3: tracks — model, CRUD API, cascade delete

**Files:**
- Create: migration `2026_06_18_081000_create_tracks_table.php`; `app/Modules/Programs/Domain/Models/Track.php`; `Application/DeleteTrack.php`; `Http/TrackController.php`, `Http/Requests/StoreTrackRequest.php`, `UpdateTrackRequest.php`, `Http/Resources/TrackResource.php`
- Modify: `app/Modules/Programs/Policies/ProgramPolicy.php` (add `manageTracks`), `routes/api.php`
- Test: `tests/Feature/Programs/TrackApiTest.php`

**Interfaces:** Produces `Track` (`program_id,key,name,description,order_index`). `DeleteTrack::handle(Track): void` removes pivot rows + nulls `cohort_participants.track_id` (defined in Tasks 4/5; until they exist, delete just removes the track). Consumed by Tasks 4–6.

- [ ] **Step 1: Migration** — `tracks`: `id` ulid pk, `organization_id` ulid index, `program_id` ulid index, `key` string, `name` string, `description` text nullable, `order_index` unsignedInteger default 0, `timestampsTz`; `unique(['program_id','key'])`.

- [ ] **Step 2: Model**
```php
<?php
declare(strict_types=1);
namespace App\Modules\Programs\Domain\Models;
use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
final class Track extends Model
{
    use BelongsToTenant, HasUlids;
    protected $fillable = ['program_id', 'key', 'name', 'description', 'order_index'];
    protected $casts = ['order_index' => 'integer'];
}
```

- [ ] **Step 3: Failing test** (`TrackApiTest`) — owner creates a program. Assert: `POST /api/v1/programs/{program}/tracks {key,name}` → 201; `GET /api/v1/programs/{program}/tracks` lists it; `PATCH /api/v1/tracks/{id}` updates name; `DELETE /api/v1/tracks/{id}` → 200/204; duplicate key in same program → 422; member without `programs.manage` → 403; cross-tenant `{program}`/`{id}` → 404.

- [ ] **Step 4: Implement** controller (mirror `ProgramController`: tenant-scoped resolution, `authorize('manageTracks', Program::class)` / `('manageTracks', Track::class)`, `TrackResource`, audit `track.created`/`track.updated`/`track.deleted`). `DeleteTrack::handle(Track $track)` wrapped in `DB::transaction` — at this task it just `$track->delete();` (pivot + participant nulling are added in Tasks 4 & 5 when those tables exist; add a TODO-free note: those steps are appended by Tasks 4/5). Add `ProgramPolicy::manageTracks(ExternalUser $user): bool => app(TenantContext::class)->can('programs.manage');`. Routes:
```php
Route::get('/programs/{program}/tracks', [TrackController::class, 'index']);
Route::post('/programs/{program}/tracks', [TrackController::class, 'store']);
Route::patch('/tracks/{id}', [TrackController::class, 'update']);
Route::delete('/tracks/{id}', [TrackController::class, 'destroy']);
```

- [ ] **Step 5: Green** — full suite; pint; phpstan; commit `feat(programs): program tracks — CRUD API`.

---

### Task 4: stage→track applicability (pivot + scoping endpoint + resolver)

**Files:**
- Create: migration `2026_06_18_081100_create_program_stage_track_table.php`; `app/Modules/Stages/Application/StageApplicability.php`; `app/Modules/Stages/Application/SetStageTracks.php`; `Http/Requests/SetStageTracksRequest.php`
- Modify: `StageController.php` (add `setTracks`), `StagePolicy.php` (reuse `update`/add `manageTracks`), `routes/api.php`, `DeleteTrack.php` (cascade pivot rows)
- Test: `tests/Feature/Stages/StageTrackScopingTest.php`, `tests/Unit/Stages/StageApplicabilityTest.php`

**Interfaces:** Produces `StageApplicability::appliesTo(ProgramStage $stage, ?string $trackId): bool` (global if no pivot rows; else true iff a row matches `$trackId`). Consumed by Task 6.

- [ ] **Step 1: Migration** — `program_stage_track`: `id` ulid pk, `organization_id` ulid index, `program_stage_id` ulid index, `track_id` ulid index, `timestampsTz`; `unique(['program_stage_id','track_id'])`. (No dedicated model needed; query the table via a tiny pivot model `StageTrack` OR `DB::table`. Use a `final class StageTrack extends Model { use BelongsToTenant, HasUlids; protected $table='program_stage_track'; protected $fillable=['program_stage_id','track_id']; }` so the tenant scope applies.)

- [ ] **Step 2: Failing unit test** (`StageApplicabilityTest`) — stage with no pivot rows → `appliesTo($stage, anyTrackId)` true and `appliesTo($stage, null)` true (global). Stage scoped to track T1 → `appliesTo($stage,'T1')` true; `appliesTo($stage,'T2')` false; `appliesTo($stage,null)` false.

- [ ] **Step 3: Implement resolver**
```php
<?php
declare(strict_types=1);
namespace App\Modules\Stages\Application;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageTrack;
final class StageApplicability
{
    public function appliesTo(ProgramStage $stage, ?string $trackId): bool
    {
        $scoped = StageTrack::query()->where('program_stage_id', $stage->id)->pluck('track_id');
        if ($scoped->isEmpty()) {
            return true; // global
        }
        return $trackId !== null && $scoped->contains($trackId);
    }
}
```

- [ ] **Step 4: Failing feature test** (`StageTrackScopingTest`) — `PUT /api/v1/stages/{id}/tracks {track_ids:[T1]}` → 200; pivot rows = [T1]. `PUT … {track_ids:[]}` → clears (global). track_ids containing a track from another program → 422. member without `stages.manage` → 403; cross-tenant stage id → 404.

- [ ] **Step 5: Implement** `SetStageTracks::handle(ProgramStage $stage, array $trackIds): void` (transactional: validate every track belongs to `$stage->program_id` else throw a 422-mapped exception; delete existing pivot rows for the stage; insert new ones via `StageTrack` create so org is stamped). `StageController::setTracks` mirrors existing actions (`authorize('update', $stage)`; `SetStageTracksRequest` validates `track_ids: array`, `track_ids.*: string`). Route `Route::put('/stages/{id}/tracks', [StageController::class, 'setTracks']);`. Append to `DeleteTrack::handle`: `StageTrack::query()->where('track_id',$track->id)->delete();` before deleting the track.

- [ ] **Step 6: Green** — full suite; pint; phpstan; commit `feat(stages): stage→track applicability (pivot, scoping endpoint, resolver)`.

---

### Task 5: cohort_participants — enrollment aggregate + API

**Files:**
- Create: migration `2026_06_18_081200_create_cohort_participants_table.php`; `app/Modules/Cohorts/Domain/Models/CohortParticipant.php`, `Domain/Models/EnrollmentStatus.php`; `Application/EnrollParticipant.php`, `UpdateEnrollment.php`; `Http/CohortParticipantController.php`, `Http/Requests/EnrollParticipantRequest.php`, `UpdateEnrollmentRequest.php`, `Http/Resources/CohortParticipantResource.php`
- Modify: `app/Modules/Cohorts/Policies/CohortPolicy.php` (add `manageParticipants`), `routes/api.php`, `DeleteTrack.php` (null participants' track_id)
- Test: `tests/Feature/Cohorts/EnrollmentApiTest.php`

**Interfaces:** Produces `CohortParticipant` (`cohort_id,external_user_id,track_id?,status,enrolled_at`). Consumed by Task 6 (resolve participant's track).

- [ ] **Step 1: Migration** — `cohort_participants`: `id` ulid pk, `organization_id` ulid index, `cohort_id` ulid index, `external_user_id` ulid index, `track_id` ulid nullable index, `status` string default `'active'`, `enrolled_at` timestampTz nullable, `timestampsTz`; `unique(['cohort_id','external_user_id'])`.

- [ ] **Step 2: Enum** `EnrollmentStatus: string { Active='active'; Withdrawn='withdrawn'; Completed='completed'; }` (`declare(strict_types=1)`).

- [ ] **Step 3: Model**
```php
<?php
declare(strict_types=1);
namespace App\Modules\Cohorts\Domain\Models;
use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
final class CohortParticipant extends Model
{
    use BelongsToTenant, HasUlids;
    protected $fillable = ['cohort_id', 'external_user_id', 'track_id', 'status', 'enrolled_at'];
    protected $casts = ['status' => EnrollmentStatus::class, 'enrolled_at' => 'datetime'];
}
```

- [ ] **Step 4: Failing test** (`EnrollmentApiTest`) — owner; a program + cohort + a track T1 (same program); an `ExternalUser` participant. Assert: `POST /api/v1/cohorts/{cohort}/participants {external_user_id, track_id:T1}` → 201 (enrolled, track set, status active, enrolled_at set); `POST` again same user → 422 (unique); `PATCH /api/v1/cohort-participants/{id} {track_id:null}` → 200 (untracked); `track_id` belonging to a DIFFERENT program → 422; member without `cohorts.manage` → 403; cross-tenant `{cohort}`/`{id}` → 404.

- [ ] **Step 5: Implement** `EnrollParticipant::handle(Cohort $cohort, string $externalUserId, ?string $trackId): CohortParticipant` (validate `$trackId`, if given, belongs to `$cohort->program_id` else 422-mapped exception; create with `status=active`, `enrolled_at=now()`; org forced from context). `UpdateEnrollment::handle(CohortParticipant $p, array $attrs)` (same track-program validation when track_id present). Controller mirrors `CohortController` (tenant-scoped resolution; `authorize('manageParticipants', Cohort::class)`; `CohortParticipantResource`; audit `participant.enrolled`/`participant.updated`). `CohortPolicy::manageParticipants(ExternalUser $user): bool => app(TenantContext::class)->can('cohorts.manage');`. Routes:
```php
Route::post('/cohorts/{cohort}/participants', [CohortParticipantController::class, 'store']);
Route::patch('/cohort-participants/{id}', [CohortParticipantController::class, 'update']);
```
Append to `DeleteTrack::handle`: `CohortParticipant::query()->where('track_id',$track->id)->update(['track_id'=>null]);` (inside the transaction, before deleting the track).

- [ ] **Step 6: Green** — full suite; pint; phpstan; commit `feat(cohorts): participant enrollment aggregate (cohort_participants) + track assignment`.

---

### Task 6: enforce track applicability in `AdvanceParticipantStage::enter`

**Files:**
- Create: `app/Modules/Stages/Domain/Exceptions/StageNotApplicableToTrackException.php`
- Modify: `app/Modules/Stages/Application/AdvanceParticipantStage.php` (inject `StageApplicability`; add guard)
- Test: `tests/Feature/Stages/AdvanceTrackApplicabilityTest.php`

**Interfaces:** Consumes `StageApplicability` (Task 4), `CohortParticipant` (Task 5).

- [ ] **Step 1: Exception** — `StageNotApplicableToTrackException` extends `\RuntimeException`; `forStage(string $stageId)`.

- [ ] **Step 2: Failing test** — program with a global stage G and a track-scoped stage S (scoped to T1). Participant P1 enrolled with track T1; P2 enrolled untracked (track_id null). Assert: `enter(...,G)` works for both. `enter(...,S)` works for P1; for P2 (untracked) → `StageNotApplicableToTrackException` (no status written). A participant with NO enrollment record at all → treated as untracked (global OK, scoped blocked).

- [ ] **Step 3: Implement guard** — add constructor injection of `StageApplicability` to `AdvanceParticipantStage` (alongside the existing `ExpressionEvaluator`). At the very top of `enter()` (BEFORE the prerequisite guard from Task 2):
```php
$enrollment = \App\Modules\Cohorts\Domain\Models\CohortParticipant::query()
    ->where('cohort_id', $cohort->id)
    ->where('external_user_id', $participant->id)
    ->first();
$trackId = $enrollment?->track_id; // null when untracked or not enrolled
if (! $this->applicability->appliesTo($stage, $trackId)) {
    throw \App\Modules\Stages\Domain\Exceptions\StageNotApplicableToTrackException::forStage($stage->id);
}
```
(Final `enter` guard order: track-applicability → prerequisites → published-version → entry-rule.)

- [ ] **Step 4: Green** — full suite; pint; phpstan; commit `feat(stages): enforce track applicability on participant entry`.

---

## Milestone M3 — Archival

### Task 7: program archive/unarchive + read-only guard + listing filter

**Files:**
- Create: `app/Modules/Programs/Application/ArchiveProgram.php`, `UnarchiveProgram.php`; `app/Modules/Programs/Domain/Exceptions/ProgramArchivedException.php`; `app/Modules/Programs/Application/ProgramArchivedGuard.php`
- Modify: `ProgramController.php` (`archive`, `unarchive`, `index` filter), `ProgramPolicy.php` (add `archive`), the mutating services/controllers to call the guard, `routes/api.php`
- Test: `tests/Feature/Programs/ProgramArchivalTest.php`

**Interfaces:** Produces `ArchiveProgram::handle(Program): Program`, `UnarchiveProgram::handle(Program): Program`, `ProgramArchivedGuard::ensureNotArchived(Program): void` (throws `ProgramArchivedException` → 409).

- [ ] **Step 1: Exception + guard**
```php
// ProgramArchivedException extends \RuntimeException; static forProgram(string $id).
// ProgramArchivedGuard:
final class ProgramArchivedGuard
{
    public function ensureNotArchived(Program $program): void
    {
        if ($program->status === ProgramStatus::Archived) {
            throw ProgramArchivedException::forProgram($program->id);
        }
    }
}
```
(`Program::$status` casts to `ProgramStatus` — confirm the existing cast; if it's a plain string compare to `ProgramStatus::Archived->value`.)

- [ ] **Step 2: Failing test** (`ProgramArchivalTest`) — owner; a published program with a stage + cohort + track. Assert:
  - `POST /api/v1/programs/{id}/archive` → 200, status archived; audit recorded.
  - While archived: `PATCH /programs/{id}` → 409; `POST /programs/{id}/publish` → 409; `POST /programs/{id}/stages` → 409; `POST /programs/{id}/tracks` → 409; `POST /programs/{id}/cohorts` → 409; `POST /stage-dependencies` under it → 409 (or the dependency add path); enrolling a participant in its cohort → 409; `enter()` advance → 409.
  - While archived, ALLOWED: `GET /programs/{id}` (200), `POST /programs/{id}/clone` (201 → new Draft), `POST /programs/{id}/unarchive` (200 → Draft).
  - `GET /programs` excludes the archived program by default; `GET /programs?status=archived` returns it; `GET /programs?include_archived=1` returns all.
  - member without `programs.manage` → 403 on archive/unarchive; cross-tenant id → 404.

- [ ] **Step 3: Implement** `ArchiveProgram`/`UnarchiveProgram` (transactional; set status; audit `program.archived`/`program.unarchived`). `ProgramController::archive/unarchive` mirror `publish` (resolve tenant-scoped, `authorize('archive', $program)`, return `ProgramResource`). Wire `ProgramArchivedGuard::ensureNotArchived($program)` at the start of every mutating path within a program: `UpdateProgram`/program update, `PublishProgram`, stage create/update/reorder/publish, `AddStageDependency`, `SetStageTracks`, track create/update/delete, cohort create/update under the program, `EnrollParticipant`/`UpdateEnrollment`, and `AdvanceParticipantStage::enter` (resolve the program via `$stage->program`/`$cohort->program`). Map `ProgramArchivedException` → 409 in the controllers (error envelope). `ProgramPolicy::archive => can('programs.manage')`. Listing filter in `ProgramController::index`: default `->where('status','!=',ProgramStatus::Archived->value)`; if `request('status')==='archived'` filter to archived; if `request()->boolean('include_archived')` apply no status filter. Routes:
```php
Route::post('/programs/{id}/archive', [ProgramController::class, 'archive']);
Route::post('/programs/{id}/unarchive', [ProgramController::class, 'unarchive']);
```

- [ ] **Step 4: Green** — full suite; pint; phpstan; commit `feat(programs): archive/unarchive with read-only guard + listing filter`.

---

## Milestone M4 — Isolation suite + docs + gate

### Task 8: extend the Phase-2 tenant-isolation suite

**Files:** Modify `tests/Feature/Phase2TenantIsolationTest.php`.

- [ ] **Step 1:** Add cross-tenant + authz coverage for every new endpoint, mirroring the existing matrix structure (manage-capable Org-B owner → 404 by scope or 403 by tenant-scoped `authorize()`; no-manage member → 403; list endpoints hide other-org rows). Endpoints to add: tracks index/store, `PATCH /tracks/{id}`, `DELETE /tracks/{id}`, `PUT /stages/{id}/tracks`, dependencies index/store, `DELETE /stage-dependencies/{id}`, `POST /cohorts/{cohort}/participants`, `PATCH /cohort-participants/{id}`, `POST /programs/{id}/archive`, `/unarchive`. Seed the Org-A track/dependency/enrollment in `setupOrgAData()`.
- [ ] **Step 2:** Run; if any new endpoint leaks cross-tenant data, fix the controller (tenant-scoped resolution) — do not weaken. Full suite green; pint; phpstan; commit `test: extend Phase-2 isolation suite for tracks/dependencies/enrollment/archival`.

### Task 9: docs + full gate

**Files:** Modify `docs/phase-2-notes.md`, `docs/03-data-ownership.md`, `docs/02-domain-boundaries.md`.

- [ ] **Step 1:** Document tracks (applicability rule, enrollment, untracked→global), stage dependencies (AND-semantics, cycle/cross-program rejection, entry guard), and archival (read-only 409, clone/unarchive allowed, listing filter). Verify every cited symbol/table/route/permission-key exists (`grep -rn "tenantId(" docs/` stays empty). New tables added to data-ownership.
- [ ] **Step 2:** Whole gate: `php artisan test && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M` all green. Commit `docs: Phase 2 completion notes (tracks, dependencies, archival)`.

---

## Self-Review (against the spec)

**Spec coverage:** §4.1 tracks → Tasks 3–6 (tracks CRUD, pivot+applicability, enrollment, enter-guard); §4.2 dependencies → Tasks 1–2; §4.3 archival → Task 7; §4.4 cross-cutting (tenant-owned, migrations-after-080000, reuse keys, no controller logic) → Global Constraints + every task; §5 testing → per-task + Task 8; §6 acceptance → Tasks 2,6 (guards), 1 (cycle), 7 (archival), 8 (isolation). Track-deletion cascade (§4.1) → Tasks 4 & 5 append to `DeleteTrack`.

**Placeholder scan:** complete code for migrations, models, the resolver, cycle detection, all three guards, and the archived guard; boilerplate controllers/requests/resources reference exact mirror files + enumerate methods/keys/paths/validation — no "add validation"/"similar to". The one forward-reference (`DeleteTrack` gains pivot+participant cascade in Tasks 4/5) is explicit, not a placeholder.

**Type consistency:** `StageApplicability::appliesTo(ProgramStage, ?string): bool`, `AddStageDependency::handle(ProgramStage, string): StageDependency`, `EnrollParticipant::handle(Cohort, string, ?string): CohortParticipant`, `ProgramArchivedGuard::ensureNotArchived(Program): void`, `ParticipantStageState::Completed`, `ProgramStatus::Archived`, permission keys `programs.manage`/`stages.manage`/`cohorts.manage`, and the three exception names are used consistently across tasks. Migration order: 081000 tracks → 081100 program_stage_track → 081200 cohort_participants → 081300 stage_dependencies (FK-safe; no enforced FKs).

**Deviation flagged:** `StageApplicability` injected into `AdvanceParticipantStage` changes its constructor — Task 6 updates every construction site (it's resolved from the container, so only the binding/test `app(...)` paths matter; the implementer verifies no `new AdvanceParticipantStage(...)` call sites break).
