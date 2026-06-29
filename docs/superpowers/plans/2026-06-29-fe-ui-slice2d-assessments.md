# FE UI Slice 2d — Assessments (Score & Decide) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Selection-MVP "Score & Decide" surface — author versioned/immutable scoring models, assign reviewers (blind, round-robin), capture per-reviewer scorecards, aggregate to a per-stage leaderboard, and decide (advance/reject/waitlist) with an immutable snapshot.

**Architecture:** Mirror the Forms (2b) / Stages (2c) UI-first model exactly. A new `assessments.ts` schema/api/MSW layer models a `ScoringModel`, its immutable numbered `ScoringModelVersion`s, `ReviewerAssignment`s, `Scorecard`s, and `Decision`s. Scoring math is **additive points** (per-criterion `max_points` IS the weight) computed with decimal scale-2 half-up via a tiny in-repo `lib/decimal.ts`; pure `lib/scoring.ts` + `lib/reviewerAssignment.ts` engines hold all math with no fetch/React. Authoring surfaces clone the 2c builder/versions/preview/binding patterns; reviewer surfaces (blind queue + scorecard) are new; manager leaderboard/decide extend `SubmissionsPage`. **Per-stage binding** is stored on a cohort `stage_scoring_model_version_ids` map, decoupled from the immutable pipeline version.

**Tech Stack:** React 19, Vite, TypeScript, shadcn/Tailwind 4, @tanstack/react-query, Zod, MSW, react-router-dom, Vitest + Testing Library, Playwright, Storybook.

## Authoritative Context

- Design spec: `docs/superpowers/specs/2026-06-29-fe-ui-slice2d-assessments-design.md` (approved).
- Backend contract: `docs/plan/build-specs/10-assessment-engine.md` — templates, criteria, rubrics, evaluator assignment, blind review, decimal scoring, aggregation, disqualification; **published versions immutable; no arbitrary code in rules**; the canonical decisions table is owned by `08-application-management.md` (do not create a second outcomes table).
- Versioning/immutability: root `CLAUDE.md` § Versioning and Historical Integrity — published scoring models immutable + versioned; submissions retain exact version references; decimal arithmetic with defined precision/scale/rounding/missing-value.
- Backend reality: Assessments is a scaffold with no routes (`docs/status/implementation-status.md`, 2026-06-29) — this slice is **UI-first on MSW**, like 2b/2c.

## Global Constraints

- **Design system:** shadcn/Tailwind only; theme tokens (`bg-card`, `border-border`, `bg-secondary`, `text-secondary-foreground`, `text-muted-foreground`, `bg-primary`, `text-primary-foreground`, `bg-accent`, `text-destructive`) + `cn()` from `../lib/utils`. Status-badge pattern: `<span data-status={…} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">`. **No new shadcn `ui/` primitives** — existing `Button`/`Field`/`Link`/`Spinner`/`StateBlock`/`Banner` + native controls.
- **Reuse, don't duplicate:** `VersionHistoryList({ versions: {id,version,status,published_at}[]; selectedIds: [string,string]|null; onSelect })` and `VersionCompare({ left, right })` where side = `{ label, lines: string[] }`. Clone `StagePipelineBindingPicker` → `ScoringModelBindingPicker`. Clone `StagePipelineBuilderPage`/`StagePipelineCanvas`/`StageInspector` → scoring-model builder/canvas/criterion-inspector. Clone `StagePipelineVersionsPage`/`StagePipelinePreviewPage`.
- **snake_case schemas** to match `stages.ts`/`cohorts.ts`/Laravel.
- **Decimal:** scale = 2, rounding = **half-up**; sums computed in integer "cents" to avoid float drift; model max is the constant denominator. Points authored as integer or 2-dp decimal, clamped `0..max_points`.
- **Immutability:** publish snapshots the draft criteria into an immutable numbered version (`status:'published'`, `published_at` set); published versions read-only; "Edit" forks a new draft; binding offers published versions only; decisions keep an exact snapshot (`model_version_id` + scorecards + mean).
- **No arbitrary code in rules:** criteria + descriptors are declarative data only — no expression strings, no `eval`.
- **UI-first test rules:** pages call real `src/api/` clients; MSW intercepts `fetch`. Unit tests mock `fetch` via `vi.spyOn(globalThis,'fetch')` + `jsonResponse` from `../tests/test-utils`. Seed XSRF cookie in `beforeEach` when a mutation runs (`Object.defineProperty(document,'cookie',{value:'XSRF-TOKEN=t',writable:true,configurable:true})`). `afterEach(() => vi.restoreAllMocks())` (+ `vi.unstubAllGlobals()` when stubbing `location`).
- **AppShell no-idle-fetch invariant:** every test rendering a page containing `AppShell` includes `vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))`. Pages that embed the binding picker / scoring queries must mock `../api/assessments` in unrelated content tests (as `CohortDetailPage.test.tsx` mocks `../api/stages`).
- **Test render helper:** `<DirectionProvider><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>…</QueryClientProvider></DirectionProvider>`. No MemoryRouter — pages take props.
- **`aria-label` caution:** never add an `aria-label` where visible text should be the accessible name.
- **Commits** end with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Run all commands from `cd frontend`; verify `git branch --show-current` is `feat/fe-ui-slice2d-assessments` before each commit; `git add` only the task's files (never `-A`).
- **Per-task gate:** `cd frontend && npm run lint && npx vitest run <task files> && npx tsc --noEmit`.

