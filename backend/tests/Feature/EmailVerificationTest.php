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
