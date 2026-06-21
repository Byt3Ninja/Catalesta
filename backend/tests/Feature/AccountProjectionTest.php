<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\LinkedIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_is_keyed_on_sub_not_email(): void
    {
        $claims = ['sub' => 'sg_user_01', 'email' => 'a@example.com', 'name' => 'A', 'locale' => 'en', 'profile_updated_at' => 1781712000];
        $l1 = LinkedIdentity::projectFromClaims($claims);

        // same sub, new email -> SAME account updated, not a new row
        $l2 = LinkedIdentity::projectFromClaims([...$claims, 'email' => 'changed@example.com']);

        $this->assertSame($l1->account->id, $l2->account->id);
        $this->assertSame('changed@example.com', $l2->account->email);
        $this->assertDatabaseCount('accounts', 1);
    }

    public function test_different_sub_creates_distinct_user_even_with_same_email(): void
    {
        LinkedIdentity::projectFromClaims(['sub' => 'sg_user_01', 'email' => 'same@example.com']);
        LinkedIdentity::projectFromClaims(['sub' => 'sg_user_02', 'email' => 'same@example.com']);
        $this->assertDatabaseCount('accounts', 2);
    }
}
