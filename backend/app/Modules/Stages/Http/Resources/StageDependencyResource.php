<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http\Resources;

use App\Modules\Stages\Domain\Models\StageDependency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StageDependency
 */
final class StageDependencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_stage_id' => $this->program_stage_id,
            'depends_on_program_stage_id' => $this->depends_on_program_stage_id,
            'created_at' => $this->created_at,
        ];
    }
}
