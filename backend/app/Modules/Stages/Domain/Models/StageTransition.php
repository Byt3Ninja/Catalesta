<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Rules\Exceptions\InvalidExpressionException;
use App\Shared\Rules\ExpressionValidator;
use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StageTransition extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_id', 'from_program_stage_id', 'to_program_stage_id', 'condition', 'order_index'];

    /**
     * @return array<string, string|class-string>
     */
    protected $casts = [
        'condition' => 'array',
        'order_index' => 'integer',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $model): void {
            /** @var array<mixed>|null $condition */
            $condition = $model->condition;

            if ($condition === null || $condition === []) {
                return;
            }

            /** @throws InvalidExpressionException */
            app(ExpressionValidator::class)->validate($condition);
        });
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<ProgramStage, $this>
     */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(ProgramStage::class, 'from_program_stage_id');
    }

    /**
     * @return BelongsTo<ProgramStage, $this>
     */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(ProgramStage::class, 'to_program_stage_id');
    }
}
