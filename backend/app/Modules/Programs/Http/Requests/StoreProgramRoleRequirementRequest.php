<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Requests;

use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class StoreProgramRoleRequirementRequest extends FormRequest
{
    /**
     * Validate the class instance.
     */
    public function validateResolved(): void
    {
        $this->checkAuthorization();
        parent::validateResolved();
    }

    private function checkAuthorization(): void
    {
        $program = Program::query()->withoutGlobalScope('tenant')->find($this->route('program'));
        if ($program === null) {
            return; // let the controller's findOrFail produce a clean 404
        }
        if ($this->user() === null || ! Gate::forUser($this->user())->allows('update', $program)) {
            throw new AuthorizationException;
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var string $programId */
        $programId = $this->route('program');

        /** @var int $minCount */
        $minCount = (int) $this->input('min_count', 0);

        return [
            'role_key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('program_role_requirements')->where('program_id', $programId),
            ],
            'min_count' => ['integer', 'min:0'],
            'max_count' => ['nullable', 'integer', 'min:'.$minCount],
            'is_required' => ['boolean'],
        ];
    }
}
