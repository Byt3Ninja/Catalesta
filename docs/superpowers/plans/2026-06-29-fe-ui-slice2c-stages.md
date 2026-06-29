# FE UI Slice 2c — Stages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the stage-pipeline authoring subsystem — a three-pane pipeline builder with ordered stages, per-stage entry/exit gating rules and conditional routing, parallel-stage grouping with dependencies, a read-only participant-journey preview, generic version history + compare, pipeline binding to a cohort, and the **Program configuration hub** that ties Forms (2b) and Stages together — UI-first on shadcn/Tailwind + MSW.

**Architecture:** Mirror the Forms (2b) versioning model exactly. A new `stages.ts` schema/api/MSW layer models a `StagePipeline`, its immutable numbered `StagePipelineVersion`s, and the working draft. Stage gating reuses the **existing rule-condition primitive from 2b** (`src/lib/visibility.ts`'s condition shape) rather than inventing a second predicate language — a pure `src/lib/stageRouting.ts` engine resolves the next stage(s) and validates ordering/parallelism/cycles. The builder holds a draft `StagePipelineVersion` in local state and autosaves via `saveStagePipelineDraft`. Reuse the generic `VersionHistoryList` + `VersionCompare` built in 2b (verified prop contracts below). Clone the `FormBindingPicker` pattern into `StagePipelineBindingPicker`. **No new shadcn `ui/` primitives** — build with existing `Button`/`Field`/`Input`/`Card`/`DropdownMenu` + native controls, exactly as `FormBuilderPage`/`ApplyField` do.

**Tech Stack:** React 19, Vite, TypeScript, shadcn/Tailwind, @tanstack/react-query, Zod, MSW, react-router-dom, Vitest + Testing Library, Playwright, Storybook.

## Authoritative Context

- Backend contract: `docs/plan/build-specs/06-stage-engine.md` — stage definitions, **versions, ordering, dependencies, parallel stages, conditional routing, entry/exit rules, participant state; published versions immutable; no arbitrary code execution in rules.**
- Versioning/immutability invariants: root `CLAUDE.md` § Versioning and Historical Integrity — published stages are immutable and versioned; executions retain exact version references.
- Predecessor slices: 2a (cohort lifecycle) deferred `stagePipelineVersionId` binding + the wizard "Attach stages" step + the `CohortDetailPage` "Stage pipeline" row to **2c**; 2a also added `/programs/:programId/config` as a `ComingSoon` placeholder "replaced in Slice 2c". 2b (forms) built `VersionHistoryList`/`VersionCompare` generic so "stages (2c) can pass stage descriptions".

## Global Constraints

- **Design system:** shadcn/Tailwind only; theme tokens (`bg-card`, `border-border`, `bg-secondary`, `text-secondary-foreground`, `text-muted-foreground`, `bg-primary`, `text-primary-foreground`, `bg-accent`) + `cn()` from `../lib/utils`. Reuse the status-badge pattern `<span data-status={...} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">`. **No new shadcn `ui/` primitives** — use existing ones + native controls styled with tokens.
- **Reuse, don't duplicate:**
  - Reuse the generic 2b components verbatim (props verified against source):
    - `VersionHistoryList({ versions: VersionItem[]; selectedIds: [string,string] | null; onSelect: (id: string) => void })`.
    - `VersionCompare({ left: Side; right: Side })` where `Side = { label: string; lines: string[] }`. Stages map each version to `lines` = ordered human-readable stage descriptions (`"1. Screening — entry: application.submitted; exit: score ≥ 70"`).
  - Reuse the rule-condition primitive from `src/lib/visibility.ts` (do NOT fork a second predicate language). A stage entry/exit rule is `{ all | any: Condition[] }` over the same `Condition` shape; `stageRouting.ts` evaluates it with the existing condition evaluator.
  - Clone `FormBindingPicker` → `StagePipelineBindingPicker` (same shape: list published versions, select, confirm, bind).
  - Extend `cohorts.ts` (schema + api) — add `stage_pipeline_version_id`; do NOT introduce new cohort objects.
- **snake_case schemas** to match `apply.ts`/`cohorts.ts`/`forms.ts`/Laravel: `pipeline_id`, `created_at`, `published_at`, `latest_version`, `published_version_ids`, `current_draft_version_id`, `stage_id`, `entry_rule`, `exit_rule`, `next_stage_ids`, `depends_on_stage_ids`, `parallel_group`, `stage_pipeline_version_id`.
- **UI-first:** pages call real `src/api/` clients; MSW intercepts `fetch`. Unit tests mock `fetch` directly via `vi.spyOn(globalThis,'fetch')` + `jsonResponse` from `../tests/test-utils`. MSW state is module-mutable and persists within a session.
- **AppShell no-idle-fetch invariant:** AppShell-rendered components must not fetch at mount beyond the roles query. Builder/preview/versions pages fetch on route entry (their own `useQuery`) — fine. Every test rendering a page containing `AppShell` includes `vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))`.
- **Test render helper:** `<DirectionProvider><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>…</QueryClientProvider></DirectionProvider>`. **No MemoryRouter** — pages take props, not `useParams`. Seed XSRF cookie in `beforeEach` when a mutation runs. `afterEach(() => vi.restoreAllMocks())`.
- **Versioning rule (mirror 2b):** Publish snapshots the current draft into an immutable numbered version (`status:'published'`, `published_at` set). Published versions are read-only; "Edit" forks a new draft. Binding offers published versions only. Historical bindings keep their exact `stage_pipeline_version_id`.
- **No arbitrary code in rules:** gating rules are declarative `Condition` trees only — no expression strings, no `eval`. Validation rejects unknown operators/fields.
- **`aria-label` caution:** never add an `aria-label` where visible text should be the accessible name.
- **Gate sweep includes `npm run lint`** alongside vitest/build/build-storybook/e2e.
- **Commits** end with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Run all commands from `cd frontend`; verify `git branch --show-current` is the 2c feature branch before each commit; `git add` only the task's files (never `-A`).

## File Structure

| File | Responsibility | Task |
|------|----------------|------|
| `src/schemas/stages.ts` | Zod: `StageRule` (reuse visibility `Condition`), `Stage`, `StagePipelineVersion`, `StagePipeline` + errors `GetPipelineError`/`SavePipelineError`/`PublishPipelineError` | 1 |
| `src/api/stages.ts` | `listStagePipelines`/`getStagePipeline`/`getStagePipelineVersion`/`createStagePipeline`/`saveStagePipelineDraft`/`publishStagePipeline`/`forkStagePipelineDraft`/`listStagePipelineVersions`/`listStageTemplates` | 1 |
| `src/api/stages.test.ts` | api unit tests | 1 |
| `src/mocks/handlers.ts` | + pipelines/versions/templates/binding handlers on a module-mutable store | 1, 7 |
| `src/mocks/handlers.stages.test.ts` | handler-registration guard | 1 |
| `src/lib/stageRouting.ts` | pure `resolveNextStages(pipeline, currentStageId, state)` + `validatePipeline(stages)` (cycle/parallel/dependency/unreachable checks) | 2 |
| `src/lib/stageRouting.test.ts` | engine unit tests | 2 |
| `src/pages/StagePipelinePreviewPage.tsx` (+ test/story) | read-only participant-journey render (ordered + parallel groups + routing arrows) | 3 |
| `src/pages/StagePipelineBuilderPage.tsx` | three-pane builder shell + palette + canvas | 4, 5, 6 |
| `src/components/StagePipelineCanvas.tsx` | ordered/parallel stage canvas, reorder, select | 4 |
| `src/components/StageInspector.tsx` | selected-stage config (name/type/entry+exit rule/dependencies/parallel group) | 5 |
| `src/components/StageRoutingEditor.tsx` | conditional next-stage routing editor (cycle-prevented) | 6 |
| `src/pages/StagePipelineBuilderPage.test.tsx` / `.stories.tsx` | builder tests + story | 4, 5, 6 |
| `src/components/StagePipelineVersionsPage`→`src/pages/StagePipelineVersionsPage.tsx` (+ test) | history + compare (reuses `VersionHistoryList` + `VersionCompare`) | 8 |
| `src/components/StagePipelineBindingPicker.tsx` (+ test) | bind a published pipeline version to a cohort (clone of `FormBindingPicker`) | 7 |
| `src/schemas/cohorts.ts` / `src/api/cohorts.ts` | + `stage_pipeline_version_id` + `bindCohortStagePipeline` | 7 |
| `src/pages/CohortDetailPage.tsx` | wire the real "Stage pipeline" row + binding entry | 7 |
| `src/pages/ProgramConfigPage.tsx` (+ test/story) | NEW program config hub (Forms + Stages cards, deep links) — replaces `ComingSoon` | 9 |
| `src/app/App.tsx` | + `/programs/:programId/stages/:pipelineId/edit\|preview\|versions` routes; replace `ProgramConfigRoute` body with `ProgramConfigPage` | 10 |
| `src/pages/CohortSetupWizard*.tsx` | wire wizard "Attach stages" step to `StagePipelineBindingPicker` (no longer placeholder) | 7 |
| `src/tests/a11y.test.tsx` | + cases for builder/preview/versions/config hub | 10 |
| `tests/e2e/fe-ui-slice2c.spec.ts` | build → publish → bind pipeline e2e | 10 |

---

### Task 1: Stages data layer — schema + api + MSW

**Files:** Create `src/schemas/stages.ts`, `src/api/stages.ts`, `src/api/stages.test.ts`, `src/mocks/handlers.stages.test.ts`; Modify `src/mocks/handlers.ts`.

**Interfaces — Produces:**
- Schema types `Stage`, `StagePipelineVersion`, `StagePipeline`; `StageRule = { match: 'all'|'any'; conditions: Condition[] }` reusing the `Condition` shape exported by `src/schemas/forms.ts`/`src/lib/visibility.ts` (import, do not redefine). Errors `GetPipelineError`, `SavePipelineError`, `PublishPipelineError`.
- `Stage` fields: `stage_id`, `name`, `type: 'review'|'interview'|'task'|'decision'|'automated'`, `entry_rule: StageRule | null`, `exit_rule: StageRule | null`, `next_stage_ids: string[]`, `depends_on_stage_ids: string[]`, `parallel_group: string | null`, `order: number`.
- `StagePipelineVersion` fields: `version_id`, `pipeline_id`, `version: number`, `status: 'draft'|'published'`, `stages: Stage[]`, `created_at`, `published_at: string | null`.
- `StagePipeline` fields: `pipeline_id`, `program_id`, `name`, `latest_version`, `published_version_ids: string[]`, `current_draft_version_id: string | null`, `created_at`.
- API: `listStagePipelines(programId)`, `getStagePipeline(id)`, `getStagePipelineVersion(versionId)`, `createStagePipeline(programId, name)`, `saveStagePipelineDraft(pipelineId, stages)`, `publishStagePipeline(pipelineId)`, `forkStagePipelineDraft(pipelineId, fromVersionId)`, `listStagePipelineVersions(pipelineId)`, `listStageTemplates()`.

**Steps:**
- [ ] Define schemas reusing `Condition` from forms; export inferred types + error classes mirroring `forms.ts`.
- [ ] Implement api clients (same `request`/XSRF/error-mapping helpers as `forms.ts`).
- [ ] Add MSW handlers on a module-mutable `pipelines`/`pipelineVersions` store with 2–3 seeded pipelines + a couple of `stageTemplates`. Publish clones draft → immutable numbered version; fork creates a new draft from a version.
- [ ] api unit tests (success + each error path) via `vi.spyOn(globalThis,'fetch')` + `jsonResponse`.
- [ ] `handlers.stages.test.ts` registration guard (asserts each route is registered).

**Gate:** `npm run lint && npx vitest run src/api/stages.test.ts src/mocks/handlers.stages.test.ts`.

---

### Task 2: Stage routing engine — `stageRouting.ts`

**Files:** Create `src/lib/stageRouting.ts`, `src/lib/stageRouting.test.ts`.

**Interfaces — Produces:**
- `resolveNextStages(stages: Stage[], currentStageId: string, state: Record<string, unknown>): string[]` — evaluates each candidate's `entry_rule` (via the shared condition evaluator) against participant `state` + the current stage's `next_stage_ids`; honors `depends_on_stage_ids` (a stage is reachable only when all deps are satisfied) and returns all members of a `parallel_group` together.
- `validatePipeline(stages: Stage[]): { ok: boolean; errors: PipelineError[] }` — detects cycles in `next_stage_ids`, unreachable stages, dangling references, dependency-before-order violations, and rules referencing unknown fields/operators.

**Steps:**
- [ ] Implement pure functions (no fetch, no React). Reuse the forms condition evaluator for rule evaluation.
- [ ] Unit tests: linear path, conditional branch, parallel group fan-out, dependency gating, cycle detection, unreachable-stage detection, unknown-operator rejection.

**Gate:** `npm run lint && npx vitest run src/lib/stageRouting.test.ts`.

---

### Task 3: Participant-journey preview page

**Files:** Create `src/pages/StagePipelinePreviewPage.tsx`, `.test.tsx`, `.stories.tsx`.

- Props (no `useParams`): `{ versionId: string }`. Fetches the version via `getStagePipelineVersion`.
- Read-only render: ordered stages as a vertical flow, parallel groups boxed side-by-side, routing summarized per stage ("→ Interview when score ≥ 70, else Rejected"). Uses status-badge pattern for stage `type`.
- Test: renders seeded version, asserts stage names + a routing summary line. Mock `roles`.

**Gate:** `npm run lint && npx vitest run src/pages/StagePipelinePreviewPage.test.tsx`.

---

### Task 4: Builder shell + canvas (palette / canvas / inspector frame)

**Files:** Create `src/pages/StagePipelineBuilderPage.tsx`, `src/components/StagePipelineCanvas.tsx`; create test/story stubs extended in 5–6.

- Three-pane layout (palette of stage types | canvas | inspector), mirroring `FormBuilderPage`. Holds a draft `StagePipelineVersion` in local state; autosaves via `saveStagePipelineDraft` (debounced, same pattern as 2b).
- `StagePipelineCanvas`: ordered list with add-from-palette, reorder (up/down buttons — native, no DnD lib unless 2b used one), select-stage, parallel-group affordance. Selecting a stage drives the inspector.
- Builder is disabled/read-only when viewing a published version; "Edit" forks a draft (`forkStagePipelineDraft`).
- Tests: add a stage, reorder, select → inspector receives it; autosave called.

**Gate:** `npm run lint && npx vitest run src/pages/StagePipelineBuilderPage.test.tsx`.

---

### Task 5: Stage inspector

**Files:** Create `src/components/StageInspector.tsx`; extend builder tests.

- Configures selected stage: `name`, `type`, `depends_on_stage_ids` (multi-select of earlier stages), `parallel_group`, and `entry_rule`/`exit_rule` via a rule editor that reuses the forms condition controls.
- Edits mutate the draft up through the builder; no direct fetch.
- Tests: editing name/type/dependency updates draft; rule edit produces a valid `StageRule`.

**Gate:** `npm run lint && npx vitest run src/pages/StagePipelineBuilderPage.test.tsx`.

---

### Task 6: Conditional routing editor (cycle-prevented)

**Files:** Create `src/components/StageRoutingEditor.tsx`; extend builder tests.

- Edits a stage's `next_stage_ids` with optional per-edge condition (reusing the `Condition` controls). Offers only stages that do not introduce a cycle (calls `validatePipeline` on candidate edges; mirror 2b's `VisibilityEditor` cycle-prevention).
- Surfaces `validatePipeline` errors inline (unreachable, dangling, cycle).
- Tests: adding a back-edge that would cycle is blocked; valid branch is accepted; validation errors render.

