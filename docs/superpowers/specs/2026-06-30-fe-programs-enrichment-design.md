# Programs Enrichment (mockup-referenced) — Design

> Status: Implemented · Date: 2026-06-30 · Branch: `feat/fe-programs-enrichment`
> Deviation note: No deviations.
> Enriches the real Programs surface (`frontend/src/pages/Programs*`) using the
> `catalesta-ui/` Figma mockup as the visual/UX reference, wired to the live
> Programs backend. Single full-stack slice (user elected all-in-one over a
> decomposed sequence; boundary risk acknowledged).

## 1. Goal

Bring the real Programs list/detail/create pages up to the mockup's visual
fidelity (`CatalestaOrgProgramsPage`, `CatalestaCreateProgramPage`) — a richer
list with program **type**, derived **date range**, **capacity**, **submission
counts**, status pills, search, status-filter tabs, row actions, and pagination
— **without** inventing backend data or hardcoding live counts/statuses
(rule 08), and without porting MUI or the mockup's presentational code.

## 2. Authoritative contract & reference

- **Visual reference (harvest only):** `catalesta-ui/src/app/pages/catalesta/CatalestaOrgProgramsPage.tsx`,
  `CatalestaCreateProgramPage.tsx`, `CatalestaPublicProgramPage.tsx`. Layout,
  IA, fields, states, copy — re-expressed in the real design system.
- **Real contract (conform):** `frontend/src/schemas/programs.ts`,
  `frontend/src/api/programs.ts`, backend `ProgramController` / `ProgramResource`
  / `ProgramVersion`; `frontend/src/schemas/cohorts.ts` + `GET /cohorts` (already
  returns `capacity`, `starts_at`/`ends_at`/`enrollment_*`, `submissions_count`).
- **Design system:** Tailwind v4 + unified `radix-ui` + CVA, `components/ui/`,
  `src/styles/tokens.css`. No MUI. No hardcoded hex (mockup's `#1bbcb4`/`#f26b3a`
  → token classes). No second design system (rule 08).

## 3. Decisions (locked)

- **Only one new backend column:** `Program.type`. Everything else the mockup
  shows (dates, capacity, submissions, row status) is **read-only derivation
  over already-wired cohort data** — verified present on the Cohort aggregate.
- **`type`** = nullable enum `accelerator | incubator | hackathon | fellowship`.
  Nullable because existing programs have none; UI renders no badge when null.
- **`type` joins the published version snapshot** (Program is versioned via
  `ProgramVersion`). Editing `type` after publish mutates the live program and
  is audited — it does **not** create a new version (same semantics as
  name/description, per `ProgramController::update`).
- **Derived summary is computed on the frontend** (memoized selector), not a new
  backend endpoint: the list uses one `GET /cohorts` call grouped by
  `program_id`; the detail page reuses `ProgramCohortsSection` data. Avoids
  touching the Program controller/resource for read-only aggregation.
- **Real status vocab only.** Filter tabs = `All | Draft | Published | Archived |
  Closed` (Program statuses). The mockup's Active/Live/Open/Completed are cohort
  states; the list's per-row "activity" is shown as the program's *active cohort*
  status (derived), labelled as such — never as a program status.
- **Harvest the field, not the flow.** Create gains a `type` selector on the
  existing create form. The mockup's 782-line multi-step Create wizard is a
  separate UX and is out of scope.

## 4. Backend changes (the one schema change)

1. Migration `add_type_to_programs_table` — `string('type')->nullable()` (store
   enum value); reversible `down()` dropping the column.
2. `ProgramType` PHP enum (`accelerator|incubator|hackathon|fellowship`).
3. `Program` model: cast `type` → `ProgramType`; add to `$fillable`.
4. Validation: `ProgramController::store` + `::update` accept optional `type`
   via `Rule::enum(ProgramType::class)` (nullable).
5. `ProgramResource::toArray` emits `type` (string value or null).
6. `ProgramVersion` snapshot includes `type` (publish records it). Add/extend a
   test proving the published version captures `type`.
7. `clone` carries `type` into the new draft.

## 5. Frontend: schema + api

