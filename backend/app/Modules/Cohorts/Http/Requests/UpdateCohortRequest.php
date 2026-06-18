<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http\Requests;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCohortRequest extends FormRequest
{
    /**
     * Authorize by loading the cohort tenant-scoped.
     * A null result (foreign-org or nonexistent cohort) returns false → 403.
     * Then checks cohorts.manage via policy.
     */
    public function authorize(): bool
    {
        $cohort = Cohort::query()->find($this->route('id'));

        if ($cohort === null) {
            return false;
        }

        return $this->user() !== null
            && $this->user()->can('update', $cohort);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
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
