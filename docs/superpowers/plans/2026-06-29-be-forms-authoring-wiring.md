# Forms Authoring Backend Wiring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose a real, tenant-scoped HTTP surface for authoring application forms (create → edit-as-draft → publish → fork → list versions) behind the shipped Slice 2b Forms builder, conforming exactly to the existing frontend contract.

**Architecture:** Laravel modular monolith. The backend already owns the `Form`/`FormVersion` models, the immutable content-addressed versioning kernel (`VersionPublisher`, `ImmutableWhenPublished`, `VersionStatus`, `Versionable`), `PublishForm`, and `FormDefinitionValidator`. This slice adds the HTTP layer (`FormController`, `FormVersionController`, request classes, resources, `FormPolicy`), a persistent **one-draft-per-form** lifecycle (new `CreateForm`, `SaveFormDraft`, `ForkFormDraft` services), and adapts `PublishForm` to publish the form's existing draft. The frontend does not change (MSW is dev/test-only; the deployed FE talks to these endpoints once they exist).

**Tech Stack:** PHP 8.3 / Laravel, Pest/PHPUnit, Eloquent (Postgres, ULID PKs, JSONB), `dedoc/scramble` (code-first OpenAPI), `larastan/larastan`, `laravel/pint`, `deptrac` (module boundaries).

## Global Constraints

- Target contract is fixed by `frontend/src/api/forms.ts` (8 endpoints, exact methods + status codes) and `frontend/src/schemas/forms.ts` (Zod shapes). Backend conforms; do not change the FE.
- All endpoints live under `/api/v1` in `backend/routes/api.php`, in a `Route::middleware(['auth:sanctum', 'tenant'])` group.
- Form ownership is **org-scoped, program optional**: `forms.program_id` is nullable; routes are flat `/forms` (no program prefix).
- Every tenant query is org-scoped via `BelongsToTenant`; never trust a client-supplied org id. Cross-tenant `{id}` → neutral **404** (ADR-0009), never 403.
- Authorization is deny-by-default via `FormPolicy`; frontend visibility is never authorization.
- Published `FormVersion` rows are immutable (`ImmutableWhenPublished`); editing forks a new draft; prior version ids stay resolvable.
- Form definitions are declarative data only — `FormDefinitionValidator` rejects forbidden code keys (`expr`, `expression`, `code`, `formula`, `script`, `eval`, `fn`, `rule`) and non-enumerated field types (NFR-005). Field types are the 8 `FieldType` enum cases.
- Publish is idempotent: republishing identical (canonical, key-sorted) content returns the existing version — no duplicate row. The version id is `sha256` of the canonical definition.
- Invariant: **at most one draft `FormVersion` per form**.
- Status-code mapping honors the FE contract: publish/draft conflicts → **409** (NOT 422, which is what the Stages module uses — Forms differs deliberately).
- Commit author email MUST be `274270+Byt3Ninja@users.noreply.github.com`. Sign-off line: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Use `git -c commit.gpgsign=false`. `git add` only the task's files (never `-A`). Verify `git branch --show-current` is `feat/be-forms-authoring` before every commit.
- Per-task gate (run from `backend/`): `./vendor/bin/pint --test` && `./vendor/bin/phpstan analyse --no-progress --memory-limit=512M` && `./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress` && `php artisan test --filter=<relevant>`.

## File Structure

**Create:**
- `backend/database/migrations/2026_06_29_000000_relax_forms_for_authoring.php` — nullable `content_hash`, nullable `program_id`.
- `backend/app/Modules/Forms/Application/CreateForm.php` — create form + seed empty draft.
- `backend/app/Modules/Forms/Application/SaveFormDraft.php` — replace the draft's definition.
- `backend/app/Modules/Forms/Application/ForkFormDraft.php` — new draft from a published version.
- `backend/app/Modules/Forms/Domain/Exceptions/NoDraftToPublishException.php` — publish/draft 409 signal.
- `backend/app/Modules/Forms/Http/FormController.php` — index/store/show/versions/saveDraft/publish/fork.
- `backend/app/Modules/Forms/Http/FormVersionController.php` — version show.
- `backend/app/Modules/Forms/Http/Requests/StoreFormRequest.php`
- `backend/app/Modules/Forms/Http/Requests/SaveFormDraftRequest.php`
- `backend/app/Modules/Forms/Http/Requests/ForkFormDraftRequest.php`
- `backend/app/Modules/Forms/Http/Resources/FormResource.php`
- `backend/app/Modules/Forms/Http/Resources/FormVersionResource.php`
- `backend/app/Modules/Forms/Policies/FormPolicy.php`
- `backend/tests/Feature/Forms/FormAuthoringTest.php` — the HTTP surface tests.

**Modify:**
- `backend/app/Modules/Forms/Application/PublishForm.php` — adapt to publish the draft (`handle(Form $form): FormVersion`).
- `backend/app/Modules/Forms/Domain/Models/Form.php` — add `description`? No (derived null). Add `draftVersion()` / `publishedVersions()` relations + `$fillable` already has `name`/`program_id`/`current_published_version_id`.
- `backend/routes/api.php` — register the 8 routes + controller imports.
- `backend/app/Providers/AppServiceProvider.php` — register `FormPolicy`.
- `backend/database/seeders/PermissionCatalogSeeder.php` — add `forms.manage`.
- `backend/tests/Feature/Forms/PublishFormTest.php` — rewrite to the create→draft→publish flow.
- `backend/tests/Feature/Cohorts/CohortLifecycleTest.php` — update the `PublishForm->handle($form, [...])` setup.
- `backend/openapi/openapi.json` — regenerated by Scramble.

---

### Task 1: Schema migration — relax forms for authoring

**Files:**
- Create: `backend/database/migrations/2026_06_29_000000_relax_forms_for_authoring.php`
- Test: `backend/tests/Feature/Forms/FormSchemaTest.php`

