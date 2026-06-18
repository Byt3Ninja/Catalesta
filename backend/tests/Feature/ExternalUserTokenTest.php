<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\ExternalUserToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ExternalUserTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_are_encrypted_at_rest(): void
    {
        $row = ExternalUserToken::create([
            'external_user_id' => '01HZZZEXTERNALUSERID00000001',
            'access_token' => 'plain-access',
            'refresh_token' => 'plain-refresh',
        ]);

        $raw = DB::table('external_user_tokens')->where('id', $row->id)->value('access_token');
        $this->assertNotSame('plain-access', $raw);

        $this->assertSame('plain-access', $row->fresh()->access_token);
    }
}
