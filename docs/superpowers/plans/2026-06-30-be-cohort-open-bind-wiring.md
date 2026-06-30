# Cohort Open / Bind-Form Backend Wiring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose `POST /cohorts/{id}/bind-form` and `POST /cohorts/{id}/open` as real, tenant-scoped endpoints behind the shipped Slice 2a cohort lifecycle UI, refactoring `OpenCohort` to operate on already-bound cohort state.

**Architecture:** Laravel modular monolith. The Cohorts module already has the domain (`OpenCohort`, `cohorts.form_version_id`, `EntitlementService('cohort.open')`, audit, `isAcceptingSubmissions`). This slice adds a new `BindCohortForm` service + a `CohortStateException` (→409), refactors `OpenCohort` to `handle(Cohort)`, adds two thin controller actions + routes + a `CohortPolicy` ability pair, and starts emitting `bound_form_version_id` on `CohortResource`. The frontend does not change (MSW is dev/test-only).

**Tech Stack:** PHP 8.3 / Laravel, Pest/PHPUnit, Eloquent (Postgres, ULID PKs), `dedoc/scramble` (OpenAPI), `larastan/larastan`, `laravel/pint`, `deptrac`.

## Global Constraints

- Target contract is fixed by `frontend/src/api/cohorts.ts` (`openCohort` → `POST /cohorts/{id}/open` no body; `bindCohortForm` → `POST /cohorts/{id}/bind-form` `{form_version_id}`) and `frontend/src/schemas/cohorts.ts` (`Cohort` incl. `bound_form_version_id`). Backend conforms; do not change the FE.
- Both endpoints live under `/api/v1` in the existing `Route::middleware(['auth:sanctum', 'tenant'])` group.
- Org-scoped via `BelongsToTenant`; cross-tenant `{id}` → neutral **404** (ADR-0009), never 403.
- Authorized by `CohortPolicy`, deny-by-default; `open`/`bindForm` require `cohorts.manage` (already granted to the owner role via `CreateOrganization`).
- **Open preconditions:** `status=draft` AND a bound published `form_version_id` (else **409**); enrollment window optional (null = no time bound). `open` does NOT set the window.
- **Bind-form rules:** draft-only (non-draft → **409**); the referenced `FormVersion` must be `published` and in the cohort's org (else **404**); same version already bound → idempotent **200**; a *different* version bound → **409**.
- 409 conflicts surface from a typed `CohortStateException extends \RuntimeException` (`App\Modules\Cohorts\Domain\Exceptions`), caught in the controller. A missing cohort or form version → `ModelNotFoundException` → Laravel's default **404**.
- Audit actions come from the `App\Shared\Audit\AuditAction` enum (the canonical FR-052 registry) — add a `CohortFormBound` case; reuse `CohortOpened`.
- `OpenCohort` refactors to `handle(Cohort): Cohort`. Its SOLE `handle` caller is `backend/tests/Feature/Cohorts/CohortLifecycleTest.php` (5 call sites). `CloseCohort` is unchanged and unexposed.
- Commit author MUST be `274270+Byt3Ninja@users.noreply.github.com`; commit body ends with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Use `git -c commit.gpgsign=false`. `git add` only the task's files (never `-A`). Verify `git branch --show-current` is `feat/be-cohort-open-bind` before every commit.
- Feature-test hygiene (carried from the Forms slice): use the existing `$this->actingAsTenantRequest($user, $org)` helper in `backend/tests/TestCase.php` (resets the `TenantContext` singleton, `actingAs($user,'web')`, sets `X-Organization-Id`). `bootUserWithOrg()` / `actingAsTenant()` exist. A published `FormVersion` fixture needs a distinct `content_hash` (UNIQUE(form_id, content_hash)) — use `str_repeat('a',64)` etc.
- Per-task gate (from `backend/`): `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress && php artisan test --filter=<relevant>`.

## File Structure

**Create:**
- `backend/app/Modules/Cohorts/Domain/Exceptions/CohortStateException.php` — 409 signal.
- `backend/app/Modules/Cohorts/Application/BindCohortForm.php` — bind a published form version to a draft cohort.
- `backend/app/Modules/Cohorts/Http/Requests/BindCohortFormRequest.php` — validates `form_version_id`.
- `backend/tests/Feature/Cohorts/CohortOpenBindTest.php` — the HTTP surface tests.

