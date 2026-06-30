<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Requests;

use App\Modules\Programs\Domain\Models\ProgramType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProgramRequest extends FormRequest
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
            'type' => ['sometimes', 'nullable', Rule::enum(ProgramType::class)],
            'description' => ['sometimes', 'nullable', 'string'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
