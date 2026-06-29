<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForkFormDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['from_version_id' => ['required', 'string']];
    }
}
