<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOrganizationRequest extends FormRequest
{
    /**
     * Authorization is handled by auth:sanctum middleware.
     * Any authenticated user can attempt to create an organization.
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
            'name' => ['required', 'string', 'max:255'],
            'branding' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