## File Structure

| File | Responsibility | Task |
|------|----------------|------|
| `src/schemas/assessments.ts` | Zod: `ScoringCriterion`, `ScoringModel`, `ScoringModelVersion`, `ReviewerAssignment`, `Scorecard`, `Decision` + errors | 1 |
| `src/api/assessments.ts` (+ `.test.ts`) | model CRUD/draft/publish/fork/versions; assignments; scorecards; leaderboard; decisions | 1 |
| `src/mocks/handlers.ts` (+ `handlers.assessments.test.ts`) | module-mutable stores + handlers; publish/fork; round-robin generate; submit completeness 422 | 1, 8, 11 |
| `src/lib/decimal.ts` (+ `.test.ts`) | scale-2 half-up helpers (`roundHalfUp2`, `sumPoints`, `mean`, `format2`) | 2 |
| `src/lib/scoring.ts` (+ `.test.ts`) | `scoreCard`, `aggregate`, `proposeDecisions` | 2 |
| `src/lib/reviewerAssignment.ts` (+ `.test.ts`) | pure round-robin `assign` | 2 |
| `src/pages/ScoringModelBuilderPage.tsx` + `src/components/ScoringModelCanvas.tsx` (+ test/story) | builder shell + criteria canvas | 3, 4 |
| `src/components/ScoringCriterionInspector.tsx` | selected-criterion config | 4 |
| `src/pages/ScoringModelPreviewPage.tsx` (+ test/story) | read-only criteria/max breakdown | 5 |
| `src/pages/ScoringModelVersionsPage.tsx` (+ test) | history + compare (reuse generics) | 6 |
| `src/schemas/cohorts.ts` / `src/api/cohorts.ts` / `src/api/cohorts.test.ts` | `stage_scoring_model_version_ids` map + `bindCohortStageScoringModel` | 7 |
| `src/components/ScoringModelBindingPicker.tsx` (+ test) | bind a published version to a stage of the cohort's pipeline | 7 |
| `src/pages/CohortDetailPage.tsx` (+ test) | per-stage scoring binding rows | 7 |
| `src/pages/ReviewQueuePage.tsx` (+ test/story) | blind assigned-application queue | 8 |
| `src/pages/ScorecardPage.tsx` (+ test/story) | blind per-criterion scoring; block-submit; disqualify | 9 |
| `src/pages/SubmissionsPage.tsx` (+ test) | stage-scoped leaderboard view | 10 |
| `src/pages/SubmissionsPage.tsx` (decide) | threshold-assisted decide + commit | 11 |
| `src/pages/ProgramConfigPage.tsx` (+ test) | third "Scoring" card | 12 |
| `src/app/App.tsx` | scoring + review routes | 12 |
| `src/tests/a11y.test.tsx` | builder/preview/versions/queue/scorecard/config cases | 12 |
| `tests/e2e/fe-ui-slice2d.spec.ts` | author → bind → assign → score → decide e2e | 12 |

---

### Task 1: Assessments data layer — schema + api + MSW

**Files:** Create `src/schemas/assessments.ts`, `src/api/assessments.ts`, `src/api/assessments.test.ts`, `src/mocks/handlers.assessments.test.ts`; Modify `src/mocks/handlers.ts`.