**Gate:** `npm run lint && npx vitest run src/pages/StagePipelineBuilderPage.test.tsx`.

---

### Task 7: Cohort binding + wizard wiring

**Files:** Modify `src/schemas/cohorts.ts`, `src/api/cohorts.ts`, `src/mocks/handlers.ts`, `src/pages/CohortDetailPage.tsx`, the Cohort setup wizard page(s); Create `src/components/StagePipelineBindingPicker.tsx` (+ test).

- Schema: add `stage_pipeline_version_id: z.string().nullable().optional()` adjacent to the existing `bound_form_version_id`.
- api: `bindCohortStagePipeline(cohortId, versionId)`; MSW handler updates the cohort + offers published versions only.
- `StagePipelineBindingPicker` cloned from `FormBindingPicker`: lists published versions of the program's pipelines, select + confirm → bind.
- `CohortDetailPage`: replace the 2a "Stages: not configured" placeholder row with the real bound-version display + "Configure"/"Change" entry to the picker. Keep the existing "Stage pipeline" `<dt>` label.
- Wizard: replace the placeholder "Attach stages" step body with the binding picker (still skippable, per the 2a build-order note).
- Tests: bind flow updates cohort; detail row reflects bound version; wizard step binds.

**Gate:** `npm run lint && npx vitest run src/components/StagePipelineBindingPicker.test.tsx src/pages/CohortDetailPage.test.tsx src/api/cohorts.test.ts`.