**Modify:**
- `backend/app/Shared/Audit/AuditAction.php` — add `CohortFormBound`.
- `backend/app/Modules/Cohorts/Http/Resources/CohortResource.php` — emit `bound_form_version_id`.
- `backend/app/Modules/Cohorts/Policies/CohortPolicy.php` — add `open`/`bindForm`.
- `backend/app/Modules/Cohorts/Application/OpenCohort.php` — refactor to `handle(Cohort)`.
- `backend/app/Modules/Cohorts/Http/CohortController.php` — add `bindForm` + `open`.
- `backend/routes/api.php` — register the 2 routes.
- `backend/tests/Feature/Cohorts/CohortLifecycleTest.php` — migrate the 5 `OpenCohort::handle` call sites.
- `backend/openapi/openapi.json` — regenerated.

---

### Task 1: Foundations — exception, audit action, resource field, policy abilities

**Files:**
- Create: `backend/app/Modules/Cohorts/Domain/Exceptions/CohortStateException.php`
- Modify: `backend/app/Shared/Audit/AuditAction.php`, `backend/app/Modules/Cohorts/Http/Resources/CohortResource.php`, `backend/app/Modules/Cohorts/Policies/CohortPolicy.php`
- Test: `backend/tests/Feature/Cohorts/CohortFoundationsTest.php`

**Interfaces:**
- Produces: `App\Modules\Cohorts\Domain\Exceptions\CohortStateException` (extends `\RuntimeException`); `AuditAction::CohortFormBound` (`'cohort.form_bound'`); `CohortResource` emits `bound_form_version_id` (= `form_version_id`, nullable); `CohortPolicy::open(Account, Cohort)` and `CohortPolicy::bindForm(Account, Cohort)` → require `cohorts.manage`.

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/Cohorts/CohortFoundationsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Http\Resources\CohortResource;
use App\Shared\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CohortFoundationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_action_has_cohort_form_bound(): void
    {
        $this->assertSame('cohort.form_bound', AuditAction::CohortFormBound->value);
    }

    public function test_resource_exposes_bound_form_version_id(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = Cohort::create(['program_id' => (string) \Illuminate\Support\Str::ulid(), 'name' => 'Spring', 'status' => 'draft']);
        $cohort->update(['form_version_id' => 'fv_123']);

        $out = (new CohortResource($cohort->refresh()))->toArray(Request::create('/'));

        $this->assertSame('fv_123', $out['bound_form_version_id']);
    }

    public function test_open_and_bindform_require_cohorts_manage(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = Cohort::create(['program_id' => (string) \Illuminate\Support\Str::ulid(), 'name' => 'Spring', 'status' => 'draft']);

        // owner role has cohorts.manage
        $this->assertTrue(Gate::forUser($user)->allows('open', $cohort));
        $this->assertTrue(Gate::forUser($user)->allows('bindForm', $cohort));
    }
}
```

If `bootUserWithOrg()` does not return a member holding `cohorts.manage` by default, mirror exactly how `CohortPolicyTest` (or the nearest existing cohort/stage policy test) constructs a member WITH the permission. Confirm the helper before relying on it.

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd backend && php artisan test --filter=CohortFoundationsTest`
Expected: FAIL — `AuditAction::CohortFormBound` undefined, `bound_form_version_id` key missing, `open`/`bindForm` abilities undefined.

- [ ] **Step 3: Add the `CohortFormBound` audit action**

In `backend/app/Shared/Audit/AuditAction.php`, add next to `CohortOpened`:

```php
    case CohortFormBound = 'cohort.form_bound';
```

- [ ] **Step 4: Create the exception**

`backend/app/Modules/Cohorts/Domain/Exceptions/CohortStateException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Domain\Exceptions;

use RuntimeException;

/** Raised when a cohort is not in a valid state for the requested lifecycle transition (→ HTTP 409). */
final class CohortStateException extends RuntimeException {}
```

- [ ] **Step 5: Emit `bound_form_version_id` from `CohortResource`**

In `backend/app/Modules/Cohorts/Http/Resources/CohortResource.php`, add to the `toArray` array (after `'timeline'`) and to the `@property-read` block:

```php
            'bound_form_version_id' => $this->form_version_id,
```
```php
 * @property-read string|null $form_version_id
```

- [ ] **Step 6: Add the policy abilities**

In `backend/app/Modules/Cohorts/Policies/CohortPolicy.php`, add:

