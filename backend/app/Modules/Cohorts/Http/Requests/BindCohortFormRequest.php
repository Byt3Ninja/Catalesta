<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BindCohortFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize('bindForm', $cohort)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['form_version_id' => ['required', 'string']];
    }
}
