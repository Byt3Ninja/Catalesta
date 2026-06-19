<?php

declare(strict_types=1);

namespace App\Modules\Programs\Application;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramPolicyRecord;
use App\Modules\Programs\Domain\Models\ProgramRoleRequirement;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageRule;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Application service: deep-clone a Program into a new DRAFT program.
 *
 * Copies: program metadata, policies, role requirements, stages (each with a
 * fresh DRAFT stage_version + stage_rules), and stage_transitions (remapped
 * to the new stage ids).
 *
 * Does NOT copy: cohorts, participant state, current_published_version_id.
 *
 * organization_id is NEVER passed in create arrays — BelongsToTenant forces
 * it from TenantContext on every creating hook.
 */
final class CloneProgram
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Program $source, string $newName): Program
    {
        return DB::transaction(function () use ($source, $newName): Program {
            // ------------------------------------------------------------------
            // 1. Clone the program itself
            // ------------------------------------------------------------------
            $slug = $this->uniqueSlug($newName);

            /** @var Program $clone */
            $clone = Program::create([
                'name' => $newName,
                'slug' => $slug,
                'status' => ProgramStatus::Draft,
                'description' => $source->description,
                'settings' => $source->settings,
                // template_id deliberately omitted (stays null)
            ]);

            // ------------------------------------------------------------------
            // 2. Copy program_policies
            // ------------------------------------------------------------------
            foreach ($source->policies as $policy) {
                ProgramPolicyRecord::create([
                    'program_id' => $clone->id,
                    'key' => $policy->key,
                    'value' => $policy->value,
                ]);
            }

            // ------------------------------------------------------------------
            // 3. Copy program_role_requirements
            // ------------------------------------------------------------------
            foreach ($source->roleRequirements as $req) {
                ProgramRoleRequirement::create([
                    'program_id' => $clone->id,
                    'role_key' => $req->role_key,
                    'min_count' => $req->min_count,
                    'max_count' => $req->max_count,
                    'is_required' => $req->is_required,
                ]);
            }

            // ------------------------------------------------------------------
            // 4. Copy stages and build old→new stage-id remap
            // ------------------------------------------------------------------
            /** @var array<string, string> $stageMap old_id => new_id */
            $stageMap = [];

            foreach ($source->stages()->orderBy('order_index')->get() as $sourceStage) {
                /** @var ProgramStage $sourceStage */
                $cloneStage = ProgramStage::create([
                    'program_id' => $clone->id,
                    'key' => $sourceStage->key,
                    'name' => $sourceStage->name,
                    'type' => $sourceStage->type,
                    'order_index' => $sourceStage->order_index,
                    'parallel_group' => $sourceStage->parallel_group,
                    // current_published_version_id deliberately omitted (no published version on clone)
                ]);

                $stageMap[$sourceStage->id] = $cloneStage->id;

                // Resolve which source version to copy config+rules from:
                // prefer the published version if one exists, else latest draft
                $sourceVersion = $sourceStage->current_published_version_id
                    ? StageVersion::find($sourceStage->current_published_version_id)
                    : $sourceStage->versions()->latest()->first();

                // Create a fresh DRAFT version for the cloned stage
                /** @var StageVersion $cloneVersion */
                $cloneVersion = StageVersion::create([
                    'program_stage_id' => $cloneStage->id,
                    'version_number' => 1,
                    'status' => 'draft',
                    'config' => $sourceVersion?->config,
                ]);

                // ------------------------------------------------------------------
                // 5. Copy stage_rules from the source version
                // ------------------------------------------------------------------
                if ($sourceVersion !== null) {
                    foreach ($sourceVersion->stageRules as $rule) {
                        StageRule::create([
                            'stage_version_id' => $cloneVersion->id,
                            'type' => $rule->type,
                            'expression' => $rule->expression ?? [],
                        ]);
                    }
                }
            }

            // ------------------------------------------------------------------
            // 6. Copy stage_transitions (remapped through stageMap)
            // ------------------------------------------------------------------
            $sourceTransitions = StageTransition::where('program_id', $source->id)->get();

            foreach ($sourceTransitions as $transition) {
                $fromId = $stageMap[$transition->from_program_stage_id] ?? null;
                $toId = $stageMap[$transition->to_program_stage_id] ?? null;

                if ($fromId === null || $toId === null) {
                    // Skip transitions whose stages were not copied (shouldn't happen)
                    continue;
                }

                StageTransition::create([
                    'program_id' => $clone->id,
                    'from_program_stage_id' => $fromId,
                    'to_program_stage_id' => $toId,
                    'condition' => $transition->condition,
                    'order_index' => $transition->order_index,
                ]);
            }

            // ------------------------------------------------------------------
            // 7. Audit
            // ------------------------------------------------------------------
            $this->audit->record(
                'program.cloned',
                'program',
                $clone->id,
                ['source_program_id' => $source->id],
                ['name' => $clone->name, 'status' => $clone->status->value],
            );

            return $clone;
        });
    }

    /**
     * Generate a slug from $name that is unique within the current tenant.
     * BelongsToTenant global scope is already active (resolved-tenant request),
     * so a plain Program::where() already scopes to the correct organization.
     * If the base slug is taken, appends -2, -3, … until unique.
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
