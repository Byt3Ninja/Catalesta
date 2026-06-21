# SP-1a — Identity Model Inversion — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the OIDC `ExternalUser` identity model to `Account` + `linked_identities` (+ `linked_identity_tokens`), repoint all user FKs to `account_id`, and rewire the OIDC login onto the new model — with zero observable behavior change.

**Architecture:** A hard, atomic rename. Because tests run on fresh in-memory SQLite (`RefreshDatabase`) and there is no production data, we **edit the original `2026_06_18_*` create-migrations** to emit the target schema directly (no rename/backfill migration). Task 1 is the irreducible atomic change (schema + models + auth flow + every call site + test seams + existing-test assertions) and must end with the full existing suite green. Task 2 adds the new coverage the spec requires.

**Tech Stack:** Laravel 13 (PHP 8.3), Eloquent, ULID PKs (`HasUlids`), Sanctum SPA session guard, PostgreSQL (prod) / SQLite `:memory:` (tests), PHPUnit, Pint, Larastan.

## Global Constraints

- **Spec:** `docs/superpowers/specs/2026-06-22-sp1a-identity-model-inversion-design.md`. Spec wins on conflict.
- **Behavior-preserving:** same `/auth/*` endpoints and **byte-identical session JSON** `{ user: { id, startup_gate_subject_id, email, display_name } }`. `startup_gate_subject_id` is now derived from the account's `startup_gate` link.
- **Identifier rule:** Account id (ULID) is the primary user key; the Startup-Gate `sub` lives on a `linked_identities` row (`provider='startup_gate'`, `subject_id`); email is not yet a credential (SP-1b).
- **Tenant rules (unchanged):** `organization_id` server-set; tenant queries fail-closed, cross-tenant → 404; do NOT introduce `withoutGlobalScope('tenant')` in app code (tests already use the `TenantContext` seams).
- **No new feature:** no password, no registration, no email verify/reset, no UI, no frontend edits. Those are SP-1b.
- **Edit the original create-migrations** (the chosen approach) — do NOT add a rename/backfill migration.
- **Run from `backend/`.** Tests: `php artisan test`. Lint: `vendor/bin/pint`. Static: `vendor/bin/phpstan analyse`.
- All work on branch `feat/sp1a-identity-model-inversion` (already checked out). One commit per task.

---

### Task 1: The atomic inversion (schema + models + auth flow + call sites + seams)

This is one atomic change: renaming the model/tables breaks every reference until all are updated, so there is no green intermediate state. The deliverable is **`php artisan migrate:fresh` succeeds and the full existing suite + Pint + PHPStan are green.**

**Files:**
- Migrations (edit): `backend/database/migrations/2026_06_18_000100_create_audit_logs_table.php`, `…000200_*` (rename→accounts), `…000300_*` (rename→linked_identity_tokens), `…000400_create_profile_snapshots_table.php`, `…001300_create_organization_memberships_table.php`, `…002900_create_participant_stage_statuses_table.php`
- Migration (create): `backend/database/migrations/2026_06_18_000250_create_linked_identities_table.php`
- Models: rename `ExternalUser.php`→`Account.php`, `ExternalUserToken.php`→`LinkedIdentityToken.php`, new `LinkedIdentity.php` (all under `backend/app/Modules/Identity/Domain/Models/`)
- `backend/config/auth.php`
- `backend/app/Modules/Identity/Application/CompleteLogin.php`, `CaptureProfileSnapshot.php`
- `backend/app/Modules/Identity/Http/AuthController.php`, `MeController.php`
- `backend/app/Modules/Organizations/Domain/Models/OrganizationMembership.php`
- `backend/app/Http/Middleware/ResolveTenant.php`
- `backend/app/Modules/Organizations/Application/CreateOrganization.php`
- `backend/app/Modules/Organizations/Http/MembershipController.php`, `Requests/StoreMembershipRequest.php`, `Resources/MembershipResource.php`, `OrganizationController.php`
- `backend/app/Modules/Stages/Application/AdvanceParticipantStage.php`
- `backend/app/Shared/Audit/AuditLogger.php`
- `backend/tests/TestCase.php` and existing tests (see Step 13)