```php
    /** Opening a cohort requires the `cohorts.manage` permission. */
    public function open(Account $user, Cohort $cohort): bool
    {
        return app(TenantContext::class)->can('cohorts.manage');
    }

    /** Binding a form to a cohort requires the `cohorts.manage` permission. */
    public function bindForm(Account $user, Cohort $cohort): bool
    {
        return app(TenantContext::class)->can('cohorts.manage');
    }
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `cd backend && php artisan test --filter=CohortFoundationsTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Cohorts/Domain/Exceptions/CohortStateException.php backend/app/Shared/Audit/AuditAction.php backend/app/Modules/Cohorts/Http/Resources/CohortResource.php backend/app/Modules/Cohorts/Policies/CohortPolicy.php backend/tests/Feature/Cohorts/CohortFoundationsTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(cohorts): bind/open foundations (exception, audit action, resource field, policy)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: `BindCohortForm` service + `POST /cohorts/{id}/bind-form`

**Files:**
- Create: `backend/app/Modules/Cohorts/Application/BindCohortForm.php`, `backend/app/Modules/Cohorts/Http/Requests/BindCohortFormRequest.php`, `backend/tests/Feature/Cohorts/CohortOpenBindTest.php`
- Modify: `backend/app/Modules/Cohorts/Http/CohortController.php`, `backend/routes/api.php`

**Interfaces:**
- Consumes: `CohortStateException`, `AuditAction::CohortFormBound`, `CohortResource`, `CohortPolicy@bindForm`, `App\Modules\Forms\Domain\Models\FormVersion` (org-scoped via `BelongsToTenant`), `App\Shared\Audit\AuditLogger`.
- Produces: `BindCohortForm::handle(Cohort $cohort, string $formVersionId): Cohort`; `CohortController@bindForm` (POST `/cohorts/{id}/bind-form`, route `cohorts.bind-form`).

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/Cohorts/CohortOpenBindTest.php` (this file grows in Task 3):

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CohortOpenBindTest extends TestCase
{
    use RefreshDatabase;

    /** A published form version in the current tenant. */
    private function publishedVersion(string $hash = null): FormVersion
    {
        $form = Form::create(['name' => 'Intake']);

        return FormVersion::create([
            'form_id' => $form->id,
            'status' => 'published',
            'version_number' => 1,
            'content_hash' => $hash ?? str_repeat('a', 64),
            'definition' => [['type' => 'short_text', 'label' => 'Name', 'id' => 'a']],
            'published_at' => now(),
        ]);
    }

    private function draftCohort(): Cohort
    {
        return Cohort::create(['program_id' => (string) Str::ulid(), 'name' => 'Spring', 'status' => 'draft']);
    }

    public function test_bind_form_sets_the_version_and_returns_200(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $version->id]);

        $res->assertStatus(200)->assertJsonPath('data.bound_form_version_id', $version->id);
    }

    public function test_bind_same_version_is_idempotent(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $version->id])
            ->assertStatus(200);
    }

    public function test_bind_different_version_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $v1 = $this->publishedVersion(str_repeat('a', 64));
        $cohort->update(['form_version_id' => $v1->id]);
        $v2 = $this->publishedVersion(str_repeat('b', 64));

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $v2->id])
            ->assertStatus(409);
    }

    public function test_bind_on_non_draft_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id, 'status' => 'open']);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $version->id])
            ->assertStatus(409);
    }

    public function test_bind_unknown_or_draft_version_is_404(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        // a draft (unpublished) version is not bindable
        $form = Form::create(['name' => 'Intake']);
        $draftVersion = FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $draftVersion->id])
            ->assertStatus(404);
    }

    public function test_bind_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $version->id])
            ->assertStatus(404);
    }

    public function test_bind_requires_cohorts_manage_403(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();

        // a member without cohorts.manage — mirror the nearest existing low-privilege-member feature test
        [$viewer, $viewerOrg] = $this->bootUserWithOrg('Viewer Org'); // adjust per the repo's helper to drop cohorts.manage
        // NOTE: implementer — construct $viewer as a member of $org WITHOUT cohorts.manage, following the
        // pattern used by the Forms slice's test_member_without_forms_manage_cannot_create_form (StageApiTest pattern).
        $this->markTestIncomplete('replace with a real no-permission member per the repo helper');
    }
}
```