**Interfaces — Produces:**
- Types: `ScoringCriterion = { criterion_id: string; label: string; max_points: number; descriptors: string[] | null }`; `ScoringModel = { model_id; program_id; name; latest_version: number; published_version_ids: string[]; current_draft_version_id: string | null; created_at }`; `ScoringModelVersion = { version_id; model_id; version: number; status: 'draft'|'published'; criteria: ScoringCriterion[]; created_at; published_at: string | null }`; `ReviewerAssignment = { assignment_id; cohort_id; stage_id; application_id; reviewer_id; status: 'pending'|'submitted' }`; `Scorecard = { scorecard_id; cohort_id; stage_id; application_id; reviewer_id; model_version_id; values: Record<string,number>; disqualified: boolean; status: 'draft'|'submitted'; submitted_at: string | null }`; `Decision = { decision_id; cohort_id; stage_id; application_id; outcome: 'advance'|'reject'|'waitlist'; snapshot: { model_version_id: string; scorecards: Scorecard[]; mean: string; decided_at: string }; decided_by: string }`.
- Errors mirroring `stages.ts`: `GetScoringModelError`, `SaveScoringModelError`, `PublishScoringModelError`, `AssignmentError`, `ScorecardError`, `DecisionError` (codes `'NOT_FOUND'|'FORBIDDEN'|'VALIDATION'|'CONFLICT'|'UNAUTHENTICATED'|'UNKNOWN'`).
- API: `listScoringModels(programId)`, `getScoringModel(id)`, `getScoringModelVersion(versionId)`, `createScoringModel(programId,name)`, `saveScoringModelDraft(modelId, criteria)`, `publishScoringModel(modelId)`, `forkScoringModelDraft(modelId, fromVersionId)`, `listScoringModelVersions(modelId)`, `listAssignments(cohortId, stageId)`, `getScorecard(cohortId, stageId, applicationId, reviewerId)`. (Mutating assignment/scorecard/decision endpoints land in Tasks 8/9/11.)
- MSW routes: `GET /programs/:programId/scoring-models`, `GET /scoring-models/:id`, `GET /scoring-model-versions/:versionId`, `POST /programs/:programId/scoring-models`, `PATCH /scoring-models/:id/draft`, `POST /scoring-models/:id/publish`, `POST /scoring-models/:id/fork`, `GET /scoring-models/:id/versions`, `GET /cohorts/:cohortId/stages/:stageId/assignments`, `GET /cohorts/:cohortId/stages/:stageId/scorecards/:applicationId/:reviewerId`.

**Steps:**
- [ ] Define Zod schemas + inferred types + error classes, mirroring `src/schemas/stages.ts` exactly (response wrappers `{ data }`).
- [ ] Implement api clients reusing the `apiFetch`/`csrfFetch` + status→error mapping from `src/api/stages.ts`.
- [ ] Add MSW stores `scoringModels`/`scoringModelVersions` (2 seeded models: one published `sm_pub` with version `smv_pub_1` containing 3 criteria, one draft `sm_draft`) + empty `assignments`/`scorecards`/`decisions` arrays; handlers for the routes above. Publish clones draft→immutable numbered version; fork creates a draft from a version (copy `stages.ts` handler logic, s/stages/criteria).
- [ ] `assessments.test.ts`: success + each error path via `vi.spyOn(globalThis,'fetch')` + `jsonResponse`.
- [ ] `handlers.assessments.test.ts`: registration guard asserting each route resolves (mirror `handlers.stages.test.ts`).

**Gate:** `cd frontend && npm run lint && npx vitest run src/api/assessments.test.ts src/mocks/handlers.assessments.test.ts && npx tsc --noEmit`.

---

### Task 2: Pure engines — decimal, scoring, assignment

**Files:** Create `src/lib/decimal.ts`, `src/lib/decimal.test.ts`, `src/lib/scoring.ts`, `src/lib/scoring.test.ts`, `src/lib/reviewerAssignment.ts`, `src/lib/reviewerAssignment.test.ts`.

**Interfaces — Produces:**
- `decimal.ts`:
  ```ts
  const cents = (n: number) => Math.round(n * 100)          // half-up for n >= 0
  export const roundHalfUp2 = (n: number): number => cents(n) / 100
  export const sumPoints = (values: number[]): number => values.reduce((a, v) => a + cents(v), 0) / 100
  export const mean = (values: number[]): number =>
    values.length === 0 ? 0 : Math.round(values.reduce((a, v) => a + cents(v), 0) / values.length) / 100
  export const format2 = (n: number): string => n.toFixed(2)
  ```
