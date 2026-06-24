# FE-2: Cohorts Management UI — Design

> Date: 2026-06-24 · Status: Approved (brainstorming) · Scope: third slice of the
> "complete the Phase-1a frontend end-to-end" initiative (after FE-0 router, FE-1 programs).

## Context

FE-1 added the program detail/edit/clone surfaces. Cohorts are created **under a
program** and are the unit applicants apply to. The backend
(`backend/app/Modules/Cohorts/Http/CohortController.php`) exposes:

| Action | Route | Notes |
|---|---|---|
| index | `GET /cohorts` | tenant-wide; each cohort carries `program_id`, `status`, `submissions_count`. Already wired in FE (`listCohorts`, used by Home). |
| store | `POST /programs/{program}/cohorts` | creates under a program; **always starts `draft`** (controller forces it). |
| show | `GET /cohorts/{id}` | **not yet wired in FE** |
| update | `PATCH /cohorts/{id}` | **not yet wired**; name/status/capacity/dates/timeline |

The frontend already has `listCohorts` + `cohortSchema` and a wired
`/cohorts/:cohortId/submissions` surface.

## Backend gap — open/close + form-binding NOT wired (deferred, flagged)

The `OpenCohort` application service (binds the published form version, sets the
enrollment window, runs the `EntitlementService` check, audits `cohort.opened`)
**exists but is invoked nowhere** — no controller or route references it (verified
by grep). `store()` forces `draft`; `update()` merely `fill()`s validated fields.
The only way to reach `status: 'open'` via the API is a raw `PATCH {status:'open'}`,
which would flip the column **without** binding a form, the entitlement check, or
the `cohort.opened` audit — leaving the public apply page form-less. That is a
product-incorrect "open."

**Decision (user-approved):** FE-2 ships **no** open/close / status-transition /
form-binding controls. It builds only the cleanly-backed surface: list, create
(draft), show, and edit of metadata. **Backend follow-up:** wire `OpenCohort` (and
a `CloseCohort`) to operator endpoints, including form-version selection, before a
later FE slice adds the open/close UI.

## Architecture

### Routes & navigation
- **`ProgramDetailPage` (FE-1) gains a "Cohorts" section** under the program
  actions:
  - lists this program's cohorts by reusing the tenant `useQuery(['cohorts'], listCohorts)`
    and filtering client-side to `cohort.program_id === programId`;
  - each cohort's name links to `/cohorts/{cohort.id}`;
  - a lean **Create cohort** form (name only) → `createCohort(programId, { name })`;
    on success invalidates `['cohorts']`.
- **New gated console route `path="/cohorts/:cohortId"` → `CohortDetailRoute`**
  wrapper (reads `useParams().cohortId`, wraps the unchanged render-prop
  `ConsoleGate`, passes `cohortId` to the page). Sibling to the existing
  `/cohorts/:cohortId/submissions`.

### Units
- `api/cohorts.ts` (modify): add `getCohort`, `createCohort`, `updateCohort`.
- `schemas/cohorts.ts` (modify): add `cohortResponseSchema` and three error
  classes (`GetCohortError`, `CreateCohortError`, `UpdateCohortError`).
- `pages/CohortDetailPage.tsx` (create): detail + inline metadata edit.
- `pages/ProgramDetailPage.tsx` (modify): add the Cohorts section + create form.
- `app/App.tsx` (modify): add the `:cohortId` route + thin wrapper.

### API client additions (exact shapes)
- `getCohort(id: string): Promise<Cohort>` — `GET /cohorts/{id}` (plain `fetch`,
  `credentials:'include'`). `200`→Cohort; `401→UNAUTHENTICATED`; `404→NOT_FOUND`;
  else `UNKNOWN`. Uses `cohortResponseSchema`.
- `createCohort(programId: string, input: { name: string }): Promise<Cohort>` —
  `POST /programs/{programId}/cohorts` via `csrfFetch`, body `{ name }`. `201`→Cohort;
  `401→UNAUTHENTICATED`; `403→FORBIDDEN` (foreign/missing program or no permission);
  `422→VALIDATION` (first field message); else `UNKNOWN`.
- `updateCohort(id: string, input: { name?: string; capacity?: number | null; enrollment_opens_at?: string | null; enrollment_closes_at?: string | null; starts_at?: string | null; ends_at?: string | null }): Promise<Cohort>`
  — `PATCH /cohorts/{id}` via `csrfFetch`, body = `input`. `200`→Cohort;
  `401/403/404`; `422→VALIDATION`; else `UNKNOWN`.

Error code unions — `GetCohortError`: `NOT_FOUND|UNAUTHENTICATED|UNKNOWN`;
`CreateCohortError` & `UpdateCohortError`:
`VALIDATION|FORBIDDEN|NOT_FOUND|UNAUTHENTICATED|UNKNOWN`. Reuse
`firstValidationMessage`/`readValidationDetails` for 422.

