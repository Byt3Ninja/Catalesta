<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BindCohortStagePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize('bindStagePipeline', $cohort)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['stage_pipeline_version_id' => ['required', 'string']];
    }
}
