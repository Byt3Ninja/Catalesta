<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http\Requests;

use App\Modules\Stages\Domain\Models\ProgramStage;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateStageRequest extends FormRequest
{
    /**
     * Authorize by performing a tenant-scoped load of the stage.
     * Null (not found in tenant) → false → 403.
     */
    public function authorize(): bool
    {
        /** @var string|null $stageId */
        $stageId = $this->route('id');

        if ($stageId === null) {
            return false;
        }

        $stage = ProgramStage::query()->find($stageId);

        if ($stage === null) {
            return false;
        }

        return $this->user()?->can('update', $stage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'parallel_group' => ['sometimes', 'nullable', 'string', 'max:100'],
            'config' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