**Interfaces produced (used by Task 2):**
- `App\Modules\Identity\Domain\Models\Account` (Authenticatable, `linkedIdentities(): HasMany`, `startupGateSubjectId(): ?string`)
- `App\Modules\Identity\Domain\Models\LinkedIdentity` (`account(): BelongsTo`, `token(): HasOne`, `static projectFromClaims(array $claims): self`)
- `App\Modules\Identity\Domain\Models\LinkedIdentityToken`
- `TestCase::makeAccount(array $overrides = []): Account` (creates an Account + attaches a `startup_gate` link)

> Note on graphify: these are known files; the implementer may Read/Edit them directly (graphify is a code-navigation aid, already done in planning).

- [ ] **Step 1: Edit `…000200` → `accounts` table.** Rename the file to `2026_06_18_000200_create_accounts_table.php` (`git mv`), and replace the `Schema::create(...)` body with:

```php
Schema::create('accounts', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->string('email')->nullable();
    $t->string('display_name')->nullable();
    $t->string('avatar_url')->nullable();
    $t->string('locale', 16)->nullable();
    $t->boolean('is_platform_admin')->default(false);
    $t->rememberToken();
    $t->timestampsTz();
});
```

Update the `down()` (if present) to `Schema::dropIfExists('accounts');`.

- [ ] **Step 2: Create `…000250_create_linked_identities_table.php`.** New file `backend/database/migrations/2026_06_18_000250_create_linked_identities_table.php`:

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
        Schema::create('linked_identities', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('account_id');
            $t->string('provider');
            $t->string('subject_id');
            $t->string('display_name')->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('locale', 16)->nullable();
            $t->unsignedBigInteger('profile_version')->default(0);
            $t->string('synchronization_status')->default('pending');
            $t->timestampTz('synchronized_at')->nullable();
            $t->timestampTz('linked_at')->nullable();
            $t->timestampTz('last_login_at')->nullable();
            $t->timestampsTz();

            $t->unique(['provider', 'subject_id']);
            $t->unique(['account_id', 'provider']);
            $t->index('account_id');

            $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linked_identities');
    }
};
```

- [ ] **Step 3: Edit `…000300` → `linked_identity_tokens`.** `git mv` the file to `2026_06_18_000300_create_linked_identity_tokens_table.php`, and replace the create body with:

```php
Schema::create('linked_identity_tokens', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->ulid('linked_identity_id')->index();
    $t->text('access_token');
    $t->text('refresh_token')->nullable();
    $t->jsonb('scopes')->nullable();
    $t->timestampTz('expires_at')->nullable();
    $t->timestampsTz();
});
```

Update `down()` to `Schema::dropIfExists('linked_identity_tokens');`.

- [ ] **Step 4: Edit `…000400` profile_snapshots FK.** In `2026_06_18_000400_create_profile_snapshots_table.php`:
  - `$t->ulid('external_user_id')->index();` → `$t->ulid('account_id')->index();`
  - the foreign block →
```php
$t->foreign('account_id')
    ->references('id')
    ->on('accounts')
    ->onDelete('cascade');
```

- [ ] **Step 5: Edit `…001300` organization_memberships FK.** In `2026_06_18_001300_create_organization_memberships_table.php`, replace every `external_user_id` with `account_id` (the column, the `unique(['organization_id', 'account_id'])`, the `index('account_id')`, and the foreign block), and change the foreign target to accounts:

```php
$t->foreign('account_id')
    ->references('id')
    ->on('accounts')
    ->cascadeOnDelete();