**Interfaces:**
- Produces: a `forms` table whose `program_id` is nullable, and a `form_versions` table whose `content_hash` is nullable. Later tasks rely on creating `Form` with only `name` and draft `FormVersion` with `content_hash = null`.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Forms/FormSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FormSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_form_can_be_created_without_a_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $form = Form::create(['name' => 'Intake']);

        $this->assertNull($form->program_id);
    }

    public function test_a_draft_version_can_be_stored_without_a_content_hash(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $form = Form::create(['name' => 'Intake']);
        $draft = FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $this->assertNull($draft->content_hash);
        $this->assertSame('draft', $draft->status->value);
        $this->assertSame(0, $draft->version_number);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend && php artisan test --filter=FormSchemaTest`
Expected: FAIL — `program_id`/`content_hash` NOT NULL violation.

- [ ] **Step 3: Write the migration**

Create `backend/database/migrations/2026_06_29_000000_relax_forms_for_authoring.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Forms become org-scoped reusable assets (program optional), and a form
    // carries one mutable draft version that has no content_hash until published.
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $t): void {
            $t->ulid('program_id')->nullable()->change();
        });

        Schema::table('form_versions', function (Blueprint $t): void {
            $t->string('content_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('form_versions', function (Blueprint $t): void {
            $t->string('content_hash', 64)->nullable(false)->change();
        });

        Schema::table('forms', function (Blueprint $t): void {
            $t->ulid('program_id')->nullable(false)->change();
        });
    }
};
```

Note: `->change()` requires `doctrine/dbal` on older Laravel; Laravel 11+ supports it natively. If `change()` errors, confirm the Laravel version and use the native modify path (no dbal needed on 11+).

- [ ] **Step 4: Add the `draftVersion` / `publishedVersions` relations to `Form`**

Modify `backend/app/Modules/Forms/Domain/Models/Form.php` — add below `versions()`:

```php
    /** @return HasMany<FormVersion, $this> */
    public function publishedVersions(): HasMany
    {
        return $this->hasMany(FormVersion::class)->where('status', 'published')->orderBy('version_number');
    }

    public function draftVersion(): ?FormVersion
    {
        return $this->versions()->where('status', 'draft')->first();
    }
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `cd backend && php artisan test --filter=FormSchemaTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/database/migrations/2026_06_29_000000_relax_forms_for_authoring.php backend/app/Modules/Forms/Domain/Models/Form.php backend/tests/Feature/Forms/FormSchemaTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(forms): relax schema for authoring (nullable program_id + content_hash)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: `forms.manage` permission + `FormPolicy`

**Files:**
- Create: `backend/app/Modules/Forms/Policies/FormPolicy.php`
- Modify: `backend/database/seeders/PermissionCatalogSeeder.php`, `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Feature/Forms/FormPolicyTest.php`

**Interfaces:**
- Produces: a registered `FormPolicy` with abilities `viewAny(Account)`, `view(Account, Form)`, `create(Account)`, `update(Account, Form)`, `publish(Account, Form)`. `create`/`update`/`publish` require the `forms.manage` permission via `app(TenantContext::class)->can('forms.manage')`. Controllers in later tasks call `$this->authorize(...)` against these.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Forms/FormPolicyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Domain\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class FormPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_with_forms_manage_may_create(): void
    {
        [$user, $org] = $this->bootUserWithOrg(); // operator role includes forms.manage
        $this->actingAsTenant($user, $org);

        $this->assertTrue(Gate::forUser($user)->allows('create', Form::class));
    }

    public function test_member_without_forms_manage_may_not_create(): void
    {
        [$user, $org] = $this->bootUserWithOrg(role: 'viewer'); // viewer lacks forms.manage
        $this->actingAsTenant($user, $org);

        $this->assertFalse(Gate::forUser($user)->allows('create', Form::class));
    }

    public function test_any_member_may_view_any(): void
    {
        [$user, $org] = $this->bootUserWithOrg(role: 'viewer');
        $this->actingAsTenant($user, $org);

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Form::class));
    }
}
```

Before relying on `bootUserWithOrg(role: ...)`, confirm the helper's signature in `backend/tests/TestCase.php` (or its trait). If it does not accept a role argument, seed/grant `forms.manage` the same way `StagePolicyTest` (or the nearest existing policy test) does — match that exact pattern.

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend && php artisan test --filter=FormPolicyTest`
Expected: FAIL — policy not registered / `forms.manage` unknown.

- [ ] **Step 3: Add `forms.manage` to the permission catalog**

In `backend/database/seeders/PermissionCatalogSeeder.php`, add `'forms.manage'` alongside the existing `*.manage` keys (e.g. next to `'stages.manage'`), and grant it to the same roles that hold `'stages.manage'` (match the existing role→permission grants verbatim).

- [ ] **Step 4: Create `FormPolicy`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Policies;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for Form. view/viewAny: any authenticated tenant member
 * (BelongsToTenant + tenant middleware bound the visible set). create/update/publish
 * require forms.manage. Deny-by-default for everything else.
 */
final class FormPolicy
{
    public function viewAny(Account $user): bool
    {
        return true;
    }

    public function view(Account $user, Form $form): bool
    {
        return true;
    }

    public function create(Account $user): bool
    {
        return app(TenantContext::class)->can('forms.manage');
    }

    public function update(Account $user, Form $form): bool
    {
        return app(TenantContext::class)->can('forms.manage');
    }

    public function publish(Account $user, Form $form): bool
    {
        return app(TenantContext::class)->can('forms.manage');
    }
}
```

- [ ] **Step 5: Register the policy**

In `backend/app/Providers/AppServiceProvider.php`, register `Form::class => FormPolicy::class` exactly as the existing policies (e.g. `ProgramStage::class => StagePolicy::class`) are registered (`Gate::policy(...)` or the `$policies` array — match the file).

- [ ] **Step 6: Run the test to verify it passes**

