<?php

declare(strict_types=1);

namespace App\Modules\Programs\Application;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramPolicyRecord;
use App\Modules\Programs\Domain\Models\ProgramRoleRequirement;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Programs\Domain\Models\ProgramTemplate;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageRule;
use App\Modules\Stages\Domain\Models\StageRuleType;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Materializes a new DRAFT Program from a ProgramTemplate blueprint.
 *
 * Creates: program + stages (each with a fresh DRAFT StageVersion + rules) +
 * policies + role_requirements + transitions (remapped via stage key → new id).
 *
 * Does NOT create cohorts.
 * organization_id is NEVER placed in any create array — BelongsToTenant forces it.
 */
final class CreateProgramFromTemplate
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(ProgramTemplate $template, string $name): Program
    {
        return DB::transaction(function () use ($template, $name): Program {
            /** @var array<string, mixed> $blueprint */
            $blueprint = $template->blueprint;

            // ------------------------------------------------------------------
            // 1. Create the program
            // ------------------------------------------------------------------
            $slug = $this->uniqueSlug($name);

            /** @var array<string, mixed> $programData */
            $programData = $blueprint['program'] ?? [];

            /** @var Program $program */
            $program = Program::create([
                'name' => $name,
                'slug' => $slug,
                'status' => ProgramStatus::Draft,
                'description' => $programData['description'] ?? null,
                'settings' => $programData['settings'] ?? null,
            ]);

            // ------------------------------------------------------------------
            // 2. Create stages + versions + rules; build key → new stage id map
            // ------------------------------------------------------------------
            /** @var array<string, string> $keyToIdMap stage_key => new_stage_id */
            $keyToIdMap = [];

            /** @var array<int, array<string, mixed>> $stagesData */
            $stagesData = $blueprint['stages'] ?? [];

            foreach ($stagesData as $stageData) {
                $stageKey = (string) $stageData['key'];

                /** @var ProgramStage $newStage */
                $newStage = ProgramStage::create([
                    'program_id' => $program->id,
                    'key' => $stageKey,
                    'name' => $stageData['name'],
                    'type' => $stageData['type'],
                    'order_index' => $stageData['order_index'],
                    'parallel_group' => $stageData['parallel_group'] ?? null,
                    // current_published_version_id deliberately omitted
                ]);

                $keyToIdMap[$stageKey] = $newStage->id;

                // Create a fresh DRAFT version
                /** @var StageVersion $newVersion */
                $newVersion = StageVersion::create([
                    'program_stage_id' => $newStage->id,
                    'version_number' => 1,
                    'status' => 'draft',
                    'config' => $stageData['config'] ?? null,
                ]);

                // Copy stage rules from blueprint
                /** @var array<int, array<string, mixed>> $rulesData */
                $rulesData = $stageData['rules'] ?? [];

                foreach ($rulesData as $ruleData) {
                    StageRule::create([
                        'stage_version_id' => $newVersion->id,
                        'type' => StageRuleType::from((string) $ruleData['type']),
                        'expression' => $ruleData['expression'] ?? [],
                    ]);
                }
            }

            // ------------------------------------------------------------------
            // 3. Create policies
            // ------------------------------------------------------------------
            /** @var array<int, array<string, mixed>> $policiesData */
            $policiesData = $blueprint['policies'] ?? [];

            foreach ($policiesData as $policyData) {
                ProgramPolicyRecord::create([
                    'program_id' => $program->id,
                    'key' => $policyData['key'],
                    'value' => $policyData['value'],
                ]);
            }

            // ------------------------------------------------------------------
            // 4. Create role requirements
            // ------------------------------------------------------------------
            /** @var array<int, array<string, mixed>> $roleRequirementsData */
            $roleRequirementsData = $blueprint['role_requirements'] ?? [];

            foreach ($roleRequirementsData as $reqData) {
                ProgramRoleRequirement::create([
                    'program_id' => $program->id,
                    'role_key' => $reqData['role_key'],
                    'min_count' => $reqData['min_count'],
                    'max_count' => $reqData['max_count'],
                    'is_required' => $reqData['is_required'],
                ]);
            }

            // ------------------------------------------------------------------
            // 5. Create transitions (remapped via key → new id)
            // ------------------------------------------------------------------
            /** @var array<int, array<string, mixed>> $transitionsData */
            $transitionsData = $blueprint['transitions'] ?? [];

            foreach ($transitionsData as $transitionData) {
                $fromKey = (string) $transitionData['from_stage_key'];
                $toKey = (string) $transitionData['to_stage_key'];

                $fromId = $keyToIdMap[$fromKey] ?? null;
                $toId = $keyToIdMap[$toKey] ?? null;

                if ($fromId === null || $toId === null) {
                    continue;
                }

                StageTransition::create([
                    'program_id' => $program->id,
                    'from_program_stage_id' => $fromId,
                    'to_program_stage_id' => $toId,
                    'condition' => $transitionData['condition'] ?? null,
                    'order_index' => $transitionData['order_index'] ?? 0,
                ]);
            }

            // ------------------------------------------------------------------
            // 6. Audit
            // ------------------------------------------------------------------
            $this->audit->record(
                'program.created_from_template',
                'program',
                $program->id,
                [],
                ['name' => $program->name, 'template_id' => $template->id],
            );

            return $program;
        });
    }

    /**
     * Generate a slug from $name unique within the current tenant's programs.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (Program::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
