# Tenant Isolation Model

## Overview

Catalesta enforces **fail-closed tenant isolation** at the data-access layer. Every tenant-owned model must use the `BelongsToTenant` trait; queries without a resolved tenant return no rows, and writes throw an exception.

## Key Rules

### 1. Tenant-Owned Models Use `BelongsToTenant`

Every model with an `organization_id` column must declare:

```php
use App\Shared\Tenancy\BelongsToTenant;

class MyModel extends Model
{
    use BelongsToTenant;
    // ...
}
```

The trait registers a global scope that filters all queries by the current tenant (resolved from `TenantContext`).

### 2. Fail-Closed Read Behavior

- **With resolved tenant** (normal HTTP request): queries return only rows matching `organization_id = $context->tenantId()`.
- **Without resolved tenant** (queue job, console command, API token with no org context): queries return **no rows**. No exception; the resultset is simply empty.

This prevents silent cross-tenant leaks in background jobs and adhoc commands.

### 3. Fail-Closed Write Behavior

- **With resolved tenant**: writes succeed; `organization_id` is forced from `TenantContext` (see rule 4).
- **Without resolved tenant**: writes throw `TenantContextMissingException`. Explicit: a job or command must deliberately opt-in to cross-tenant system access.

### 4. `organization_id` is Server-Set, Never Mass-Assignable

The `organization_id` column **must never appear in `$fillable`**. Instead:

- **Request creates** (controllers, form requests): `organization_id` is assigned directly from `TenantContext::tenantId()` after validation, before persistence.
- **System/bootstrap paths** (seeders, queue jobs with cross-tenant scope): use `TenantContext::runAsSystem(fn)` and assign `organization_id` directly.

Example:

```php
// In CreateOrganizationService (system context):
TenantContext::runAsSystem(function () {
    $org = Organization::create([
        'name' => 'Acme Inc',
        // organization_id NOT in input — assigned directly:
    ]);
    $org->organization_id = $org->id; // Set before save or in boot hook
    $org->save();
});
```

### 5. Cross-Tenant System Access

For queue jobs, console commands, or platform-admin endpoints that must legitimately access multiple tenants:

```php
TenantContext::runAsSystem(function () {
    // Queries ignore the global tenant scope
    // Writes do NOT check for a resolved tenant
    $allOrganizations = Organization::all(); // All orgs, not filtered
    
    foreach ($allOrganizations as $org) {
        $org->processMonthlyReport();
    }
});
```

**When to use:**
- Scheduled jobs that operate on all tenants (e.g., billing, maintenance).
- Console commands for ops/support.
- Platform-admin endpoints (if they exist).

**Never use** in regular request handlers. If a controller needs cross-tenant data, the endpoint itself should be plainly named (e.g., `/api/admin/organizations`) and backed by a policy that explicitly allows platform access.

## Architecture Test

`tests/Architecture/TenantIsolationArchTest.php` enforces that every model with an `organization_id` column uses the `BelongsToTenant` trait. It checks:

- All models in `backend/app/Modules/*/Domain/Models`.
- Each model's `$table` for an `organization_id` column via the database schema.
- If present, the trait must be used.

**Global allowlist** (documented inside the test): models that have `organization_id` but legitimately do not use the trait (e.g., system configuration, audit logs with optional tenant context). The allowlist is explicitly maintained and reviewed on every addition.

## Adding a New Tenant-Owned Model

1. **Create the model** with an `organization_id` column:
   ```php
   Schema::create('my_models', function (Blueprint $table) {
       $table->id();
       $table->foreignId('organization_id')->constrained('organizations');
       $table->string('name');
       $table->timestamps();
   });
   ```

2. **Add the trait** to the model:
   ```php
   use BelongsToTenant;
   ```

3. **Set `$fillable` explicitly** — never include `organization_id`:
   ```php
   protected $fillable = ['name'];
   ```

4. **Assign `organization_id` server-side** in the service/action that creates the model:
   ```php
   $model = MyModel::create($validated); // $validated does NOT contain organization_id
   $model->organization_id = TenantContext::tenantId();
   $model->save();
   ```
   Or use an observer/boot hook to automate this.

5. **The architecture test will verify** the trait is present on the next test run.

## Spanning Tenants Legitimately

### Pattern 1: System Context with Loop

```php
// Process all orgs' monthly tasks
TenantContext::runAsSystem(function () {
    Organization::chunk(100, function ($orgs) {
        foreach ($orgs as $org) {
            $tasks = Task::where('organization_id', $org->id)
                         ->where('due_at', '<=', today())
                         ->get();
            // ...
        }
    });
});
```

### Pattern 2: Explicit Cross-Tenant Policy

```php
// API endpoint for platform admins to view a specific org's data
Route::get('/admin/organizations/{id}/members', function (Request $request, $id) {
    $this->authorize('viewTenantData', [Organization::class, $id]);
    
    // Still use TenantContext::runAsSystem for the reads
    TenantContext::runAsSystem(function () use ($id) {
        return OrganizationMembership::where('organization_id', $id)->get();
    });
});
```

### Pattern 3: Queue Job Scoped to One Org (Preferred)

```php
// Job resolves tenant before dispatching
dispatch(new ProcessCohortGraduations($cohort->organization_id));

// In the job:
public function handle()
{
    TenantContext::resolveUsing($this->organizationId);
    // Queries are now scoped; no system context needed
    Cohort::whereStatus('active')->each(fn ($c) => $c->graduate());
}
```

## Enforcement & Monitoring

- **Architecture test** (CI/CD gate): runs on every commit; fails if a tenant-owned model lacks the trait.
- **Static analysis** (PHPStan): no current rules, but could flag direct `$model->organization_id =` assignments outside of bootstrap hooks.
- **Code review**: every new model and service is checked for the pattern.

## FAQ

**Q: Why not a base `TenantModel` class?**  
A: The trait enforces isolation. A base class would be convention-only and wouldn't prevent accidental omissions. The architecture test is the real guard.

**Q: Can I bypass the scope for a read?**  
A: Yes, explicitly: `Model::withoutGlobalScope('tenant')->where(...)->get()`. This is logged for audit (via the scope hook) and must never appear in request handlers. Only system context or documented allowlist exceptions use this.

**Q: What if a job needs multiple tenants?**  
A: Use `TenantContext::runAsSystem()` and loop. The pattern is explicit and auditable.

**Q: Is `TenantContext::isSystem()` a security check?**  
A: No — it's a *diagnostic* to know if you're in system context. Authorization (e.g., "is this user a platform admin?") is separate. Use policies + `$request->user()->can()`.

## References

- `App\Shared\Tenancy\BelongsToTenant` — the trait and global scope
- `App\Shared\Tenancy\TenantContext` — `runAsSystem()`, `resolveUsing()`, `tenantId()`, `isSystem()`
- `App\Shared\Tenancy\TenantContextMissingException` — thrown on scoped writes without context
- `tests/Architecture/TenantIsolationArchTest.php` — the architecture test
- Project rule #7 (non-negotiable): "Every tenant-owned record must include `organization_id`."
- Project rule #8 (non-negotiable): "Every tenant-scoped query must enforce tenant isolation."
