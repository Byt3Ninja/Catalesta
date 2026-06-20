<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Domain\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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
            'name' => [
                'required',
                'string',
                'max:255',
                // The org slug (derived from name) has a unique index; reject a colliding
                // name with a clean 422 instead of letting it 500 on the DB constraint.
                // ponytail: the unique index is the backstop for the create-race.
                function (string $attribute, mixed $value, callable $fail): void {
                    if (is_string($value) && Organization::query()->where('slug', Str::slug($value))->exists()) {
                        $fail('An organization with a similar name already exists.');
                    }
                },
            ],
            'branding' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