```

- [ ] **Step 6: Edit `…002900` participant_stage_statuses.** In `2026_06_18_002900_create_participant_stage_statuses_table.php`: `$t->ulid('external_user_id')->index();` → `$t->ulid('account_id')->index();` and `$t->unique(['cohort_id', 'external_user_id', 'program_stage_id']);` → `$t->unique(['cohort_id', 'account_id', 'program_stage_id']);`

- [ ] **Step 7: Edit `…000100` audit_logs actor column.** In `2026_06_18_000100_create_audit_logs_table.php`: `$t->ulid('actor_external_user_id')->nullable()->index();` → `$t->ulid('actor_account_id')->nullable()->index();`

- [ ] **Step 8: Rename models → `Account`, `LinkedIdentity`, `LinkedIdentityToken`.**
  `git mv ExternalUser.php Account.php`, `git mv ExternalUserToken.php LinkedIdentityToken.php`. Replace contents:

`backend/app/Modules/Identity/Domain/Models/Account.php`:
```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class Account extends Authenticatable
{
    use HasUlids;

    protected $guarded = [];

    /** @return array<string, string> */
    protected $casts = [
        'is_platform_admin' => 'boolean',
    ];

    /** @return HasMany<LinkedIdentity, $this> */
    public function linkedIdentities(): HasMany
    {
        return $this->hasMany(LinkedIdentity::class);
    }

    /** The Startup-Gate `sub` for this account, if linked (null otherwise). */
    public function startupGateSubjectId(): ?string
    {
        return $this->linkedIdentities()
            ->where('provider', 'startup_gate')
            ->value('subject_id');
    }
}
```

`backend/app/Modules/Identity/Domain/Models/LinkedIdentityToken.php`:
```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class LinkedIdentityToken extends Model
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

New `backend/app/Modules/Identity/Domain/Models/LinkedIdentity.php`:
```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class LinkedIdentity extends Model
{
    use HasUlids;

    protected $guarded = [];

    /** @return array<string, string> */
    protected $casts = [
        'synchronized_at' => 'datetime',
        'linked_at' => 'datetime',
        'last_login_at' => 'datetime',
        'profile_version' => 'integer',
    ];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasOne<LinkedIdentityToken, $this> */
    public function token(): HasOne
    {
        return $this->hasOne(LinkedIdentityToken::class);
    }

    /**
     * Resolve-or-create the Startup-Gate identity link (and its Account) from OIDC claims.
     * Upsert key is (provider='startup_gate', subject_id=sub). Behavior-preserving:
     * the account's email/display_name/avatar/locale are refreshed from claims on every
     * login, exactly as the old ExternalUser projection did.
     *
     * @param  array<string,mixed>  $claims
     */
    public static function projectFromClaims(array $claims): self
    {
        $link = self::firstOrNew([
            'provider' => 'startup_gate',
            'subject_id' => (string) $claims['sub'],
        ]);

        $account = $link->exists ? $link->account : new Account;
        $account->fill([
            'email' => $claims['email'] ?? null,
            'display_name' => $claims['name'] ?? null,
            'avatar_url' => $claims['picture'] ?? null,
            'locale' => $claims['locale'] ?? null,
        ]);
        $account->save();

        if (! $link->exists) {
            $link->account()->associate($account);
            $link->linked_at = now();
        }

        $link->fill([
            'display_name' => $claims['name'] ?? null,
            'avatar_url' => $claims['picture'] ?? null,
            'locale' => $claims['locale'] ?? null,
            'profile_version' => isset($claims['profile_updated_at']) ? (int) $claims['profile_updated_at'] : 0,
            'synchronization_status' => 'synced',
            'synchronized_at' => now(),
            'last_login_at' => now(),
        ]);
        $link->save();

        return $link;
    }
}
```

- [ ] **Step 9: Point the auth provider at `Account`.** In `backend/config/auth.php`: delete line 3 `use App\Models\User;`; change `use App\Modules\Identity\Domain\Models\ExternalUser;` → `use App\Modules\Identity\Domain\Models\Account;`; in the `users` provider change `'model' => ExternalUser::class,` → `'model' => Account::class,`.

