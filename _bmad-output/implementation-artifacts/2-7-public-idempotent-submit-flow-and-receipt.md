---
baseline_commit: 5bb5f73
---
# Story 2.7: Public idempotent submit flow + receipt

Status: ready-for-dev

> **Epic 2 · the Selection-MVP climax — applicant applies end-to-end.** Wires every prior brick: open cohort + public URL (1.4 ✅), published form version (1.3 ✅), immutable snapshot `RecordSubmission` (2.6 ✅), idempotency (2.2 ✅), content-addressed file upload (2.1 ✅), outbox event (2.3/2.4 ✅), audit (2.5 ✅).
>
> **SCOPE SPLIT (read first).** The **stepped public application *page* (UI)** depends on **Story 1.0 (frontend foundation, NOT built)** — so the *UI* half cannot land until 1.0 ships. This story's **backend submit API** (the engine the UI calls) **is** buildable now and is the priority. Build the backend slice; mark the UI page `blocked-by: 1.0`.

## Acceptance Criteria
1. **Authenticated idempotent submit (FR-030/032):** an authenticated applicant (`sub`) POSTs to the cohort's submit endpoint with answers + uploaded file refs; the submit is wrapped in `IdempotencyService` keyed on an `Idempotency-Key` → a duplicate returns the **original** result, not a second record.
2. **Closed/unpublished → 422 (FR-033), race-safe (★ 2.7):** submission to a closed/unpublished cohort returns **422**; the **close-check and the snapshot write are one transaction** (re-check open *inside* the write). An `Idempotency-Key` replay after a mid-attempt close replays the **original receipt**, not a re-evaluation.
3. **Immutable snapshot (FR-031):** the submission is recorded via `RecordSubmission` (2.6) — answers + content-addressed blob refs + the cohort's resolved form/program/rubric **version ids** — frozen, blob-pinned.
4. **Receipt + read-only status:** the applicant gets an **acknowledgment/receipt** (a stable **reference number** = the submission id) and a read-only status; resubmission shows status, not a second form.
5. **Outbox + audit + telemetry:** `ApplicationSubmitted` is written to the outbox (2.3) in the submit transaction; `application.submitted` audit (2.5); Learning-Telemetry events `application.viewed/started/abandoned{step}/submitted` (FR-080) are emitted — **a story is not done until those events are verified in a dashboard a human has looked at** (Learning Telemetry DoD).
6. **File upload:** uploaded files are stored content-addressed (2.1 `ContentAddressedStore`), returning digests the submit references; type/size guarded.

## ★ Integration landmines (must handle)
- **Applicant is NOT a tenant member → org comes from the COHORT.** The applicant authenticates via `sub` but has no org/`TenantContext`. The submission's `organization_id` must be the **cohort's** org so the operator sees it. `RecordSubmission` (2.6) currently relies on `BelongsToTenant` auto-setting org from context → **adjust it** to record under the cohort's org explicitly (e.g. `TenantContext::runAsSystem(...)` + set `organization_id = $cohort->organization_id`, which BelongsToTenant's "explicit org" path permits). Add a test for this.
- **Idempotency scope/fingerprint:** scope e.g. `application.submit:{cohort_id}`, key = the `Idempotency-Key` header, fingerprint = `RequestFingerprint::for($sub, $payload)` so a different applicant/payload can't replay another's receipt (2.2 AC-7).
- **Closed-cohort resolution is public/system:** resolve the cohort via `TenantContext::runAsSystem` (per the tenancy arch test — not `withoutGlobalScope`), like `ApplyController` (1.4).

