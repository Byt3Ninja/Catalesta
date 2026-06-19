<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Identity\Domain\Models\ExternalUserToken;
use App\Modules\Identity\Domain\Models\ProfileSnapshot;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationPermission;
use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Audit\AuditLog;
use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

final class TenantIsolationArchTest extends TestCase
{
    use RefreshDatabase;

    /** Models that legitimately are NOT tenant-scoped (global / identity / audit). */
    private const GLOBAL_ALLOWLIST = [
        Organization::class,
        OrganizationPermission::class,
        ExternalUser::class,
        ExternalUserToken::class,
        ProfileSnapshot::class,
        AuditLog::class,
    ];

    public function test_every_model_with_organization_id_uses_belongs_to_tenant(): void
    {
        $classes = $this->modelClasses();
        $this->assertNotEmpty($classes, 'No model classes discovered — the architecture test would pass vacuously; check the Finder path.');

        foreach ($classes as $class) {
            if (in_array($class, self::GLOBAL_ALLOWLIST, true)) {
                continue;
            }
            $model = new $class;
            $table = $model->getTable();
            if (! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }
            $this->assertContains(
                BelongsToTenant::class,
                class_uses_recursive($class),
                "$class has an organization_id column but does not use BelongsToTenant (tenant isolation gap).",
            );
        }

        $this->assertContains(Program::class, $classes, 'Program model not discovered by the architecture test.');
    }

    public function test_global_allowlist_models_do_not_use_belongs_to_tenant(): void
    {
        foreach (self::GLOBAL_ALLOWLIST as $class) {
            $this->assertNotContains(
                BelongsToTenant::class,
                class_uses_recursive($class),
                "$class is allowlisted as global but uses BelongsToTenant.",
            );
        }
    }

    public function test_no_without_global_scope_tenant_in_app_outside_tenancy(): void
    {
        $finder = (new Finder)->files()->in(app_path())->name('*.php')
            ->notPath('Shared/Tenancy');
        $offenders = [];
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $contents = (string) file_get_contents($file->getRealPath());
            if (Str::contains($contents, ["withoutGlobalScope('tenant')", 'withoutGlobalScope("tenant")'])) {
                $offenders[] = $file->getRelativePathname();
            }
        }
        $this->assertSame([], $offenders, 'Use TenantContext::runAsSystem instead of withoutGlobalScope(\'tenant\') in app code.');
    }

    /** @return list<class-string<Model>> */
    private function modelClasses(): array
    {
        $finder = (new Finder)->files()->in(app_path())->name('*.php');
        $classes = [];
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $class = $this->classFromFile($file->getRealPath());
            if ($class !== null && is_subclass_of($class, Model::class)
                && ! (new \ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private function classFromFile(string $path): ?string
    {
        $src = (string) file_get_contents($path);
        if (! preg_match('/namespace\s+([^;]+);/', $src, $ns) || ! preg_match('/(?:final\s+)?class\s+(\w+)/', $src, $cls)) {
            return null;
        }
        $fqcn = trim($ns[1]).'\\'.$cls[1];

        return class_exists($fqcn) ? $fqcn : null;
    }
}
