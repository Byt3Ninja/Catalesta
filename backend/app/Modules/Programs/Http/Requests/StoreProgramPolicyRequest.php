<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProgramPolicyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var string $programId */
        $programId = $this->route('program');

        return [
            'key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('program_policies')->where('program_id', $programId),
            ],
            'value' => ['present'],
        ];
    }
}
