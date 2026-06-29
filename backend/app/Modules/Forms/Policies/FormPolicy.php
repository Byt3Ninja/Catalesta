<?php

declare(strict_types=1);

namespace App\Modules\Forms\Policies;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for Form.
 *
 * viewAny/view: any authenticated tenant member may read forms.
 *   BelongsToTenant global scope + ResolveTenant middleware ensure only the
 *   correct tenant's records are visible; no extra permission key required.
 *
 * create/update/publish: require `forms.manage` permission.
 * Deny-by-default for everything else (no before() hook).
 */
final class FormPolicy
{
    /**
     * Any authenticated tenant member may list forms.
     */
    public function viewAny(Account $user): bool
    {
        return true;
    }

    /**
     * Any authenticated tenant member may view a single form.
     */
    public function view(Account $user, Form $form): bool
    {
        return true;
    }

    /**
     * Creating a form requires forms.manage.
     */
    public function create(Account $user): bool
    {
        return app(TenantContext::class)->can('forms.manage');
    }

    /**
     * Updating a form requires forms.manage.
     */
    public function update(Account $user, Form $form): bool
    {
        return app(TenantContext::class)->can('forms.manage');
    }

    /**
     * Publishing a form version requires forms.manage.
     */
    public function publish(Account $user, Form $form): bool
    {
        return app(TenantContext::class)->can('forms.manage');
    }
}
