<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

interface TenantMembership
{
    public function organizationId(): string;

    /** @return array<int,string> */
    public function effectivePermissionKeys(): array;
}