Run: `cd backend && php artisan test --filter=FormPolicyTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Forms/Policies/FormPolicy.php backend/database/seeders/PermissionCatalogSeeder.php backend/app/Providers/AppServiceProvider.php backend/tests/Feature/Forms/FormPolicyTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(forms): add forms.manage permission and FormPolicy

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: API resources (`FormVersionResource`, `FormResource`)

**Files:**
- Create: `backend/app/Modules/Forms/Http/Resources/FormVersionResource.php`, `backend/app/Modules/Forms/Http/Resources/FormResource.php`
- Test: `backend/tests/Feature/Forms/FormResourceTest.php`

**Interfaces:**
- Produces:
  - `FormVersionResource` maps a `FormVersion` → `{ id, form_id, version, status, fields, created_at, published_at }` where `version`=`version_number`, `fields`=`definition`, timestamps ISO-8601, `published_at` nullable.
  - `FormResource` maps a `Form` (with `versions` loaded) → `{ id, name, description, latest_version, published_version_ids, current_draft_version_id }` where `description`=`null`, `latest_version`=max published `version_number` (0 if none), `published_version_ids`=published version ids in version order, `current_draft_version_id`=id of the single `status=draft` version or `null`.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Forms/FormResourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Modules\Forms\Http\Resources\FormResource;
use App\Modules\Forms\Http\Resources\FormVersionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class FormResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_resource_renames_fields_to_the_fe_contract(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        $draft = FormVersion::create([
            'form_id' => $form->id,
            'definition' => [['type' => 'short_text', 'label' => 'Name', 'id' => 'f1']],
        ]);

        $out = (new FormVersionResource($draft))->toArray(Request::create('/'));

        $this->assertSame($draft->id, $out['id']);
        $this->assertSame($form->id, $out['form_id']);
        $this->assertSame(0, $out['version']);
        $this->assertSame('draft', $out['status']);
        $this->assertSame([['type' => 'short_text', 'label' => 'Name', 'id' => 'f1']], $out['fields']);
        $this->assertNull($out['published_at']);
    }

    public function test_form_resource_derives_draft_pointer_and_published_ids(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        $published = FormVersion::create([
            'form_id' => $form->id, 'status' => 'published', 'version_number' => 1,
            'content_hash' => str_repeat('a', 64), 'definition' => [], 'published_at' => now(),
        ]);
        $draft = FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $out = (new FormResource($form->load('versions')))->toArray(Request::create('/'));

        $this->assertNull($out['description']);
        $this->assertSame(1, $out['latest_version']);
        $this->assertSame([$published->id], $out['published_version_ids']);
        $this->assertSame($draft->id, $out['current_draft_version_id']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend && php artisan test --filter=FormResourceTest`
Expected: FAIL — resource classes do not exist.

- [ ] **Step 3: Create `FormVersionResource`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Resources;

use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $form_id
 * @property int $version_number
 * @property VersionStatus $status
 * @property array<int, array<string, mixed>> $definition
 * @property Carbon $created_at
 * @property Carbon|null $published_at
 */
final class FormVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'version' => $this->version_number,
            'status' => $this->status->value,
            'fields' => $this->definition,
            'created_at' => $this->created_at->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Create `FormResource`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Resources;

use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $name
 * @property Collection<int, FormVersion> $versions
 */
final class FormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $versions = $this->versions; // requires ->load('versions')
        $published = $versions->where('status.value', 'published')
            ->sortBy('version_number')->values();
        $draft = $versions->firstWhere('status.value', 'draft');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => null,
            'latest_version' => (int) ($published->max('version_number') ?? 0),
            'published_version_ids' => $published->pluck('id')->all(),
            'current_draft_version_id' => $draft?->id,
        ];
    }
}
```

Note: `status` is cast to the `VersionStatus` enum, so filter on `status.value`. If the collection-string-accessor (`where('status.value', ...)`) does not resolve the enum in this Laravel version, fall back to a closure: `->filter(fn (FormVersion $v) => $v->status->value === 'published')`. Verify which works when the test runs.

- [ ] **Step 5: Run the test to verify it passes**

Run: `cd backend && php artisan test --filter=FormResourceTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Forms/Http/Resources/FormVersionResource.php backend/app/Modules/Forms/Http/Resources/FormResource.php backend/tests/Feature/Forms/FormResourceTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(forms): API resources mapping persistence to the FE contract

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: `CreateForm` + `GET /forms`, `POST /forms`, `GET /forms/{id}`

**Files:**
- Create: `backend/app/Modules/Forms/Application/CreateForm.php`, `backend/app/Modules/Forms/Http/FormController.php`, `backend/app/Modules/Forms/Http/Requests/StoreFormRequest.php`, `backend/tests/Feature/Forms/FormAuthoringTest.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Consumes: `FormResource`, `FormPolicy` (Tasks 2–3).
- Produces:
  - `CreateForm::handle(string $name): Form` — creates a `Form` (org from tenant context, `program_id = null`) plus an empty draft `FormVersion` (`definition = []`, status draft, version_number 0). Returns the form with `versions` loaded.
  - `FormController@index` (GET `/forms`, 200), `@store` (POST `/forms`, 201), `@show` (GET `/forms/{id}`, 200). Routes named `forms.index`, `forms.store`, `forms.show`.

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Forms/FormAuthoringTest.php` (this file grows across Tasks 4–8):

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FormAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_returns_201_with_an_empty_draft(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson('/api/v1/forms', ['name' => 'Intake']);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Intake')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.latest_version', 0)
            ->assertJsonPath('data.published_version_ids', []);

        $formId = $res->json('data.id');
        $this->assertNotNull($res->json('data.current_draft_version_id'));
        $this->assertDatabaseHas('form_versions', ['form_id' => $formId, 'status' => 'draft', 'version_number' => 0]);
    }

    public function test_create_form_requires_a_name(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $this->actingAsTenantRequest($user, $org)
            ->postJson('/api/v1/forms', ['name' => ''])
            ->assertStatus(422);
    }

    public function test_create_form_requires_authentication(): void
    {
        $this->postJson('/api/v1/forms', ['name' => 'X'])->assertStatus(401);
    }

    public function test_index_lists_only_the_callers_org_forms(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        Form::create(['name' => 'Mine']);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');
        $this->actingAsTenant($other, $otherOrg);
        Form::create(['name' => 'Theirs']);

        $res = $this->actingAsTenantRequest($user, $org)->getJson('/api/v1/forms');
        $res->assertStatus(200);
        $names = array_column($res->json('data'), 'name');
        $this->assertSame(['Mine'], $names);
    }

    public function test_show_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Mine']);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/forms/{$form->id}")
            ->assertStatus(404);
    }
}
```

`actingAsTenantRequest($user, $org)` denotes "authenticated request carrying the org's `X-Organization-Id` header". Use whatever the existing feature tests use for this (inspect `StageController`/`CohortController` tests — e.g. `withHeader('X-Organization-Id', $org->id)` + `actingAs($user)` or a `Sanctum::actingAs` helper). Match that pattern exactly; do not invent a new helper.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && php artisan test --filter=FormAuthoringTest`
Expected: FAIL — routes 404 / `CreateForm` missing.

- [ ] **Step 3: Create `CreateForm`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Application;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Support\Facades\DB;