- `scoring.ts`:
  ```ts
  import type { ScoringCriterion, Scorecard } from '../schemas/assessments'
  export function scoreCard(criteria: ScoringCriterion[], values: Record<string, number>):
    { earned: number; max: number; complete: boolean }
  // earned = sumPoints of present values; max = sumPoints of max_points;
  // complete = every criterion has a finite value in [0, max_points]
  export function aggregate(criteria: ScoringCriterion[], cards: Scorecard[]):
    { mean: number; model_max: number; count: number; min: number; max: number; disqualified: boolean }
  // only status==='submitted' cards; per-card earned via scoreCard;
  // model_max = sumPoints(max_points) (constant denominator); mean = mean(earned[]);
  // min/max = Math.min/max of earned (0 when none); disqualified = cards.some(c => c.disqualified)
  export function proposeDecisions(rows: { application_id: string; mean: number; disqualified: boolean }[], cutoff: number):
    { application_id: string; proposal: 'advance' | 'reject' }[]
  // disqualified -> reject; else mean >= cutoff ? advance : reject
  ```
- `reviewerAssignment.ts`:
  ```ts
  export function assign(applicationIds: string[], panelReviewerIds: string[], perApp: number):
    { application_id: string; reviewer_ids: string[] }[]
  // deterministic round-robin: rotating pointer over panel; perApp clamped to panel length;
  // each app gets the next `perApp` distinct reviewers cyclically
  ```

**Steps:**
- [ ] Write `decimal.test.ts`: `sumPoints([15,18,8])===41`; `mean([46,41,38])===41.67`; `mean([])===0`; `roundHalfUp2(41.665)===41.67`; `format2(41.67)==='41.67'`. Implement `decimal.ts`. Run → pass.
- [ ] Write `scoring.test.ts`: complete vs incomplete `scoreCard` (missing criterion → `complete:false`); `aggregate` mean+spread over 3 submitted cards; draft cards excluded; `disqualified` true when any card flagged; `proposeDecisions` (above cutoff→advance, below→reject, disqualified→reject regardless). Implement `scoring.ts`. Run → pass.
- [ ] Write `reviewerAssignment.test.ts`: 4 apps × panel [A,B,C,D] perApp 2 → each app 2 distinct reviewers, balanced load (each reviewer count within ±1); perApp>panel clamps; empty panel → empty reviewer_ids. Implement `reviewerAssignment.ts`. Run → pass.
- [ ] Commit.

**Gate:** `cd frontend && npm run lint && npx vitest run src/lib/decimal.test.ts src/lib/scoring.test.ts src/lib/reviewerAssignment.test.ts && npx tsc --noEmit`.

---

### Task 3: Scoring-model builder shell + criteria canvas

**Files:** Create `src/pages/ScoringModelBuilderPage.tsx`, `src/components/ScoringModelCanvas.tsx`, `src/pages/ScoringModelBuilderPage.test.tsx`, `src/pages/ScoringModelBuilderPage.stories.tsx`.

**Interfaces — Produces:**
- `ScoringModelBuilderPage({ modelId: string })` — clone of `StagePipelineBuilderPage`: holds draft criteria in local state, debounced (400 ms) autosave via `saveScoringModelDraft`, publish (disabled when 0 criteria), read-only + "Edit (new draft)" fork when viewing a published version; dirty-tracking so autosave never fires on load/seed; canvas `data-version-id={seededId}`.
- `ScoringModelCanvas({ criteria, selectedId, readOnly, onSelect, onMove, onRemove })` — clone of `StagePipelineCanvas`: ordered criteria list (label + `max N pts` badge), native up/down reorder, select, remove. "Add criterion" button in the builder palette adds `{ criterion_id: 'crit_<seq>', label: 'New criterion', max_points: 10, descriptors: null }`.

**Steps:**
- [ ] Clone `StagePipelineCanvas.tsx` → `ScoringModelCanvas.tsx` (criteria instead of stages; badge shows `max {max_points} pts`; no parallel-group concept).
- [ ] Clone `StagePipelineBuilderPage.tsx` → `ScoringModelBuilderPage.tsx`: swap api (`getScoringModel`/`getScoringModelVersion`/`saveScoringModelDraft`/`publishScoringModel`/`forkScoringModelDraft`), state holds `ScoringCriterion[]`, palette = single "Add criterion" button. Inspector pane = minimal selected-criterion summary (Task 4 replaces it).
- [ ] Test (mirror `StagePipelineBuilderPage.test.tsx`): add criterion → canvas shows it; reorder; autosave does NOT fire on load; autosave fires after add (PATCH `/scoring-models/sm_draft/draft`); publish flips to read-only; published → Edit forks. Story mirrors the 2c builder story.

