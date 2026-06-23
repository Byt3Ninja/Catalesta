---
project_name: 'Catalesta'
user_name: 'Byteninja'
date: '2026-06-23'
sections_completed: ['technology_stack', 'language_rules_php_laravel']
existing_patterns_found: 0
status: 'category-2-merged'
open_decisions:
  - 'config/decimal-paths.php artifact + custom PHPStan rule NoFloatInDecimalPaths.php — to be created in a follow-up story (target: Epic 0 hygiene or Reliability epic)'
  - 'ADR-0005 (Single-Database Topology with Row-Level Tenancy) — decision recorded in §Database Topology 2026-06-23; promote to ADR alongside ADR-0004 in Epic 0'
---

# Project Context for AI Agents

_This file contains critical rules and patterns that AI agents must follow when implementing code in this project. Focus on unobvious details that agents might otherwise miss._

---

## Technology Stack & Versions

### Backend (`backend/`)

- PHP `^8.3`
- Laravel framework `^13.8`
- Laravel Sanctum `^4` (API auth)
- Laravel Tinker `^3.0`
- brick/math `^0.17.2` (decimal arithmetic — used for authoritative scoring per `CLAUDE.md` "Versioning and Historical Integrity")
- firebase/php-jwt `^7.0` (JWT for the SG-mock OIDC + linked-identity flows)
- league/flysystem-aws-s3-v3 `^3.34` (S3-backed document storage)
- predis/predis `^3.5` (Redis client)
- Composer autoload: `App\\` → `app/`, `Tests\\` → `tests/`
- PSR-4 modular structure under `app/Modules/{ModuleName}`

### Backend dev tooling

- dedoc/scramble `^0.13.28` (OpenAPI generator)
- larastan/larastan `^3.10` — PHPStan **level 6**, paths: `app/`, tmp: `storage/phpstan`
- laravel/pint `^1.27` (formatter)
- phpunit/phpunit `^12.5.12` with testsuites: `Unit`, `Feature`, `Contract` (configured in `phpunit.xml`)
- mockery/mockery `^1.6`
- roave/security-advisories `dev-latest`

### Frontend (`frontend/`)

- React `^19.2.6`
- react-hook-form `^7.79.0` + `@hookform/resolvers ^5.4.0` + zod `^4.4.3` (form + validation stack)
- @tanstack/react-query `^5.101.0` (server state)
- TypeScript `~6.0.2`
- Vite `^8.0.12`
- vitest `^4.1.9` + `@vitest/coverage-v8` + `@vitest/browser-playwright`
- Playwright `^1.61.0` (e2e)
- ESLint `^10.3.0` + `typescript-eslint ^8.59.2`
- Storybook `^10.4.6`

### API contract tooling

- Spectral (`.spectral.yaml`) extends `spectral:oas`; `array-items` downgraded to `warn` for the Stages publish 422 response (Scramble inference quirk, tracked separately).

### Repo layout (confirmed canonical — auto-memory architecture-decisions §3)

- `backend/` — Laravel modular monolith
- `frontend/` — React SPA
- `services/` — sibling services (e.g. `startup-gate-mock`)
- `adr/` — architecture decision records
- `docs/` — long-form product / architecture / saas / quality / ux / status / plan / superpowers / ux docs
- `_bmad/` — BMM v6 installation
- `_bmad-output/` — BMAD planning + implementation artifacts
- `graphify-out/` — repo knowledge-graph snapshot

### Module inventory (as of 2026-06-23)

- **24 modules canonical** (auto-memory architecture-decisions §2; `CLAUDE.md` "Required Modules"). Of these, **20 scaffolded** under `backend/app/Modules/`: Applications, Assessments, Audit, Cohorts, Documents, Forms, Graduation, Identity, Integrations, Mentorship, Organizations, Profiles, Programs, Reporting, RoleAssignments, Stages, Startups, Tasks, Training, Workflows.
- **4 absent** (folder not yet scaffolded): FinalEvaluation, Notifications, Search, Administration.
- Authoritative as-built status: `docs/status/implementation-status.md` (last updated 2026-06-19 — refresh tracked as audit finding F-003).

### Routes

`backend/routes/`: `api.php` (versioned API), `console.php`, `startup-gate-mock.php` (OIDC mock provider), `web.php`.

### Migrations

