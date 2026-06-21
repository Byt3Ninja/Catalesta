<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Domain\Models\LinkedIdentity;
use App\Modules\Identity\Domain\Models\LinkedIdentityToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LinkedIdentityTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_are_encrypted_at_rest(): void
    {
        $account = Account::create([
            'email' => 'token@test.example',
            'is_platform_admin' => false,
        ]);

        $link = LinkedIdentity::create([
            'account_id' => $account->id,
            'provider' => 'startup_gate',
            'subject_id' => 'sg_token_subject',
            'linked_at' => now(),
        ]);

        $row = LinkedIdentityToken::create([
            'linked_identity_id' => $link->id,
            'access_token' => 'plain-access',
            'refresh_token' => 'plain-refresh',
        ]);

        $raw = DB::table('linked_identity_tokens')->where('id', $row->id)->value('access_token');
        $this->assertNotSame('plain-access', $raw);

        $this->assertSame('plain-access', $row->fresh()->access_token);
    }
}
