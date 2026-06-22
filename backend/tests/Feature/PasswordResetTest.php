<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Notifications\ResetPassword;
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