- [ ] **Step 10: Rewire `CompleteLogin`.** In `backend/app/Modules/Identity/Application/CompleteLogin.php`: update imports (drop `ExternalUser`/`ExternalUserToken`; add `Account`, `LinkedIdentity`, `LinkedIdentityToken`), change the return type `ExternalUser` → `Account` (signature and the transaction closure), and replace the transaction body:

```php
public function handle(string $code): Account
{
    $nonce = (string) session('oidc.nonce', '');
    $verifier = (string) session('oidc.code_verifier', '');

    try {
        /** @var array<string,mixed> $tokens */
        $tokens = $this->identityProvider->exchangeCode($code, $verifier);
        $claims = $this->identityProvider->validateIdToken((string) $tokens['id_token'], $nonce);
    } catch (InvalidTokenException $e) {
        throw new AuthenticationException('Invalid token: '.$e->getMessage());
    }

    return DB::transaction(function () use ($tokens, $claims): Account {
        $link = LinkedIdentity::projectFromClaims($claims);
        $account = $link->account;

        LinkedIdentityToken::where('linked_identity_id', $link->id)->delete();

        $scopes = isset($tokens['scope'])
            ? array_values(array_filter(explode(' ', (string) $tokens['scope'])))
            : [];

        LinkedIdentityToken::create([
            'linked_identity_id' => $link->id,
            'access_token' => (string) $tokens['access_token'],
            'refresh_token' => isset($tokens['refresh_token']) ? (string) $tokens['refresh_token'] : null,
            'scopes' => $scopes,
            'expires_at' => now()->addSeconds((int) $tokens['expires_in']),
        ]);

        $this->captureProfileSnapshot->capture(
            $account,
            'identity',
            null,
            $claims,
            'profile.basic.read',
            $link->profile_version,
        );

        Auth::login($account);
        session()->regenerate();

        $this->auditLogger->record(
            'auth.login',
            'account',
            (string) $account->id,
            [],
            ['sub' => $link->subject_id],
        );

        return $account;
    });
}
```

- [ ] **Step 11: Update `CaptureProfileSnapshot`.** In `backend/app/Modules/Identity/Application/CaptureProfileSnapshot.php`: add a trailing parameter `int $profileVersion = 0` to `capture(...)`, change the first parameter's type-hint from `ExternalUser` to `Account` (rename `$user` → `$account` throughout the method), and in the `ProfileSnapshot::create([...])` array change `'external_user_id' => $user->id` → `'account_id' => $account->id` and `'profile_version' => $user->profile_version` → `'profile_version' => $profileVersion`. Update imports (`ExternalUser` → `Account`).

- [ ] **Step 12: Update the remaining call sites** (mechanical — `external_user_id`→`account_id`, `ExternalUser`→`Account`):
  - `OrganizationMembership.php`: `use App\Modules\Identity\Domain\Models\ExternalUser;` → `…\Account;`; `protected $fillable = ['account_id', 'status'];`; the docblock `@property string $external_user_id` → `$account_id`; rename `externalUser(): BelongsTo` → `account(): BelongsTo` returning `$this->belongsTo(Account::class)` (and its `@return BelongsTo<ExternalUser…>` → `<Account…>`).
  - `ResolveTenant.php` (membership query): `->where('external_user_id', $user->id)` → `->where('account_id', $user->id)`.
  - `CreateOrganization.php`: `new OrganizationMembership(['external_user_id' => $creator->id, 'status' => 'active'])` → `['account_id' => $creator->id, …]`. If the param/type-hints reference `ExternalUser`, change to `Account`.
  - `MembershipController.php`: `'external_user_id' => $data['external_user_id']` → `'account_id' => $data['account_id']`.
  - `StoreMembershipRequest.php` line 25: `'external_user_id' => ['required', 'string', 'exists:external_users,id']` → `'account_id' => ['required', 'string', 'exists:accounts,id']`.
  - `MembershipResource.php`: `'external_user_id' => $this->external_user_id,` → `'account_id' => $this->account_id,`.
  - `OrganizationController.php`: `->where('external_user_id', $user->id)` → `->where('account_id', $user->id)`.
  - `AdvanceParticipantStage.php` (both occurrences): `'external_user_id' => $participant->id` / `->where('external_user_id', $participant->id)` → `account_id`.
  - `AuditLogger.php` line 22: `'actor_external_user_id' => optional($this->request->user())->id,` → `'actor_account_id' => optional($this->request->user())->id,`.
  - `MeController.php` (token read, lines 119–121):