For `test_bind_requires_cohorts_manage_403`, do NOT leave `markTestIncomplete`. Replace it with a real no-permission member exactly as the Forms slice did (`test_member_without_forms_manage_cannot_create_form`, which followed the `StageApiTest` pattern of an active membership with no permission keys) and assert **403**. If that construction is genuinely unavailable, report it rather than shipping an incomplete test.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd backend && php artisan test --filter=CohortOpenBindTest`
Expected: FAIL — `/bind-form` route 404 / `BindCohortForm` missing.

- [ ] **Step 3: Create `BindCohortForm`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Application;

use App\Modules\Cohorts\Domain\Exceptions\CohortStateException;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Binds a published form version to a draft cohort. Idempotent when the same
 * version is re-bound; refuses (409) a different version or a non-draft cohort.
 */
final class BindCohortForm
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws CohortStateException on a non-draft cohort or a conflicting bound version
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when no published version with that id exists in the tenant
     */
    public function handle(Cohort $cohort, string $formVersionId): Cohort
    {
        if ($cohort->status !== CohortStatus::Draft) {
            throw new CohortStateException('A form can only be bound while the cohort is a draft.');
        }

        // Tenant-scoped (BelongsToTenant) + published-only — a foreign or draft version is not bindable.
        $version = FormVersion::query()->where('status', 'published')->findOrFail($formVersionId);

        if ($cohort->form_version_id === $version->id) {
            return $cohort; // idempotent re-bind of the same version
        }

        if ($cohort->form_version_id !== null) {
            throw new CohortStateException('A different form version is already bound to this cohort.');
        }

        $cohort = DB::transaction(function () use ($cohort, $version): Cohort {
            $cohort->update(['form_version_id' => $version->id]);

            return $cohort->refresh();
        });

        $this->audit->record(AuditAction::CohortFormBound->value, 'cohort', $cohort->id, [], [
            'form_version_id' => $version->id,
        ]);

        return $cohort;
    }
}
```

- [ ] **Step 4: Create `BindCohortFormRequest`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BindCohortFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize('bindForm', $cohort)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['form_version_id' => ['required', 'string']];
    }
}
```

- [ ] **Step 5: Add `bindForm` to `CohortController`**

Add imports (`BindCohortForm`, `BindCohortFormRequest`, `CohortStateException`) and the method:

```php
    public function bindForm(BindCohortFormRequest $request, BindCohortForm $service, string $id): JsonResponse
    {
        $cohort = Cohort::query()->findOrFail($id);
        $this->authorize('bindForm', $cohort);

        /** @var array{form_version_id: string} $data */
        $data = $request->validated();

        try {
            $cohort = $service->handle($cohort, $data['form_version_id']);
        } catch (CohortStateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new CohortResource($cohort))->response()->setStatusCode(200);
    }
```

(The service's `FormVersion::findOrFail` throws `ModelNotFoundException`, which Laravel renders as 404 — confirm the app does not override it.)

- [ ] **Step 6: Register the route**

In `backend/routes/api.php`, inside the `['auth:sanctum','tenant']` group near the cohort direct routes, add:

```php
        Route::post('/cohorts/{id}/bind-form', [CohortController::class, 'bindForm'])->name('cohorts.bind-form');
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `cd backend && php artisan test --filter=CohortOpenBindTest`
Expected: PASS (7 tests).

- [ ] **Step 8: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress && php artisan test --filter=CohortOpenBindTest
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Cohorts/Application/BindCohortForm.php backend/app/Modules/Cohorts/Http/Requests/BindCohortFormRequest.php backend/app/Modules/Cohorts/Http/CohortController.php backend/routes/api.php backend/tests/Feature/Cohorts/CohortOpenBindTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(cohorts): bind-form endpoint (draft-only, idempotent, 409 conflict)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Refactor `OpenCohort` + `POST /cohorts/{id}/open`

> Characterization-first: run `CohortLifecycleTest` green as a baseline before changing `OpenCohort`, then refactor and migrate that test.

**Files:**
- Modify: `backend/app/Modules/Cohorts/Application/OpenCohort.php`, `backend/app/Modules/Cohorts/Http/CohortController.php`, `backend/routes/api.php`, `backend/tests/Feature/Cohorts/CohortLifecycleTest.php`, `backend/tests/Feature/Cohorts/CohortOpenBindTest.php`

**Interfaces:**
- Consumes: `CohortStateException`, `EntitlementService` (`check('cohort.open')`), `AuditAction::CohortOpened`, `AuditLogger`, `CohortResource`, `CohortPolicy@open`.
- Produces: `OpenCohort::handle(Cohort $cohort): Cohort` (**new signature**); `CohortController@open` (POST `/cohorts/{id}/open`, route `cohorts.open`).

