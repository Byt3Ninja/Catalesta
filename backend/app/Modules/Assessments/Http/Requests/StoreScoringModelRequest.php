<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreScoringModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize('create', ScoringModel::class)
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
