# SP-1b-i — Native Auth API (backend) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add native credential auth (register, password login, email verification, password reset) as new API endpoints on the existing `Account` model, with a soft email-verified gate on org create/join — OIDC untouched.

**Architecture:** New `NativeAuthController` + FormRequests + two queued notifications + an `EnsureEmailVerified` middleware + named rate limiters, built on Laravel's session guard (Sanctum SPA) and password broker (the `password_reset_tokens` table already exists). A new `AccountSessionResource` replaces the inline auth JSON and adds `email_verified`/`linked_providers`/`has_password` (and makes `startup_gate_subject_id` nullable). Six independently-testable tasks.

**Tech Stack:** Laravel 13 (PHP 8.3), Eloquent, ULID PKs, Sanctum SPA session, Laravel Notifications (queued, Redis; sync in tests), password broker, PostgreSQL (prod) / SQLite `:memory:` (tests), PHPUnit + `Notification::fake`, Pint, Larastan.

## Global Constraints

- **Spec:** `docs/superpowers/specs/2026-06-22-sp1b-i-native-auth-backend-design.md`. Spec wins on conflict.
- **OIDC stays green; no frontend changes.** Session/callback JSON change is additive; `startup_gate_subject_id` becomes nullable but is non-null for all existing (OIDC) users.
- **Email is a local credential only** (CLAUDE rule 5); Account id (ULID) stays primary key; `sub` stays on the link. Passwords are hashed (`'hashed'` cast); never log/audit the password or any token.
- **Enumeration-safe:** password-login returns the same generic 422 for unknown-email and wrong-password; `password/forgot` always returns 200.
- **Email verification link** points at a backend signed GET that redirects to the frontend (`FRONTEND_URL/auth/email-verified`); **password-reset link** points at the frontend page (`FRONTEND_URL/auth/reset-password?token=…&email=…`).
- **Rate limits:** 6/min on register, password-login, email-resend, password-forgot (keyed per spec §6).
- **SG-linked accounts are auto-verified** (`email_verified_at` set in `projectFromClaims` and in the `makeAccount` test seam) so the org gate is one uniform `hasVerifiedEmail()` check.
- Run from `backend/`. Tests `php artisan test`; lint `vendor/bin/pint`; static `php -d memory_limit=1G vendor/bin/phpstan analyse`.
- Branch `feat/sp1b-i-native-auth` (already checked out). One commit per task.

---

### Task 1: Foundation — schema, Account, session resource, frontend config, rate limiters

**Files:**
- Create: `backend/database/migrations/2026_06_22_000100_add_native_auth_to_accounts.php`
- Modify: `backend/app/Modules/Identity/Domain/Models/Account.php`
- Modify: `backend/app/Modules/Identity/Domain/Models/LinkedIdentity.php` (`projectFromClaims`)
- Create: `backend/app/Modules/Identity/Http/Resources/AccountSessionResource.php`
- Modify: `backend/app/Modules/Identity/Http/AuthController.php` (callback + session use the resource)
- Modify: `backend/app/Modules/Identity/Http/MeController.php` (`me()` adds the 3 fields)
- Modify: `backend/config/app.php` (add `frontend_url`)
- Modify: `backend/app/Providers/AppServiceProvider.php` (register rate limiters)
- Modify: `backend/tests/TestCase.php` (`makeAccount` sets `email_verified_at`)
- Test: `backend/tests/Feature/NativeAuthFoundationTest.php`

**Interfaces produced:**
- `accounts.password` (nullable), `accounts.email_verified_at` (nullable), `accounts.email` UNIQUE.
- `Account implements MustVerifyEmail`, `use Notifiable`; casts `password=>'hashed'`, `email_verified_at=>'datetime'`.
- `App\Modules\Identity\Http\Resources\AccountSessionResource` (wrap `user`; fields: id, email, display_name, email_verified, startup_gate_subject_id, linked_providers, has_password).
- `config('app.frontend_url')`.
- Rate limiters: `auth-register`, `auth-login`, `auth-resend`, `auth-forgot`.

