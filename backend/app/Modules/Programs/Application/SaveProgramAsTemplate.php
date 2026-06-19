<?php

declare(strict_types=1);

namespace App\Modules\Programs\Application;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramTemplate;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Serializes a Program and all its sub-resources into a ProgramTemplate blueprint.
 *
 * Blueprint shape:
 * {
 *   program: { name, description, settings },
 *   stages: [ { key, name, type, order_index, parallel_group, config, rules: [{type, expression}] } ],
 *   policies: [ { key, value } ],
 *   role_requirements: [ { role_key, min_count, max_count, is_required } ],
 *   transitions: [ { from_stage_key, to_stage_key, condition, order_index } ]
 * }
 *
 * Transitions use STAGE KEYS (not ids) so the blueprint is id-independent.
 * organization_id is NEVER placed in any create array — BelongsToTenant forces it.
 */
final class SaveProgramAsTemplate
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Program $program, string $name): ProgramTemplate
    {
        return DB::transaction(function () use ($program, $name): ProgramTemplate {
            // ------------------------------------------------------------------
            // 1. Build stage data + key-map for transition serialization
            // ------------------------------------------------------------------
            /** @var array<string, string> $keyMap stage_id => stage_key */
            $keyMap = [];

            $stages = [];

            foreach ($program->stages()->orderBy('order_index')->get() as $stage) {
                $keyMap[$stage->id] = $stage->key;

                // Resolve which version to capture config + rules from
                $sourceVersion = $stage->current_published_version_id
                    ? StageVersion::find($stage->current_published_version_id)
                    : $stage->versions()->latest()->first();

                $rules = [];
                if ($sourceVersion !== null) {
                    foreach ($sourceVersion->stageRules as $rule) {
                        $rules[] = [
                            'type' => $rule->type->value,
                            'expression' => $rule->expression ?? [],
                        ];
                    }
                }

                $stages[] = [
                    'key' => $stage->key,
                    'name' => $stage->name,
                    'type' => $stage->type->value,
                    'order_index' => $stage->order_index,
                    'parallel_group' => $stage->parallel_group,
                    'config' => $sourceVersion?->config,
                    'rules' => $rules,
                ];
            }

            // ------------------------------------------------------------------
            // 2. Build policy data
            // ------------------------------------------------------------------
            $policies = [];
            foreach ($program->policies as $policy) {
                $policies[] = [
                    'key' => $policy->key,
                    'value' => $policy->value,
                ];
            }

            // ------------------------------------------------------------------
            // 3. Build role requirement data
            // ------------------------------------------------------------------
            $roleRequirements = [];
            foreach ($program->roleRequirements as $req) {
                $roleRequirements[] = [
                    'role_key' => $req->role_key,
                    'min_count' => $req->min_count,
                    'max_count' => $req->max_count,
                    'is_required' => $req->is_required,
                ];
            }

            // ------------------------------------------------------------------
            // 4. Build transition data using stage KEYS
            // ------------------------------------------------------------------
            $transitions = [];
            $sourceTransitions = StageTransition::where('program_id', $program->id)->get();

            foreach ($sourceTransitions as $transition) {
                $fromKey = $keyMap[$transition->from_program_stage_id] ?? null;
                $toKey = $keyMap[$transition->to_program_stage_id] ?? null;

                if ($fromKey === null || $toKey === null) {
                    continue;
                }

                $transitions[] = [
                    'from_stage_key' => $fromKey,
                    'to_stage_key' => $toKey,
                    'condition' => $transition->condition,
                    'order_index' => $transition->order_index,
                ];
            }

            // ------------------------------------------------------------------
            // 5. Assemble blueprint
            // ------------------------------------------------------------------
            $blueprint = [
                'program' => [
                    'name' => $program->name,
                    'description' => $program->description,
                    'settings' => $program->settings,
                ],
                'stages' => $stages,
                'policies' => $policies,
                'role_requirements' => $roleRequirements,
                'transitions' => $transitions,
            ];

            // ------------------------------------------------------------------
            // 6. Persist template (organization_id stamped by BelongsToTenant)
            // ------------------------------------------------------------------
            $slug = $this->uniqueSlug($name);

            /** @var ProgramTemplate $template */
            $template = ProgramTemplate::create([
                'name' => $name,
                'slug' => $slug,
                'blueprint' => $blueprint,
            ]);

            // ------------------------------------------------------------------
            // 7. Audit
            // ------------------------------------------------------------------
            $this->audit->record(
                'program_template.created',
                'program_template',
                $template->id,
                [],
                ['name' => $template->name],
            );

            return $template;
        });
    }

    /**
     * Generate a slug from $name unique within the current tenant's templates.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (ProgramTemplate::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
