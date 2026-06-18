<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Policies;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for the OrganizationMembership model.
 *
 * Judgment call — viewAny():
 *   We require `members.manage` rather than returning `true` for any resolved
 *   member. The membership list exposes personally identifiable information
 *   (user IDs, status, roles) about all members of the organization. Restricting
 *   it to users who hold `members.manage` follows least-privilege; a plain member
 *   or someone with only `members.invite` does not need to enumerate all existing
 *   memberships. If a "read-only member list" use-case arises (e.g. a separate
 *   `members.view` permission), add it here alongside `members.manage`.
 *
 * create(): allows anyone who holds `members.invite` OR `members.manage`.
 *   `members.invite` is the narrow right to add a new member.
 *   `members.manage` implies full membership control, so it covers invite too.
 *   Platform admins bypass via TenantContext.
 */
final class MembershipPolicy
{
    /**
     * Determine whether the user can list memberships.
     *
     * Requires `members.manage`. Platform admins always allowed.
     */
    public function viewAny(ExternalUser $user): bool
    {
        return app(TenantContext::class)->can('members.manage');
    }

    /**
     * Determine whether the user can create (invite) a new membership.
     *
     * Requires either `members.invite` or `members.manage`.
     * Platform admins always allowed.
     */
    public function create(ExternalUser $user): bool
    {
        $ctx = app(TenantContext::class);

        return $ctx->can('members.invite') || $ctx->can('members.manage');
    }
}