- [ ] **Step 1: Migration.** Create the migration file:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $t): void {
            $t->string('password')->nullable();
            $t->timestampTz('email_verified_at')->nullable();
            $t->unique('email');
        });

        // SG-linked accounts are trusted-verified (no-op on a fresh DB).
        DB::table('accounts')
            ->whereNull('email_verified_at')
            ->whereExists(function ($q): void {
                $q->select(DB::raw('1'))
                    ->from('linked_identities')
                    ->whereColumn('linked_identities.account_id', 'accounts.id')
                    ->where('linked_identities.provider', 'startup_gate');
            })
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $t): void {
            $t->dropUnique(['email']);
            $t->dropColumn(['password', 'email_verified_at']);
        });
    }
};
```

> Assumption: no two existing accounts share a non-null email (true on a fresh DB / tests; SG subs map 1:1 to accounts).

- [ ] **Step 2: Account model.** Replace `Account.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class Account extends Authenticatable implements MustVerifyEmail
{
    use HasUlids;
    use Notifiable;

    protected $guarded = [];

    /** @return array<string, string> */
    protected $casts = [
        'is_platform_admin' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
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

(The `sendEmailVerificationNotification`/`sendPasswordResetNotification` overrides are added in Tasks 2 and 5 when those notifications exist.)

- [ ] **Step 3: Auto-verify SG accounts in `projectFromClaims`.** In `LinkedIdentity::projectFromClaims`, change the account fill block to also set verification:

```php
        $account = $link->exists ? $link->account : new Account;
        $account->fill([
            'email' => $claims['email'] ?? null,
            'display_name' => $claims['name'] ?? null,
            'avatar_url' => $claims['picture'] ?? null,
            'locale' => $claims['locale'] ?? null,
        ]);
        if ($account->email_verified_at === null) {
            $account->email_verified_at = now(); // SG email is trusted
        }
        $account->save();
```

- [ ] **Step 4: `AccountSessionResource`.** Create `backend/app/Modules/Identity/Http/Resources/AccountSessionResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The canonical session-user payload. Wrapped as { "user": {...} } to match the
 * existing /auth/callback and /auth/session response shape consumed by the SPA.
 */
final class AccountSessionResource extends JsonResource
{
    public static $wrap = 'user';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Account $a */
        $a = $this->resource;

        return [
            'id' => $a->id,
            'email' => $a->email,
            'display_name' => $a->display_name,
            'email_verified' => $a->hasVerifiedEmail(),
            'startup_gate_subject_id' => $a->startupGateSubjectId(),
            'linked_providers' => $a->linkedIdentities()->pluck('provider')->all(),
            'has_password' => $a->password !== null,
        ];
    }
}
```

- [ ] **Step 5: Wire the resource into `AuthController`.** In `callback()` and `session()`, replace the `return response()->json(['user' => [...]]);` blocks with the resource. Add `use App\Modules\Identity\Http\Resources\AccountSessionResource;`. Each becomes:

```php
        return (new AccountSessionResource($user))->response();
```

(`callback` keeps its validation + `$user = $completeLogin->handle(...)`; `session` keeps `$user = $request->user();`.)

- [ ] **Step 6: Extend `me()`.** In `MeController::me()`, add the three fields to the `data` array (keep `avatar_url`/`locale`):

```php
        return response()->json([
            'data' => [
                'id' => $user->id,
                'startup_gate_subject_id' => $user->startupGateSubjectId(),
                'email' => $user->email,
                'display_name' => $user->display_name,
                'avatar_url' => $user->avatar_url,
                'locale' => $user->locale,
                'email_verified' => $user->hasVerifiedEmail(),
                'linked_providers' => $user->linkedIdentities()->pluck('provider')->all(),
                'has_password' => $user->password !== null,
            ],
        ]);
```

- [ ] **Step 7: `config/app.php` frontend_url.** Add inside the returned config array:

```php
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
```

- [ ] **Step 8: Register rate limiters.** In `backend/app/Providers/AppServiceProvider.php`, add to `boot()` (add imports `use Illuminate\Cache\RateLimiting\Limit; use Illuminate\Http\Request; use Illuminate\Support\Facades\RateLimiter;`):

```php
        $perEmailIp = fn (Request $r) => Limit::perMinute(6)
            ->by(strtolower((string) $r->input('email')).'|'.$r->ip());

        RateLimiter::for('auth-register', fn (Request $r) => Limit::perMinute(6)->by((string) $r->ip()));
        RateLimiter::for('auth-login', $perEmailIp);
        RateLimiter::for('auth-forgot', $perEmailIp);
        RateLimiter::for('auth-resend', fn (Request $r) => Limit::perMinute(6)->by(optional($r->user())->id ?: (string) $r->ip()));
```

- [ ] **Step 9: `makeAccount` test seam.** In `backend/tests/TestCase.php`, set the SG-account-is-verified invariant — make `makeAccount` create verified accounts (add `'email_verified_at' => now()` to the `Account::create([...])` array). Leave the linked-identity creation as-is.

- [ ] **Step 10: Write the foundation test.** Create `backend/tests/Feature/NativeAuthFoundationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NativeAuthFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_table_has_native_auth_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('accounts', 'password'));
        $this->assertTrue(Schema::hasColumn('accounts', 'email_verified_at'));
    }

    public function test_password_is_hashed_on_set(): void
    {
        $a = Account::create(['email' => 'h@example.com', 'password' => 'secret-pw']);
        $this->assertNotSame('secret-pw', $a->password);
        $this->assertTrue(password_verify('secret-pw', $a->password));
    }

    public function test_make_account_is_verified_and_session_resource_reports_it(): void
    {
        $a = $this->makeAccount();
        $this->assertTrue($a->fresh()->hasVerifiedEmail());

        $arr = (new \App\Modules\Identity\Http\Resources\AccountSessionResource($a))
            ->toArray(request());

        $this->assertTrue($arr['email_verified']);
        $this->assertSame(['startup_gate'], $arr['linked_providers']);
        $this->assertFalse($arr['has_password']);
        $this->assertArrayHasKey('startup_gate_subject_id', $arr);
    }
}
```

- [ ] **Step 11: Run + commit.**
```bash
php artisan test --filter=NativeAuthFoundationTest 2>&1 | tail -6
php artisan test 2>&1 | tail -6           # OIDC suite stays green (callback/session now use the resource)
vendor/bin/pint --dirty 2>&1 | tail -5
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress 2>&1 | tail -10
git add -A && git commit -m "feat(auth): native-auth foundation — schema, Account(MustVerifyEmail), session resource

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```
Expected: foundation test passes; full suite green; Pint/PHPStan clean.

---

### Task 2: Email verification machinery (notification + verify + resend)

**Files:**
- Create: `backend/app/Modules/Identity/Notifications/VerifyEmail.php`
- Create: `backend/app/Modules/Identity/Http/NativeAuthController.php` (with `verify` + `resend`)
- Modify: `backend/app/Modules/Identity/Domain/Models/Account.php` (override `sendEmailVerificationNotification`)
- Modify: `backend/routes/api.php` (verify route + resend route)
- Test: `backend/tests/Feature/EmailVerificationTest.php`

**Interfaces produced:**
- `App\Modules\Identity\Notifications\VerifyEmail` (queued, `via=['mail']`).
- Routes `auth.email.verify` (GET, signed) and `auth.email.resend` (POST, auth:sanctum).
- `NativeAuthController::verify(Request, string $id, string $hash, AuditLogger): RedirectResponse`, `::resend(Request): Response`.

- [ ] **Step 1: `VerifyEmail` notification.** Create `backend/app/Modules/Identity/Notifications/VerifyEmail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class VerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'auth.email.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject('Verify your email address')
            ->line('Please confirm your email address to finish setting up your Catalesta account.')
            ->action('Verify email', $url)
            ->line('If you did not create an account, no further action is required.');
    }
}
```

- [ ] **Step 2: Account override.** In `Account.php`, add (and import `use App\Modules\Identity\Notifications\VerifyEmail;`):

```php
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }
```

- [ ] **Step 3: Controller `verify` + `resend`.** Create `backend/app/Modules/Identity/Http/NativeAuthController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Audit\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class NativeAuthController extends Controller
{
    /**
     * GET /api/v1/auth/email/verify/{id}/{hash}  (signed)
     * Validates the signed link, marks the email verified, redirects to the SPA.
     */
    public function verify(Request $request, string $id, string $hash, AuditLogger $audit): RedirectResponse
    {
        /** @var Account $account */
        $account = Account::findOrFail($id);

        if (! hash_equals(sha1((string) $account->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $account->hasVerifiedEmail()) {
            $account->markEmailAsVerified();
            event(new Verified($account));
            $audit->record('auth.email_verified', 'account', (string) $account->id);
        }

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/auth/email-verified');
    }

    /**
     * POST /api/v1/auth/email/resend  (auth:sanctum)
     */
    public function resend(Request $request): Response
    {
        /** @var Account $account */
        $account = $request->user();

        if (! $account->hasVerifiedEmail()) {
            $account->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }
}
```

- [ ] **Step 4: Routes.** In `routes/api.php`, after the OIDC `auth/callback` route, add the public signed verify route; and inside the existing `auth:sanctum` group add resend:

```php
    Route::get('/auth/email/verify/{id}/{hash}', [NativeAuthController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('auth.email.verify');
```
```php
        Route::post('/auth/email/resend', [NativeAuthController::class, 'resend'])
            ->middleware('throttle:auth-resend')->name('auth.email.resend');
```
Add `use App\Modules\Identity\Http\NativeAuthController;` to the imports.

- [ ] **Step 5: Test.** Create `backend/tests/Feature/EmailVerificationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function unverified(): Account
    {
        return Account::create(['email' => 'v@example.com', 'email_verified_at' => null]);
    }

    private function verifyUrl(Account $a): string
    {
        return URL::temporarySignedRoute('auth.email.verify', now()->addMinutes(60), [
            'id' => $a->id,
            'hash' => sha1((string) $a->getEmailForVerification()),
        ]);
    }

    public function test_valid_signed_link_verifies_and_redirects(): void
    {
        $a = $this->unverified();
        $this->get($this->verifyUrl($a))->assertRedirect();
        $this->assertTrue($a->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email_verified', 'actor_account_id' => null, 'target_id' => $a->id]);
    }

    public function test_tampered_hash_is_rejected(): void
    {
        $a = $this->unverified();
        $url = $this->verifyUrl($a);
        // Valid signature but wrong hash segment → 403 from the controller check.
        $bad = str_replace(sha1((string) $a->getEmailForVerification()), sha1('wrong'), $url);
        $this->get($bad)->assertStatus(403);
        $this->assertFalse($a->fresh()->hasVerifiedEmail());
    }

    public function test_unsigned_or_expired_link_rejected(): void
    {
        $a = $this->unverified();
        $this->get("/api/v1/auth/email/verify/{$a->id}/".sha1((string) $a->getEmailForVerification()))
            ->assertStatus(403); // missing signature
    }

    public function test_resend_queues_notification_for_unverified_authed_user(): void
    {
        Notification::fake();
        $a = Account::create(['email' => 'r@example.com', 'email_verified_at' => null]);
        $this->actingAs($a, 'web')->postJson('/api/v1/auth/email/resend')->assertNoContent();
        Notification::assertSentTo($a, VerifyEmail::class);
    }

    public function test_resend_is_throttled(): void
    {
        $a = Account::create(['email' => 't@example.com', 'email_verified_at' => null]);
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($a, 'web')->postJson('/api/v1/auth/email/resend')->assertNoContent();
        }
        $this->actingAs($a, 'web')->postJson('/api/v1/auth/email/resend')->assertStatus(429);
    }
}
```

- [ ] **Step 6: Run + commit.**
```bash
php artisan test --filter=EmailVerificationTest 2>&1 | tail -8
vendor/bin/pint --dirty 2>&1 | tail -5
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress 2>&1 | tail -10
git add -A && git commit -m "feat(auth): email verification — signed verify link + resend (queued)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Registration

**Files:**
- Create: `backend/app/Modules/Identity/Http/Requests/RegisterRequest.php`
- Modify: `backend/app/Modules/Identity/Http/NativeAuthController.php` (add `register`)
- Modify: `backend/routes/api.php` (register route)
- Test: `backend/tests/Feature/RegistrationTest.php`

**Interfaces consumed:** `AccountSessionResource` (Task 1), `VerifyEmail`/`sendEmailVerificationNotification` (Task 2). **Produced:** route `auth.register`, `NativeAuthController::register(RegisterRequest, AuditLogger): JsonResponse`.

- [ ] **Step 1: `RegisterRequest`.** Create `backend/app/Modules/Identity/Http/Requests/RegisterRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:accounts,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 2: `register` method.** Add to `NativeAuthController` (imports: `use App\Modules\Identity\Http\Requests\RegisterRequest; use App\Modules\Identity\Http\Resources\AccountSessionResource; use Illuminate\Support\Facades\Auth; use Illuminate\Support\Facades\DB;`):

```php
    /**
     * POST /api/v1/auth/register
     * Creates a native account, issues a session (unverified), sends verification.
     */
    public function register(RegisterRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var array{email:string,password:string,display_name?:string|null} $data */
        $data = $request->validated();

        $account = DB::transaction(function () use ($data, $audit): Account {
            $account = Account::create([
                'email' => $data['email'],
                'password' => $data['password'], // hashed by the 'hashed' cast
                'display_name' => $data['display_name'] ?? null,
            ]);

            $account->sendEmailVerificationNotification();
            $audit->record('auth.register', 'account', (string) $account->id);

            return $account;
        });

        Auth::login($account);
        $request->session()->regenerate();

        return (new AccountSessionResource($account))->response()->setStatusCode(201);
    }
```

- [ ] **Step 3: Route.** In `routes/api.php`, add near the other public auth routes:

```php
    Route::post('/auth/register', [NativeAuthController::class, 'register'])
        ->middleware('throttle:auth-register')->name('auth.register');
```

- [ ] **Step 4: Test.** Create `backend/tests/Feature/RegistrationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_unverified_account_with_session_and_sends_verification(): void
    {
        Notification::fake();

        $res = $this->postJson('/api/v1/auth/register', [
            'email' => 'New@Example.com',
            'password' => 'super-secret',
            'display_name' => 'New User',
        ])->assertStatus(201);

        $res->assertJsonPath('user.email', 'new@example.com');       // lowercased
        $res->assertJsonPath('user.email_verified', false);
        $res->assertJsonPath('user.has_password', true);
        $res->assertJsonPath('user.linked_providers', []);
        $res->assertJsonPath('user.startup_gate_subject_id', null);

        $a = Account::where('email', 'new@example.com')->firstOrFail();
        $this->assertNotNull($a->password);
        $this->assertNull($a->email_verified_at);
        Notification::assertSentTo($a, VerifyEmail::class);

        // Session was issued.
        $this->getJson('/api/v1/auth/session')->assertOk()->assertJsonPath('user.email', 'new@example.com');
    }

    public function test_duplicate_email_rejected_422(): void
    {
        Account::create(['email' => 'dupe@example.com', 'password' => 'x']);
        $this->postJson('/api/v1/auth/register', ['email' => 'dupe@example.com', 'password' => 'super-secret'])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_weak_password_rejected_422(): void
    {
        $this->postJson('/api/v1/auth/register', ['email' => 'weak@example.com', 'password' => 'short'])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 5: Run + commit.**
```bash
php artisan test --filter=RegistrationTest 2>&1 | tail -8
vendor/bin/pint --dirty 2>&1 | tail -5
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress 2>&1 | tail -10
git add -A && git commit -m "feat(auth): native registration (email+password, session, queued verification)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Password login (enumeration-safe)

**Files:**
- Modify: `backend/app/Modules/Identity/Http/NativeAuthController.php` (add `login`)
- Modify: `backend/routes/api.php` (login route)
- Test: `backend/tests/Feature/PasswordLoginTest.php`

**Interfaces consumed:** `AccountSessionResource`. **Produced:** route `auth.password.login`, `NativeAuthController::login(Request, AuditLogger): JsonResponse`.

- [ ] **Step 1: `login` method.** Add to `NativeAuthController` (imports: `use Illuminate\Support\Facades\Hash; use Illuminate\Validation\ValidationException;`):

```php
    /**
     * POST /api/v1/auth/password/login
     * Enumeration-safe: unknown email and wrong password return the SAME 422.
     */
    public function login(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $account = Account::where('email', strtolower($data['email']))->first();

        if ($account === null || $account->password === null
            || ! Hash::check($data['password'], $account->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        Auth::login($account);
        $request->session()->regenerate();
        $audit->record('auth.login', 'account', (string) $account->id);

        return (new AccountSessionResource($account))->response();
    }
```

- [ ] **Step 2: Route.**
```php
    Route::post('/auth/password/login', [NativeAuthController::class, 'login'])
        ->middleware('throttle:auth-login')->name('auth.password.login');
```

- [ ] **Step 3: Test.** Create `backend/tests/Feature/PasswordLoginTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $email = 'login@example.com', string $pw = 'super-secret'): Account
    {
        return Account::create(['email' => $email, 'password' => $pw, 'email_verified_at' => now()]);
    }

    public function test_correct_credentials_log_in(): void
    {
        $this->account();
        $this->postJson('/api/v1/auth/password/login', ['email' => 'login@example.com', 'password' => 'super-secret'])
            ->assertOk()->assertJsonPath('user.email', 'login@example.com');
        $this->getJson('/api/v1/auth/session')->assertOk();
        $a = Account::where('email', 'login@example.com')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_account_id' => $a->id]);
    }

    public function test_wrong_password_and_unknown_email_return_identical_422(): void
    {
        $this->account();
        $wrongPw = $this->postJson('/api/v1/auth/password/login', ['email' => 'login@example.com', 'password' => 'nope'])
            ->assertStatus(422)->json('error.details');
        $unknown = $this->postJson('/api/v1/auth/password/login', ['email' => 'ghost@example.com', 'password' => 'nope'])
            ->assertStatus(422)->json('error.details');
        $this->assertSame($wrongPw, $unknown); // no user-existence leak
    }

    public function test_sso_only_account_cannot_native_login(): void
    {
        Account::create(['email' => 'sso@example.com', 'password' => null, 'email_verified_at' => now()]);
        $this->postJson('/api/v1/auth/password/login', ['email' => 'sso@example.com', 'password' => 'anything'])
            ->assertStatus(422);
    }

    public function test_login_is_throttled(): void
    {
        $this->account();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/password/login', ['email' => 'login@example.com', 'password' => 'nope'])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/password/login', ['email' => 'login@example.com', 'password' => 'nope'])->assertStatus(429);
    }
}
```

- [ ] **Step 4: Run + commit.**
```bash
php artisan test --filter=PasswordLoginTest 2>&1 | tail -8
vendor/bin/pint --dirty 2>&1 | tail -5
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress 2>&1 | tail -10
git add -A && git commit -m "feat(auth): native password login (enumeration-safe, throttled, audited)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Password reset (forgot + reset)

**Files:**
- Create: `backend/app/Modules/Identity/Notifications/ResetPassword.php`
- Modify: `backend/app/Modules/Identity/Domain/Models/Account.php` (override `sendPasswordResetNotification`)
- Modify: `backend/app/Modules/Identity/Http/NativeAuthController.php` (add `forgot` + `reset`)
- Modify: `backend/routes/api.php` (two routes)
- Test: `backend/tests/Feature/PasswordResetTest.php`

**Interfaces produced:** routes `auth.password.forgot`, `auth.password.reset`; the `ResetPassword` notification.

- [ ] **Step 1: `ResetPassword` notification.** Create `backend/app/Modules/Identity/Notifications/ResetPassword.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $token) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = (string) $notifiable->getEmailForPasswordReset();
        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/auth/reset-password?token='.$this->token.'&email='.urlencode($email);

        return (new MailMessage)
            ->subject('Reset your password')
            ->line('You requested a password reset. Click below to choose a new password.')
            ->action('Reset password', $url)
            ->line('This link expires in 60 minutes. If you did not request this, ignore this email.');
    }
}
```

- [ ] **Step 2: Account override.** In `Account.php` add (import `use App\Modules\Identity\Notifications\ResetPassword;`):

```php
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }
```

- [ ] **Step 3: `forgot` + `reset`.** Add to `NativeAuthController` (imports `use Illuminate\Support\Facades\Password;`):

```php
    /**
     * POST /api/v1/auth/password/forgot — always 200 (no enumeration).
     */
    public function forgot(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);
        Password::sendResetLink(['email' => strtolower($data['email'])]);
        $audit->record('auth.password_reset_requested', 'account', null);

        return response()->json(['message' => 'If that email exists, a reset link has been sent.']);
    }

    /**
     * POST /api/v1/auth/password/reset
     */
    public function reset(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $status = Password::reset(
            ['email' => strtolower($data['email']), 'password' => $data['password'], 'token' => $data['token']],
            function (Account $account, string $password): void {
                $account->password = $password; // hashed by cast
                $account->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This password reset token is invalid or has expired.']);
        }

        $audit->record('auth.password_reset_completed', 'account', null);

        return response()->json(['message' => 'Your password has been reset.']);
    }
```

- [ ] **Step 4: Routes.**
```php
    Route::post('/auth/password/forgot', [NativeAuthController::class, 'forgot'])
        ->middleware('throttle:auth-forgot')->name('auth.password.forgot');
    Route::post('/auth/password/reset', [NativeAuthController::class, 'reset'])
        ->middleware('throttle:auth-forgot')->name('auth.password.reset');
```

- [ ] **Step 5: Test.** Create `backend/tests/Feature/PasswordResetTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_known_and_unknown_both_return_200_but_only_known_is_notified(): void
    {
        Notification::fake();
        $a = Account::create(['email' => 'known@example.com', 'password' => 'old-secret', 'email_verified_at' => now()]);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'known@example.com'])->assertOk();
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])->assertOk();

        Notification::assertSentTo($a, ResetPassword::class);
        Notification::assertSentToTimes($a, ResetPassword::class, 1);
    }

    public function test_reset_changes_password_and_old_fails_new_works(): void
    {
        $a = Account::create(['email' => 'reset@example.com', 'password' => 'old-secret', 'email_verified_at' => now()]);
        $token = Password::broker()->createToken($a);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token, 'email' => 'reset@example.com', 'password' => 'brand-new-pw',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pw', $a->fresh()->password));
        // new password logs in; old does not
        $this->postJson('/api/v1/auth/password/login', ['email' => 'reset@example.com', 'password' => 'brand-new-pw'])->assertOk();
    }

    public function test_invalid_token_rejected_422(): void
    {
        Account::create(['email' => 'bad@example.com', 'password' => 'old-secret']);
        $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'not-a-real-token', 'email' => 'bad@example.com', 'password' => 'brand-new-pw',
        ])->assertStatus(422);
    }
}
```

> Note: `BaseResetNotification` import is unused if your linter flags it — remove it; the assertion uses our `ResetPassword`.

- [ ] **Step 6: Run + commit.**
```bash
php artisan test --filter=PasswordResetTest 2>&1 | tail -8
vendor/bin/pint --dirty 2>&1 | tail -5
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress 2>&1 | tail -10
git add -A && git commit -m "feat(auth): password reset (forgot/reset via broker, queued email, no enumeration)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Org-create email-verified gate