---

### Task 8: Version history + compare page

**Files:** Create `src/pages/StagePipelineVersionsPage.tsx` (+ test).

- Props: `{ pipelineId: string }`. Fetches versions via `listStagePipelineVersions`.
- Reuse `VersionHistoryList` (verified props: `versions`, `selectedIds: [string,string]|null`, `onSelect`) for selection, and `VersionCompare` (`left`/`right` = `{ label, lines }`) where `lines` are the ordered stage-description strings for each selected version.
- "Edit" on a published version forks a draft and routes to the builder.
- Tests: select two versions → compare renders added/removed stage lines; fork routes.

**Gate:** `npm run lint && npx vitest run src/pages/StagePipelineVersionsPage.test.tsx`.

---

### Task 9: Program configuration hub

**Files:** Create `src/pages/ProgramConfigPage.tsx` (+ test/story).

- Replaces the `ComingSoon` placeholder behind `/programs/:programId/config` (App.tsx ~L178). Props: `{ programId: string }`.
- Two cards: **Forms** (links to the program's forms — list + "open builder", reusing `listForms`) and **Stages** (links to the program's pipelines via `listStagePipelines`, "open builder", "new pipeline" via `createStagePipeline`). Shows published-version counts and draft state.
- Tests: lists forms + pipelines for the program; "new pipeline" creates + routes to builder. Mock `roles`.