**Gate:** `cd frontend && npm run lint && npx vitest run src/pages/ScoringModelBuilderPage.test.tsx && npx tsc --noEmit`.

---

### Task 4: Criterion inspector

**Files:** Create `src/components/ScoringCriterionInspector.tsx`; Modify `src/pages/ScoringModelBuilderPage.tsx` (wire inspector); extend `src/pages/ScoringModelBuilderPage.test.tsx`.

**Interfaces — Produces:**
- `ScoringCriterionInspector({ criterion, readOnly, onChange })` where `onChange(patch: Partial<ScoringCriterion>)`. Fields: `label` (`Field`), `max_points` (`Field type="number" min={0}` → `Number(e.target.value)`), `descriptors` (a small add/remove list of free-text guidance lines → `string[] | null`). No direct fetch; edits flow up through the builder's `updateCriteria` (dirty-tracked).

**Steps:**
- [ ] Create `ScoringCriterionInspector.tsx` (mirror `StageInspector.tsx` shape minus rule/dependency/routing editors; descriptors editor mirrors the inline add/remove rows pattern).
- [ ] Wire it into the builder inspector pane (`onChange={(patch) => updateCriteria(criteria.map(c => c.criterion_id === selected.criterion_id ? { ...c, ...patch } : c))}`).
- [ ] Extend builder test: select a criterion → inspector shows its label value; edit label → canvas reflects it; edit `max_points` → canvas badge updates; the draft PATCH body carries the new `max_points`.

**Gate:** `cd frontend && npm run lint && npx vitest run src/pages/ScoringModelBuilderPage.test.tsx && npx tsc --noEmit`.

---

### Task 5: Scoring-model preview page

**Files:** Create `src/pages/ScoringModelPreviewPage.tsx`, `.test.tsx`, `.stories.tsx`.

**Interfaces — Produces:**
- `ScoringModelPreviewPage({ versionId: string })` — fetch via `getScoringModelVersion`; read-only ordered criteria cards (`1. Team`, `max 20 pts`, descriptors list), and a footer "Total possible: {Σ max_points} pts" using `sumPoints`. Loading/error/empty states. `<h2>` per criterion under the page `<h1>` (heading-order).

**Steps:**
- [ ] Clone `StagePipelinePreviewPage.tsx` → `ScoringModelPreviewPage.tsx`; replace stage rendering with criteria rendering + total.
- [ ] Test: renders seeded version's criterion labels + the total-possible line; error state on 404. Mock `roles`. Story mirrors the 2c preview story.

**Gate:** `cd frontend && npm run lint && npx vitest run src/pages/ScoringModelPreviewPage.test.tsx && npx tsc --noEmit`.

---

### Task 6: Version history + compare page

**Files:** Create `src/pages/ScoringModelVersionsPage.tsx`, `.test.tsx`.

**Interfaces — Produces:**
- `ScoringModelVersionsPage({ modelId: string })` — clone of `StagePipelineVersionsPage`: fetch model (name + published ids) + versions; reuse `VersionHistoryList` + `VersionCompare`; `criteriaToLines(criteria)` → `"1. Team — max 20"`; "Edit (new draft)" forks latest published and `window.location.assign('/programs/{program_id}/scoring/{modelId}/edit')`.

**Steps:**
- [ ] Clone `StagePipelineVersionsPage.tsx` → `ScoringModelVersionsPage.tsx`; swap api + line mapper.
- [ ] Test (mirror `StagePipelineVersionsPage.test.tsx`): select two versions → diff shows added/removed criterion lines (`data-diff`); Edit forks (POST `/scoring-models/:id/fork`) + `assign` to builder URL; versions load-error state. Stub `location` via `vi.stubGlobal`.

**Gate:** `cd frontend && npm run lint && npx vitest run src/pages/ScoringModelVersionsPage.test.tsx && npx tsc --noEmit`.

---

### Task 7: Per-stage binding + cohort wiring

**Files:** Modify `src/schemas/cohorts.ts`, `src/api/cohorts.ts`, `src/api/cohorts.test.ts`, `src/mocks/handlers.ts`, `src/pages/CohortDetailPage.tsx`, `src/pages/CohortDetailPage.test.tsx`; Create `src/components/ScoringModelBindingPicker.tsx`, `.test.tsx`.

