# FE UI — Slice 2: Operator Setup → Intake — Design

**Date:** 2026-06-27
**Type:** Slice design (child of `2026-06-26-fe-ui-rebuild-program-map-design.md`)
**Status:** Draft — pending user review
**Depends on:** Slice 0 (Foundation) — done. Independent of Slices 1, 3.

## Goal

Build the operator's "stand up a cohort and open it for intake" path on
shadcn/ui + Tailwind, UI-first against MSW fixtures: re-skin the existing
program/cohort screens and add the cohort setup wizard, the form authoring
subsystem (builder + conditional logic + preview + binding + versioning), the
stage engine, the program configuration hub, and a templates gallery.

## Locked decisions (from brainstorming)

1. **Decomposition:** one spec, three sub-slices built in order — **2a** cohort
   lifecycle, **2b** forms, **2c** stages & config.
2. **Form builder:** full, including **conditional visibility logic** (the
   preview/apply renderer honors it).
3. **Versioning:** full lifecycle (draft → publish → immutable version), version
   history list, **and side-by-side version compare**.
4. **Templates:** functional clone ("Use template" → editable draft); no
   save-as-template.
5. **Config hub:** all 5 tabs present; **Stages + Forms** fully wired; **Roles /
   Workflows / Notifications** render `ComingSoonPage` placeholder panels until
   their slices land.

## Global Constraints

Inherited from the program map and Slices 0–1; every task implicitly includes
these:

- **Design system:** shadcn/ui + Tailwind, zinc + indigo. Use existing theme
  tokens (`bg-popover`, `bg-accent`, `border-input`, `text-muted-foreground`,
  etc.) and the `cn()` helper. No `ds-*` CSS.
- **UI-first:** screens call **real `src/api/` client functions**; MSW
  intercepts `fetch` and returns fixtures typed against `src/schemas/` Zod
  schemas. Slice 9 flips `VITE_USE_MOCKS=false` with no screen changes.
- **MSW state:** module-level mutable fixtures persist edits across navigation
  within a session; tests reset state in `afterEach`.
- **No backend / auth / tenancy changes.** `X-Organization-Id` header + Sanctum
  session auth untouched.
- **AppShell no-idle-fetch invariant:** no component rendered by AppShell may
  fetch at mount. Lazy-fetch on tab activation / route entry. Tests that render
  AppShell stub `listMyRoles` (`vi.mock('../api/roles', …)`).
- **a11y/RTL/contrast are first-class:** Arabic/Tajawal RTL, dark mode,
  keyboard operability; new pages join the `a11y.test.tsx` axe suite; contrast
  verified arithmetically in the existing `contrast.test.ts`.
- **`aria-label` caution:** an explicit `aria-label` overrides visible text as
  the accessible name — do not add one where visible text should be the name.

## Architecture & data layer

Same three-layer pattern as Slices 0–1: **schemas → api clients → MSW
handlers**, with screens calling real client signatures.

### New Zod schemas (`src/schemas/`)

**`forms.ts`**
- `FieldType` — the 7 existing `ApplyField` primitives: `short_text`,
  `long_text`, `single_select`, `multi_select`, `date`, `file_upload`,
  `consent`. The builder, `FormRenderer`, and `ApplyField` all agree on this
  union (a drift fails typecheck).
- `FieldValidation` — `{ minLength?, maxLength?, pattern?, minSelections?, maxSelections? }`
  (text fields use min/maxLength + pattern; `multi_select` uses
  min/maxSelections).
- `VisibilityRule` — `{ match: 'all' | 'any', conditions: VisibilityCondition[] }`.
- `VisibilityCondition` — `{ fieldId: string, operator: 'equals' | 'not_equals' | 'includes' | 'is_empty', value: string | null }`.
- `FormField` — `{ id, type: FieldType, label, help?, required: boolean, options?: string[], validation?: FieldValidation, visibility?: VisibilityRule }`.
- `FormVersion` — `{ id, formId, version: number, status: 'draft' | 'published', fields: FormField[], createdAt, publishedAt: string | null }`.
- `Form` — `{ id, name, description?, latestVersion: number, publishedVersionIds: string[], currentDraftVersionId: string | null }`.

**`stages.ts`**
- `Gate` — `{ id, kind: 'form_submitted' | 'score_threshold' | 'manual_approval' | 'date_reached', label, config: Record<string, unknown> }`.
- `Stage` — `{ id, name, type: 'application' | 'screening' | 'evaluation' | 'decision' | 'delivery', order: number, entryGates: Gate[], exitGates: Gate[] }`.
- `StageVersion` — `{ id, pipelineId, version: number, status: 'draft' | 'published', stages: Stage[], createdAt, publishedAt: string | null }`.
- `StagePipeline` — `{ id, name, latestVersion: number, publishedVersionIds: string[], currentDraftVersionId: string | null }`.
  (Stages are versioned as an immutable **pipeline** snapshot — the unit the
  inventory calls "versioned stages (immutable)".)

