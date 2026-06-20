<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public application submit (Story 2.7). The Idempotency-Key arrives
 * as a header; it is merged into the input so a missing key returns a clean 422
 * with field details rather than a 500. Files are type/size-guarded pre-store.
 */
final class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is behind auth:sanctum; any authenticated applicant may submit.
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxKb = (int) ceil(((int) config('blob.max_bytes')) / 1024);

        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'answers' => ['required', 'array'],
            'blob_digests' => ['sometimes', 'array', 'max:50'],
            'blob_digests.*' => ['string', 'regex:/^[a-f0-9]{64}$/'],
            // Cap the file COUNT, not just per-file size: getContent() loads every
            // upload into memory at once, so an uncapped array could exhaust the
            // worker. 20 files at the per-file ceiling is a generous application.
            'files' => ['sometimes', 'array', 'max:20'],
            'files.*' => ['file', 'max:'.$maxKb, 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ];
    }
}
