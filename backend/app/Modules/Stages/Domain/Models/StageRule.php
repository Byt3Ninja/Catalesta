<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Shared\Rules\Exceptions\InvalidExpressionException;
use App\Shared\Rules\ExpressionValidator;
use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StageRule extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['stage_version_id', 'type', 'expression'];

    /**
     * @return array<string, string|class-string>
     */
    protected $casts = [
        'expression' => 'array',
        'type' => StageRuleType::class,
    ];

    protected static function booted(): void
    {
        self::saving(function (self $model): void {
            /** @var array<mixed> $expression */
            $expression = $model->expression ?? [];

            if ($expression === []) {
                return;
            }

            /** @throws InvalidExpressionException */
            app(ExpressionValidator::class)->validate($expression);
        });
    }

    /**
     * @return BelongsTo<StageVersion, $this>
     */
    public function stageVersion(): BelongsTo
    {
        return $this->belongsTo(StageVersion::class);
    }
}