/**
 * Creates an org-scoped form (program optional) and seeds its single empty draft
 * version. The draft is intentionally not run through FormDefinitionValidator —
 * an empty working copy is valid until publish.
 */
final class CreateForm
{
    public function handle(string $name): Form
    {
        return DB::transaction(function () use ($name): Form {
            $form = Form::create(['name' => $name]);
            FormVersion::create(['form_id' => $form->id, 'definition' => []]);

            return $form->load('versions');
        });
    }
}
```

Note: `organization_id` on `Form` / `FormVersion` is set by `BelongsToTenant` from the tenant context on create — confirm by inspecting the trait; if it is not auto-populated, set `organization_id => app(TenantContext::class)->organizationId()` explicitly (match how `StageController@store` obtains it: there it copies `program->organization_id`).

- [ ] **Step 4: Create `StoreFormRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize('create', Form::class)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
```

- [ ] **Step 5: Create `FormController` (index/store/show)**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http;

use App\Modules\Forms\Application\CreateForm;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Http\Requests\StoreFormRequest;
use App\Modules\Forms\Http\Resources\FormResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class FormController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Form::class);

        $forms = Form::query()->with('versions')->orderByDesc('created_at')->get();

        return FormResource::collection($forms);
    }

    public function store(StoreFormRequest $request, CreateForm $service): JsonResponse
    {
        $this->authorize('create', Form::class);

        /** @var array{name: string} $data */
        $data = $request->validated();
        $form = $service->handle($data['name']);

        return (new FormResource($form))->response()->setStatusCode(201);
    }

    public function show(string $id): FormResource
    {
        $form = Form::query()->with('versions')->findOrFail($id);
        $this->authorize('view', $form);

        return new FormResource($form);
    }
}
```

- [ ] **Step 6: Register the routes**

In `backend/routes/api.php`, add `use App\Modules\Forms\Http\FormController;` with the other imports, and inside the `['auth:sanctum', 'tenant']` group add:

```php
        // Forms authoring (org-scoped reusable assets) — Slice: forms backend wiring
        Route::get('/forms', [FormController::class, 'index'])->name('forms.index');
        Route::post('/forms', [FormController::class, 'store'])->name('forms.store');
        Route::get('/forms/{id}', [FormController::class, 'show'])->name('forms.show');
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `cd backend && php artisan test --filter=FormAuthoringTest`
Expected: PASS (5 tests).

- [ ] **Step 8: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Forms/Application/CreateForm.php backend/app/Modules/Forms/Http/FormController.php backend/app/Modules/Forms/Http/Requests/StoreFormRequest.php backend/routes/api.php backend/tests/Feature/Forms/FormAuthoringTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(forms): create/list/show form endpoints

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Version listing — `GET /forms/{form}/versions`, `GET /form-versions/{id}`

**Files:**
- Create: `backend/app/Modules/Forms/Http/FormVersionController.php`
- Modify: `backend/app/Modules/Forms/Http/FormController.php` (add `versions`), `backend/routes/api.php`, `backend/tests/Feature/Forms/FormAuthoringTest.php`

**Interfaces:**
- Consumes: `FormVersionResource`, `FormResource`, `FormPolicy`.
- Produces: `FormController@versions` (GET `/forms/{form}/versions`, 200, `FormVersionResource` collection ordered by `version_number`); `FormVersionController@show` (GET `/form-versions/{id}`, 200). Routes `forms.versions.index`, `form-versions.show`.

- [ ] **Step 1: Add the failing tests**

Append to `FormAuthoringTest`:

```php
    public function test_versions_index_lists_in_version_order(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'status' => 'published', 'version_number' => 1, 'content_hash' => str_repeat('a', 64), 'definition' => [], 'published_at' => now()]);
        FormVersion::create(['form_id' => $form->id, 'definition' => []]); // draft

        $res = $this->actingAsTenantRequest($user, $org)->getJson("/api/v1/forms/{$form->id}/versions");
        $res->assertStatus(200);
        $this->assertCount(2, $res->json('data'));
        $this->assertSame([1, 0], array_column($res->json('data'), 'version'));
    }

    public function test_version_show_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        $v = FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/form-versions/{$v->id}")
            ->assertStatus(404);
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && php artisan test --filter=FormAuthoringTest`
Expected: FAIL — the two new routes 404.

- [ ] **Step 3: Add `versions` to `FormController`**

Add to `backend/app/Modules/Forms/Http/FormController.php` (and import `FormVersionResource` + `FormVersion`):

```php
    public function versions(string $form): AnonymousResourceCollection
    {
        $model = Form::query()->findOrFail($form);
        $this->authorize('view', $model);

        $versions = FormVersion::query()
            ->where('form_id', $model->id)
            ->orderByDesc('version_number')
            ->get();

        return FormVersionResource::collection($versions);
    }
```

- [ ] **Step 4: Create `FormVersionController`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http;

use App\Modules\Forms\Domain\Models\FormVersion;
use App\Modules\Forms\Http\Resources\FormVersionResource;
use Illuminate\Routing\Controller;

final class FormVersionController extends Controller
{
    public function show(string $id): FormVersionResource
    {
        $version = FormVersion::query()->findOrFail($id);

        return new FormVersionResource($version);
    }
}
```

(Tenant scope via `BelongsToTenant` makes the cross-tenant lookup `findOrFail` → 404. A version is world-readable within the tenant; no per-row policy needed, matching how published versions feed the public apply flow.)

- [ ] **Step 5: Register the routes**

Add `use App\Modules\Forms\Http\FormVersionController;` and, in the tenant group:

```php
        Route::get('/forms/{form}/versions', [FormController::class, 'versions'])->name('forms.versions.index');
        Route::get('/form-versions/{id}', [FormVersionController::class, 'show'])->name('form-versions.show');
```

Place `/forms/{form}/versions` and the other `/forms/...` literal segments so Laravel does not bind `versions` as an `{id}`; literal segments win, but keep `/forms/{id}` after the more specific `/forms` routes for clarity.

- [ ] **Step 6: Run to verify pass**

Run: `cd backend && php artisan test --filter=FormAuthoringTest`
Expected: PASS (7 tests total).

