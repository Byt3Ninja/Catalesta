<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTrackRequest extends FormRequest
{
    /**
     * Authorization is handled by ProgramPolicy via the controller's $this->authorize().
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
        /** @var string $programId */
        $programId = $this->route('program');

        return [
            'key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tracks')->where('program_id', $programId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'order_index' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
