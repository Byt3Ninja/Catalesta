<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Shared\Audit\AuditLog;
use App\Shared\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Story RA.2 (slice 1) — enforced audit of authorization decisions.
 * A Gate::after hook records every DENIAL and every allow on a non-read
 * (mutating/sensitive) ability; read allows (view/viewAny) are not recorded.
 * Best-effort: a write failure never blocks the decision.
 */
final class AuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_denied_authorization_records_an_audit_row(): void
    {
        [$user, $org] = $this->bootUserWithOrg('Acme');
        $this->actingAsTenant($user, $org);

        Gate::define('ra2-delete-thing', fn () => false);

        try {
            Gate::authorize('ra2-delete-thing');
        } catch (\Throwable) {
            // denial throws AuthorizationException; the decision must still be recorded
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'authz.ra2-delete-thing',
            'result' => 'denied',
            'actor_account_id' => $user->id,
        ]);
    }

    public function test_allowed_mutating_ability_records_an_audit_row(): void
    {
        [$user, $org] = $this->bootUserWithOrg('Acme');
        $this->actingAsTenant($user, $org);

        Gate::define('ra2-update-thing', fn () => true);

        $this->assertTrue(Gate::allows('ra2-update-thing'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'authz.ra2-update-thing',
            'result' => 'allowed',
            'actor_account_id' => $user->id,
        ]);
    }

    public function test_allowed_read_ability_is_not_recorded(): void
    {
        [$user, $org] = $this->bootUserWithOrg('Acme');
        $this->actingAsTenant($user, $org);

        Gate::define('view', fn () => true);

        $this->assertTrue(Gate::allows('view'));

        $this->assertDatabaseMissing('audit_logs', ['action' => 'authz.view']);
    }

    public function test_audit_write_failure_never_blocks_the_decision(): void
    {
        [$user, $org] = $this->bootUserWithOrg('Acme');
        $this->actingAsTenant($user, $org);

        // Swap in an AuditLogger that throws on every write.
        $this->app->bind(AuditLogger::class, fn () => new class extends AuditLogger
        {
            public function __construct() {}

            public function record(string $action, ?string $targetType, ?string $targetId, array $before = [], array $after = [], string $result = 'success', ?string $organizationId = null, ?string $actorAccountId = null): AuditLog
            {
                throw new \RuntimeException('audit sink down');
            }
        });

        Gate::define('ra2-publish-thing', fn () => true);

        // Best-effort: the decision must still resolve true despite the write blowing up.
        $this->assertTrue(Gate::allows('ra2-publish-thing'));
    }
}