**Gate:** `npm run lint && npx vitest run src/pages/ProgramConfigPage.test.tsx`.

---

### Task 10: Routes, a11y, e2e, final gate

**Files:** Modify `src/app/App.tsx`, `src/tests/a11y.test.tsx`; Create `tests/e2e/fe-ui-slice2c.spec.ts`.

- Routes: add `/programs/:programId/stages/:pipelineId/edit|preview|versions` (each behind `ConsoleGate`, reading the param into a route wrapper that passes props — same pattern as the `/forms/:formId/*` wrappers). Replace `ProgramConfigRoute`'s `ComingSoonPage` body with `<ProgramConfigPage programId={…} />`. Remove the now-stale "Placeholder until Slice 2c" comment.
- a11y: add `axe` cases for builder, preview, versions, and config hub (no violations).
- e2e: program config hub → new pipeline → add/route stages → publish → bind to a cohort → cohort detail shows the bound version.
- **Final gate sweep:** `npm run lint && npx vitest run && npm run build && npm run build-storybook && npx playwright test tests/e2e/fe-ui-slice2c.spec.ts`.

---

## Acceptance Criteria → Test Mapping

| AC | Test |
|----|------|
| Pipeline drafts save; publish creates an immutable numbered version | `api/stages.test.ts`, `handlers.stages.test.ts` |
| Published versions are read-only; Edit forks a draft | builder + versions tests |
| Routing engine resolves linear/conditional/parallel/dependency paths; rejects cycles & unknown operators | `lib/stageRouting.test.ts` |
| Builder edits stages, rules, dependencies, routing without cycles | `StagePipelineBuilderPage.test.tsx` |
| A published pipeline version binds to a cohort; historical binding keeps its exact version id | `StagePipelineBindingPicker.test.tsx`, `cohorts.test.ts`, `CohortDetailPage.test.tsx` |
| Version compare shows stage-level diffs | `StagePipelineVersionsPage.test.tsx` |
| Program config hub lists/creates forms + pipelines | `ProgramConfigPage.test.tsx` |
| No a11y violations on new surfaces | `a11y.test.tsx` |
| End-to-end build→publish→bind | `tests/e2e/fe-ui-slice2c.spec.ts` |

## Risks / Notes

- **Rule language reuse:** if 2b's `Condition` shape proves too narrow for stage gating (e.g. needs cross-stage state references), extend it in `visibility.ts`/`forms.ts` rather than forking — record the extension as a note in this plan. Do not introduce a second predicate dialect.
- **Reorder UX:** match whatever 2b used for field reordering (native up/down vs DnD). Do not add a DnD dependency just for stages.
- **Scope boundary:** this slice is authoring + binding only. Runtime participant *advancement through* the pipeline (executing entry/exit rules against live applicants) is a later slice — preview is illustrative, not an execution engine.
- **Backend reality:** UI-first on MSW; the Laravel `Stages` module already exists (`app/Modules/Stages`, versioned engine) but wiring real endpoints is out of scope here, consistent with 2a/2b.