**Files:**
- Create: `backend/app/Http/Middleware/EnsureEmailVerified.php`
- Modify: `backend/bootstrap/app.php` (alias)
- Modify: `backend/routes/api.php` (apply to `POST /organizations` + membership store)
- Test: `backend/tests/Feature/EmailVerifiedGateTest.php`

**Interfaces consumed:** `Account::hasVerifiedEmail()`, `makeAccount` (verified). **Produced:** middleware alias `verified.email`.

- [ ] **Step 1: Middleware.** Create `backend/app/Http/Middleware/EnsureEmailVerified.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasVerifiedEmail()) {
            return response()->json(['error' => [
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Email verification is required for this action.',
                'correlation_id' => CorrelationId::get(),
            ]], 403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Alias.** In `bootstrap/app.php` `withMiddleware`, extend the alias map (import `use App\Http\Middleware\EnsureEmailVerified;`):

```php
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'verified.email' => EnsureEmailVerified::class,
        ]);
```

- [ ] **Step 3: Apply to routes.** In `routes/api.php`:
  - `POST /organizations` (in the `auth:sanctum`-only group) → add `verified.email`:
```php
        Route::post('/organizations', [OrganizationController::class, 'store'])
            ->middleware('verified.email')->name('organizations.store');
```
  - `POST /organizations/{org}/memberships` (in the `auth:sanctum`+`tenant` group) → add `verified.email`:
```php
        Route::post('/organizations/{org}/memberships', [MembershipController::class, 'store'])
            ->middleware('verified.email')->name('organizations.memberships.store');
