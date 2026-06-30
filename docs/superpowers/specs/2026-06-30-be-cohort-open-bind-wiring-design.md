# Cohort Open / Bind-Form Backend Wiring — Design

> Status: Approved (design) · Date: 2026-06-30 · Branch: `feat/be-cohort-open-bind`
> Slice: backend wiring behind the UI-first Slice 2a cohort lifecycle (setup wizard + enrollment editor)

## 1. Goal

Expose the two in-scope cohort lifecycle operations — **bind a published form
version** to a cohort and **open** the cohort for enrollment — as real,
tenant-scoped HTTP endpoints, behind the already-shipped Slice 2a UI (currently
MSW-only for open / bind-form). The backend already owns the domain logic
(`OpenCohort`, `cohorts.form_version_id`, `EntitlementService('cohort.open')`,
audit, and `isAcceptingSubmissions` driving the live public apply flow). This
slice adds the operator HTTP layer and refactors `OpenCohort` to match the FE's
decoupled contract.

## 2. Authoritative contract

Fixed by the shipped frontend; the backend conforms and the FE does not change
(MSW is dev/test-only):

- `frontend/src/api/cohorts.ts` — `openCohort` (`POST /cohorts/{id}/open`, no body)
  and `bindCohortForm` (`POST /cohorts/{id}/bind-form`, `{form_version_id}`),
  with their exact status codes.
- `frontend/src/schemas/cohorts.ts` — the `Cohort` shape, including the
  `bound_form_version_id` field this slice must start emitting.

## 3. Decisions (locked)

- **Open preconditions:** `open` requires a bound **published** form version
  (else 409) and `status=draft` (else 409). The enrollment window is **optional**
  — a null window means open with no time bound, consistent with
  `Cohort::isAcceptingSubmissions` (null opens/closes ⇒ always within while open).
  `open` does **not** set the window; the window is set beforehand via the
  existing `PATCH /cohorts/{id}` (which already enforces date ordering).
- **Bind-form rules:** binding is allowed only while `status=draft` (else 409).
  No form bound → bind (200). Same version re-bound → idempotent 200. A
  *different* version while one is bound → 409 (matches the FE contract). The
  bound version must be a `published` `FormVersion` in the cohort's org (else
  404). No force/unbind path this slice (documented follow-up).
- **`OpenCohort` refactored** from `handle(Cohort, FormVersion, Carbon, Carbon)`
  to **`handle(Cohort): Cohort`** — it operates on already-bound cohort state
  (form bound via bind-form, window via PATCH), mirroring the Forms slice's
  publish-the-draft adaptation. Keeps the entitlement gate + `cohort.opened`
  audit. **Sole caller of `OpenCohort::handle` is `CohortLifecycleTest`** (5 call
  sites) — verified by blast-radius grep; `PublishProgram` only references it in a
  docblock, `SubmitApplication` and the Applications submit tests use
  `CloseCohort`/`CohortStatus`, not `OpenCohort`.
- **`CloseCohort` stays unexposed** — no FE caller; window-based
  `isAcceptingSubmissions` already governs acceptance. Unchanged by this slice.
- **Out of scope:** `bind-stage-pipeline` (depends on the Stages-model
  reconciliation) and `bind-stage-scoring-model` (depends on the Assessments
  backend). The FE keeps its MSW for those; their cohort columns are not added.

## 4. HTTP surface (2 endpoints)

Both in the authenticated, tenant-scoped group (`['auth:sanctum','tenant']`).
Cross-tenant `{id}` → neutral **404** (ADR-0009). Authorized by `CohortPolicy`
against `cohorts.manage`.

| Method | Path | Controller action | Service | Success | Errors |
|---|---|---|---|---|---|
| POST | `/cohorts/{id}/bind-form` `{form_version_id}` | `CohortController@bindForm` | `BindCohortForm` | 200 | 404 (cohort or published version not in tenant), 409 (non-draft, or different version bound), 403, 422 (missing `form_version_id`) |
| POST | `/cohorts/{id}/open` | `CohortController@open` | `OpenCohort` (refactored) | 200 | 404, 409 (not draft, or no form bound), 403 |

Routes `cohorts.bind-form`, `cohorts.open`, registered next to the existing
`cohorts.show`/`cohorts.update` direct routes. Controller actions are thin:
`findOrFail` (tenant-scoped) → `authorize` → service → `CohortResource`. The 409
conflicts surface from a typed `CohortStateException` caught in the controller.

## 5. Services

- **`BindCohortForm::handle(Cohort $cohort, string $formVersionId): Cohort`** (new)
  — throws `CohortStateException` (→409) if `status !== draft`; looks up the
  `FormVersion` scoped to tenant + `status=published` via `findOrFail` (→404);
  if the same version is already bound, returns the cohort unchanged (idempotent
  200); if a *different* version is bound, throws `CohortStateException` (→409);
  else sets `form_version_id`, audits `cohort.form_bound`, returns the cohort.
- **`OpenCohort::handle(Cohort $cohort): Cohort`** (refactored) — throws
  `CohortStateException` (→409) if `status !== draft` or `form_version_id === null`;
  runs `EntitlementService('cohort.open')`; flips `status` to `Open`; audits
  `cohort.opened`. Does not touch the enrollment window.
- **`CohortStateException extends \RuntimeException`** (new, in
  `Cohorts\Domain\Exceptions`) — the 409 signal shared by both services.

## 6. Resource & authorization

- `CohortResource` adds `'bound_form_version_id' => $this->form_version_id` (the
  FE schema field; currently absent). The two out-of-scope binding fields
  (`stage_pipeline_version_id`, `stage_scoring_model_version_ids`) are not added —
  their columns do not exist.
- `CohortPolicy` gains `open(Account, Cohort)` and `bindForm(Account, Cohort)`,
  both requiring `app(TenantContext::class)->can('cohorts.manage')` (already
  granted to the owner role). All reads/writes org-scoped via `BelongsToTenant`.

## 7. Testing

Feature tests (`backend/tests/Feature/Cohorts/`):
- **bind-form:** 200 first bind; idempotent 200 same version; 409 different
  version; 409 non-draft cohort; 404 missing cohort; 404 non-published / foreign
  form version; cross-tenant 404; 403 without `cohorts.manage`; 422 missing
  `form_version_id`.
- **open:** 200 draft→open with a form bound; 409 no form bound; 409 already
  open; cross-tenant 404; 403 without `cohorts.manage`; entitlement-gate path.
- **Refactor:** characterize `OpenCohort` first, then migrate `CohortLifecycleTest`
  (5 call sites) to bind-then-open; confirm `CloseCohort` tests + the public
  submit/close-race tests stay green.
- `CohortResource` exposes `bound_form_version_id` (assert on show/open/bind).
- Regenerate `backend/openapi/openapi.json` (Scramble); `OpenApiSpecTest` +
  Spectral green; full backend gate (pint, phpstan, deptrac, `php artisan test`).

## 8. Explicitly out of scope (follow-ups)

- No unbind / force-replace path for a mis-bound draft (the FE has no force flag).
- `bind-stage-pipeline` / `bind-stage-scoring-model` (need the Stages
  reconciliation and Assessments backend respectively).
- An explicit close endpoint (no FE caller; auto-close is window-driven).