- [ ] **Step 1: Baseline — run the existing lifecycle test**

Run: `cd backend && php artisan test --filter=CohortLifecycleTest`
Expected: PASS with the current `handle($cohort, $form, $opensAt, $closesAt)` signature. Record as baseline.

- [ ] **Step 2: Refactor `OpenCohort`**

Replace `backend/app/Modules/Cohorts/Application/OpenCohort.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Application;

use App\Modules\Cohorts\Domain\Exceptions\CohortStateException;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Entitlement\EntitlementService;
use Illuminate\Support\Facades\DB;

/**
 * Opens a draft cohort for applications. The form is bound beforehand (BindCohortForm)
 * and the enrollment window is set via PATCH /cohorts/{id}; this transition only
 * validates state, gates on EntitlementService (FR-060), flips status to Open, and
 * audits (cohort.opened). The window is optional — a null window opens with no time bound.
 */
final class OpenCohort
{
    public function __construct(
        private readonly EntitlementService $entitlement,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws CohortStateException if the cohort is not a draft or has no bound form
     */
    public function handle(Cohort $cohort): Cohort
    {
        if ($cohort->status !== CohortStatus::Draft) {
            throw new CohortStateException('Only a draft cohort can be opened.');
        }

        if ($cohort->form_version_id === null) {
            throw new CohortStateException('A form must be bound before the cohort can be opened.');
        }

        $this->entitlement->check('cohort.open');

        $cohort = DB::transaction(function () use ($cohort): Cohort {
            $cohort->update(['status' => CohortStatus::Open]);

            return $cohort->refresh();
        });

        $this->audit->record(AuditAction::CohortOpened->value, 'cohort', $cohort->id, [], [
            'form_version_id' => $cohort->form_version_id,
        ]);

        return $cohort;
    }
}
```

- [ ] **Step 3: Migrate `CohortLifecycleTest` (5 call sites)**

