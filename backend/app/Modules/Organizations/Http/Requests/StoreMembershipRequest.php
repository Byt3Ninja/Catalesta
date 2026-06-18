<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMembershipRequest extends FormRequest
{
    /**
     * Authorization is handled by MembershipPolicy via $this->authorize() in controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_user_id' => ['required', 'string', 'exists:external_users,id'],
            'role_keys' => ['sometimes', 'array'],
            'role_keys.*' => ['string'],
        ];
    }
}
