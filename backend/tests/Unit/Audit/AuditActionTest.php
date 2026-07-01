<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use App\Shared\Audit\AuditAction;
use PHPUnit\Framework\TestCase;

final class AuditActionTest extends TestCase
{
    /**
     * Completeness guard (FR-052): the enumerated P1a audited set is EXACTLY these
     * actions. Adding/removing/renaming a case without updating FR-052 fails here.
     */
    public function test_registry_is_exactly_the_enumerated_p1a_set(): void
    {
        $expected = [
            'program.published',
            'cohort.opened',
            'cohort.form_bound',
            'cohort.closed',
            'application.submitted',
            'submission.scored',
            'decision.recorded',
            'decision.reopened',
            'decisions.exported',
            'stage_pipeline.published',
            'cohort.stage_pipeline_bound',
            'scoring_model.published',
        ];

        $actual = array_map(fn (AuditAction $a) => $a->value, AuditAction::cases());

        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, 'AuditAction must match the FR-052 enumerated set exactly');
    }
}