- [ ] **Step 7: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Forms/Http/FormVersionController.php backend/app/Modules/Forms/Http/FormController.php backend/routes/api.php backend/tests/Feature/Forms/FormAuthoringTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(forms): version listing endpoints

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: `SaveFormDraft` + `PATCH /forms/{id}/draft`

**Files:**
- Create: `backend/app/Modules/Forms/Application/SaveFormDraft.php`, `backend/app/Modules/Forms/Domain/Exceptions/NoDraftToPublishException.php`, `backend/app/Modules/Forms/Http/Requests/SaveFormDraftRequest.php`
- Modify: `backend/app/Modules/Forms/Http/FormController.php` (add `saveDraft`), `backend/routes/api.php`, `backend/tests/Feature/Forms/FormAuthoringTest.php`

**Interfaces:**
- Consumes: `FormDefinitionValidator` (existing), `FormVersionResource`, `FormPolicy`.
- Produces:
  - `NoDraftToPublishException extends \RuntimeException` — signals "form has no editable draft" (also reused by publish in Task 7).
  - `SaveFormDraft::handle(Form $form, array $fields): FormVersion` — finds the form's draft (throws `NoDraftToPublishException` if none), validates `$fields` via `FormDefinitionValidator` (only when non-empty — an empty draft is allowed mid-edit), replaces `definition`, returns the draft.
  - `FormController@saveDraft` (PATCH `/forms/{id}/draft`, 200; 404 missing form; 409 `NoDraftToPublishException`; 422 `InvalidFormDefinitionException`). Route `forms.draft.update`.

- [ ] **Step 1: Add the failing tests**

Append to `FormAuthoringTest`:

```php
    public function test_save_draft_replaces_definition(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $fields = [['type' => 'short_text', 'label' => 'Name', 'id' => 'f1', 'required' => true]];
        $res = $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/forms/{$form->id}/draft", ['fields' => $fields]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.fields.0.label', 'Name');
    }

    public function test_save_draft_rejects_a_forbidden_code_key_with_422(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/forms/{$form->id}/draft", ['fields' => [
                ['type' => 'number', 'label' => 'Score', 'expr' => 'evil()'],
            ]])
            ->assertStatus(422);
    }

    public function test_save_draft_returns_409_when_there_is_no_draft(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        // a fully published form with no working draft
        FormVersion::create(['form_id' => $form->id, 'status' => 'published', 'version_number' => 1, 'content_hash' => str_repeat('a', 64), 'definition' => [], 'published_at' => now()]);

        $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/forms/{$form->id}/draft", ['fields' => [['type' => 'short_text', 'label' => 'X', 'id' => 'a']]])
            ->assertStatus(409);
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && php artisan test --filter=FormAuthoringTest`
Expected: FAIL — `/draft` route 404.

- [ ] **Step 3: Create the exception**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Domain\Exceptions;

use RuntimeException;

/** Raised when a form has no editable draft version (nothing to save or publish). */
final class NoDraftToPublishException extends RuntimeException {}
```

- [ ] **Step 4: Create `SaveFormDraft`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Application;

use App\Modules\Forms\Domain\Exceptions\NoDraftToPublishException;
use App\Modules\Forms\Domain\FormDefinitionValidator;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;

final class SaveFormDraft
{
    public function __construct(private readonly FormDefinitionValidator $validator) {}

    /**
     * @param  array<int, array<string, mixed>>  $fields
     *
     * @throws NoDraftToPublishException when the form has no draft version
     * @throws \App\Modules\Forms\Domain\Exceptions\InvalidFormDefinitionException
     */
    public function handle(Form $form, array $fields): FormVersion
    {
        /** @var FormVersion|null $draft */
        $draft = FormVersion::query()
            ->where('form_id', $form->id)
            ->where('status', 'draft')
            ->first();

        if ($draft === null) {
            throw new NoDraftToPublishException('This form has no draft version to edit.');
        }

        if ($fields !== []) {
            $this->validator->validate($fields); // type + no-code enforcement (NFR-005)
        }

        $draft->definition = $fields;
        $draft->save();

        return $draft;
    }
}
```

- [ ] **Step 5: Create `SaveFormDraftRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveFormDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes 'update'
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['present', 'array'],
            'fields.*.type' => ['required', 'string'],
        ];
    }
}
```

- [ ] **Step 6: Add `saveDraft` to `FormController`**

Add (import `SaveFormDraft`, `SaveFormDraftRequest`, `NoDraftToPublishException`, `InvalidFormDefinitionException`, `FormVersionResource`):

```php
    public function saveDraft(SaveFormDraftRequest $request, SaveFormDraft $service, string $id): JsonResponse
    {
        $form = Form::query()->findOrFail($id);
        $this->authorize('update', $form);

        /** @var array{fields: array<int, array<string, mixed>>} $data */
        $data = $request->validated();

        try {
            $version = $service->handle($form, $data['fields']);
        } catch (NoDraftToPublishException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (InvalidFormDefinitionException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['fields' => [$e->getMessage()]]], 422);
        }

        return (new FormVersionResource($version))->response()->setStatusCode(200);
    }
```

- [ ] **Step 7: Register the route**

In the tenant group:

```php
        Route::patch('/forms/{id}/draft', [FormController::class, 'saveDraft'])->name('forms.draft.update');
```

- [ ] **Step 8: Run to verify pass**

Run: `cd backend && php artisan test --filter=FormAuthoringTest`
Expected: PASS (10 tests total).

- [ ] **Step 9: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Forms/Application/SaveFormDraft.php backend/app/Modules/Forms/Domain/Exceptions/NoDraftToPublishException.php backend/app/Modules/Forms/Http/Requests/SaveFormDraftRequest.php backend/app/Modules/Forms/Http/FormController.php backend/routes/api.php backend/tests/Feature/Forms/FormAuthoringTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(forms): save-draft endpoint (declarative validation, 409/422)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Adapt `PublishForm` to publish the draft + `POST /forms/{id}/publish`

> Characterization-first: the existing `PublishFormTest` is green at the start of this task (baseline). Run it before changing anything, then adapt the service and rewrite the affected tests to the new flow.

**Files:**
- Modify: `backend/app/Modules/Forms/Application/PublishForm.php`, `backend/app/Modules/Forms/Http/FormController.php` (add `publish`), `backend/routes/api.php`, `backend/tests/Feature/Forms/PublishFormTest.php` (rewrite), `backend/tests/Feature/Cohorts/CohortLifecycleTest.php` (update setup), `backend/tests/Feature/Forms/FormAuthoringTest.php` (add HTTP tests)

