# Forms Authoring Backend Wiring — Design

> Status: Approved (design) · Date: 2026-06-29 · Branch: `feat/be-forms-authoring`
> Slice: backend wiring behind the UI-first Forms builder (FE Slice 2b, PR #61)

## 1. Goal

Expose a real, tenant-scoped HTTP surface for **authoring application forms**
(create, edit-as-draft, publish, fork, list versions) behind the already-shipped
Slice 2b Forms builder, which is currently MSW-only. The backend already owns the
hard parts — immutable content-addressed versioning, `PublishForm`,
`FormDefinitionValidator`, and the `Form`/`FormVersion` models consumed by the
apply flow. This slice adds the **authoring HTTP layer** and a **persistent draft
lifecycle**, conforming exactly to the existing frontend contract.

## 2. Authoritative contract

The target shape is fixed by the shipped frontend and must not change:

- `frontend/src/api/forms.ts` — the 8 endpoints, methods, status codes, and bodies.
- `frontend/src/schemas/forms.ts` — the Zod response shapes (`Form`, `FormVersion`,
  `FormField`, list/single envelopes `{ data: ... }`).

The backend conforms to these. The frontend does not change in this slice (MSW is
dev/test-only; the deployed FE talks to these real endpoints once they exist).

## 3. Decisions (locked)

- **Form ownership: org-scoped, program optional.** `forms.program_id` becomes
  nullable. Forms are reusable org assets created with just a name; routes stay at
  `/forms` (no program prefix). This honors the shipped FE contract over the Epic 2
  `program_id NOT NULL` assumption (recorded contradiction, resolved in favor of
  approved 2b behavior).
- **Draft lifecycle: one persistent mutable draft per form.** A form has at most
  one `FormVersion` with `status=draft` (`version_number=0`, `content_hash=NULL`,
  `definition` mutable). `PATCH /draft` mutates it; `publish` promotes it to an
  immutable published version; `fork` creates a new draft from a published version.
- **`PublishForm` is adapted** to publish the form's existing draft (rather than
  taking a `definition` argument). Existing callers (`PublishFormTest`,
  `CohortLifecycleTest`) are updated; a characterization test is written first.
- **No new columns for `current_draft_version_id` / `description`** — both are
  derived in the API resource (draft = the single `status=draft` row; `description`
  returns `null`, the FE never writes it).

## 4. Schema change (one migration)

`backend/database/migrations/2026_06_29_000000_relax_forms_for_authoring.php`:

- `form_versions.content_hash` → **nullable**. Drafts carry no hash; Postgres
  treats multiple NULLs as distinct, so `UNIQUE(form_id, content_hash)` still
  enforces one published row per content hash.
- `forms.program_id` → **nullable**.

Rollback (`down`): restore `NOT NULL` on both. (Safe only if no NULL rows exist;
documented as a forward-preferred migration.)

## 5. Draft → publish state machine

```
create form ──► [draft v0, hash=NULL, definition=[]]
   │                  │  PATCH /draft {fields}     (mutates definition; 409 if no draft)
   │                  ▼
   │             [draft v0, definition=[...]]
   │                  │  POST /publish
   │                  ▼
   │   compute hash(canonical(definition))
   │   ┌── existing published row with this hash?
   │   │        yes ─► delete redundant draft, return existing,
   │   │               set forms.current_published_version_id
   │   └── no  ─► set draft.content_hash (still draft, allowed),
   │              VersionPublisher.publish(draft)  → version_number, Published, published_at
   │              set forms.current_published_version_id
   ▼
POST /fork {from_version_id} ──► copy published definition into a new draft v0
```

**Why detect idempotency before setting the hash:** writing the draft's
`content_hash` to a value an existing published row already holds would violate
`UNIQUE(form_id, content_hash)`. So the existing-published lookup runs while the
draft still has `content_hash=NULL`.

`ImmutableWhenPublished` already blocks mutation of published rows (except
`status→archived`); a `PATCH /draft` or `publish` against a form whose only
version is published surfaces as `VersionStateException` → mapped to **409**.

## 6. HTTP surface

All under the authenticated, tenant-scoped route group (`auth:sanctum` +
`BelongsToTenant`, `X-Organization-Id`). Cross-tenant `{id}` → neutral **404**
(ADR-0009).

