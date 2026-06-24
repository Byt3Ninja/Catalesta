# FE-1: Programs Lifecycle UI — Design

> Date: 2026-06-24 · Status: Approved (brainstorming) · Scope: second slice of the
> "complete the Phase-1a frontend end-to-end" initiative (after FE-0 router foundation).

## Context

FE-0 migrated routing to `react-router-dom` (param routes now trivial). The
Programs surface today is a single flat screen (`frontend/src/pages/ProgramsPage.tsx`)
that does **list + create + publish** inline. The backend Programs module already
exposes the **full** lifecycle (`backend/app/Modules/Programs/Http/ProgramController.php`):

| Action | Route | Notes |
|---|---|---|
| index | `GET /programs` | wired in FE already |
| store | `POST /programs` | wired (create → draft) |
| show | `GET /programs/{id}` | **not yet wired in FE** |
| update | `PATCH /programs/{id}` | **not yet wired** — name/description/settings; works on published too |
| publish | `POST /programs/{id}/publish` | wired |
| clone | `POST /programs/{id}/clone` | **not yet wired** — deep-copy → new draft, requires `name` |

FE-1 adds the missing surfaces (detail, edit, clone) so the program lifecycle is
genuinely end-to-end. Phase-1a principle holds: every flow here is backed by a
real endpoint.

## Backend behavior this design relies on (verified in source)

- `update()` (ProgramController:89): *"Programs are NOT immutable — PATCH works on
  Published programs too."* Editing mutates the live program in place and writes a
  `program.updated` audit row (before/after). It does **not** fork a new version.
- `publish()` records an immutable version snapshot (via `PublishProgram` service)
  and is what versioning hangs off — not editing.
- `clone()` requires `{ name: string (≤255) }`, deep-copies into a new **draft**,
  returns `201` with the clone resource. Auth ability: `clone`.
- Editable fields on update: `name`, `description`, `settings`. **`settings` is an
  open key/value map with no schema → out of scope for this UI** (name + description
  only).

## Correctness fix (contradiction surfaced during brainstorming)

The current `ProgramsPage` shows: *"Editing a published program later creates a new
version — it never changes what was published."* This contradicts `update()` (edits
mutate in place; no new version). FE-1 corrects the copy: **publishing** records an
immutable version; **editing** changes the live program (audited) and does not fork
a version. Editing a published program is permitted.

## Architecture

### Routes & navigation
- Add gated console route `path="/programs/:programId"` → `ProgramDetailRoute`
  wrapper (reads `useParams().programId`, wraps the unchanged render-prop
  `ConsoleGate`, passes `programId` + `organization` to the page).
- `/programs` (list) stays. Each program row becomes a link to
  `/programs/{program.id}` (the program name is the link).
- **Lifecycle actions consolidate onto the detail screen**: Edit, Clone, and
  Publish-if-draft live on `ProgramDetailPage`. **Publish is removed from the list
  rows** — one locus for lifecycle, leaner list.
