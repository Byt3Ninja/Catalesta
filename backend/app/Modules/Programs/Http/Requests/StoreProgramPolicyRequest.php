<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Requests;

use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class StoreProgramPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $program = Program::query()->withoutGlobalScope('tenant')->find($this->route('program'));
        if ($program === null) {
            return true; // let the controller's findOrFail produce a clean 404
        }

        return $this->user() !== null && Gate::forUser($this->user())->allows('update', $program);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var string $programId */
        $programId = $this->route('program');

        return [
            'key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('program_policies')->where('program_id', $programId),
            ],
            'value' => ['present'],
        ];
    }
}
