<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http\Requests;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStageRequest extends FormRequest
{
    /**
     * Authorize by performing a tenant-scoped load of the parent program.
     * Null (not found in tenant) → false → 403.
     */
    public function authorize(): bool
    {
        /** @var string|null $programId */
        $programId = $this->route('program');

        if ($programId === null) {
            return false;
        }

        $program = Program::query()->find($programId);

        if ($program === null) {
            return false;
        }

        return $this->user()?->can('create', ProgramStage::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_column(StageType::cases(), 'value'))],
            'parallel_group' => ['sometimes', 'nullable', 'string', 'max:100'],
            'config' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
