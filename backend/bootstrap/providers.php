<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Stages\StagesServiceProvider;
use App\Providers\AppServiceProvider;
use App\StartupGateMock\StartupGateMockServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    StartupGateMockServiceProvider::class,
    StagesServiceProvider::class,
];