**Interfaces:**
- Consumes: `CreateForm`, `SaveFormDraft` (Tasks 4, 6), `FormDefinitionValidator`, `VersionPublisher`, `AuditLogger`, `NoDraftToPublishException`.
- Produces:
  - `PublishForm::handle(Form $form): FormVersion` — **new signature** (no `$definition` argument). Publishes the form's draft per the spec state machine; idempotent; throws `NoDraftToPublishException` when there is no draft or the draft is empty.
  - `FormController@publish` (POST `/forms/{id}/publish`, 200; 404 missing form; 409 `NoDraftToPublishException`). Route `forms.publish`.

- [ ] **Step 1: Baseline — run the existing PublishForm test**

Run: `cd backend && php artisan test --filter=PublishFormTest`
Expected: PASS (the current `handle($form, $definition)` behavior). Record this as the pre-change baseline.

- [ ] **Step 2: Rewrite `PublishFormTest` to the new flow (failing)**

Replace `backend/tests/Feature/Forms/PublishFormTest.php` with tests that drive create→draft→publish. Helper builds a form with a saved draft, then publishes:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Application\CreateForm;
use App\Modules\Forms\Application\PublishForm;
use App\Modules\Forms\Application\SaveFormDraft;
use App\Modules\Forms\Domain\Exceptions\NoDraftToPublishException;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Versioning\VersionStateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublishFormTest extends TestCase
{
    use RefreshDatabase;

    private function definition(): array
    {
        return [
            ['type' => 'short_text', 'label' => 'Name', 'id' => 'f1', 'required' => true],
            ['type' => 'single_select', 'label' => 'Stage', 'id' => 'f2', 'options' => ['idea', 'mvp']],
        ];
    }

    /** Create a form (seeds draft) under tenant context and save a draft definition. */
    private function formWithDraft(array $fields): Form
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = $this->app->make(CreateForm::class)->handle('Intake');
        $this->app->make(SaveFormDraft::class)->handle($form, $fields);

        return $form->refresh();
    }

    public function test_publishes_the_draft_immutably_with_a_content_hash(): void
    {
        $form = $this->formWithDraft($this->definition());

        $version = $this->app->make(PublishForm::class)->handle($form);

        $this->assertSame('published', $version->status->value);
        $this->assertSame(1, $version->version_number);
        $this->assertNotNull($version->published_at);
        $this->assertSame(64, strlen((string) $version->content_hash));
        $this->assertSame($version->id, $form->fresh()->current_published_version_id);
        $this->assertNull($form->fresh()->draftVersion(), 'the draft was promoted, leaving no open draft');

        $this->expectException(VersionStateException::class);
        $version->update(['definition' => []]);
    }

    public function test_publish_with_no_draft_throws(): void
    {
        $form = $this->formWithDraft($this->definition());
        $this->app->make(PublishForm::class)->handle($form); // promotes the only draft

        $this->expectException(NoDraftToPublishException::class);
        $this->app->make(PublishForm::class)->handle($form->refresh());
    }

    public function test_publish_of_empty_draft_throws(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = $this->app->make(CreateForm::class)->handle('Intake'); // empty draft

        $this->expectException(NoDraftToPublishException::class);
        $this->app->make(PublishForm::class)->handle($form);
    }

    public function test_identical_republish_is_idempotent(): void
    {
        $form = $this->formWithDraft($this->definition());
        $a = $this->app->make(PublishForm::class)->handle($form);

        // fork a new draft with the same content, then republish → same version, no new row
        $this->app->make(\App\Modules\Forms\Application\ForkFormDraft::class)->handle($form->refresh(), $a->id);
        $b = $this->app->make(PublishForm::class)->handle($form->refresh());

        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('form_versions', 1);
    }
}
```

(The idempotent test depends on `ForkFormDraft` from Task 8. If executing tasks strictly in order, mark that one `@group pending-fork` / skip it until Task 8, then un-skip. The reviewer should confirm it is enabled by end of Task 8.)

- [ ] **Step 3: Run to verify failure**

Run: `cd backend && php artisan test --filter=PublishFormTest`
Expected: FAIL — `handle($form)` arity mismatch / `NoDraftToPublishException` not thrown.

- [ ] **Step 4: Adapt `PublishForm`**

Replace the body of `backend/app/Modules/Forms/Application/PublishForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Application;

use App\Modules\Forms\Domain\Exceptions\NoDraftToPublishException;
use App\Modules\Forms\Domain\FormDefinitionValidator;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Audit\AuditLogger;
use App\Shared\Versioning\VersionPublisher;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the form's single draft version as an immutable, content-addressed
 * version. The version id is sha256 of the canonical (key-sorted) definition;
 * republishing content identical to an existing published version returns that
 * version and discards the redundant draft (idempotent, no duplicate row).
 */
final class PublishForm
{
    public function __construct(
        private readonly FormDefinitionValidator $validator,
        private readonly VersionPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws NoDraftToPublishException when there is no draft, or the draft is empty
     */
    public function handle(Form $form): FormVersion
    {
        /** @var FormVersion|null $draft */
        $draft = FormVersion::query()
            ->where('form_id', $form->id)
            ->where('status', 'draft')
            ->first();

        if ($draft === null || $draft->definition === []) {
            throw new NoDraftToPublishException('This form has no publishable draft.');
        }

        $this->validator->validate($draft->definition);
        $hash = hash('sha256', $this->validator->canonicalJson($draft->definition));

        $version = DB::transaction(function () use ($form, $draft, $hash): FormVersion {
            /** @var FormVersion|null $existing */
            $existing = FormVersion::query()
                ->where('form_id', $form->id)
                ->where('status', 'published')
                ->where('content_hash', $hash)
                ->first();

            if ($existing !== null) {
                $draft->delete();                 // discard redundant draft (avoids UNIQUE collision)
                $form->update(['current_published_version_id' => $existing->id]);

                return $existing;
            }

            $draft->content_hash = $hash;         // still a draft row — mutation allowed
            $draft->save();
            $this->publisher->publish($draft);    // version_number, Published, published_at
            $form->update(['current_published_version_id' => $draft->id]);

            return $draft->refresh();
        });

        $this->audit->record('form.published', 'form_version', $version->id, [], [
            'content_hash' => $hash,
            'version_number' => $version->version_number,
        ]);

        return $version;
    }
}
```

- [ ] **Step 5: Add `publish` to `FormController`**

Add (import `PublishForm`):

```php
    public function publish(PublishForm $service, string $id): JsonResponse
    {
        $form = Form::query()->findOrFail($id);
        $this->authorize('publish', $form);

        try {
            $version = $service->handle($form);
        } catch (NoDraftToPublishException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new FormVersionResource($version))->response()->setStatusCode(200);
    }
```

- [ ] **Step 6: Add HTTP publish tests to `FormAuthoringTest`**

```php
    public function test_publish_promotes_the_draft_and_returns_200(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'definition' => [['type' => 'short_text', 'label' => 'Name', 'id' => 'a']]]);

        $res = $this->actingAsTenantRequest($user, $org)->postJson("/api/v1/forms/{$form->id}/publish");
        $res->assertStatus(200)->assertJsonPath('data.status', 'published')->assertJsonPath('data.version', 1);
    }

    public function test_publish_returns_409_when_nothing_to_publish(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'definition' => []]); // empty draft

        $this->actingAsTenantRequest($user, $org)->postJson("/api/v1/forms/{$form->id}/publish")->assertStatus(409);
    }
