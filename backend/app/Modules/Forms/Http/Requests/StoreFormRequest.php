<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize('create', Form::class)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