## Tasks
- [ ] **Submit endpoint** — `POST /v1/apply/{cohort}/submit` behind `auth:sanctum` (applicant `sub`; NOT the `tenant` middleware). Controller mirrors the thin-controller pattern; resolves the cohort (system context), guards open-state, delegates to a `SubmitApplication` service.
- [ ] **`SubmitApplication` service** (`app/Modules/Applications/Application/`): in ONE `DB::transaction` — re-check cohort open (else 422 `CohortClosedException`), pin/store uploaded blobs, call (adjusted) `RecordSubmission` under the cohort's org, write `ApplicationSubmitted` to the outbox, audit `application.submitted`. The whole thing wrapped in `IdempotencyService::remember(scope, key, fingerprint, fn)` so replay returns the original receipt.
- [ ] **Adjust `RecordSubmission`** to accept the cohort's org (applicant has none) — see landmine. Keep 2.6's tests green; add the cross-org applicant test.
- [ ] **File upload** — accept multipart; store via `ContentAddressedStore`; return digests into the snapshot. Type/size guard.
- [ ] **Receipt/status** — response carries the submission id (reference number) + status; a `GET` status endpoint (or extend `/apply/{cohort}` for the authenticated applicant) returns their read-only submission.
- [ ] **Telemetry** — emit FR-080 events (`application.viewed` on the public GET; `started/abandoned{step}/submitted` on the submit path). Verify in a dashboard (DoD).
- [ ] **422 mapping** — `CohortClosedException` → 422 via the bootstrap/app.php renderer (add the mapping).
- [ ] **Tests** — idempotent double-submit (one record, same receipt); 422 closed (+ ★ close-mid-attempt replays original receipt); snapshot recorded under cohort org; file upload→digest in snapshot; outbox + audit written; cross-actor fingerprint can't replay.
- [ ] **UI page (`blocked-by: 1.0`)** — the stepped mobile-web RTL application page + receipt screen. Defer until Story 1.0 (frontend foundation) ships. Document, do not build now.

## Dev Notes
- **Reuse (all built):** `RecordSubmission` (2.6, adjust for org), `IdempotencyService`+`RequestFingerprint` (2.2), `ContentAddressedStore` (2.1), `OutboxProducer` (2.3), `AuditLogger`+`AuditAction::ApplicationSubmitted` (2.5), `ApplyController`/`runAsSystem` pattern (1.4), `Cohort`/`CohortStatus` (1.4), `FormVersion` (1.3).
- **Version ids for the snapshot:** the cohort's `form_version_id` (1.4) → the form's program/rubric version ids. Resolve at submit time, copy into the snapshot (2.6 already copies).
- **CI discipline (hard-won this session):** annotate every `array` param (`@param array<...>`, PHPStan L6); no `@template`; honest types (no always-true `is_array`); use `TenantContext::runAsSystem` not `withoutGlobalScope`; **regenerate `openapi/openapi.json`** (`php artisan scramble:export --path=openapi/openapi.json`) after adding routes (Contract test) — these each red-failed CI earlier. Tests on SQLite; `bootUserWithOrg()`/`actingAsTenant()` helpers; for the applicant, create an `ExternalUser` and `actingAs($user,'web')` WITHOUT tenant context.
- Migrations: none expected (reuses `application_submissions`); if a status endpoint needs an index, add `…000900`.

## Dev Agent Record
### Agent Model Used
claude-opus-4-8[1m]
### Completion Notes List
**UI slice — DONE** (frontend green: typecheck + lint + 39 Vitest tests, 13 files; now unblocked — Story 1.0 shipped).
- **Form-fetch prereq:** `GET /v1/apply/{cohort}` extended to return the published `FormVersion.definition` (`form`), resolved in the same `runAsSystem` as the cohort, so the page can render the field schema. (committed: backend, 346 tests green.)
- **Stepped submit page** (`src/pages/ApplyPage.tsx` + `ApplyField.tsx` + `useApplyDraft.ts`, `src/api/apply.ts`, `src/schemas/apply.ts`; routed off the pathname in `App.tsx`, no router dep): renders all 8 field types from the definition, one field per step + a **confirm step** ("can't edit after submitting"); per-step **localStorage autosave** keyed by cohort; **EN↔AR** toggle (via `DirectionProvider`) preserving the draft + flipping `dir`; `dir="auto"` on free-text; **idempotent submit** with a persisted `Idempotency-Key` (retry → same calm receipt) + optimistic-lock `Button`; **keepable receipt** showing the reference number. Calm states (`Banner`/`StateBlock`) for closed (422 `COHORT_CLOSED`), in-flight (409), conflict/validation (422), 401 sign-in prompt, and offline. Reuses the Story 1.0 primitives only; no new deps.
- **Still deferred:** FR-080 telemetry (no sink — slice 3) and the OIDC login UI (Story 1.1; the page only surfaces the 401 "sign in to submit" prompt). Standalone GET status endpoint also remains deferred (POST receipt + idempotent replay cover the acknowledgment).

**Backend submit slice — DONE** (336/336 tests green, PHPStan L6 clean, Pint clean, OpenAPI regenerated).