**`templates.ts`**
- `Template` — `{ id, kind: 'program' | 'stage' | 'form', name, description, payload: unknown }` (payload is a `FormVersion.fields`, a `StageVersion.stages`, or a program preset, shaped per `kind`).

**`cohorts.ts` (extend existing)**
- Add `enrollmentWindow?: { opensAt: string, closesAt: string, capacity: number | null }`.
- Add `boundFormVersionId?: string | null`.
- Add `stagePipelineVersionId?: string | null`.
- Add `setupStatus?: 'draft' | 'configuring' | 'open'` to drive the wizard +
  "Open" transition.

### New api clients (`src/api/`)

Mirror real REST shapes; MSW intercepts. Exact endpoint paths finalized in the
plan, following the existing `api/cohorts.ts` conventions.

- **`api/forms.ts`** — `listForms`, `getForm`, `getFormVersion`, `createForm`,
  `saveDraft(formId, fields)`, `publishForm(formId)`, `forkDraft(formId, fromVersionId)`,
  `listFormVersions(formId)`.
- **`api/stages.ts`** — `getPipeline`, `getPipelineVersion`, `saveDraft`,
  `publishPipeline`, `forkDraft`, `listPipelineVersions`.
- **`api/templates.ts`** — `listTemplates(kind?)`, `getTemplate`,
  `useTemplate(templateId)` → returns a new editable draft (form/pipeline) seeded
  from the template payload.
- **`api/cohorts.ts` (extend)** — `setEnrollmentWindow`, `bindForm(cohortId, formVersionId)`,
  `attachPipeline(cohortId, pipelineVersionId)`, `openCohort(cohortId)`.

### MSW handlers (`src/mocks/handlers.ts`, extend)

Module-mutable fixtures for forms + versions, pipelines + versions, templates,
and cohort config. Edits persist within a session. Seed data: ≥2 forms (one
published with a couple of versions, one draft), a couple of stage pipelines,
and a handful of templates per kind. Tests reset via `afterEach`.

### Versioning rule (cross-cutting, forms + stages)

- **Publish** snapshots the current draft into an immutable, numbered version
  (`status: 'published'`, `publishedAt` set).
- Published versions are **read-only**; "Edit" calls `forkDraft` to create a new
  draft from a chosen version.
- A read-only **version-history list** per form/pipeline.
- A **side-by-side version compare** (field-level / stage-level diff between two
  selected versions).
- **Binding** (form → cohort) and **attach** (pipeline → cohort) only offer
  **published** versions.
- The versioning UI is **generic**: `VersionHistoryList` and `VersionCompare`
  components are parameterized over an item-renderer, built in 2b for forms and
  reused in 2c for stages (DRY).

### Routing (`src/app/App.tsx`, extend)

New operator routes (final paths set in the plan):

- `/programs/:programId` — Program detail (re-skin) — entry points to cohorts +
  config hub.
- `/programs/:programId/config` — Program configuration hub (tabbed).
- `/programs/:programId/cohorts/:cohortId` — Cohort detail (re-skin).
- `/programs/:programId/cohorts/:cohortId/setup` — Cohort setup wizard.
- `/programs/:programId/cohorts/:cohortId/enrollment` — Enrollment window editor.
- `/forms/:formId/edit` — Form builder; `/forms/:formId/preview` — Form preview;
  `/forms/:formId/versions` — version history + compare.
- `/pipelines/:pipelineId/edit` — Stage-engine config;
  `/pipelines/:pipelineId/versions` — stage version history + compare.
- `/templates` — Templates gallery.

---

## Sub-slice 2a — Cohort lifecycle (~5 screens)

The operator's "stand up a cohort" path: three re-skins + two new screens.

| Screen | Build | Behavior |
|--------|-------|----------|
| Program detail | re-skin `ProgramDetailPage` | View/edit/clone/publish program in shadcn; entry points to the cohorts section and config hub. |
| Program cohorts section | re-skin `ProgramCohortsSection` | List/create cohorts under the program; row → cohort detail; "New cohort" → setup wizard. |
| Cohort detail | re-skin `CohortDetailPage` | Edit metadata; shows enrollment-window summary, bound-form, and stage-pipeline status with deep links to edit each. |
| Cohort setup wizard | NEW | shadcn stepper: **Create → Attach form → Attach stages → Set dates → Review → Open.** Steps revisitable; draft persists via MSW (`setupStatus`); "Open" flips the cohort to *open*. Steps 2–3 **embed** the 2b form-binding and 2c stage-attach surfaces rather than reimplementing them. |
| Enrollment window editor | NEW | Open/close window: `opensAt`, `closesAt`, optional `capacity`. Standalone; reachable from cohort detail and wizard step 4. Validates `closesAt` > `opensAt`. |

**Cross-screen:** loading/empty/error via `StateBlock`; mutations use react-query
optimistic update + invalidation; publish/open are confirmed actions.