- Detail screen has a "← Programs" back link to `/programs` (router `<Link>` / the
  app's existing `Link` component as appropriate).

### Units (each one responsibility, independently testable)
- `api/programs.ts` (modify): add `getProgram`, `updateProgram`, `cloneProgram`.
- `schemas/programs.ts` (modify): add `GetProgramError`, `UpdateProgramError`,
  `CloneProgramError` (same `ApiError`-subclass pattern as the existing
  `CreateProgramError` / `PublishProgramError`). Codes — `GetProgramError`:
  `NOT_FOUND | UNAUTHENTICATED | UNKNOWN`; `UpdateProgramError`:
  `VALIDATION | FORBIDDEN | NOT_FOUND | UNAUTHENTICATED | UNKNOWN`;
  `CloneProgramError`: `VALIDATION | FORBIDDEN | NOT_FOUND | UNAUTHENTICATED | UNKNOWN`.
- `pages/ProgramDetailPage.tsx` (create): the detail/edit/clone/publish surface.
- `pages/ProgramsPage.tsx` (modify): rows link to detail; remove inline publish
  and its mutation/error handling; correct the versioning banner copy.
- `app/App.tsx` (modify): add the `:programId` route + thin wrapper.

### API client additions (exact shapes)
- `getProgram(id: string): Promise<Program>` — `GET /programs/{id}` with
  `credentials: 'include'` (plain `fetch`, like `listPrograms`; a GET needs no
  CSRF). `200` → parsed `Program`; `401 → GetProgramError('UNAUTHENTICATED')`;
  `404 → GetProgramError('NOT_FOUND')`; else `GetProgramError('UNKNOWN')`.
- `updateProgram(id: string, input: { name?: string; description?: string | null }): Promise<Program>`
  — `PATCH /programs/{id}` via `csrfFetch`. `200` → parsed `Program`; `401 →
  UNAUTHENTICATED`; `403 → FORBIDDEN`; `404 → NOT_FOUND`; `422 → VALIDATION`
  (surface server's first message); else `UNKNOWN`.
- `cloneProgram(id: string, name: string): Promise<Program>` — `POST
  /programs/{id}/clone` via `csrfFetch`, body `{ name }`. `201` → parsed `Program`
  (the new draft); `401/403/404/422/UNKNOWN` as above.

Reuse `programResponseSchema` for single-resource parsing. Reuse
`firstValidationMessage` / `readValidationDetails` for 422 handling (as
`createProgram` does).

### ProgramDetailPage behavior
- Renders inside `AppShell` (console surface) with `rail={<nav aria-label="Sections">Programs</nav>}`,
  consistent with `ProgramsPage`.
- Drives `useQuery(['program', programId], () => getProgram(programId))`.
- View mode shows: name, slug, status badge (reuse the `STATUS_LABEL` map / `ds-badge`
  pattern), description (or a muted "No description"), created/updated timestamps.
- **Edit:** an "Edit" button toggles an inline form (name required + description),
  Save / Cancel. Save runs `updateProgram` mutation; on success invalidates
  `['program', programId]` and `['programs']`, returns to view mode. In-place, no
  separate route (matches the 2-field simplicity).
- **Clone:** a "Clone" button reveals a name `Field` + confirm. On success
  (`useNavigate`) routes to `/programs/{clone.id}` (the new draft's detail).
- **Publish:** shown only when `status === 'draft'`. Single action with the
  existing explanatory banner ("Publishing records an immutable version…"), no
  modal. On success invalidates `['program', programId]` + `['programs']`.

## Data flow

`react-query` is the server-state layer (unchanged). Detail reads `['program', id]`;
mutations invalidate `['program', id]` and the list `['programs']` so both stay
fresh. No new global state. Navigation via react-router (`Link`, `useNavigate`).

## Error / edge handling (rule 08 — all states)

- **Loading:** `Spinner` while the program query is pending.
- **Error (load):** `StateBlock variant="error"` + "Try again" (`refetch`).
- **Not found:** bad/stale `:programId` → `getProgram` 404 → a clear "That program
  no longer exists." block with a back link to `/programs`.
- **Forbidden:** `403` on update/clone/publish → "You don't have permission to …"
  banner. Frontend visibility is **not** the authz boundary (server enforces); we
  have no client-side abilities feed, so actions render and degrade on 403.
- **Validation:** `422` on edit/clone → server's first message in a banner; entered
  values preserved.
- **Disabled / pending:** action buttons show `loading` and disable while their
  mutation is in flight; Save disabled when name is empty.
- **Empty:** the list's existing empty state is unchanged.
- **Accessible names, keyboard, focus, RTL:** reuse the existing design-system
  components (`Field`, `Button`, `Banner`, `FormLayout`, `StateBlock`, `AppShell`,
  `Link`) which already carry these; `<bdi>` around tenant/program names as
  elsewhere. No new design system.

## Testing

- `pages/ProgramDetailPage.test.tsx` (new): renders fields from a mocked
  `getProgram`; edit → save success (PATCH issued, view updates); edit → 422 shows
  message + preserves input; clone success → navigates to new id; clone 403 →
  permission banner; publish shown only for draft and issues the publish call;
  bad id → 404 not-found block.
- `pages/ProgramsPage.test.tsx` (update): a program row links to
  `/programs/{id}`; no inline Publish button on rows; corrected banner copy
  asserted (no "creates a new version").
- `app/App.test.tsx` (update): `/programs/:programId` renders `ProgramDetailPage`
  for an authenticated org user (and unauth → login, like other console routes).
- Gates: `npm run typecheck && npm run lint && npm run test && npm run build` green.

## Out of scope (FE-1)

- `settings` editing (open map, no schema).
- Sub-resources: cohorts (FE-2), stages, tracks, policies, role-requirements,
  templates — not surfaced here.
- Unified nav shell / `ConsoleLayout` (deferred FE-0.5); pages keep their own
  `AppShell`.
- Client-side permission/abilities feed (actions render + handle 403).
- Archive/close program transitions (no dedicated endpoint wired; statuses exist
  in the schema but FE-1 only drives draft→published).

## Risks

- **Removing inline publish from the list** is a small UX change; mitigated because
  publish remains available (on detail) and the list gets a clearer
  drill-in affordance. Covered by the updated `ProgramsPage` test.
- **Stale detail after edit/clone** — mitigated by invalidating both query keys.
- **Clone navigation** depends on the `201` body carrying the new id; covered by a
  test asserting `useNavigate` target.