- `POST /v1/apply/{cohort}/submit` (auth:sanctum, NO tenant middleware). Thin controller resolves the cohort under system context (clean 404 vs the 422 a closed cohort returns) and delegates to `SubmitApplication`.
- `SubmitApplication` wraps everything in `IdempotencyService::remember(scope=`application.submit:{cohort_id}`, key=Idempotency-Key header, fingerprint=`RequestFingerprint::for(sub, {cohort,answers,blobs})`)`. Inside ONE `DB::transaction` (under `runAsSystem`): re-checks `Cohort::lockForUpdate()` open-state (★ FR-033 close race) → 422 `CohortClosedException`; stores inline uploads; records the snapshot via `RecordSubmission`; emits `ApplicationSubmitted` to the outbox; audits `application.submitted` under the cohort org.
- **Landmine handled:** `RecordSubmission` now sets `organization_id` explicitly from the cohort (applicant has no `TenantContext`); 2.6's tenant-context callers are unaffected (BelongsToTenant forces the same value when a tenant is resolved). `AuditLogger::record` gained an optional trailing `$organizationId` so the tenant-less submit audits under the cohort's org.
- **Exception→HTTP mapping** added to `bootstrap/app.php`: `CohortClosedException`→422, `IdempotencyConflictException`→422, `IdempotencyInFlightException`→409.
- **File upload:** the submit accepts inline multipart `files[]` (stored content-addressed, type/size guarded) and/or already-stored `blob_digests[]`; both land in the snapshot's `blob_refs`.

**Deliberately deferred to the UI slice (`blocked-by: Story 1.0`) — NOT built here:**
- The stepped mobile-web RTL application page + receipt/status screen.
- A standalone `GET` read-only status endpoint — needs a submitter-identity column on `application_submissions` (2.6 stores no `sub`); it is UI-facing. The POST already returns the full keepable receipt (`reference_number` + `status` + `submitted_at`), and idempotency replay makes a re-tap return that same receipt (AC-4 substance).
- FR-080 Learning-Telemetry events (`application.viewed/started/abandoned{step}/submitted`) and the **"verified in a dashboard a human has looked at"** DoD — these are frontend/observability and cannot be satisfied autonomously. The outbox `ApplicationSubmitted` event is the durable backend signal in the meantime.

### Review fixes (post-merge, branch `fix/2-7-review`)
Addressing the PR #13 multi-agent code review:
- **#1 (MinIO orphan on rollback)** — `ContentAddressedStore::store()` for inline uploads now runs BEFORE the `DB::transaction` (inside the idempotency closure, so it still runs once per real attempt and is skipped on replay). A MinIO `put` is not transactional; storing inside the txn orphaned the bucket object on rollback (the `Blob` row rolled back, the object did not, and refcount GC could never see it).
- **#4 (memory exhaustion)** — `SubmitApplicationRequest` now caps `files` at 20 and `blob_digests` at 50; `getContent()` loads every upload into memory, so the count must be bounded, not just per-file size.
- **#2 (FR-033 untested on SQLite)** — added `PublicSubmitCloseRaceTest` (pgsql-gated, `DatabaseMigrations`) that proves with two real connections that a `CloseCohort` UPDATE blocks while a submit holds `Cohort::lockForUpdate()`. Verified PASSING against a throwaway Postgres DB; skips cleanly on the SQLite suite (`lockForUpdate` is a no-op there). **Note:** the default `php artisan test` (SQLite) still cannot exercise the lock — a pgsql CI lane is recommended to keep this guarantee covered.
- **#3 (program/rubric version ids null)** — left as a tracked Epic-3 gap (no code change); they resolve from the form→program/rubric link that does not exist yet.
- Cleanup items (#5 double-pin, #6 double cohort resolve, #7 dead `$now` param, #8 nested-txn comment) — deferred; lower value, noted for a later pass.

### File List
- `app/Modules/Applications/Application/SubmitApplication.php` (new)
- `app/Modules/Applications/Application/Exceptions/CohortClosedException.php` (new)
- `app/Modules/Applications/Http/SubmitController.php` (new)
- `app/Modules/Applications/Http/Requests/SubmitApplicationRequest.php` (new)
- `app/Modules/Applications/Application/RecordSubmission.php` (set org from cohort; widen version-id type)
- `app/Modules/Cohorts/Domain/Models/Cohort.php` (add `isAcceptingSubmissions()`)
- `app/Modules/Cohorts/Http/ApplyController.php` (use `isAcceptingSubmissions()`)
- `app/Shared/Audit/AuditLogger.php` (optional explicit `$organizationId`)
- `bootstrap/app.php` (422/409 exception mappings)
- `routes/api.php` (submit route)
- `openapi/openapi.json` (regenerated)
- `tests/Feature/Applications/PublicSubmitTest.php` (new)
