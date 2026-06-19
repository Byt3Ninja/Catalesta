<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTrackRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'order_index' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