In `backend/tests/Feature/Cohorts/CohortLifecycleTest.php`, each call currently of the form
`$this->app->make(OpenCohort::class)->handle($cohort, $form, $opensAt, $closesAt)` becomes: set the
binding + window on the cohort first, then call the no-arg handle. Replace each site with the pattern
(using that site's own `$cohort`, `$form` (a published `FormVersion`), and its two `Carbon` window args):

```php
$cohort->update([
    'form_version_id' => $form->id,
    'enrollment_opens_at' => $opensAt,
    'enrollment_closes_at' => $closesAt,
]);
$opened = $this->app->make(OpenCohort::class)->handle($cohort->refresh());
```

For the sites that don't capture the return value, drop `$opened =`. Preserve each test's existing
assertions — e.g. the past-window site (`now()->subDays(2), now()->subDay()`) still opens the cohort
(status Open) while `isAcceptingSubmissions()` returns false, so its "not accepting" assertion holds.
The cohort must be `draft` at each call (it is in these setups); if a site opens an already-open cohort,
adjust the setup so the cohort is draft immediately before `handle`.

- [ ] **Step 4: Add `open` to `CohortController`**

Add (import `OpenCohort`):

```php
    public function open(OpenCohort $service, string $id): JsonResponse
    {
        $cohort = Cohort::query()->findOrFail($id);
        $this->authorize('open', $cohort);

        try {
            $cohort = $service->handle($cohort);
        } catch (CohortStateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new CohortResource($cohort))->response()->setStatusCode(200);
    }
```

- [ ] **Step 5: Register the route**

```php
        Route::post('/cohorts/{id}/open', [CohortController::class, 'open'])->name('cohorts.open');
```

- [ ] **Step 6: Add HTTP open tests to `CohortOpenBindTest`**

```php
    public function test_open_transitions_draft_to_open_with_a_bound_form(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/open")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'open');
    }

    public function test_open_without_a_bound_form_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort(); // no form bound

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/open")
            ->assertStatus(409);
    }

    public function test_open_already_open_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id, 'status' => 'open']);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/open")
            ->assertStatus(409);
    }

    public function test_open_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->postJson("/api/v1/cohorts/{$cohort->id}/open")
            ->assertStatus(404);
    }
```

- [ ] **Step 7: Run the affected suites to verify they pass**

Run: `cd backend && php artisan test --filter=CohortLifecycleTest && php artisan test --filter=CohortOpenBindTest`
Expected: PASS (CohortLifecycleTest green on the new flow; CohortOpenBindTest now 11 tests).

- [ ] **Step 8: Run the gate and commit**

```bash
cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add backend/app/Modules/Cohorts/Application/OpenCohort.php backend/app/Modules/Cohorts/Http/CohortController.php backend/routes/api.php backend/tests/Feature/Cohorts/CohortLifecycleTest.php backend/tests/Feature/Cohorts/CohortOpenBindTest.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "feat(cohorts): open endpoint; refactor OpenCohort to publish-bound-state

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Regenerate OpenAPI + full-suite sweep

**Files:**
- Modify: `backend/openapi/openapi.json` (regenerated)

**Interfaces:**
- Consumes: the routes/controllers from Tasks 1–3.
- Produces: an OpenAPI doc including `/cohorts/{id}/bind-form` and `/cohorts/{id}/open`; green `OpenApiSpecTest` + Spectral; full backend suite green.

- [ ] **Step 1: Regenerate the OpenAPI document**

From `backend/`, run the repo's Scramble export (confirm against `composer.json` / `config/scramble.php`; the standard command is):

Run: `cd backend && php artisan scramble:export --path=openapi/openapi.json`
Then: `cd backend && git diff --stat openapi/openapi.json`
Expected: the diff adds paths `/v1/cohorts/{id}/bind-form` and `/v1/cohorts/{id}/open`, and adds `bound_form_version_id` to the cohort schema.

- [ ] **Step 2: Contract test + Spectral lint**

Run: `cd backend && php artisan test --filter=OpenApiSpecTest`
Expected: PASS.
Run (from repo root): `npx --yes @stoplight/spectral-cli lint backend/openapi/openapi.json --ruleset .spectral.yaml --fail-severity=error`
Expected: 0 errors. If Spectral flags the new operations, add minimal Scramble-recognized PHPDoc on `CohortController@open`/`@bindForm` mirroring the existing controller actions, then regenerate. Do not hand-edit the JSON.

- [ ] **Step 3: Full backend gate**

Run: `cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress && php artisan test`
Expected: all green (full suite). Resolve any deptrac boundary issue only if it matches an existing allowed pattern; the Cohorts→Forms dependency (`BindCohortForm` references `FormVersion`) already exists via `OpenCohort` importing `FormVersion`, so no new cross-module edge is introduced — confirm deptrac stays at 0 violations.

- [ ] **Step 4: Commit**

```bash
cd backend && git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add openapi/openapi.json
# include any controller doc tweaks made for Spectral:
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" add app/Modules/Cohorts/Http/CohortController.php
git -c commit.gpgsign=false -c user.email="274270+Byt3Ninja@users.noreply.github.com" commit -m "chore(cohorts): regenerate OpenAPI for open/bind-form endpoints

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- §3 open preconditions (draft + bound form, window optional) → Task 3 (`OpenCohort` refactor) + open tests. ✓
- §3 bind-form rules (draft-only, published-only 404, same idempotent, different 409) → Task 2 (`BindCohortForm` + tests). ✓
- §3 `OpenCohort` refactor + sole caller migration → Task 3 (characterization-first + 5-site migration). ✓
- §3 `CloseCohort` untouched/unexposed → no task modifies it. ✓
- §4 endpoints + status codes (200/404/409/403/422) → Task 2 (bind) + Task 3 (open). ✓
- §5 services + `CohortStateException` → Task 1 (exception) + Task 2 (`BindCohortForm`) + Task 3 (`OpenCohort`). ✓
- §6 `CohortResource.bound_form_version_id` + `CohortPolicy` open/bindForm → Task 1. ✓
- §7 testing (bind cases, open cases, refactor migration, resource field, OpenAPI) → Tasks 1–4. ✓
- §8 out-of-scope (no unbind, no stage/scoring binds, no close endpoint) → no task adds them. ✓

**Placeholder scan:** No `TBD`/`add error handling`/`similar to`. The one `markTestIncomplete` in Task 2's 403 test is explicitly called out with instructions to replace it with a real no-permission member before the task is complete (it must NOT ship as incomplete) — flagged, not deferred.

**Type consistency:** `BindCohortForm::handle(Cohort, string): Cohort`, `OpenCohort::handle(Cohort): Cohort`, `CohortStateException`, `AuditAction::CohortFormBound`, `bound_form_version_id` (= `form_version_id`), `CohortPolicy::open/bindForm` — names/signatures consistent across Tasks 1–3 and match the FE contract (`form_version_id` body key, `bound_form_version_id` response key, status codes).