### CohortDetailPage behavior
- `{ cohortId }` prop only (gate admits the surface; page needs just the id).
  Renders inside `AppShell` with `rail`.
- `useQuery(['cohort', cohortId], () => getCohort(cohortId), { retry: false })`.
- View shows: name, slug, status badge (reuse a `STATUS_LABEL` map over
  `draft|open|closed|completed`), capacity (or "No cap"), the four dates (raw ISO
  or "—" when null), `submissions_count` (0 when absent), and a `Link` to
  `/cohorts/{cohortId}/submissions`.
- **Edit:** an "Edit" button toggles an inline form: `name` (required), `capacity`
  (`<input type="number" min="1">`, empty→null), and the four dates
  (`<input type="date">`, empty→null). Save runs `updateCohort` mutation; on
  success invalidates `['cohort', cohortId]` + `['cohorts']`, returns to view.
  Cancel discards. Backend enforces the ordering chain
  (`enrollment_opens ≤ enrollment_closes ≤ starts ≤ ends`); a 422 surfaces the
  server message and stays in edit mode.
- **No status / open / close control.** A short muted note states opening a cohort
  for applications isn't available yet.

## Data flow

`react-query` server-state layer (unchanged). Cohort detail reads
`['cohort', id]`; the program-detail cohorts section and Home read `['cohorts']`.
Create/update invalidate the relevant keys so list + detail stay fresh.
Navigation via the existing design-system `Link` (`href`) and the router.

## Error / edge handling (rule 08)

- **Loading:** `Spinner` while the cohort/query is pending.
- **Load error / 404:** `GetCohortError` `NOT_FOUND` → "That cohort no longer
  exists." + back link to the program (`/programs/{program_id}` once loaded, else
  `/programs`); other errors → generic block + "Try again".
- **Forbidden:** `403` on create/update → "You don't have permission…" banner.
  Frontend visibility is not authorization (server enforces; no abilities feed).
- **Validation:** `422` → server's first message; entered values preserved.
- **Disabled/pending:** action buttons `loading`/disabled while their mutation runs;
  Save disabled when name empty.
- **Empty:** the program's cohorts section shows an empty state when the program
  has no cohorts yet.
- Accessible names/keyboard/focus/RTL via the existing design-system components
  (`AppShell`, `Field`, `Button`, `Banner`, `FormLayout`, `StateBlock`, `Link`,
  `Spinner`); `<bdi>` around cohort/program names. No new design system.

## Testing

- `api/cohorts.test.ts` (new file or extend): `getCohort` 200/404/401;
  `createCohort` 201 (asserts the request URL contains `/programs/{programId}/cohorts`
  and body `{name}`), 422→VALIDATION, 403→FORBIDDEN; `updateCohort` 200 (asserts
  PATCH + body), 422→VALIDATION.
- `pages/CohortDetailPage.test.tsx` (new): renders fields from a mocked
  `getCohort`; 404 → not-found block; edit → save (capacity + a date issued in the
  PATCH body, view updates); edit → 422 shows message + stays editable; the
  submissions link points at `/cohorts/{id}/submissions`.
- `pages/ProgramDetailPage.test.tsx` (extend): the Cohorts section lists only this
  program's cohorts (filtered by `program_id`); creating a cohort issues the
  per-program POST and the new cohort appears; a cohort row links to
  `/cohorts/{id}`.
- `app/App.test.tsx` (extend): `/cohorts/:cohortId` renders `CohortDetailPage` for
  an authenticated org user.
- Gates: `npm run typecheck && npm run lint && npm run test && npm run build` green.

## Out of scope (FE-2)

- Open/close, any status transition, and form-version binding (backend not wired).
- `timeline` editing (open map).
- The cohort funnel view (`GET /cohorts/{cohort}/funnel`).
- A standalone tenant-wide `/cohorts` list (cohorts are reached via their program
  and via Home; a standalone list can come with FE-0.5's nav shell).
- Unified nav shell / `ConsoleLayout` (deferred FE-0.5); pages keep their `AppShell`.

## Risks

- **Date ordering 422s** from the backend chain — mitigated by surfacing the server
  message verbatim and keeping the form open with values preserved.
- **`['cohorts']` cache shared** between Home, the program cohorts section, and any
  future list — invalidation on create/update keeps them consistent; the
  program-detail section filters the shared list rather than issuing a second query.
- **Client-side `program_id` filter** (no per-program index endpoint) — acceptable;
  the tenant list is already loaded and small in Phase-1a. Noted as a follow-up if
  cohort counts grow (add `GET /programs/{program}/cohorts`).
