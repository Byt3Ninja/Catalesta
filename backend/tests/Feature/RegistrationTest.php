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
