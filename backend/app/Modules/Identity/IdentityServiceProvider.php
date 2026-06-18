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

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IdentityProvider::class, StartupGateIdentityProvider::class);
        $this->app->bind(ProfileProvider::class, StartupGateProfileProvider::class);
        $this->app->bind(ConsentProvider::class, StartupGateConsentProvider::class);
        $this->app->bind(RoleProfileProvider::class, StartupGateRoleProfileProvider::class);
        $this->app->bind(StartupMembershipProvider::class, StartupGateStartupMembershipProvider::class);
        $this->app->bind(AchievementPublisher::class, StartupGateAchievementPublisher::class);
    }
}