```php
$link = $user->linkedIdentities()->where('provider', 'startup_gate')->first();
$tokenRecord = $link
    ? LinkedIdentityToken::where('linked_identity_id', $link->id)->latest('created_at')->first()
    : null;
```
    Update `MeController` imports (`ExternalUserToken`→`LinkedIdentityToken`).
  - `AuthController.php` + `MeController.php` **session JSON**: wherever the user payload is built with `$user->startup_gate_subject_id`, replace with `$user->startupGateSubjectId()` so the response keeps the `startup_gate_subject_id` field. In `AuthController::logout`, the token cleanup that deletes by `external_user_id` becomes: resolve the user's `startup_gate` link and `LinkedIdentityToken::where('linked_identity_id', $link->id)->delete()` (guard for a null link). Read both files and apply; the response key name `startup_gate_subject_id` does NOT change.

- [ ] **Step 13: Update test seams + existing test assertions.**
  - `tests/TestCase.php`: replace `makeExternalUser` with `makeAccount` (and update its callers `bootUserWithOrg`, `actingAsTenant` to use `Account` + `account_id`):
```php
protected function makeAccount(array $overrides = []): Account
{
    $account = Account::create(array_merge([
        'email' => Str::uuid()->toString().'@test.example',
        'is_platform_admin' => false,
    ], $overrides));

    LinkedIdentity::create([
        'account_id' => $account->id,
        'provider' => 'startup_gate',
        'subject_id' => 'sub-'.Str::uuid()->toString(),
        'linked_at' => now(),
    ]);

    return $account;
}
```
    In `bootUserWithOrg`: `$user = $this->makeExternalUser();` → `$this->makeAccount();`. In `actingAsTenant`: type-hint `ExternalUser $user` → `Account $user` and `->where('external_user_id', $user->id)` → `->where('account_id', $user->id)`. Update imports.
  - `AuthFlowTest.php` (`test_user_logs_in_through_oidc_and_projection_uses_sub`): `$this->assertDatabaseHas('external_users', ['startup_gate_subject_id' => 'sg_user_01']);` → `$this->assertDatabaseHas('linked_identities', ['provider' => 'startup_gate', 'subject_id' => 'sg_user_01']);`. The `assertJsonPath('user.startup_gate_subject_id', 'sg_user_01')` and `assertDatabaseCount('profile_snapshots', 1)` stay unchanged.
  - `git mv ExternalUserTokenTest.php LinkedIdentityTokenTest.php`: change the table `external_user_tokens`→`linked_identity_tokens` and the FK column to `linked_identity_id` (create a `LinkedIdentity` to satisfy the column); keep the encryption assertions.
  - `git mv ExternalUserProjectionTest.php AccountProjectionTest.php`: drive projection via `LinkedIdentity::projectFromClaims($claims)`; `assertDatabaseCount('external_users', …)` → `assertDatabaseCount('accounts', …)`; `test_projection_is_keyed_on_sub_not_email` asserts the same account id is reused and its email updates; `test_different_sub_creates_distinct_user…` → `assertDatabaseCount('accounts', 2)`.
  - Grep for any remaining references and fix: `grep -rn "makeExternalUser\|ExternalUser\|external_user_id\|external_user_tokens\|actor_external_user_id\|->externalUser" backend/app backend/tests backend/config backend/database` — must return nothing after this step (except inside comments you intentionally keep).
  - Grep the frontend for any membership-API field usage that would break: `grep -rn "external_user_id" frontend/src` — expected empty (membership management UI not built); if non-empty, STOP and report (it would be an unplanned frontend contract change).

