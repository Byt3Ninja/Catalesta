<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Authorization is handled by OrganizationPolicy via $this->authorize() in controller.
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
            'name' => ['sometimes', 'string', 'max:255'],
            'branding' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
