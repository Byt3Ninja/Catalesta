<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Full submission detail for the operator (Story 2.8) — the immutable snapshot
 * (Story 2.6) the applicant submitted: answers, content-addressed blob refs, and
 * the resolved form/program/rubric version ids. This is also what Epic-3 scoring
 * reads (the snapshot, never the live form).
 *
 * @property-read string $id
 * @property-read string $cohort_id
 * @property-read array<string, mixed> $submission_snapshot
 * @property-read Carbon $created_at
 */
final class SubmissionDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference_number' => $this->id,
            'cohort_id' => $this->cohort_id,
            'submitted_at' => $this->created_at->toIso8601String(),
            'snapshot' => $this->submission_snapshot,
        ];
    }
}