```

- [ ] **Step 7: Update `CohortLifecycleTest` setup**

In `backend/tests/Feature/Cohorts/CohortLifecycleTest.php`, the helper around line 34–35 currently does:

```php
$form = Form::create(['program_id' => $cohort->program_id, 'name' => 'Intake']);
$version = $this->app->make(PublishForm::class)->handle($form, [ ...definition... ]);
```

Replace with the new flow (seed draft → save → publish; `program_id` now optional):

```php
$form = Form::create(['program_id' => $cohort->program_id, 'name' => 'Intake']);
FormVersion::create(['form_id' => $form->id, 'definition' => []]);
$this->app->make(\App\Modules\Forms\Application\SaveFormDraft::class)->handle($form, [
    ['type' => 'short_text', 'label' => 'Name', 'id' => 'f1', 'required' => true],
]);
$version = $this->app->make(PublishForm::class)->handle($form->refresh());
```

Keep the rest of the helper (the `OpenCohort->handle($cohort, $form, ...)` calls) unchanged — `$version` is still the published `FormVersion` those assertions expect. Add `use App\Modules\Forms\Domain\Models\FormVersion;` if missing.

- [ ] **Step 8: Run the affected suites to verify pass**

Run: `cd backend && php artisan test --filter=PublishFormTest && php artisan test --filter=FormAuthoringTest && php artisan test --filter=CohortLifecycleTest`
Expected: PASS (PublishFormTest with the fork-dependent case skipped until Task 8; FormAuthoringTest 12; CohortLifecycleTest green).

- [ ] **Step 9: Register the route, run the gate, commit**

Add to the tenant group: `Route::post('/forms/{id}/publish', [FormController::class, 'publish'])->name('forms.publish');`

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Forms/Application/PublishForm.php backend/app/Modules/Forms/Http/FormController.php backend/routes/api.php backend/tests/Feature/Forms/PublishFormTest.php backend/tests/Feature/Forms/FormAuthoringTest.php backend/tests/Feature/Cohorts/CohortLifecycleTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(forms): publish-the-draft endpoint; adapt PublishForm signature

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: `ForkFormDraft` + `POST /forms/{id}/fork`

**Files:**
- Create: `backend/app/Modules/Forms/Application/ForkFormDraft.php`, `backend/app/Modules/Forms/Http/Requests/ForkFormDraftRequest.php`
- Modify: `backend/app/Modules/Forms/Http/FormController.php` (add `fork`), `backend/routes/api.php`, `backend/tests/Feature/Forms/FormAuthoringTest.php`; un-skip the idempotent test in `PublishFormTest`

**Interfaces:**
- Consumes: `FormVersionResource`, `FormPolicy`.
- Produces:
  - `ForkFormDraft::handle(Form $form, string $fromVersionId): FormVersion` — if the form already has a draft, returns it unchanged (invariant: ≤1 draft). Else requires `$fromVersionId` to be a **published** version of this form (else throws `ModelNotFoundException` → 404) and creates a new draft `FormVersion` copying its `definition` (deep copy).
  - `FormController@fork` (POST `/forms/{id}/fork`, 201). Route `forms.fork`.

- [ ] **Step 1: Add the failing tests**

Append to `FormAuthoringTest`:

```php
    public function test_fork_creates_a_new_draft_from_a_published_version(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        $published = FormVersion::create(['form_id' => $form->id, 'status' => 'published', 'version_number' => 1, 'content_hash' => str_repeat('a', 64), 'definition' => [['type' => 'short_text', 'label' => 'Name', 'id' => 'a']], 'published_at' => now()]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/forms/{$form->id}/fork", ['from_version_id' => $published->id]);

        $res->assertStatus(201)->assertJsonPath('data.status', 'draft')->assertJsonPath('data.fields.0.label', 'Name');
        $this->assertSame(2, FormVersion::where('form_id', $form->id)->count());
    }

    public function test_fork_with_an_unpublished_or_foreign_version_is_404(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        $draft = FormVersion::create(['form_id' => $form->id, 'definition' => []]); // not published

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/forms/{$form->id}/fork", ['from_version_id' => $draft->id])
            ->assertStatus(404);
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `cd backend && php artisan test --filter=FormAuthoringTest`
Expected: FAIL — `/fork` route 404.

- [ ] **Step 3: Create `ForkFormDraft`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Application;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;

final class ForkFormDraft
{
    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when $fromVersionId
     *         is not a published version of $form
     */
    public function handle(Form $form, string $fromVersionId): FormVersion
    {
        /** @var FormVersion|null $existingDraft */
        $existingDraft = FormVersion::query()
            ->where('form_id', $form->id)
            ->where('status', 'draft')
            ->first();

        if ($existingDraft !== null) {
            return $existingDraft; // invariant: at most one draft per form
        }

        /** @var FormVersion $source */
        $source = FormVersion::query()
            ->where('form_id', $form->id)
            ->where('status', 'published')
            ->findOrFail($fromVersionId);

        return FormVersion::create([
            'form_id' => $form->id,
            'definition' => json_decode(json_encode($source->definition), true), // deep copy
        ]);
    }
}
```

- [ ] **Step 4: Create `ForkFormDraftRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForkFormDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['from_version_id' => ['required', 'string']];
    }
}
```

- [ ] **Step 5: Add `fork` to `FormController`**

Add (import `ForkFormDraft`, `ForkFormDraftRequest`):

```php
    public function fork(ForkFormDraftRequest $request, ForkFormDraft $service, string $id): JsonResponse
    {
        $form = Form::query()->findOrFail($id);
        $this->authorize('update', $form);

        /** @var array{from_version_id: string} $data */
        $data = $request->validated();
        $draft = $service->handle($form, $data['from_version_id']); // ModelNotFoundException → 404

        return (new FormVersionResource($draft))->response()->setStatusCode(201);
    }
