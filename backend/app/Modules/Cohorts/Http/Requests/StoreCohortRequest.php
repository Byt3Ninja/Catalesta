<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http\Requests;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCohortRequest extends FormRequest
{
    /**
     * Authorize by loading the parent program tenant-scoped.
     * A null result (foreign-org or nonexistent program) returns false → 403,
     * preventing cross-tenant probing before any validation queries run.
     */
    public function authorize(): bool
    {
        $program = Program::query()->find($this->route('program'));

        if ($program === null) {
            return false;
        }

        return $this->user() !== null
            && $this->user()->can('create', Cohort::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::enum(CohortStatus::class)],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'enrollment_opens_at' => ['sometimes', 'nullable', 'date'],
            'enrollment_closes_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:enrollment_opens_at',
            ],
            'starts_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:enrollment_closes_at',
            ],
            'ends_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],
            'timeline' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