- [ ] **Step 14: Migrate + run the full suite + gates.**

Run (from `backend/`):
```bash
php artisan migrate:fresh 2>&1 | tail -5
php artisan test 2>&1 | tail -30
vendor/bin/pint --dirty 2>&1 | tail -10
vendor/bin/phpstan analyse 2>&1 | tail -20
```
Expected: migrate OK; **all tests pass** (the existing OIDC/auth/tenant/membership/stage suites are green against the new schema); Pint clean; PHPStan clean. Fix anything red before committing.

- [ ] **Step 15: Commit.**
```bash
git add -A
git commit -m "refactor(identity): invert ExternalUser → Account + linked_identities (SP-1a)

Rename external_users→accounts, add linked_identities, rename
external_user_tokens→linked_identity_tokens, repoint external_user_id→
account_id across memberships/profile_snapshots/participant_stage_statuses
and audit actor_account_id. Rewire OIDC login onto Account+LinkedIdentity.
Behavior-preserving: same endpoints, same session JSON, existing suite green.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: New coverage (schema-shape, projection, isolation, audit)

**Files:**
- Test: `backend/tests/Feature/IdentityModelInversionTest.php` (new)
- Test: extend `backend/tests/Feature/AccountProjectionTest.php` (the renamed projection test) with the duplicate-`sub` reuse case if not already covered.

**Interfaces consumed (from Task 1):** `Account`, `LinkedIdentity`, `LinkedIdentityToken`, `TestCase::makeAccount`.

- [ ] **Step 1: Write the schema-shape + projection tests (failing only if Task 1 is wrong).** Create `backend/tests/Feature/IdentityModelInversionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Domain\Models\LinkedIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IdentityModelInversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_schema_shape(): void
    {
        $this->assertTrue(Schema::hasTable('accounts'));
        $this->assertTrue(Schema::hasTable('linked_identities'));
        $this->assertTrue(Schema::hasTable('linked_identity_tokens'));
        $this->assertFalse(Schema::hasTable('external_users'));
        $this->assertFalse(Schema::hasTable('external_user_tokens'));

        $this->assertFalse(Schema::hasColumn('accounts', 'startup_gate_subject_id'));
        $this->assertTrue(Schema::hasColumn('linked_identities', 'subject_id'));
        $this->assertTrue(Schema::hasColumn('organization_memberships', 'account_id'));
        $this->assertFalse(Schema::hasColumn('organization_memberships', 'external_user_id'));
        $this->assertTrue(Schema::hasColumn('profile_snapshots', 'account_id'));
        $this->assertTrue(Schema::hasColumn('participant_stage_statuses', 'account_id'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'actor_account_id'));
    }

    public function test_projection_creates_account_link_and_reuses_on_second_login(): void
    {
        $claims = ['sub' => 'sg_abc', 'email' => 'a@example.com', 'name' => 'A'];

        $link1 = LinkedIdentity::projectFromClaims($claims);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('linked_identities', 1);
        $this->assertSame('sg_abc', $link1->subject_id);

        $link2 = LinkedIdentity::projectFromClaims(['sub' => 'sg_abc', 'email' => 'changed@example.com', 'name' => 'A']);
        $this->assertSame($link1->account_id, $link2->account_id);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertSame('changed@example.com', Account::find($link2->account_id)->email);
    }

    public function test_account_exposes_startup_gate_subject_id(): void
    {
        $account = $this->makeAccount();
        $this->assertNotNull($account->startupGateSubjectId());
        $this->assertStringStartsWith('sub-', $account->startupGateSubjectId());
    }
}
```

- [ ] **Step 2: Run it.**
```bash
php artisan test --filter=IdentityModelInversionTest 2>&1 | tail -20
```
Expected: PASS (Task 1 already implemented the schema/projection). If any assertion fails, the defect is in Task 1 — fix there.

- [ ] **Step 3: Tenant-isolation re-verification on `account_id`.** Confirm an existing tenant-isolation test already exercises `organization_memberships` cross-tenant → 404 against the renamed column. If `backend/tests/Feature/TenantIsolationTest.php` (or `Phase2TenantIsolationTest`) covers membership-scoped reads, it will already run against `account_id` after Task 1 — verify by running it:
```bash
php artisan test --filter=TenantIsolation 2>&1 | tail -20
```
Expected: PASS. If no test touches the membership FK directly, add one case to `IdentityModelInversionTest` asserting a cross-tenant `organization_memberships` read returns no rows under the wrong tenant context (use `actingAsTenant` + a second org), mirroring the existing isolation pattern. (Only add if the grep `grep -rln "account_id" backend/tests/Feature/*TenantIsolation*` shows no coverage.)

- [ ] **Step 4: Audit actor assertion.** Add to `IdentityModelInversionTest`:
```php
public function test_login_audit_records_actor_account_id(): void
{
    // Drives the full OIDC callback like AuthFlowTest, then asserts the audit row.
    // Reuse AuthFlowTest's Http::fake + MockKeys setup helper if one exists;
    // otherwise assert via a direct projection + AuditLogger call is out of scope —
    // prefer asserting through the same callback path AuthFlowTest uses.
    $this->markTestIncomplete('Implement using the AuthFlowTest OIDC fake harness; assert audit_logs.actor_account_id is the new account id.');
}
```
Then replace the `markTestIncomplete` body by following `AuthFlowTest`'s OIDC fake setup to perform a real callback and assert `$this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_account_id' => $accountId]);`. (Read `AuthFlowTest.php` for the exact `Http::fake`/`MockKeys` harness and mirror it.)

- [ ] **Step 5: Run the full suite + gates again.**
```bash
php artisan test 2>&1 | tail -30
vendor/bin/pint --dirty 2>&1 | tail -10
vendor/bin/phpstan analyse 2>&1 | tail -20
```
Expected: all green.

- [ ] **Step 6: Commit.**
```bash
git add -A
git commit -m "test(identity): SP-1a schema-shape, projection, isolation, audit-actor coverage

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage** (spec section → task):
- §3 target schema (accounts, linked_identities, linked_identity_tokens, FK repoints, actor_account_id) → Task 1 Steps 1–7 ✓
- §4 edit-original-migrations approach → Task 1 Steps 1–7 ✓
- §5 models (Account/LinkedIdentity/LinkedIdentityToken), auth flow (CompleteLogin/AuthController/MeController), config, blast-radius call sites, test seams → Task 1 Steps 8–13 ✓
- §6 session shape unchanged (`startupGateSubjectId()` derivation) → Task 1 Steps 10, 12 ✓
- §7 regression green → Task 1 Step 14; schema-shape + projection + isolation + audit → Task 2 ✓
- §8 out of scope (no password/UI/frontend) → enforced by Global Constraints; Step 13 frontend grep guards against accidental contract change ✓

**Placeholder scan:** the only deliberate "incomplete" is Task 2 Step 4's `markTestIncomplete`, which is immediately replaced in the same step with a concrete assertion using the existing AuthFlowTest harness — not a left-behind placeholder. All schema/model/CompleteLogin code is complete and verbatim-derived from current source. Call-site edits are exact find→replace pairs.

**Type/name consistency:** `Account`, `LinkedIdentity` (`projectFromClaims`, `account()`, `token()`), `LinkedIdentityToken` (`linked_identity_id`), `account_id`, `actor_account_id`, `startupGateSubjectId()`, `makeAccount` used identically across Tasks 1 and 2. `CaptureProfileSnapshot::capture(..., int $profileVersion = 0)` matches the call in CompleteLogin Step 10.

**Known atomicity note:** Task 1 cannot have green sub-steps (a rename is all-or-nothing); its single green checkpoint is Step 14 (full suite). This is intentional and called out in Architecture.
