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
        $program = Program::query()->find($this->route('program'));
        // Tenant-scoped: a foreign-org (or nonexistent) program resolves to null here.
        // Return false → 403 BEFORE any unique-validation query runs, so neither an
        // unauthorized in-tenant caller nor a cross-tenant caller can probe key existence.
        if ($program === null) {
            return false;
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
