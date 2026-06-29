<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveFormDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes 'update'
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['present', 'array'],
            'fields.*.type' => ['required', 'string'],
        ];
    }
}