```

- [ ] **Step 4: Test.** Create `backend/tests/Feature/EmailVerifiedGateTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmailVerifiedGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_native_account_cannot_create_org(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $a = Account::create(['email' => 'unv@example.com', 'password' => 'super-secret', 'email_verified_at' => null]);

        $this->actingAs($a, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Acme'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_verified_native_account_can_create_org(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $a = Account::create(['email' => 'ver@example.com', 'password' => 'super-secret', 'email_verified_at' => now()]);

        $this->actingAs($a, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Verified Co'])
            ->assertStatus(201);
    }

    public function test_sg_account_passes_gate(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $a = $this->makeAccount(); // verified by the seam (Task 1)

        $this->actingAs($a, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'SG Co'])
            ->assertStatus(201);
    }
}
```

- [ ] **Step 5: Full suite + gates + commit.**
```bash
php artisan test 2>&1 | tail -8        # entire suite incl. OIDC + all native auth
vendor/bin/pint --dirty 2>&1 | tail -5
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress 2>&1 | tail -10
git add -A && git commit -m "feat(auth): email-verified gate on org create/join (EnsureEmailVerified middleware)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage** (spec section → task):
- §3 migration (password, email_verified_at, unique email, SG backfill) → Task 1 Step 1 ✓
- §4 Account MustVerifyEmail+Notifiable+casts + SG auto-verify → Task 1 Steps 2–3 ✓
- §5 endpoints: register → T3; password/login → T4; email/verify + resend → T2; password/forgot + reset → T5 ✓
- §6 queued notifications (frontend-coordinated URLs) → T2 (VerifyEmail), T5 (ResetPassword); throttle limiters → T1 Step 8 + applied per endpoint; audit actions → each task ✓
- §7 org-create email-verified gate → Task 6 ✓
- §8 evolved session JSON (`AccountSessionResource`) used by callback/session/register/login + `/me` extended → Task 1 Steps 4–6, consumed in T3/T4 ✓
- §9 tests (register, login enumeration-safe, verify, resend throttle, forgot/reset no-enum, gate, throttle, session shape) → distributed across T1–T6 ✓

