<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveScoringModelDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller authorizes 'update'
    }

    /**
     * Structural rules only — exact-shape validation (max_points > 0, label required)
     * happens in CriteriaValidator inside the service. Controller reads criteria via
     * input('criteria', []) to avoid nested-key stripping by validated().
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'criteria' => ['present', 'array'],
            'criteria.*.criterion_id' => ['required', 'string'],
            'criteria.*.label' => ['required', 'string'],
            'criteria.*.max_points' => ['required', 'numeric', 'gt:0'],
            'criteria.*.descriptors' => ['nullable', 'array'],
            'criteria.*.descriptors.*' => ['string'],
        ];
    }
}
