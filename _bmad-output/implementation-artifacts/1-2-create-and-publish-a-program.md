---
baseline_commit: d3c85a3
---

# Story 1.2: Create and publish a program

Status: review

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As an **operator**,
I want to create a program and publish it,
so that I have a published, immutable basis to run cohorts from.

## Acceptance Criteria

1. **Publish → immutable version (FR-010, FR-012).** Given an operator in their organization with a draft program, when they publish it, then a **published, immutable `ProgramVersion`** is recorded (snapshot of the program definition, `version_number`, `published_at`). The published version row **cannot be mutated or deleted**; editing the program and re-publishing creates a **new** version, never altering a prior published one. Prior version ids remain resolvable. *(NEW — programs do not use the versioning kernel today; see Dev Notes "The core gap".)*
2. **Entitlement gate at the call site (FR-060).** `program.publish` is gated through `EntitlementService::check('program.publish')` **inside `PublishProgram`** (allow-all in P1a, throws in P1b) — mirroring `OpenCohort::handle`'s `cohort.open` gate. The publish must be wrapped in a single `DB::transaction` so the status flip, the immutable version, and the audit row commit or roll back together.
3. **Clone / instantiate-from-template into a new draft (FR-013).** An existing program can be **cloned** or **instantiated from a template** into a new **draft** program (new id, new unique slug, `status=draft`). *(Services already exist — see Dev Notes; this AC is verifying they produce a clean new draft and are covered by tests, not rebuilding them.)*
4. **Publishing is audited (`program.published`, FR-052).** Publishing writes one immutable `audit_logs` row with `action='program.published'`, the actor's `sub`, the `organization_id`, and `target_id` = the program id, with `before`/`after` status. *(Emit already exists; assert it stays correct after the Task 1 changes and runs inside the transaction.)*
5. **Cross-tenant isolation (AR-6 / FR-004).** Publishing, reading, or cloning a program in an organization the operator is not a member of returns a **neutral 404** (never 200), proven by an explicit cross-tenant isolation test over the program **and** the new `program_versions` table. `organization_id` is server-set on `program_versions` (never client-supplied).

## Tasks / Subtasks