**Interfaces — Produces:**
- Cohort schema: add `stage_scoring_model_version_ids: z.record(z.string(), z.string()).nullable().optional()`.
- api: `bindCohortStageScoringModel(cohortId, stageId, versionId)` → `POST /cohorts/:id/bind-stage-scoring-model` body `{ stage_id, scoring_model_version_id }` → updated cohort; MSW handler sets `cohort.stage_scoring_model_version_ids[stage_id] = versionId`. New `BindScoringModelError` in `schemas/cohorts.ts` (mirror `BindStagePipelineError`).
- `ScoringModelBindingPicker({ cohortId, programId, stageId, boundVersionId, onBound })` — clone of `StagePipelineBindingPicker`: lists the program's scoring models → published versions only → select + Bind (`bindCohortStageScoringModel(cohortId, stageId, versionId)`); `data-testid="bound-scoring-label"`; select id `scoring-binding-select`.

**Steps:**
- [ ] Add the cohort schema field + `BindScoringModelError` + api client + MSW handler (mirror the bind-stage-pipeline handler).
- [ ] `cohorts.test.ts`: `bindCohortStageScoringModel` POSTs `{ stage_id, scoring_model_version_id }` to the right URL + returns cohort with the map entry; 404 → NOT_FOUND; 409 → CONFLICT.
- [ ] Create `ScoringModelBindingPicker.tsx` by cloning `StagePipelineBindingPicker.tsx` (program-scoped list; published-only; one extra `stageId` arg threaded into the bind call). Test: published-only options; bind → `onBound` + correct POST body; bound label.
- [ ] `CohortDetailPage`: under "Stage pipeline", when a pipeline is bound render a **per-stage scoring** sub-section — one row per stage of the bound pipeline version (fetch via `getStagePipelineVersion(cohort.stage_pipeline_version_id)`), each showing `Bound: {map[stage_id] ?? 'not configured'}` + a `ScoringModelBindingPicker` for that `stageId`. Mock `../api/assessments` + `../api/stages` in `CohortDetailPage.test.tsx`; assert a bound stage row reflects its version id.

**Gate:** `cd frontend && npm run lint && npx vitest run src/components/ScoringModelBindingPicker.test.tsx src/pages/CohortDetailPage.test.tsx src/api/cohorts.test.ts && npx tsc --noEmit`.

---

### Task 8: Reviewer assignment + blind review queue

**Files:** Modify `src/api/assessments.ts`, `src/mocks/handlers.ts`; Create `src/pages/ReviewQueuePage.tsx`, `.test.tsx`, `.stories.tsx`.

**Interfaces — Produces:**
- api: `generateAssignments(cohortId, stageId, { reviewer_ids: string[]; per_app: number })` → `POST /cohorts/:id/stages/:stageId/assignments` → `ReviewerAssignment[]`; handler reads the stage's applications (reuse the seeded cohort submissions), runs `assign(...)` from `lib/reviewerAssignment`, replaces the store slice, returns them. `listAssignments(cohortId, stageId)` already exists (Task 1).
- `ReviewQueuePage({ cohortId, stageId, reviewerId })` — fetch `listAssignments` filtered to `reviewer_id === reviewerId`; **blind**: show a masked application label (e.g. `Application #{index+1}` or `application_id` only — never applicant name) + status badge (pending/submitted) + a `Link` to `/cohorts/{cohortId}/stages/{stageId}/review/{application_id}`. Loading/error/empty states.

**Steps:**
- [ ] Add `generateAssignments` api + MSW handler (uses `lib/reviewerAssignment.assign`).
- [ ] Create `ReviewQueuePage.tsx` (blind list). Test: seeds assignments for `reviewerId`, asserts masked labels + status + scorecard links render; asserts **no applicant identity field** is shown. Story renders a seeded queue. Mock `roles`.

**Gate:** `cd frontend && npm run lint && npx vitest run src/pages/ReviewQueuePage.test.tsx && npx tsc --noEmit`.

---

### Task 9: Scorecard page (blind, block-submit, disqualify)

**Files:** Modify `src/api/assessments.ts`, `src/mocks/handlers.ts`; Create `src/pages/ScorecardPage.tsx`, `.test.tsx`, `.stories.tsx`.

**Interfaces — Produces:**
- api: `saveScorecardDraft(cohortId, stageId, applicationId, reviewerId, { values, disqualified })` → `PATCH /cohorts/:id/stages/:stageId/scorecards/:applicationId/:reviewerId`; `submitScorecard(...)` → `POST …/submit` (handler returns **422** if any criterion is unscored; on success sets `status:'submitted'`, `submitted_at`, and flips the matching assignment to `submitted`).
- `ScorecardPage({ cohortId, stageId, applicationId, reviewerId, modelVersionId })` — fetch the scorecard (`getScorecard`) + the bound model version (`getScoringModelVersion`). Render the **blind** submission summary (no applicant identity) + one numeric input per criterion (`0..max_points`), a live **decimal total** via `scoreCard`, a `disqualified` checkbox, and **Submit disabled until `complete`**. Autosave draft on change (debounced). Peers' scores not shown.

