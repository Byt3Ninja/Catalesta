<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreStageDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'depends_on_program_stage_id' => ['required', 'string'],
        ];
    }
}