**Refinements vs spec (flagged):**
1. **Email-verify link → backend signed GET that redirects to `FRONTEND_URL/auth/email-verified`** (Task 2) instead of a frontend page reconstructing a URL signature. Standard, robust Laravel pattern; same UX. Affects the 1b-ii contract: the verify "page" is a landing/redirect target, not a form. **Password reset is unchanged** (frontend page + broker token).
2. **Enumeration-safe login uses `ValidationException` (422 `VALIDATION_ERROR`)** with one generic message for both failure modes, rather than introducing a new `INVALID_CREDENTIALS` error code — avoids touching the global exception map; bodies are identical (the enumeration guard is satisfied).

**Placeholder scan:** every step has complete code; no TBD/"handle errors". The one `BaseResetNotification` import in the reset test is flagged for removal if unused.

**Type/name consistency:** `AccountSessionResource` (wrap `user`, 7 fields) used identically in T1/T3/T4; rate-limiter names `auth-register`/`auth-login`/`auth-resend`/`auth-forgot` defined in T1 and referenced in T2–T5; route names `auth.email.verify`/`auth.email.resend`/`auth.register`/`auth.password.login`/`auth.password.forgot`/`auth.password.reset` consistent; `NativeAuthController` method signatures match their routes; `email_verified_at`/`hasVerifiedEmail()`/`has_password` consistent throughout.

**Out of scope (enforced):** no frontend; the session-schema *frontend* fix and CSRF are SP-1b-ii; SG link/unlink + email-match is SP-2; import is SP-4.