```

(`ModelNotFoundException` renders as 404 by Laravel's default handler — confirm the app does not override it to a different status.)

- [ ] **Step 6: Register the route + un-skip the idempotent PublishForm test**

Add to the tenant group: `Route::post('/forms/{id}/fork', [FormController::class, 'fork'])->name('forms.fork');`
Remove the skip/`@group pending-fork` marker from `test_identical_republish_is_idempotent` in `PublishFormTest`.

- [ ] **Step 7: Run to verify pass**

Run: `cd backend && php artisan test --filter=FormAuthoringTest && php artisan test --filter=PublishFormTest`
Expected: PASS (FormAuthoringTest 14; PublishFormTest including the idempotent case).

- [ ] **Step 8: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Forms/Application/ForkFormDraft.php backend/app/Modules/Forms/Http/Requests/ForkFormDraftRequest.php backend/app/Modules/Forms/Http/FormController.php backend/routes/api.php backend/tests/Feature/Forms/FormAuthoringTest.php backend/tests/Feature/Forms/PublishFormTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(forms): fork-draft endpoint (one-draft invariant)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 9: Regenerate OpenAPI + full-suite sweep

**Files:**
- Modify: `backend/openapi/openapi.json` (regenerated)

**Interfaces:**
- Consumes: all routes/controllers from Tasks 4–8.
- Produces: an OpenAPI document including the 8 forms routes; green `OpenApiSpecTest` + Spectral lint; full backend suite green.

- [ ] **Step 1: Regenerate the OpenAPI document**

Determine the project's export command (check `composer.json` scripts and `backend/config/scramble.php` for the output path). The standard Scramble command is:

Run: `cd backend && php artisan scramble:export --path=openapi/openapi.json`
If the command name/flag differs, use the one the repo already uses to produce `backend/openapi/openapi.json` (whatever generated the current file). Then:

Run: `cd backend && git diff --stat openapi/openapi.json`
Expected: the diff adds paths `/forms`, `/forms/{id}`, `/forms/{form}/versions`, `/form-versions/{id}`, `/forms/{id}/draft`, `/forms/{id}/publish`, `/forms/{id}/fork`.

- [ ] **Step 2: Run the contract test + Spectral lint**

Run: `cd backend && php artisan test --filter=OpenApiSpecTest`
Expected: PASS.
Run (from repo root): `npx --yes @stoplight/spectral-cli lint backend/openapi/openapi.json --ruleset .spectral.yaml --fail-severity=error`
Expected: no errors. If Spectral flags missing operation descriptions/tags, add the minimal PHPDoc/attributes Scramble needs on the new controller actions to satisfy the ruleset, then regenerate.

- [ ] **Step 3: Full backend gate**

Run: `cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress && php artisan test`
Expected: all green. Resolve any deptrac boundary violation (the Forms HTTP layer may depend only on Forms + Shared + Identity::Account for policy typehints — if deptrac flags a new edge, add the allowed dependency to `deptrac.yaml` only if it matches an existing allowed pattern; otherwise rework to avoid the cross-module reference).

- [ ] **Step 4: Commit**

```bash
cd backend && git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add openapi/openapi.json
# include any controller doc/attribute tweaks made for Spectral:
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add app/Modules/Forms/Http
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "chore(forms): regenerate OpenAPI for forms authoring endpoints

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- §3 ownership (org-scoped, program optional) → Task 1 (nullable program_id) + Task 4 (`CreateForm` with no program). ✓
- §3 draft lifecycle (one mutable draft) → Task 4 (seed), Task 6 (edit), Task 7 (promote), Task 8 (fork; ≤1 invariant). ✓
- §3 `PublishForm` adapted + callers updated + characterization first → Task 7. ✓
- §3 derived `current_draft_version_id`/`description` → Task 3 (`FormResource`). ✓
- §4 migration (nullable content_hash + program_id) → Task 1. ✓
- §5 publish state machine + idempotency-before-hash → Task 7 (`PublishForm`). ✓
- §6 all 8 endpoints + status codes → Tasks 4 (3), 5 (2), 6 (1), 7 (1), 8 (1) = 8. ✓
- §7 contract mapping (field renames, derived fields, no-code passthrough) → Task 3 + Task 6 (validator on save). ✓
- §8 `FormPolicy` deny-by-default + `forms.manage` + tenant 404 → Task 2 + cross-tenant tests in Tasks 4/5. ✓
- §9 testing (characterization, all endpoint cases, OpenAPI regen) → Tasks 1–9. ✓
- §10 invariants (≤1 draft, description read-only) → Task 8 + Task 3. ✓

**Placeholder scan:** No `TBD`/`add error handling`/`similar to`. Every code step shows complete code. The few "confirm the helper/command against the repo" notes are explicit verification instructions (the exact helper names differ per repo and must be matched, not guessed) — not deferred implementation.

**Type consistency:** `PublishForm::handle(Form): FormVersion`, `SaveFormDraft::handle(Form, array): FormVersion`, `CreateForm::handle(string): Form`, `ForkFormDraft::handle(Form, string): FormVersion`, `NoDraftToPublishException` — names/signatures consistent across Tasks 4–8. `FormVersionResource` field names (`version`, `fields`, `published_at`) and `FormResource` (`description`, `latest_version`, `published_version_ids`, `current_draft_version_id`) match `frontend/src/schemas/forms.ts` exactly.

**Cross-task ordering note:** `PublishFormTest::test_identical_republish_is_idempotent` (Task 7) depends on `ForkFormDraft` (Task 8); it is explicitly skipped in Task 7 and un-skipped in Task 8.
