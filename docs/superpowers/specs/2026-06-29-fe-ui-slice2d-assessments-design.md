# FE UI Slice 2d — Assessments (Score & Decide) Design Spec

> Status: Approved (brainstorming) · Date: 2026-06-29 · Track: Frontend UI slices (UI-first on MSW)
> Predecessors: 2b Forms (#61), 2c Stages (#62). Successor of this spec: an implementation plan via `writing-plans`.

## 1. Goal

Build the **Selection-MVP "Score & Decide" surface** for Catalesta: author versioned, immutable
**scoring models**; assign reviewers (blind); capture per-reviewer **scorecards**; aggregate to a
per-stage **leaderboard**; and **decide** (advance / reject / waitlist) with an immutable snapshot.
UI-first on shadcn/Tailwind + MSW, mirroring 2b/2c conventions.

## 2. Authoritative context & constraints

- Backend contract: `docs/plan/build-specs/10-assessment-engine.md` — templates, criteria, rubrics,
  evidence, evaluator assignment, blind review, decimal scoring, aggregation, disqualification, audit;
  **published versions immutable; no arbitrary code in rules**. The **decisions table is owned by
  `08-application-management.md`** — this slice does not create a second outcomes table; it records
  decisions into one canonical decisions concept (modeled in MSW here).
- Scoring rules (root `CLAUDE.md` § Versioning and Historical Integrity): **decimal arithmetic**;
  defined precision/scale/rounding/weight-normalization/missing-value; immutable published models +
  exact version references on submissions; reproducible historical results; no `eval`.
- Backend reality (`docs/status/implementation-status.md`, 2026-06-29): **Assessments is a 1-file
  scaffold with no routes**. This slice is **UI-first on MSW**, consistent with 2b/2c.
- Conventions inherited from 2b/2c: snake_case schemas; pages take props (no `useParams`); real
  `src/api/` clients with MSW intercept; unit tests mock `fetch` via `jsonResponse`; AppShell
  no-idle-fetch invariant; reuse generic `VersionHistoryList`/`VersionCompare`; clone the
  binding-picker pattern; **no new shadcn `ui/` primitives**; debounced autosave on builders;
  publish snapshots an immutable numbered version, Edit forks a draft.

## 3. Locked design decisions

| Area | Decision |
|---|---|
| **Scoring math** | **Additive points.** Each criterion contributes earned points up to a per-criterion `max_points`; **the max distribution IS the weight** (no separate normalization). Model score = Σ earned; model max = Σ `max_points`. |
| **Precision** | **Decimal, scale 2, rounding half-up.** Implemented with an in-repo fixed-point helper (`lib/decimal.ts`) — values held as integer-scaled, sums exact, one half-up rounding on the mean. No new dependency. |
| **Missing value** | A scorecard **cannot be submitted** until every criterion has a value. Drafts may be partial; a *submitted* scorecard is always complete. |
| **Disqualification** | A scorecard carries a `disqualified` boolean, independent of points. Any submitted reviewer disqualification **flags the application** in aggregation regardless of mean. |
| **Cross-reviewer aggregation** | Application score @stage = **mean of submitted reviewer totals** (decimal, half-up), with reviewer count + spread (min/max). Denominator (model max) is constant across reviewers. |
| **Assignment** | **Auto round-robin**, per stage: a panel of reviewer ids + `per_app` count → balanced assignment of reviewers to the stage's applications (pure function). |
| **Blind review** | Reviewers see the submission with **applicant identity masked**, and **cannot see peers' scores** until their own scorecard is submitted. (UI masking in this slice; server enforcement is later.) |
| **Decide** | **Threshold-assisted**, per stage: manager sets a cutoff → system proposes `advance`/`reject` per application (disqualified → `reject`) → manager overrides per row (may set `waitlist`) → commits. Each decision keeps an **immutable snapshot** (model version id + the scorecards + computed mean at decision time). `advance` follows the stage's 2c `next_stage_ids` routing. |
| **Binding** | **Per stage.** A published scoring-model version binds to a specific stage of the cohort's bound 2c pipeline. Stored on the **cohort** as a `stage_scoring_model_version_ids` map (`stage_id → version_id`) — **not** by mutating the immutable pipeline version, so bindings survive pipeline forks. |
| **Surfaces** | **Hybrid by actor.** Manager leaderboard + decide **extend `SubmissionsPage`** (stage-scoped). Reviewer **blind queue + scorecard are new pages**. |

## 4. Data model (`src/schemas/assessments.ts`, snake_case)

- `ScoringCriterion`: `{ criterion_id, label, max_points (decimal>0), descriptors: string[] | null }`
  (`descriptors` = optional free-text guidance lines for reviewers — e.g. what high vs low points
  mean for this criterion; declarative data only, no levels-structure and no expressions).
- `ScoringModel`: `{ model_id, program_id, name, latest_version, published_version_ids[], current_draft_version_id, created_at }`.
- `ScoringModelVersion`: `{ version_id, model_id, version, status: 'draft'|'published', criteria: ScoringCriterion[], created_at, published_at }`.
- `ReviewerAssignment`: `{ assignment_id, cohort_id, stage_id, application_id, reviewer_id, status: 'pending'|'submitted' }`.
- `Scorecard`: `{ scorecard_id, cohort_id, stage_id, application_id, reviewer_id, model_version_id, values: Record<criterion_id, number>, disqualified: boolean, status: 'draft'|'submitted', submitted_at: string|null }`.
- `Decision`: `{ decision_id, cohort_id, stage_id, application_id, outcome: 'advance'|'reject'|'waitlist', snapshot: { model_version_id, scorecards: Scorecard[], mean: string, decided_at }, decided_by }`.
- Errors mirror `stages.ts`: `GetScoringModelError` / `SaveScoringModelError` / `PublishScoringModelError` (+ assignment/scorecard/decision error codes).
- **Cohort delta** (`src/schemas/cohorts.ts`): add `stage_scoring_model_version_ids: z.record(z.string(), z.string()).nullable().optional()`.

## 5. Pure engines (`src/lib/`, no fetch/React)

- `decimal.ts` — fixed-point scale-2 helpers: `add`, `sumPoints`, `mean(values: number[]) → string` (half-up to 2 dp), `cmp`. One runnable self-check.
- `scoring.ts` —
  - `scoreCard(criteria, values) → { earned: string, max: string, complete: boolean }`
  - `aggregate(model, scorecards) → { mean: string, max: string, count: number, min: string, max_total: string, disqualified: boolean }`
  - `proposeDecisions(rows, cutoff) → Array<{ application_id, proposal: 'advance'|'reject' }>` (disqualified → `reject`).
- `reviewerAssignment.ts` — `assign(applicationIds, panelReviewerIds, perApp) → Array<{ application_id, reviewer_ids: string[] }>`: deterministic, balanced round-robin.

## 6. API + MSW (`src/api/assessments.ts`, handlers on a module-mutable store)

- Models: `listScoringModels(programId)`, `getScoringModel`, `getScoringModelVersion`, `createScoringModel(programId, name)`, `saveScoringModelDraft(modelId, criteria)`, `publishScoringModel(modelId)`, `forkScoringModelDraft(modelId, fromVersionId)`, `listScoringModelVersions(modelId)`.
- Assignment: `listAssignments(cohortId, stageId)`, `generateAssignments(cohortId, stageId, { reviewer_ids, per_app })` (server applies round-robin).
- Scorecards: `getScorecard(cohortId, stageId, applicationId, reviewerId)`, `saveScorecardDraft(...)`, `submitScorecard(...)` (422 if incomplete).
- Leaderboard/decide: `getStageLeaderboard(cohortId, stageId)` (aggregated rows), `proposeStageDecisions(cohortId, stageId, cutoff)`, `commitStageDecisions(cohortId, stageId, decisions[])`.
- Cohort binding (`src/api/cohorts.ts`): `bindCohortStageScoringModel(cohortId, stageId, versionId)` → updates the cohort map.
- Publish clones draft → immutable numbered version; fork creates a draft from a version (mirror stages).

## 7. Surfaces

Authoring (mirror 2c builder/versions/preview/binding verbatim):
- `ScoringModelBuilderPage` + `ScoringModelCanvas` (criteria list: add/reorder/remove) + `ScoringCriterionInspector` (label, `max_points`, optional descriptors). Draft autosave; publish; read-only + Edit-forks when viewing published.
- `ScoringModelVersionsPage` — reuse `VersionHistoryList` + `VersionCompare`; lines = ordered criterion descriptions (`"1. Team — max 20"`).
- `ScoringModelPreviewPage` — read-only criteria + max breakdown.
- `ScoringModelBindingPicker` — pick a **stage** of the cohort's bound pipeline → pick a **published** model version → bind (writes the cohort map). Clone of the 2b/2c picker; published-only.

Reviewing (new, blind, reviewer actor):
- `ReviewQueuePage({ cohortId, stageId, reviewerId })` — assigned applications (identity masked), per-row status (pending/submitted) → link to scorecard.
- `ScorecardPage({ cohortId, stageId, applicationId, reviewerId })` — blind submission view + per-criterion inputs (0..max), live decimal total, `disqualify` toggle, **Submit disabled until complete**; peers' scores hidden until submitted.

Deciding (manager, extends existing):
- `SubmissionsPage` gains a **stage-scoped Leaderboard** view: ranked by mean (+ count/spread, disqualified flag); a cutoff input → threshold proposals → per-row override (advance/reject/waitlist) → **Commit decisions** (snapshot kept; advance follows 2c routing).
- `ProgramConfigPage` gains a third **Scoring** card (list models, create → builder).

## 8. Routes (`src/app/App.tsx`, behind `ConsoleGate`, props from params)

- `/programs/:programId/scoring/:modelId/edit|preview|versions`
- `/cohorts/:cohortId/stages/:stageId/review` (queue) · `/cohorts/:cohortId/stages/:stageId/review/:applicationId` (scorecard)
- Leaderboard reachable from `SubmissionsPage` (stage selector) — no standalone route required.

## 9. Actors & permissions

- **Program manager / reviewer-admin:** author models, bind per stage, generate assignments, view leaderboard, decide.
- **Reviewer / evaluator:** only their blind queue + scorecards.
- Frontend visibility is **not** authorization (server-side enforcement is a later, backend slice). MSW models reviewers + the current reviewer id.

## 10. Task breakdown (~12 tasks; multi-PR)

1. Data layer — `schemas/assessments.ts` + `api/assessments.ts` + MSW + tests + handler guard.
2. Pure engines — `lib/decimal.ts`, `lib/scoring.ts`, `lib/reviewerAssignment.ts` + unit tests.
3. Scoring-model builder shell + `ScoringModelCanvas` (criteria add/reorder/remove, autosave, publish/fork).
4. `ScoringCriterionInspector` (label / max_points / descriptors) wired through the draft.
5. `ScoringModelPreviewPage` (+ test/story).
6. `ScoringModelVersionsPage` — history + compare (reuse generics).
7. Per-stage binding — cohort schema/api delta + `ScoringModelBindingPicker` + `CohortDetailPage` wiring (per-stage rows).
8. Reviewer assignment — `generateAssignments` (round-robin) + `ReviewQueuePage` (blind).
9. `ScorecardPage` (blind, decimal total, block-submit, disqualify).
10. Stage leaderboard — extend `SubmissionsPage` + `getStageLeaderboard` aggregation.
11. Threshold-assisted decide — propose/override/commit + immutable snapshot + decisions store.
12. Routes + `ProgramConfigPage` Scoring card + a11y cases + e2e (author → bind → assign → score → decide) + final gate sweep.

## 11. Acceptance criteria → test mapping

| AC | Test |
|---|---|
| Scoring model drafts save; publish → immutable numbered version; Edit forks | `api/assessments.test.ts`, builder/versions tests |
| Additive total = Σ earned; decimal scale-2 half-up; max = Σ max_points | `lib/scoring.test.ts`, `lib/decimal.test.ts` |
| Scorecard cannot submit until every criterion has a value | `ScorecardPage` test |
| Mean aggregation across reviewers (+ count/spread); disqualification flags | `lib/scoring.test.ts`, leaderboard test |
| Round-robin assignment is balanced & deterministic | `lib/reviewerAssignment.test.ts` |
| A published model version binds to a stage; stored on cohort map; survives fork | `ScoringModelBindingPicker.test.tsx`, `cohorts.test.ts` |
| Threshold proposes; manager overrides; commit records immutable snapshot; advance routes | leaderboard/decide test |
| Blind review masks applicant identity & hides peers' scores pre-submit | `ScorecardPage`/`ReviewQueuePage` tests |
| No a11y violations on new surfaces | `a11y.test.tsx` |
| End-to-end author → bind → assign → score → decide | `tests/e2e/fe-ui-slice2d.spec.ts` |

## 12. Risks / assumptions / future

- **Per-stage coupling (primary risk):** scoring binds to the 2c pipeline's stages, but the FE 2c
  pipeline/version shape is **MSW-only and diverged from the backend Stages engine**. Mitigation:
  store bindings on the **cohort map** (decoupled from the immutable pipeline version). When the
  backend Stages engine is wired, the cohort-map binding and the stage-id references must be
  reconciled with the real stage identifiers. Recorded as the explicit integration debt for 2d.
- **Reviewer identity / authz:** modeled in MSW (reviewers + current reviewer id); real authorization
  and server-side blind enforcement are a later backend slice.
- **Decimal:** scale = 2, rounding = half-up, model max is the constant denominator; points may be
  authored as integer or 2-dp decimal. No arbitrary precision needed (bounded sums + one mean).
- **No arbitrary code in rules:** criteria + descriptors are declarative data only.
- **Scope boundary:** scoring/decide is per stage for the cohort's *current* pipeline; cross-stage
  analytics, reviewer calibration, and weighted (non-additive) models are out of scope.