**Build-order note (forward dependency):** 2a ships before 2b/2c, so the
wizard's **Attach form** (step 2) and **Attach stages** (step 3) initially render
a lightweight "configure after the form/pipeline exists" panel that deep-links to
the standalone (then-future) binding and stage-config routes. When 2b and 2c
land, they wire their binding / stage-attach surfaces **into** those wizard steps
(the wizard owns the step shell; 2b/2c fill the step body). This keeps each
sub-slice independently shippable and testable.

## Sub-slice 2b — Forms (~3 screens + versioning surfaces)

The form authoring subsystem — the heaviest part of the slice.

### Form builder (NEW) — three-pane

- **Palette (left):** the 7 `ApplyField` field types; click to append to canvas.
- **Canvas (center):** ordered field list; select to edit; **reorder via
  keyboard-accessible move up/down controls** (default — no new drag-and-drop
  dependency; drag is a deferred enhancement). Add/remove fields.
- **Inspector (right):** selected field config — label, help, required, options
  (selects), **validation** (text: minLength/maxLength/pattern; multi_select:
  min/maxSelections), and the **conditional-logic editor**: `match all|any` plus
  a list of conditions `{ trigger field, operator, value }`. **Only fields
  earlier in order are selectable as triggers**, which structurally prevents
  cycles.
- Draft **autosaves** to MSW (`saveDraft`); **Publish** snapshots an immutable
  numbered version.

### Form preview (NEW)

- Read-only applicant render via a **new `FormRenderer`** component that composes
  `ApplyField`s and **live-evaluates `VisibilityRule`** as the user fills (fields
  show/hide in real time).
- **LTR/RTL toggle** so Arabic/Tajawal layout is verified here.
- `ApplyField` is unchanged; `FormRenderer` is the new reusable piece (Slice 4's
  `ApplyPage` can later adopt it). Reachable from the builder ("Preview").

### Form binding (NEW)

- Attach a **published** form version to a cohort; picker lists published
  versions only, shows the currently-bound version, warns on rebind. Standalone
  + embedded as wizard step 2.

### Versioning surfaces

- Per-form read-only **`VersionHistoryList`** + **`VersionCompare`** (field-level
  diff between two versions). Built generic for reuse in 2c.

## Sub-slice 2c — Stages & config (~4 screens)

| Screen | Build | Behavior |
|--------|-------|----------|
| Stage-engine config | NEW | Configure a cohort's ordered **stage pipeline**: add/reorder stages (keyboard-accessible move up/down), set each stage's **entry/exit gates** (`Gate` cards: kind + config). "Start from template" seeds a preset pipeline. Draft autosaves; publish snapshots an immutable version. |
| Stages list / version view | NEW | Versioned, immutable pipeline: stage list + per-pipeline **`VersionHistoryList`** and **`VersionCompare`** — the *same* generic components from 2b, parameterized for stages. |
| Program configuration hub | NEW | Tabbed shell. **Stages** + **Forms** tabs fully wired to the 2c/2b screens. **Roles / Workflows / Notifications** tabs render `ComingSoonPage` placeholder panels. Tabs fetch lazily on activation (no idle fetch at shell mount). |
| Templates gallery | NEW | Browse program/stage/form templates (filter by kind), detail drawer, **Use template → `useTemplate` → editable draft**. Also surfaced as "start from template" in the cohort wizard and form builder. No save-as-template. |

## Cross-cutting concerns

- **Testing per sub-slice:** Vitest + Testing Library unit tests per screen;
  Storybook stories for the builder, `FormRenderer`, `VersionCompare`, and the
  config hub; **Playwright e2e** for the spine flows — cohort setup wizard
  end-to-end, form *build → publish → bind*, stage pipeline config. Gates
  (vitest / build / build-storybook / e2e) green to close each sub-slice.
- **MSW:** fixtures typed against the new Zod schemas; persist within a session;
  `afterEach` reset.
- **AppShell no-idle-fetch invariant:** config-hub tabs fetch on tab activation;
  builder/preview/wizard fetch on route entry; nothing new fetches at shell
  mount; AppShell-rendering tests stub `listMyRoles`.
- **RTL & a11y:** Arabic/Tajawal verified via the preview LTR/RTL toggle and
  stories; keyboard-accessible reorder, wizard step focus management,
  dialog/drawer aria, `VersionCompare` as an accessible field-level diff. New
  pages added to `a11y.test.tsx`; contrast covered by the existing arithmetic
  suite.
- **States:** `StateBlock` for loading/empty/error throughout; react-query
  optimistic updates + invalidation; confirm dialogs for publish / open / rebind.

## Out of scope (deferred)

- Drag-and-drop field/stage reordering (move up/down ships now; drag later).
- Save-as-template round-trip.
- Roles / Workflows tab content (Slice 3); Notifications config content (Slice 1).
- Real API wiring (Slice 9 — flip `VITE_USE_MOCKS`).
- Applicant-side apply flow (Slice 4 — though `FormRenderer` is built here for
  later reuse).
