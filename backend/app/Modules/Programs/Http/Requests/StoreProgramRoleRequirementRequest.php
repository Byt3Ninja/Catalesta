<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProgramRoleRequirementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var string $programId */
        $programId = $this->route('program');

        /** @var int $minCount */
        $minCount = (int) $this->input('min_count', 0);

        return [
            'role_key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('program_role_requirements')->where('program_id', $programId),
            ],
            'min_count' => ['integer', 'min:0'],
            'max_count' => ['nullable', 'integer', 'min:'.$minCount],
            'is_required' => ['boolean'],
        ];
    }
}