**Steps:**
- [ ] Add `saveScorecardDraft` + `submitScorecard` api + MSW handlers (422-on-incomplete; assignment status flip).
- [ ] Create `ScorecardPage.tsx`. Test: enter values → live total updates (`scoreCard`); Submit disabled while a criterion is blank, enabled when all filled; submit → POST fires; toggling `disqualified` is carried in the draft PATCH body; applicant identity never rendered. Story renders a partially-filled card. Mock `roles`.

**Gate:** `cd frontend && npm run lint && npx vitest run src/pages/ScorecardPage.test.tsx && npx tsc --noEmit`.

---

### Task 10: Stage leaderboard (extend SubmissionsPage)

**Files:** Modify `src/api/assessments.ts`, `src/mocks/handlers.ts`, `src/pages/SubmissionsPage.tsx`, `src/pages/SubmissionsPage.test.tsx`.

**Interfaces — Produces:**
- api: `getStageLeaderboard(cohortId, stageId)` → `GET /cohorts/:id/stages/:stageId/leaderboard` → `Array<{ application_id: string; mean: number; model_max: number; count: number; min: number; max: number; disqualified: boolean }>` (same field names as `aggregate`'s return); handler aggregates submitted scorecards per application via `lib/scoring.aggregate` and sorts by `mean` desc.
- `SubmissionsPage` gains a **Leaderboard** view (a stage selector + a ranked table: rank, masked application label, `mean`/`model_max` via `format2`, count, spread `min–max`, disqualified flag). Read `SubmissionsPage`'s existing props/structure first and add the view without breaking the funnel/list.

**Steps:**
- [ ] Add `getStageLeaderboard` api + MSW aggregation handler.
- [ ] Read `src/pages/SubmissionsPage.tsx`; add the stage-scoped leaderboard view (its own `useQuery`; respects the AppShell no-idle-fetch rule — fetch only on the leaderboard tab/selection).
- [ ] Extend `SubmissionsPage.test.tsx`: with seeded submitted scorecards, the leaderboard renders applications ranked by mean with count/spread; disqualified row flagged. Mock `../api/assessments` and `roles`.

**Gate:** `cd frontend && npm run lint && npx vitest run src/pages/SubmissionsPage.test.tsx && npx tsc --noEmit`.

---

### Task 11: Threshold-assisted decide + commit

**Files:** Modify `src/api/assessments.ts`, `src/mocks/handlers.ts`, `src/pages/SubmissionsPage.tsx`, `src/pages/SubmissionsPage.test.tsx`.

**Interfaces — Produces:**
- api: `proposeStageDecisions(cohortId, stageId, cutoff)` → `POST …/decisions/propose` → `Array<{ application_id; proposal: 'advance'|'reject' }>` (handler uses `lib/scoring.proposeDecisions` over the leaderboard); `commitStageDecisions(cohortId, stageId, decisions: { application_id; outcome }[])` → `POST …/decisions/commit` → `Decision[]` (handler builds the immutable snapshot — `model_version_id` + the application's submitted scorecards + mean — per committed decision).
- `SubmissionsPage` leaderboard gains: a cutoff `Field type="number"`, a "Propose" action → per-row proposal, a per-row outcome `<select>` (advance/reject/waitlist) seeded from the proposal (overridable), and a "Commit decisions" button → `commitStageDecisions`. On success show committed outcomes; note that `advance` follows the stage's 2c `next_stage_ids` (illustrative in MSW).

**Steps:**
- [ ] Add propose/commit api + MSW handlers (snapshot built on commit).
- [ ] Add the decide controls to the leaderboard view.
- [ ] Extend `SubmissionsPage.test.tsx`: set cutoff + Propose → rows above cutoff propose `advance`, below `reject`, disqualified `reject`; override a row to `waitlist`; Commit → POST body carries the overridden outcomes and a committed `Decision` (snapshot present) comes back. Seed XSRF.

**Gate:** `cd frontend && npm run lint && npx vitest run src/pages/SubmissionsPage.test.tsx && npx tsc --noEmit`.

---

### Task 12: Routes, program-config card, a11y, e2e, final gate

**Files:** Modify `src/app/App.tsx`, `src/pages/ProgramConfigPage.tsx`, `src/pages/ProgramConfigPage.test.tsx`, `src/tests/a11y.test.tsx`; Create `tests/e2e/fe-ui-slice2d.spec.ts`.

**Steps:**
- [ ] Routes in `App.tsx` (behind `ConsoleGate`, params → props, mirror the 2c stage routes):
  - `/programs/:programId/scoring/:modelId/edit` → `ScoringModelBuilderPage`
  - `/programs/:programId/scoring/:modelId/preview` → resolver (current draft else latest published) → `ScoringModelPreviewPage`
  - `/programs/:programId/scoring/:modelId/versions` → `ScoringModelVersionsPage`
  - `/cohorts/:cohortId/stages/:stageId/review` → `ReviewQueuePage` (reviewerId from the current-user/`/me` context wrapper)
  - `/cohorts/:cohortId/stages/:stageId/review/:applicationId` → `ScorecardPage` (resolves the stage's bound `model_version_id` from the cohort map)
- [ ] `ProgramConfigPage`: add a third **Scoring** card (list `listScoringModels(programId)`; "New scoring model" form → `createScoringModel` → `assign` to builder; rows link to builder). Extend its test.
- [ ] `a11y.test.tsx`: add axe cases for the builder, preview, versions, review queue, scorecard, and the config Scoring card (mock the relevant `fetch`/`roles`; `data-version-id`/await content before `axe.run`).
- [ ] `tests/e2e/fe-ui-slice2d.spec.ts`: author a scoring model (add criterion → publish) → bind it to a stage from cohort detail → generate assignments → reviewer scores an application and submits → manager sets cutoff, proposes, commits a decision. Scope dual "Published version" selects by id (`#scoring-binding-select`).
- [ ] **Final gate sweep:** `cd frontend && npm run lint && npx vitest run && npm run build && npm run build-storybook && npx playwright test tests/e2e/fe-ui-slice2d.spec.ts`.

---

## Acceptance Criteria → Test Mapping

| AC | Test |
|----|------|
| Scoring model drafts save; publish → immutable numbered version; Edit forks | `api/assessments.test.ts`, `ScoringModelBuilderPage.test.tsx`, `ScoringModelVersionsPage.test.tsx` |
| Additive total = Σ earned; decimal scale-2 half-up; max = Σ max_points | `lib/scoring.test.ts`, `lib/decimal.test.ts` |
| Scorecard cannot submit until every criterion has a value | `ScorecardPage.test.tsx` |
| Mean aggregation (+ count/spread); disqualification flags | `lib/scoring.test.ts`, `SubmissionsPage.test.tsx` |
| Round-robin assignment balanced & deterministic | `lib/reviewerAssignment.test.ts`, `ReviewQueuePage.test.tsx` |
| Published version binds to a stage; stored on cohort map; survives fork | `ScoringModelBindingPicker.test.tsx`, `cohorts.test.ts`, `CohortDetailPage.test.tsx` |
| Threshold proposes; manager overrides; commit records immutable snapshot | `SubmissionsPage.test.tsx` |
| Blind review masks applicant identity & hides peers' scores pre-submit | `ReviewQueuePage.test.tsx`, `ScorecardPage.test.tsx` |
| No a11y violations on new surfaces | `a11y.test.tsx` |
| End-to-end author → bind → assign → score → decide | `tests/e2e/fe-ui-slice2d.spec.ts` |

## Risks / Notes

- **Per-stage coupling (primary):** binding references the 2c pipeline's stage ids, but the FE 2c pipeline shape is MSW-only and diverged from the backend Stages engine. Bindings live on the **cohort map** (decoupled from the immutable pipeline version) to limit blast radius; reconcile stage-id references when the backend Stages engine is wired. Do not mutate published pipeline versions.
- **Reviewer identity/authz & blind enforcement:** modeled in MSW (reviewers + current reviewer id); real server-side authorization and blind enforcement are a later backend slice. Frontend masking is not security.
- **Decimal:** scale 2, half-up, integer-cents summation; model max is the constant denominator. No arbitrary-precision dep.
- **SubmissionsPage growth:** Tasks 10–11 extend an existing page — read it first and keep the funnel/list intact; if it grows unwieldy, a focused leaderboard sub-component split is acceptable (don't restructure unrelated parts).
- **Decisions table:** modeled in MSW here; when wired, decisions feed the canonical table owned by application-management — do not create a second outcomes store server-side.