- [x] **Task 1 — Immutable program versioning + entitlement gate (backend)** (AC: #1, #2, #4, #5)
  - [x] 1.1 NEW `ProgramVersion` model (`app/Modules/Programs/Domain/Models/ProgramVersion.php`) — mirror `FormVersion` exactly: `implements Versionable`, `use BelongsToTenant, HasUlids, ImmutableWhenPublished`; `versionParentColumn() => 'program_id'`; `validateForPublish()` no-op (program is valid at create time); `program()` BelongsTo; casts `status => VersionStatus::class`, `definition => 'array'`, `version_number => 'integer'`, `published_at => 'datetime'`.
  - [x] 1.2 NEW migration `create_program_versions_table` — mirror `form_versions` / `stage_versions`: `ulid id`, `ulid organization_id` (index), `ulid program_id` (index), `unsignedInteger version_number default 0`, `string status default 'draft'`, `jsonb definition`, `timestampTz published_at nullable`, `timestampsTz`, `unique(['program_id','version_number'])`. (No `content_hash` needed for 1.2 — that's Story 1.3's form contract; programs version by number like stages.)
  - [x] 1.3 MODIFY `PublishProgram::handle` — inject `EntitlementService` + `VersionPublisher` alongside `AuditLogger`; call `$this->entitlement->check('program.publish')` first; wrap the rest in `DB::transaction`: flip `status => Published`, create a draft `ProgramVersion` (snapshot = name/description/settings; `organization_id` server-set from `$program->organization_id`), call `$this->versionPublisher->publish($version)` (assigns next `version_number`, sets Published + `published_at`), then `audit->record('program.published', ...)`. Keep emitting the action **string** (matches existing + `AuditAction::ProgramPublished`).
  - [x] 1.4 MODIFY `Program` model — add `versions(): HasMany<ProgramVersion>`. `ProgramController::publish` needs **no change** (EntitlementService is constructor-injected via the container binding).
  - [x] 1.5 Tests — NEW `tests/Feature/Programs/PublishProgramTest.php`: publish creates a **published** `program_versions` row (AC-1); a published `ProgramVersion` **rejects update + delete** (ImmutableWhenPublished, AC-1); re-publish after an edit creates a **second** version, leaving the first untouched (AC-1); the entitlement gate is invoked (bind a throwing `EntitlementService` → publish raises, and no version/audit row is written = transaction rolled back, AC-2); `program.published` audit row present with org + actor + target (AC-4); cross-tenant publish via API → `[403,404]`, and `program_versions` has an isolation test (AR-6, AC-5).
- [x] **Task 2 — Clone / template into a new draft (backend, verify-don't-rebuild)** (AC: #3)
  - [x] 2.1 Read `CloneProgram` + `CreateProgramFromTemplate`; confirm both return a `status=draft` program with a new id + unique slug and emit their audit events. Add/extend feature tests if missing: clone a **published** program → new **draft** (the published version is unaffected); instantiate from a `ProgramTemplate` → new draft. No service rewrite unless a test exposes a real defect.
  - [x] 2.2 Confirm the clone/template routes + policy gates exist (`routes/api.php`, `ProgramPolicy`); add a cross-tenant clone isolation assertion (Org A cannot clone Org B's program → 404).
- [x] **Task 3 — Operator program create + publish surface (frontend)** (AC: #1, #2, #3)
  - [x] 3.1 NEW `frontend/src/schemas/programs.ts` (zod `programSchema`: id, name, slug, `status` enum draft|published|archived|closed, description nullable, settings nullable, created_at, updated_at; `programResponseSchema`, `programListResponseSchema`; typed errors extending `ApiError`) and `frontend/src/api/programs.ts` (`listPrograms`, `createProgram(name, description?)`, `publishProgram(id)` — `credentials:'include'`, zod-parse, mirror `api/organizations.ts`).
  - [x] 3.2 NEW `frontend/src/pages/ProgramsPage.tsx` rendered in `AppShell` via the `ConsoleGate` render-prop on a new `/programs` route in `App.tsx`: lists programs (`StateBlock` empty state when none → create), a create form (`Field` name + optional description in `FormLayout`, `Button` loading — mirror `OnboardingPage`), and per **draft** program a **Publish** action. Published programs show a status badge and no publish button.
  - [x] 3.3 Publish UX = a **`Banner` warning** + confirm-on-the-page (publish is versioned, not destructive → **no modal**; keeps Story 1.0's modal deferral intact). Strong copy: publishing records an immutable version; editing later creates a new version. On success, invalidate `['programs']` so the list reflects the new status. **Do NOT** build the FR-062 limit banner (allow-all in P1a → no live trigger; deferred to P1b).
  - [x] 3.4 Tests — `frontend/src/api/programs.test.ts` (list/create/publish + error mapping, fetch-mocked); `ProgramsPage` page test (empty→create→list; publish flips status; error preserves input); add `ProgramsPage` to the axe a11y gate (`tests/a11y.test.tsx`) and a `ProgramsPage.stories.tsx` with an Arabic/RTL story. Verify both `dir=ltr` and `dir=rtl` render.

## Dev Notes

### ⛔ Anti-reinvention: most of the backend already exists — close 3 gaps, don't rebuild
The Programs module is brownfield. **Already built and tested** — do not rewrite:
- **`PublishProgram::handle(Program): Program`** [Source: backend/app/Modules/Programs/Application/PublishProgram.php] — flips `status => Published` and emits `program.published`. **Gaps to fix (Task 1.3):** no `EntitlementService` gate, no `DB::transaction`, **no immutable version**.
- **`ProgramController::publish(PublishProgram, string $id)`** [Source: backend/app/Modules/Programs/Http/ProgramController.php#L135-L144] — `findOrFail` + `authorize('publish', $program)` + `service->handle`. Wired at `POST /api/v1/programs/{id}/publish` [Source: backend/routes/api.php#L60]. **No controller change needed.**
- **`CloneProgram`** and **`CreateProgramFromTemplate`** [Source: backend/app/Modules/Programs/Application/{CloneProgram,CreateProgramFromTemplate}.php] — both deep-copy into a new **draft** with a fresh unique slug, transactional, and audit (`program.cloned` / `program.created_from_template`). AC-3 is **verify + test**, not build.
- **`ProgramPolicy::publish`** checks `programs.publish` against `TenantContext` [Source: backend/app/Modules/Programs/Policies/ProgramPolicy.php]; the `owner` role gets `programs.publish` in the permission catalog (see Story 1.1's `CreateOrganization` owner sync).
- **`AuditAction::ProgramPublished = 'program.published'`** already enumerated [Source: backend/app/Shared/Audit/AuditAction.php] — this is also the Story 2.5 enumerated-audit set, so keep the exact string.

### The core gap (AC-1): programs must use the versioning kernel, like forms and stages do
Today the `Program` row stays **mutable after publish** — there is no immutable artifact, so FR-010/012 ("published, immutable; editing creates a new version") is unmet. The fix is to adopt the **existing versioning kernel** exactly as `FormVersion` and `StageVersion` already do — *not* a new mechanism. [Source: backend/app/Shared/Versioning/*]
- **`Versionable`** interface — `versionParentColumn(): string` + `validateForPublish(): void` [Source: app/Shared/Versioning/Versionable.php].
- **`VersionPublisher::publish(Model&Versionable $version): void`** — asserts Draft, calls `validateForPublish()`, then in a transaction computes the next `version_number` (MAX within parent scope) and sets `status=Published`, `published_at=now()` [Source: app/Shared/Versioning/VersionPublisher.php]. Throws `VersionStateException` if not Draft.
- **`ImmutableWhenPublished`** trait — boot hooks block **update** (except `status → archived`) and **delete** on published rows [Source: app/Shared/Versioning/ImmutableWhenPublished.php]. This is what makes AC-1's "cannot mutate the published version" true at the model layer.
- **`VersionStatus`** enum: Draft | Published | Archived [Source: app/Shared/Versioning/VersionStatus.php].
- **Canonical reference to copy:** `FormVersion` [Source: backend/app/Modules/Forms/Domain/Models/FormVersion.php#L21-L65] and its migration [Source: backend/database/migrations/2026_06_20_000710_create_form_versions_table.php]. `ProgramVersion` is the same shape; **drop `content_hash`** (that's the form's Epic-2 snapshot contract — programs version by number like `stage_versions` [Source: 2026_06_18_002600_create_stage_versions_table.php]).
- **Decision — Program stays the editable draft container; versions are the frozen snapshots** (the `FormVersion`/`Form` split). The `Program` row remains editable after publish; each publish freezes a new `ProgramVersion`. This is precisely "editing creates a new version, never mutating the published one." Do **not** put `ImmutableWhenPublished` on `Program` itself — that would break edit-then-republish.

### Entitlement gate (AC-2) — mirror OpenCohort exactly
`program.publish` is one of only three enumerated `EntitlementService` call sites in P1a (with `cohort.open`, `application.submit`) [Source: epics.md#L62 FR-060]. The reference implementation is `OpenCohort::handle` [Source: backend/app/Modules/Cohorts/Application/OpenCohort.php#L21-L48]:
1. `$this->entitlement->check('program.publish');` **first**, at the call site.
2. `DB::transaction(fn () => …)` around the state change + version + audit.
3. `$this->audit->record('program.published', 'program', $program->id, $before, $after);`
- `EntitlementService::check(string $action): void` — interface [Source: app/Shared/Entitlement/EntitlementService.php]; `AllowAllEntitlementService` no-ops in P1a [Source: app/Shared/Entitlement/AllowAllEntitlementService.php]; bound in `AppServiceProvider` [Source: backend/app/Providers/AppServiceProvider.php]. Inject the **interface**, not the concrete class.

### Audit (AC-4) — already correct, keep it inside the transaction
`AuditLogger::record($action, $targetType, $targetId, $before=[], $after=[], $result='success', $organizationId=null)` [Source: app/Shared/Audit/AuditLogger.php] auto-captures actor (`request->user()->id`) and `organization_id` from `TenantContext`, plus correlation id. Keep emitting the **string** `'program.published'`. Test with `assertDatabaseHas('audit_logs', ['action'=>'program.published','target_id'=>$program->id,'organization_id'=>$org->id])`, matching `CohortLifecycleTest` [Source: backend/tests/Feature/Cohorts/CohortLifecycleTest.php#L52].

### Tenancy rules (read before touching any query) [Source: app/Shared/Tenancy/*]
- `BelongsToTenant` adds a fail-closed global `tenant` scope + a `creating` hook forcing `organization_id` from `TenantContext`. `ProgramVersion` **must** use it (AR-6) so its `organization_id` is server-set and it 404s cross-tenant.
- `TenantContext` API is **`setOrganization(...)`** + **`runAsSystem(fn)`** only — **no `runAsTenant`**.
- **Architecture test forbids `withoutGlobalScope('tenant')` outside `app/Shared/Tenancy/`** [Source: backend/tests/Architecture/TenantIsolationArchTest.php]. Don't add it in Programs.
- Program routes are tenant-scoped (require `X-Organization-Id` + active membership via `ResolveTenant`); a foreign program id resolves to a neutral **404** (the Story 1.1 / AR-6 pattern).

### Frontend — reuse the Story 1.0/1.1 foundation (do NOT re-implement) [Source: frontend/src/components/*, frontend/src/app/App.tsx]
- Primitives: `Button` (`variant`, `loading`), `Field` (labelled input, `aria-invalid`/`aria-describedby`, `dir="auto"`), `FormLayout`, `Banner` (`info|error|success`, `role=alert` on error), `Link`, `Spinner`/`Skeleton`, `StateBlock` (`empty|error|offline`), `AppShell` (console wrapper, RTL-aware). No `Select`/`Checkbox`/`Modal` primitive exists — **none needed here** (publish uses a `Banner`, not a modal; create form is name + optional description text).
- Routing: no router lib — add a `PROGRAMS_ROUTE = /^\/programs\/?$/` regex in `App.tsx` `resolveRoute()` and return `<ConsoleGate>{(org) => <ProgramsPage organization={org} />}</ConsoleGate>` (the gate supplies the org; only authenticated-with-org users reach it). Mirror how `HomePage` is wired.
- API/error conventions: mirror `api/organizations.ts` + `schemas/organizations.ts` + the shared `ApiError` base [Source: frontend/src/api/errors.ts]; `API_BASE_URL` from `src/api/client.ts`; `credentials:'include'`; zod-parse responses. React Query: shared client `app/queryClient.ts`; `useMutation` + `queryClient.invalidateQueries(['programs'])` on success (the `OnboardingPage` pattern).
- RTL/lang: reuse `useDirection()` / `DirectionProvider`; never re-decide RTL per feature (UX-DR5). Wrap any interpolated value in copy with `<bdi>` [Source: EXPERIENCE.md#L104].
- **Closest page models:** `OnboardingPage.tsx` (form + mutation + error-preserves-input) and `HomePage.tsx` (minimal AppShell page receiving `organization`).

### UX decisions for this surface (resolved — were open questions)
- **No confirm modal for publish.** Publishing is *versioned, not destructive* (program stays editable; a published version is just frozen; re-publish makes a new version). EXPERIENCE.md reserves modals for true irreversibles — "final submit, reopen a decision" [Source: EXPERIENCE.md#L82]; the scoring screen uses a **banner warning, not a modal** for its near-irreversible action [Source: mockups/p1a-key-screens.html]. Use a `Banner` + strong copy. This keeps Story 1.0's deferral of the modal component intact (build it when a truly irreversible flow needs it — Epic 3 decisions).
- **Skip the FR-062 limit banner.** EXPERIENCE.md#L60 is explicit: entitlement is allow-all in P1a, so the limit banner has **no live trigger until P1b**. Don't build a 1a block that can never fire; defer the banner to P1b.
- **Clone/template UI deferred.** AC-3 is satisfied at the service + API + test level (the capability exists and is covered). A dedicated clone/template operator UI is a later surface — keep the 1.2 frontend to create + publish + list.
- **Minimal Programs surface.** List + create + publish only; no program detail/edit screen in 1.2 (defer to the form/cohort stories that need it).

### Testing standards
- **Backend:** feature tests `actingAs($user, 'web')` (only guard) + `X-Organization-Id: $org->id` header for tenant-scoped routes. Helpers in `backend/tests/TestCase.php`: `bootUserWithOrg($name) => [$user,$org]`, `createBareOrg($name)` (foreign org for isolation), `actingAsTenant($user,$org)` (sets `TenantContext` for direct service calls), `withoutTenantContext(fn)`. Model service/audit tests on `CohortLifecycleTest`; model API/auth/cross-tenant tests on `ProgramApiTest` [Source: backend/tests/Feature/Programs/ProgramApiTest.php]. Mock the entitlement gate by binding a throwing `EntitlementService` in the container.
- **Frontend:** Vitest + Testing Library (jsdom). Render wrapper = `<DirectionProvider><QueryClientProvider client={new QueryClient({defaultOptions:{queries:{retry:false}}})}>…`; clear the shared client between tests if used. Mock fetch via `vi.spyOn(globalThis,'fetch').mockResolvedValueOnce(jsonResponse(body,status))` (shared `jsonResponse` in `src/tests/test-utils.ts`). New pages join the axe gate (`src/tests/a11y.test.tsx`); new pages get a `*.stories.tsx` with an Arabic/RTL story.
- **Gates (run after coding):** Backend — `./vendor/bin/pint --dirty --test`, `./vendor/bin/phpstan analyse --memory-limit=1G` (L6), `php artisan test`, `php artisan scramble:export --path=openapi/openapi.json` if response shapes changed. Frontend — `npm run typecheck`, `npm run lint`, `npm run test`.
- **Per-story DoD (epics#L449-455):** unit + feature + **authorization** + **tenant-isolation (cross-tenant 404)** green; lint + static analysis pass; `organization_id` enforced on the new `program_versions` table + an isolation test (AR-6); docs updated where behavior changes.

### Project Structure Notes
- Backend touched: `app/Modules/Programs/Domain/Models/{ProgramVersion.php (NEW), Program.php (MODIFY)}`, `app/Modules/Programs/Application/PublishProgram.php (MODIFY)`, `database/migrations/*_create_program_versions_table.php (NEW)`, `tests/Feature/Programs/*`. Reuse kernels `App\Shared\{Versioning,Entitlement,Audit,Tenancy}` — no kernel edits.
- Frontend touched: `frontend/src/{schemas/programs.ts, api/programs.ts, api/programs.test.ts, pages/ProgramsPage.tsx, pages/ProgramsPage.stories.tsx}` (NEW), `frontend/src/app/App.tsx` + `frontend/src/tests/a11y.test.tsx` (MODIFY). Reuse `frontend/src/components/*`.
- **No `EntitlementService` change for create** — only `program.publish` is a gated call site in P1a (FR-060). Program **create** is not gated.
- No new npm/composer dependencies. `react-hook-form`+`zod` are available; the controlled-`Field`+`useState` pattern (OnboardingPage) is also fine.

### References
- [Source: _bmad-output/planning-artifacts/epics.md#L180-L193] — Story 1.2 ACs
- [Source: _bmad-output/planning-artifacts/epics.md#L62 (FR-060), #L34-L36 (FR-010/012/013), #L59 (FR-052), #L96 (AR-6), #L449-L455 (per-story DoD)] — requirements
- [Source: _bmad-output/planning-artifacts/architecture.md#L66-L70] — versioning/immutability reuse (ADR-1), tenancy opt-in per table (ADR-3)
- [Source: backend/app/Modules/Programs/Application/{PublishProgram,CloneProgram,CreateProgramFromTemplate}.php; Http/ProgramController.php#L135-L168; Policies/ProgramPolicy.php; routes/api.php#L60-L61] — existing program services, endpoints, policy
- [Source: backend/app/Shared/Versioning/{Versionable,VersionPublisher,ImmutableWhenPublished,VersionStatus}.php; backend/app/Modules/Forms/Domain/Models/FormVersion.php; backend/database/migrations/2026_06_20_000710_create_form_versions_table.php; 2026_06_18_002600_create_stage_versions_table.php] — versioning kernel + reference implementations
- [Source: backend/app/Modules/Cohorts/Application/OpenCohort.php#L21-L48] — EntitlementService gate + transaction + audit reference pattern
- [Source: backend/app/Shared/Entitlement/{EntitlementService,AllowAllEntitlementService}.php; backend/app/Providers/AppServiceProvider.php] — entitlement seam + binding
- [Source: backend/app/Shared/Audit/{AuditLogger,AuditAction,AuditLog}.php; backend/database/migrations/2026_06_18_000100_create_audit_logs_table.php] — audit kernel
- [Source: backend/tests/TestCase.php; backend/tests/Feature/Programs/ProgramApiTest.php; backend/tests/Feature/Cohorts/CohortLifecycleTest.php] — test helpers + the closest model tests
- [Source: frontend/src/app/App.tsx; frontend/src/api/{organizations.ts,errors.ts,client.ts}; frontend/src/schemas/organizations.ts; frontend/src/pages/{OnboardingPage,HomePage}.tsx; frontend/src/components/*; frontend/src/tests/{a11y.test.tsx,test-utils.ts}] — frontend foundation & conventions to mirror
- [Source: _bmad-output/planning-artifacts/ux-designs/ux-Catalesta-2026-06-20/EXPERIENCE.md#L34,#L56-L60,#L71,#L82,#L100-L109; DESIGN.md] — program-publish surface, state patterns, modal-vs-banner, RTL/a11y floor

## Dev Agent Record

### Agent Model Used

Claude Opus 4.8 (1M context) — Tasks 1 & 2 (backend) inline; Task 3 (frontend) via a delegated agent, all gates independently re-verified.

### Debug Log References

### Completion Notes List

- **Task 1 (backend):** Adopted the existing versioning kernel for programs — NEW `ProgramVersion` (mirrors `FormVersion`, no `content_hash`) + `program_versions` migration (sequenced by `version_number`, `unique(program_id, version_number)`, server-set `organization_id`). `PublishProgram` now mirrors `OpenCohort`: `EntitlementService::check('program.publish')` at the call site, then a single `DB::transaction` flips status → Published, freezes a `ProgramVersion` (snapshot = name/description/settings) via `VersionPublisher`, and emits `AuditAction::ProgramPublished`. `Program` stays editable (existing `test_patch_works_on_published_program` still green); the immutable artifact is the version. Added `Program::versions()`. `ProgramController::publish` unchanged (gate is constructor-injected). 9 new tests in `PublishProgramTest` (immutable version, update/delete-blocked, re-publish→new version, audited, entitlement-gate-blocks-and-rolls-back, cross-tenant 404, AR-6 scope, clone-of-published interaction). Backend gates: Pint clean, PHPStan L6 0 errors; Programs suite 52→ green incl. new tests.
- **Task 2 (backend):** AC-3 verified as already-built — `CloneProgram` + `CreateProgramFromTemplate` deep-copy into a new draft with a unique slug and audit; `CloneProgramTest` + `ProgramTemplateTest` already cover happy-path, member-403, and cross-tenant 404. Added one new interaction test (clone of a *published* program → fresh draft with 0 versions; source's version untouched). No service rewrite.
- **Task 3 (frontend):** new `/programs` console surface through `ConsoleGate` — `schemas/programs.ts` + `api/programs.ts` (mirror the org client + shared `ApiError`), `ProgramsPage` (list with `StateBlock` empty state, create form preserving input on error, text status badge, draft-only Publish with per-row loading, an `info` Banner explaining versioned/immutable publish — **no modal**, Story 1.0's modal deferral preserved). No FR-062 limit banner (allow-all in P1a). Clone/template UI deferred (AC-3 satisfied at API+test level). 25 new frontend tests + axe gate + LTR/RTL stories.
- **Gates (independently re-verified):** backend `php artisan test` 360 tests / 359 pass / 1 pgsql-skip (1123 assertions), Pint clean, PHPStan L6 0 errors; frontend typecheck clean, eslint clean, vitest 75/75 across 18 files. OpenAPI unchanged (no response-shape change — programs endpoints pre-existed).
- **Deviations (frontend agent, accepted):** (1) `CreateProgramError` uses `VALIDATION` (program create has no duplicate-specific UX, unlike org); (2) publish uses an `info` Banner (`Banner` has no `warning` variant); (3) Description reuses the single-line `Field` (no textarea primitive) marked optional; (4) status badge renders text + `data-status` (never colour-alone).

### File List

Task 1 (backend) — new:
- backend/app/Modules/Programs/Domain/Models/ProgramVersion.php
- backend/database/migrations/2026_06_21_000000_create_program_versions_table.php
- backend/tests/Feature/Programs/PublishProgramTest.php

Task 1 (backend) — modified:
- backend/app/Modules/Programs/Application/PublishProgram.php (entitlement gate + DB transaction + immutable ProgramVersion via VersionPublisher)
- backend/app/Modules/Programs/Domain/Models/Program.php (added versions() relationship)

Task 2 (backend) — modified:
- backend/tests/Feature/Programs/PublishProgramTest.php (added clone-of-published interaction test; clone/template otherwise covered by existing CloneProgramTest + ProgramTemplateTest)

Task 3 (frontend) — new:
- frontend/src/schemas/programs.ts
- frontend/src/api/programs.ts
- frontend/src/pages/ProgramsPage.tsx
- frontend/src/api/programs.test.ts
- frontend/src/pages/ProgramsPage.test.tsx
- frontend/src/pages/ProgramsPage.stories.tsx

Task 3 (frontend) — modified:
- frontend/src/app/App.tsx (added /programs route through ConsoleGate)
- frontend/src/tests/a11y.test.tsx (added ProgramsPage to the axe gate)

Code-review fixes — modified:
- backend/app/Modules/Programs/Application/PublishProgram.php (#3 — dropped the redundant pre-save; VersionPublisher now does a single INSERT, no transient draft row)
- frontend/src/api/errors.ts (#4 — added shared fieldMessage / readValidationDetails / firstValidationMessage helpers)
- frontend/src/api/organizations.ts (#4 — use the shared validation helpers)
- frontend/src/api/programs.ts (#4 — use the shared validation helpers; removed the duplicated local copies)

## Change Log

| Date | Change |
|------|--------|
| 2026-06-21 | Story drafted from epics + exhaustive code/docs analysis (backend Programs module + versioning kernel + frontend foundation deep-dives); 6 UX questions resolved with defaults; status → ready-for-dev |
| 2026-06-21 | Implemented on branch `feat/1-2-create-publish-program`: Task 1 (immutable `ProgramVersion` via versioning kernel + `program.publish` entitlement gate + transaction + audit), Task 2 (clone/template verified + clone-of-published interaction test), Task 3 (operator `/programs` create+publish surface). Backend 359 pass/1 skip + Pint/PHPStan; frontend typecheck/lint + 75 tests. Status → review |
| 2026-06-21 | Code review (8 finder angles, recall-biased; several candidates refuted against source). Applied 2 fixes: #3 dropped the redundant ProgramVersion pre-save (single INSERT via VersionPublisher), #4 extracted shared frontend validation-message helpers into api/errors.ts (de-dup vs organizations.ts). Noted as follow-ups (not this-story blockers): concurrent-publish version_number race (pre-existing kernel pattern → lockForUpdate in VersionPublisher) and snapshot-depth open question. Gates re-verified: backend Programs 53 pass + Pint/PHPStan; frontend typecheck/lint + tests green. |

## Open Questions (for PM/architect before or during dev)

1. **Version snapshot depth.** Task 1.3 snapshots `name/description/settings` into `ProgramVersion.definition`. Stages/policies/role-requirements are versioned separately (Stages has its own `stage_versions`). Confirm a program version need **not** deep-snapshot child stages for P1a (the stage versions carry their own immutability). *(Default: shallow program-level snapshot; child config versions are owned by their modules. Low risk.)*
2. **Archive-on-republish.** When a program is re-published, the prior `ProgramVersion` stays `Published` (multiple published versions coexist, distinguished by `version_number`) rather than being auto-archived. *(Default: keep all published versions resolvable — matches `form_versions`; no auto-archive. Confirm if you want a single "current" pointer instead.)*
