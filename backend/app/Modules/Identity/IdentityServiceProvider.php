<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Domain\Contracts\AchievementPublisher;
use App\Modules\Identity\Domain\Contracts\ConsentProvider;
use App\Modules\Identity\Domain\Contracts\IdentityProvider;
use App\Modules\Identity\Domain\Contracts\ProfileProvider;
use App\Modules\Identity\Domain\Contracts\RoleProfileProvider;
use App\Modules\Identity\Domain\Contracts\StartupMembershipProvider;
use App\Modules\Identity\Infrastructure\StartupGate\StartupGateAchievementPublisher;
use App\Modules\Identity\Infrastructure\StartupGate\StartupGateConsentProvider;
use App\Modules\Identity\Infrastructure\StartupGate\StartupGateIdentityProvider;
use App\Modules\Identity\Infrastructure\StartupGate\StartupGateProfileProvider;
use App\Modules\Identity\Infrastructure\StartupGate\StartupGateRoleProfileProvider;
use App\Modules\Identity\Infrastructure\StartupGate\StartupGateStartupMembershipProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Provider map: config('identity.provider') key → concrete implementation set.
     *
     * Phase 1 has a single adapter set (StartupGate HTTP adapter).  The HTTP adapter
     * is environment-differentiated: when IDENTITY_PROVIDER=mock it talks to the local
     * mock OIDC server (OIDC_ISSUER / PROFILE_API_BASE_URL pointing at startup-gate-mock);
     * when IDENTITY_PROVIDER=startup_gate (or 'production') it talks to the real Startup
     * Gate service using the same env vars pointed at production endpoints.
     *
     * A future provider key (e.g. 'local_stub') can map to a different adapter set here
     * without touching any consumer or domain code.
     *
     * @var array<string, array<class-string, class-string>>
     */
    private const PROVIDER_MAP = [
        // 'mock' — local mock OIDC server (default for Phase 1 / development / tests).
        // The StartupGate HTTP adapter reads OIDC_ISSUER and PROFILE_API_BASE_URL which
        // are set to the mock server URLs in .env.testing / docker-compose.
        'mock' => [
            IdentityProvider::class => StartupGateIdentityProvider::class,
            ProfileProvider::class => StartupGateProfileProvider::class,
            ConsentProvider::class => StartupGateConsentProvider::class,
            RoleProfileProvider::class => StartupGateRoleProfileProvider::class,
            StartupMembershipProvider::class => StartupGateStartupMembershipProvider::class,
            AchievementPublisher::class => StartupGateAchievementPublisher::class,
        ],

        // 'startup_gate' / 'production' — real Startup Gate service.
        // Same adapter set; swap only the env vars (OIDC_ISSUER, PROFILE_API_BASE_URL)
        // to point at production endpoints.  No code changes required.
        'startup_gate' => [
            IdentityProvider::class => StartupGateIdentityProvider::class,
            ProfileProvider::class => StartupGateProfileProvider::class,
            ConsentProvider::class => StartupGateConsentProvider::class,
            RoleProfileProvider::class => StartupGateRoleProfileProvider::class,
            StartupMembershipProvider::class => StartupGateStartupMembershipProvider::class,
            AchievementPublisher::class => StartupGateAchievementPublisher::class,
        ],

        'production' => [
            IdentityProvider::class => StartupGateIdentityProvider::class,
            ProfileProvider::class => StartupGateProfileProvider::class,
            ConsentProvider::class => StartupGateConsentProvider::class,
            RoleProfileProvider::class => StartupGateRoleProfileProvider::class,
            StartupMembershipProvider::class => StartupGateStartupMembershipProvider::class,
            AchievementPublisher::class => StartupGateAchievementPublisher::class,
        ],
    ];

    public function register(): void
    {
        /** @var string $provider */
        $provider = config('identity.provider', 'mock');

        if (! array_key_exists($provider, self::PROVIDER_MAP)) {
            throw new RuntimeException(
                "Unknown identity provider '{$provider}'. "
                .'Set IDENTITY_PROVIDER to one of: '
                .implode(', ', array_keys(self::PROVIDER_MAP))
                .'.',
            );
        }

        foreach (self::PROVIDER_MAP[$provider] as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