- `programSchema` gains `type: z.enum([...]).nullable()`.
- `createProgram` / `updateProgram` / `cloneProgram` accept optional `type` and
  send it in the payload (omit when undefined; explicit `null` clears on update).
- No new api module; derived summary uses the existing `listCohorts` (`GET
  /cohorts`) and `ProgramCohortsSection`'s query.

## 6. Derivation layer (FE, read-only)

A pure, tested helper `deriveProgramSummary(cohorts: Cohort[])`:
- `dateRange`: `min(starts_at)` → `max(ends_at)` across the program's cohorts
  (ignore nulls; `null` range when none).
- `capacity`: sum of non-null cohort `capacity` (null when none).
- `submissions`: sum of cohort `submissions_count` (0 when none/absent).
- `activeCohortStatus`: status of the latest `open` cohort, else most recent
  cohort's status, else null. Labelled "Active cohort: <status>".
- Cohorts grouped by `program_id` for the list; passed directly on detail.
- Pure function, unit-tested; no hardcoded values; absent data → "—".

## 7. Frontend: pages

### ProgramsPage (list) — primary re-skin
- Page header: org name + program count + "Create Program" action.
- **Status-filter tabs** (real statuses) — client-side filter.
- **Search** — client-side, over program name.
- **Table** columns: Name (+ type badge), Cohorts (count), Submissions
  (derived), Capacity (derived), Status (program status pill), Active cohort
  (derived status), Date range (derived), Actions (View → detail, Edit →
  inline/detail). Responsive: horizontal scroll on narrow widths (mockup
  pattern), readable stack acceptable.
- **Pagination** — client-side (page size constant), with accessible controls.
- Create stays as the existing inline form **plus** a `type` selector.
- All rule-08 states: loading (Spinner), empty (StateBlock), error (retry),
  validation (Banner), disabled submit, permission-aware actions, accessible
  names/descriptions, keyboard operable, focus management, responsive.

### ProgramDetailPage
- Add a **type badge** by the heading and a **derived summary strip**
  (date range · capacity · submissions · active-cohort status) sourced from
  `ProgramCohortsSection`'s cohorts. Keep existing edit/clone/publish + the
  cohorts section. Edit form gains the `type` selector.

### Create
- The existing create form (`ProgramsPage` inline) gains a `type` `<select>`
  (radix Select via `components/ui`), optional.

## 8. Authorization, tenancy, versioning, a11y

- Server-side authorization unchanged; FE visibility is not authorization
  (rule 08 / CLAUDE.md). Cross-tenant program `{id}` → neutral 404 preserved.
- `type` is part of the immutable published version snapshot.
- Status conveyed by text label, never colour alone. Type badges have text.
- Branding/token contrast preserved; no colour-only signalling.

## 9. Testing

- **Backend:** migration up/down; `ProgramType` enum; create/update/clone with
  and without `type`; 422 on invalid `type`; `ProgramResource` emits `type`;
  published `ProgramVersion` snapshot captures `type`; cross-tenant 404 intact;
  full Programs suite green (`php artisan test --filter=Program`), pint/phpstan/
  deptrac, OpenAPI regenerated + Spectral.
- **Frontend:** `deriveProgramSummary` unit tests (ranges, nulls, sums, active
  status); `ProgramsPage.test.tsx` (list render, type badge, derived columns,
  status-filter tabs, search, pagination, all states); `ProgramDetailPage.test`
  (type badge + summary strip); create-with-type; MSW handlers + cohort fixtures
  with dates/capacity/counts; `.stories.tsx` for list + detail; typecheck + lint
  + vitest green.

## 10. Out of scope (deferred / explicitly excluded)

- The mockup's invented status vocab (Active/Live/Open/Completed) as *program*
  statuses.
- The multi-step Create wizard flow (`CatalestaCreateProgramPage`'s 782-line UX).
- Type/Status/Year filter *selects* with no backing data (status-tab filter only).
- Editing Cohort `capacity` here (capacity already exists; this slice only reads
  it). A capacity-editing surface is a separate Cohort story.
- Backend-computed program summary endpoint (chosen FE-derived; revisit if the
  list grows beyond one `/cohorts` call).
- `CatalestaPublicProgramPage` (public marketing variant) — separate surface.
