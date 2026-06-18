<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http\Requests;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Domain\Models\ProgramStage;
use Illuminate\Foundation\Http\FormRequest;

final class ReorderStagesRequest extends FormRequest
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

        return $this->user()?->can('reorder', ProgramStage::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage_ids' => ['required', 'array'],
            'stage_ids.*' => ['required', 'string'],
        ];
    }
}