`backend/database/migrations/` — 41 files as of 2026-06-23 (status doc records 26 as of 2026-06-19; 15-file delta tracks Epic 4 / SP-1 work).

## Critical Implementation Rules

### Database Topology (architecture invariant)

- **Single product database.** Catalesta runs on **one logical product database** (PostgreSQL or MySQL). Multi-tenancy is enforced via row-level `organization_id`, **never** via database-per-tenant or schema-per-tenant.
- **Read replicas allowed.** Same schema, same data, configured via Laravel's `read` / `write` connection split in `config/database.php`. Strongly-consistent reads (post-write reads, authorization checks, idempotency lookups, OIDC callback verification) target the writer; everything else may use replicas.
- **Per-tenant database is forbidden.** No tenant gets its own DB or schema. Tenancy lives entirely in application code + row scoping (`BelongsToTenant`, `organization_id`). Any proposal to shard, partition by tenant, or move a single tenant to its own DB requires a superseding ADR.
- **Out-of-band analytics / data lake allowed, never as a product-code read path.** The Reporting module may export to a warehouse or lake; product controllers, services, jobs, and Policies never read from that warehouse. Product reads always hit the product DB (writer or replica).
- **Non-product stores stay non-product.** Redis (cache, queue, session) is not "the product database" — this rule does not constrain it. S3 via Flysystem is not "the product database". If audit ever moves to a dedicated store, that move requires its own ADR.
- **Promoted-to-ADR pending.** Decision recorded 2026-06-23 in this section. Open: **ADR-0005 — Single-Database Topology with Row-Level Tenancy** (target: Epic 0 hygiene, alongside ADR-0004 identity-ownership inversion).

### Language-Specific Rules — PHP / Laravel

#### Language features

- PHP `^8.3` required. Native enums for closed sets, first-class callable syntax (over `Closure::fromCallable`), `: never` return type on throw-only methods. Readonly promoted properties: **yes** for DTOs / value objects; **no** for Eloquent models (Eloquent conventions win there).
- Every new file starts with `declare(strict_types=1);`. Enforced via `pint.json` (`declare_strict_types` rule); CI runs `vendor/bin/pint --test` on changed files via the pre-commit hook in `.githooks/pre-commit`.
- Typed params + return types on every public method. `mixed` only at boundaries, narrowed immediately. `void` allowed only for command-shaped handlers.
- Static analysis: larastan / PHPStan **level 6** (`backend/phpstan.neon`). New code passes clean. `phpstan-baseline.neon` is ratchet-only — PR adding a baseline entry requires the `phpstan-baseline-add` label + reviewer approval; CI diff-checks baseline size.

#### Identity & email (CLAUDE.md identity invariants)

- ULID primary keys (`char(26)`) for `Account` and every tenant-owned aggregate. Never autoincrement ints. Never expose autoincrement ints in URLs or responses.
- Email is a local login credential + verified contact attribute only — never a cross-system / cross-tenant / ownership / linkage key. **Enforcement:** CI greps for `->where('email',` and `::firstWhere('email',` outside `App\Modules\Identity\`; allowlist via `// @email-lookup-ok <story-id>` comment.
- External identities keyed by `(issuer, sub)` on `linked_identities`. The SG `sub` never lives on the account row, never becomes a local PK.
- OIDC / JWT verification goes through the shared Identity verifier — never decode tokens inline. Required checks: signature against issuer JWKS (kid-pinned); `alg` allow-list (no `none`, no HS↔RS confusion); exact `iss` and `aud`; bounded clock skew on `exp` / `nbf` / `iat`; `nonce` replay protection on the auth-code flow. `sub` linkage only after verification.
- `firebase/php-jwt` usage restricted to `App\Modules\Integrations\StartupGate\` for OIDC `id_token` verification. Sanctum is the only token issuer for Catalesta-issued tokens.

#### Decimal & money (CLAUDE.md "Versioning and Historical Integrity")

- Decimal arithmetic via `brick/math` only for scoring, weights, money, rates. Floats forbidden in those paths. Field-level precision, scale, and rounding defined explicitly.
- Decimal-path namespaces allowlisted in `config/decimal-paths.php`. Custom PHPStan rule `phpstan/rules/NoFloatInDecimalPaths.php` registered in `phpstan.neon`, scoped to those namespaces. CI fails on new violations. **OPEN:** the config file and the custom rule do not yet exist — to be created in a follow-up story (target: Epic 0 hygiene or Reliability epic).
- Custom PHPStan rule `phpstan/rules/NoNumberFormatOnBigDecimal.php` fails on `number_format()` of `BigDecimal | BigNumber`. Display: round explicitly to the field's defined scale first; never `number_format` a raw `brick/math` value.

#### Tenant isolation (CLAUDE.md tenant invariants — fail-closed)

- Tenant-owned aggregates use the `BelongsToTenant` trait. The trait throws on save/create without a resolved tenant context (fail-closed). The trait's global scope is grep-auditable.
- Bypassing the scope requires an explicit `::withoutTenantScope()` call **and** a `// SECURITY: <reason> — <story-id or ADR>` comment. `withoutGlobalScope`, `withoutGlobalScopes`, `DB::table(...)`, `whereHas` joins, and aggregate `count` / `sum` queries on tenant-owned tables MUST still filter `organization_id` explicitly — defense in depth.
- Cross-tenant reads use a separate `::acrossTenants()` builder requiring a Policy authorization gate at the call site. No silent opt-outs.
- Model-level tenant opt-out requires `#[NotTenantScoped(reason: '...')]` attribute. CI fails on empty `reason`.
- Cross-tenant org access returns **404 not 403** (auto-memory architecture-decisions §5). Six existing test assertions assume this.

