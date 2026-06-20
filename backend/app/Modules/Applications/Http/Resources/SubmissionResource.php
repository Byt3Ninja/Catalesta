<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Lightweight submission row for the operator list (Story 2.8, FR-034). The id is
 * the keepable reference number shown to the applicant (Story 2.7). The full
 * answer snapshot is intentionally omitted here — it is served by the detail
 * endpoint ({@see SubmissionDetailResource}) so the list stays cheap.
 *
 * @property-read string $id
 * @property-read string $cohort_id
 * @property-read Carbon $created_at
 */
final class SubmissionResource extends JsonResource
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
        ];
    }
}