| Method | Path | Controller action | Application service | Success | Error mapping |
|---|---|---|---|---|---|
| GET | `/forms` | `FormController@index` | — (scoped query) | 200 | 401 |
| POST | `/forms` | `FormController@store` | `CreateForm` | 201 | 422 (name required) |
| GET | `/forms/{id}` | `FormController@show` | — | 200 | 404 |
| GET | `/forms/{form}/versions` | `FormController@versions` | — | 200 | 404 |
| GET | `/form-versions/{id}` | `FormVersionController@show` | — | 200 | 404 |
| PATCH | `/forms/{id}/draft` | `FormController@saveDraft` | `SaveFormDraft` | 200 | 404, 409, 422 |
| POST | `/forms/{id}/publish` | `FormController@publish` | `PublishForm` (adapted) | 200 | 404, 409 |
| POST | `/forms/{id}/fork` | `FormController@fork` | `ForkFormDraft` | 201 | 404 |

Controllers are thin: validate request → authorize (`FormPolicy`) → call service →
return resource. Route-model binding resolves `{id}`/`{form}` org-scoped.

### Application services (one use-case per file, mirrors `PublishForm`)

- **`CreateForm`** — creates a `Form` (`name`, org from tenant context,
  `program_id=null`) + an empty draft `FormVersion` (`definition=[]`). Returns Form.
- **`SaveFormDraft`** — finds the form's draft (404 if form missing; 409 if the
  form has no draft, i.e. fully published with no working copy), validates `fields`
  via `FormDefinitionValidator`, replaces `definition`, returns the draft version.
- **`PublishForm`** (adapted) — implements §5 publish branch; returns the published
  version (idempotent).
- **`ForkFormDraft`** — given `from_version_id` (must be a published version of this
  form, else 404), creates a new draft `FormVersion` copying its `definition`.
  Guard: at most one draft per form — if a draft already exists, return it
  (no second draft created, no 409); see §10.

## 7. Contract mapping (API resources)

`FormResource` and `FormVersionResource` translate persistence ↔ FE Zod:

**`FormVersionResource`** (`FormVersion` → FE `FormVersion`):
- `id` → `id`, `form_id` → `form_id`
- `version_number` → `version`
- `status` (`draft|published`) → `status` (archived not surfaced in authoring)
- `definition` → `fields`
- `created_at` → `created_at` (ISO-8601), `published_at` → `published_at` (nullable)

**`FormResource`** (`Form` + loaded versions → FE `Form`):
- `id` → `id`, `name` → `name`, `description` → `null`
- `latest_version` = max published `version_number` (0 if none)
- `published_version_ids` = ids of `status=published` versions, version order
- `current_draft_version_id` = id of the single `status=draft` row, else `null`

The FE field types are a compatible subset of the backend `FieldType` enum (8
cases). The FE's declarative `visibility` rules (`field_id`/`operator`/`value`,
`match: all|any`) contain no forbidden keys and pass `FormDefinitionValidator`
unchanged. The validator stays authoritative for no-code enforcement (NFR-005).

## 8. Authorization & tenancy

- New `FormPolicy`, deny-by-default: `viewAny`/`view`/`create`/`update`/`publish`
  gated on org membership + operator role. Registered for `Form`.
- All reads/writes org-scoped via `BelongsToTenant`; no client-supplied org trusted.
- `Gate::after` already appends authorization denials to `audit_logs` (FR-126).
  `PublishForm` already emits the `form.published` audit event.

## 9. Testing

Feature tests (`backend/tests/Feature/Forms/`):

- **Characterization first:** capture current `PublishForm` behavior before adapting.
- CreateForm: 201 + empty draft seeded; 422 on blank name; 401 unauthenticated.
- SaveFormDraft: 200 mutates draft; 409 when no draft (fully published);
  422 on invalid field type / forbidden code key; cross-tenant 404.
- Publish: 200 promotes draft (version_number assigned, immutable thereafter);
  idempotent republish returns the same version (no duplicate row); 409 when no
  draft to publish.
- Fork: 201 new draft from a published version; 404 for a non-published /
  cross-form `from_version_id`; existing-draft guard.
- Immutability: PATCH/publish against a published-only form → 409.
- Versions/version show: 200 lists in order; cross-tenant 404.
- Update `PublishFormTest` and `CohortLifecycleTest` to the create→draft→publish flow.
- Regenerate `backend/openapi/openapi.json` (Scramble); `OpenApiSpecTest` green.

## 10. Open questions (resolved)

- **Multiple drafts per form?** No. Invariant: ≤1 draft per form. `CreateForm`
  seeds one; `ForkFormDraft` returns the existing draft if one is present rather
  than creating a second.
- **`description` writeable?** No — read-only `null` in this slice (FE has no write
  path). Add a column only if a future slice adds an edit affordance.

## 11. Explicitly out of scope (follow-ups)

- Cohort `open` / `bind-form` / `bind-stage-pipeline` routes (still MSW-only on FE).
- Flipping the FE off MSW (it is dev/test-only; deployed FE already uses real API).
- A real-backend Playwright e2e for the authoring flow.
- Stage-pipeline backend reconciliation (separate ADR).
