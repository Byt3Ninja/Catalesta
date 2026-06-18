<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Http\Resources;

use App\Modules\Organizations\Domain\Models\OrganizationRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $external_user_id
 * @property-read string $status
 * @property-read Collection<int, OrganizationRole> $roles
 */
final class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'external_user_id' => $this->external_user_id,
            'status' => $this->status,
            'roles' => $this->roles->pluck('key')->toArray(),
        ];
    }
}
