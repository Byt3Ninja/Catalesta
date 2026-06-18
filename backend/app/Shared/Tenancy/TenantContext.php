<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use App\Shared\Tenancy\Contracts\TenantMembership;

final class TenantContext
{
    private ?string $organizationId = null;

    private ?TenantMembership $membership = null;

    /** @var array<int,string> */
    private array $permissions = [];

    private bool $platformAdmin = false;

    private bool $system = false;

    /** @param array<int,string> $permissionKeys */
    public function setOrganization(string $organizationId, TenantMembership $membership, array $permissionKeys): void
    {
        $this->organizationId = $organizationId;
        $this->membership = $membership;
        $this->permissions = $permissionKeys;
    }

    public function organizationId(): ?string
    {
        return $this->organizationId;
    }

    public function membership(): ?TenantMembership
    {
        return $this->membership;
    }

    public function has(): bool
    {
        return $this->organizationId !== null;
    }

    public function actingAsPlatformAdmin(bool $is): void
    {
        $this->platformAdmin = $is;
    }

    public function can(string $permissionKey): bool
    {
        return $this->platformAdmin || in_array($permissionKey, $this->permissions, true);
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    /**
     * Run $fn with cross-tenant (system) access. The ONLY sanctioned way to span
     * tenants when no specific tenant is resolved. Re-entrant; restores prior state.
     *
     * @template T
     *
     * @param  callable():T  $fn
     * @return T
     */
    public function runAsSystem(callable $fn): mixed
    {
        $previous = $this->system;
        $this->system = true;
        try {
            return $fn();
        } finally {
            $this->system = $previous;
        }
    }
}