#### Mass-assignment (highest blast radius — silently defeats tenancy + authorization)

- Eloquent models declare `$fillable` explicitly. `$guarded = []` is forbidden.
- Never pass `$request->all()` or `$request->validated()` directly into `create` / `update` / `fill` for any model carrying `organization_id`, `account_id`, role, status, or monetary fields. Assign tenant / owner / role / status fields server-side from resolved context, never from the request payload.

#### Module boundaries (CLAUDE.md "Preserve module boundaries")

- Cross-module reads call the owning module's public interface under `App\Modules\<X>\Contracts\`, bound in that module's `ServiceProvider`. Concrete services and Eloquent models are module-private — no import from `App\Modules\<Y>\Services\...` or `App\Modules\<Y>\Models\...` outside `App\Modules\<Y>\`.
- Cross-module writes dispatch a domain event or call a documented command on the owning module's contract. Never write to another module's tables, even via its Eloquent model.
- **Enforcement:** `deptrac.yaml` at repo root, one layer per module. CI runs `vendor/bin/deptrac analyse --fail-on-uncovered`.
- Raw query restrictions: `DB::raw`, `whereRaw`, `orderByRaw`, `selectRaw`, `havingRaw` require parameter bindings — never string-interpolate request input. `orderBy` column names from request input are validated against a per-endpoint allow-list.

#### Authorization (deny by default)

- Every Policy method is explicit. `before()` returning truthy for admins is forbidden — admin paths get their own Policy method so the audit trail records what was authorized.
- `Gate::define` defaults to `false` on unknown abilities.
- Sanctum token routes assert required abilities via the `abilities:` middleware. Cookie/session auth never silently grants token-equivalent access.
- Check order: resource ownership → tenant membership → ability. All three must pass.
- Frontend visibility is never authorization (CLAUDE.md).

#### Request, controller, FormRequest

- FormRequest classes own request validation.
- Controller action bodies ≤ 15 lines: no Eloquent queries, no validation calls. Enforced via PHPStan `MaxMethodLengthRule` scoped to `App\Http\Controllers\`.
- Controller pattern: resolve FormRequest → hand off to a domain service or action → return a Resource.

#### File uploads (Documents module)

- Validate MIME via the `mimes:` rule, cap size. Store via `store()` / `storePubliclyAs()` with a server-generated ULID filename. Never use `getClientOriginalName`, `getClientOriginalExtension`, or request-supplied paths in storage keys.
- Disks are private by default. Public disks require an approved ADR. Generated URLs are signed and short-lived.
- Flysystem S3 access via `Storage::disk('s3-tenant-{id}')`. Direct `AwsS3V3Adapter` instantiation forbidden outside `app/Storage/`.

#### Background jobs & observers

- Jobs implement `App\Tenancy\Contracts\TenantAware`. The job middleware at `app/Tenancy/Middleware/SetTenantContext.php` resolves `tenant_id` from the payload, sets context, asserts membership, fails closed. Jobs without the interface throw at dispatch.
- Job constructors accept ULIDs only — never full models carrying secrets, raw tokens, OIDC `id_token`, or unredacted PII. Re-fetch inside `handle()` after tenant context is restored.
- `failed_jobs` payloads are PII at rest. Never log payload contents.
- Model observers, event listeners, and broadcast channels re-resolve tenant context from the model — request context is absent in queued listeners and console runs.

#### Logging, audit, secret hygiene

- Never log request bodies, headers, cookies, tokens, OIDC claims, `id_token` / `access_token`, password fields, signed URLs, or full model attributes.
- Logged user references use the Account ULID, never email.
- Exception reporters scrub `Authorization`, `Cookie`, `X-*-Token`, `password`, `password_confirmation`, `current_password`, `client_secret` before serialization.

#### Timing-safe equality + rate limiting

- Token / HMAC / signature comparison uses `hash_equals`. Never `==` / `===` / `strcmp` for tokens, HMACs, signatures, or reset codes.
- Auth, password reset, invitation acceptance, OIDC callback, and webhook ingress endpoints declare a named `RateLimiter` with per-identifier **and** per-IP buckets. No unthrottled credential or signature surface.

#### Deserialization & template safety (CLAUDE.md "No arbitrary code execution")

- `unserialize` on untrusted input is forbidden — cache / session payloads only. Never request bodies, file contents, or queue inputs from external sources.
- XML parsing disables external entities.
- YAML / Markdown / template parsing of tenant-authored content (forms, workflows, branding, templates) runs through the shared Rules/Expression sandbox kernel (auto-memory architecture-decisions §4).

#### Time

- Inject `Psr\Clock\ClockInterface` (bound to `Illuminate\Support\Carbon` in production, frozen in tests) for clock reads in domain code. `Carbon::now()` / `today()` without an explicit timezone is forbidden in scoring, deadlines, evaluation windows, and audit timestamps — use the injected `Clock` or `CarbonImmutable::now('UTC')`.
- Persist UTC. Comparisons normalize to UTC before `->diff` / `->gt`. Render in the request locale.

#### Service resolution

- Constructor injection. `app()`, `resolve()`, `App::make()` are forbidden outside `app/Providers/`, `app/Http/Kernel.php`, and `tests/`. Enforced via PHPStan banned-functions rule.
- No service container resolution inside Eloquent models. Models stay persistence + invariants; behavior that needs services lives in a domain service or action.

#### Redis & cache

- `predis/predis` usage only through `Cache::` / `Redis::` facades or `App\Cache\*` services. No raw `Predis\Client` injection outside `app/Cache/`.

#### Migrations

- Forward-only in all environments above local. `down()` is optional; if written, it must be exercised by a migration test or it gets deleted (no untested rollback methods).
- Destructive changes (drop column, drop table, narrow type, `NOT NULL` on existing) require a two-deploy expand/contract: ship the additive migration + backfill first, then ship the contractive migration in a later release.
- Long-running schema changes that lock a table get explicit ADR sign-off.

#### Performance & query discipline

- Endpoints with expected p95 > 2s move to queued jobs from day one. No "we'll optimize later" sync endpoints.
- Every index / list endpoint has a feature test asserting query count (Beyondcode `LaravelQueryDetector` or `assertQueryCountLessThan`). Test fails on detected N+1.

#### Escape-hatch convention

- Any `phpstan-ignore`, `withoutGlobalScope`, `DB::unprepared`, `// @email-lookup-ok`, or `$guarded = []` (forbidden — but if it ever lands) requires a `// SECURITY: <reason> — <story-id or ADR>` comment. Grep-auditable.

#### Stub regeneration

- When a Laravel default (generated FormRequest, Model, Migration stub) conflicts with this file, this file wins. Regenerated stubs must be brought into compliance before merge.

### Framework-Specific Rules (Frontend) — TypeScript / React

_To be populated in step-02, Category 3._

### Testing Rules

_To be populated in step-02, Category 4._

### Code Quality & Style Rules

_To be populated in step-02, Category 5._

### Development Workflow Rules

_To be populated in step-02, Category 6._

### Critical Don't-Miss Rules

_To be populated in step-02, Category 7._

