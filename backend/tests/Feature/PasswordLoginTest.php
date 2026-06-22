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
