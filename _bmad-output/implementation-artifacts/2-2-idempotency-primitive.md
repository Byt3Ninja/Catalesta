---
baseline_commit: 20469cf95c2812769873518bb32cbaf4f666dfb6
---
# Story 2.2: Idempotency primitive

Status: review

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

> **Track A (backend critical path) · E2.0 reliability-gate member, the load-bearing one.** Guards *both* the application submit (FR-032, Story 2.7) **and** the Geidea payment callback (FR-072/073, P1b). It must be **consumer-agnostic** — nothing in its signature is submission- or payment-specific. Tested with a throwaway closure (no HTTP, no Applications). `GATE-E2.0` member; Stories 2.6–2.8 are `blocked-by: GATE-E2.0`.
>
> **This story carries the gnarliest ★ correctness ACs in the whole epic** (in-flight 409, crash-before-response recovery, fingerprint-actor isolation, response-size cap). Treat every ★ as must-fix-before-green.

## Story

As a **platform engineer**,
I want a consumer-agnostic idempotency service,
so that any retried operation produces exactly one effect.

## Acceptance Criteria

From epics.md (Story 2.2) + AR-2/ADR-2 + the ★ Edge-Case Hardening (these ★ items are **normative parts of this story**):

1. **Schema.** An `idempotency_keys` table exists: `scope`, `key`, `request_fingerprint`, `response_snapshot` (jsonb, nullable), `status`, `locked_at`, `expires_at`, with **`UNIQUE(scope, key)`** (AR-2).
2. **Replay on match.** `IdempotencyService::remember(scope, key, fingerprint, fn)` called twice with the **same key + same fingerprint** runs the work **once** and the second call **replays the stored response** (FR-051, AR-2).
3. **Fingerprint mismatch → 422.** Same `(scope, key)` with a **different** `request_fingerprint` returns **422** (a domain `IdempotencyConflictException` mapped to 422), **never** a wrong cached replay.
4. **Concurrency → one writer.** Two concurrent first-calls (two DB connections) resolve to **one** writer via the `UNIQUE(scope,key)` claim; the loser does **not** double-write.
5. **Consumer-agnostic + testable.** Tested via a throwaway closure — no HTTP, no Applications, nothing submission- or payment-specific in the signature (the Geidea callback adopts it later for free).

### ★ Edge-case hardening (must-fix-before-green — folded from the Boundary & Edge Case Sweep)

6. **Scope isolation.** Uniqueness is `(scope, key)` — the **same key under different scopes is independent**. Include a **negative cross-scope test** (same `key`, different `scope` → two independent effects, no replay).
7. **Fingerprint includes the actor.** The caller composes `fingerprint` to incorporate the **actor** (e.g. `sub`) so the **same key from a different actor → fingerprint mismatch → 422**, never a cross-actor replay leak. Provide a small fingerprint helper and a test proving actor-A's key cannot replay actor-B's response. (The service stores the opaque fingerprint; the *contract* is "actor is in the fingerprint" — document it loudly and test it.)
8. **In-flight → 409.** A duplicate arriving while the first call is still running (row **claimed, no response yet, lock not stale**) returns **409** (`IdempotencyInFlightException`), distinct from the 422 mismatch and from a completed replay.
9. **`fn` failure semantics — pick one and test it.** **Decision: key-release on failure** — if `fn` throws, the claim is released (row removed/reclaimable) so a genuine retry can run; the exception propagates. (We do **not** cache-and-replay failures.) Documented + tested.
10. **Response size cap.** `response_snapshot` has a **max-size cap** (config). An oversize response **fails closed** (release the key, throw) — **never** truncate-and-replay a partial response.
11. **Crash-before-response is recoverable.** A crash **between `fn` success and the response write** leaves a stale claim (lock older than a timeout / past `expires_at`); a later call **reclaims** the stale lock and re-runs — **never locked-forever**.
12. **TTL is explicit.** `expires_at` is set; **hit-after-expiry re-runs as new** (declared behavior, not accidental). Document the retention window and that exactly-once holds *within* it (durable replay for callbacks, AR-2 "durable replay > redis TTL").

## Tasks / Subtasks

- [x] **Task 1 — Schema: `idempotency_keys`** (AC: 1, 6)
  - [x] Migration `database/migrations/2026_06_20_0002xx_create_idempotency_keys_table.php` (after the `blobs` migration `…000100`).
  - [x] Columns: `scope` (string), `key` (string), `request_fingerprint` (string), `response_snapshot` (jsonb, nullable), `status` (string — `claimed` | `completed`), `locked_at` (timestampTz, nullable), `expires_at` (timestampTz, nullable), `created_at` (timestampTz useCurrent). **`$t->unique(['scope','key'])`**. Index `expires_at` for reclaim/GC scans. **No `organization_id`** — the *scope* string carries any tenancy the caller wants (consumer-agnostic); document this.
- [x] **Task 2 — `IdempotencyKey` model + status enum** (AC: 1)
  - [x] `app/Shared/Idempotency/IdempotencyKey.php` — `final class … extends Model`, `$guarded=[]`, casts (`response_snapshot`=>'array', timestamps as datetime, `$timestamps=false` with DB `useCurrent`). Mirror `App\Shared\Audit\AuditLog`.
  - [x] `app/Shared/Idempotency/IdempotencyStatus.php` — `enum: string { case Claimed='claimed'; case Completed='completed'; }` (mirror `App\Shared\Versioning\VersionStatus`).
- [x] **Task 3 — Exceptions** (AC: 3, 8)
  - [x] `app/Shared/Idempotency/Exceptions/IdempotencyConflictException.php` (fingerprint mismatch → maps to **422**).
  - [x] `app/Shared/Idempotency/Exceptions/IdempotencyInFlightException.php` (in-flight → maps to **409**).
  - [x] `app/Shared/Idempotency/Exceptions/ResponseTooLargeException.php` (oversize response → fail-closed).
  - [x] **HTTP mapping note:** bootstrap/app.php's exception renderer maps known types to status codes; these are **not yet HTTP-wired in 2.2** (no endpoint here). Build them as plain exceptions with intended status documented; the 422/409 mapping is exercised by the submit endpoint in Story 2.7. (Do not add HTTP wiring in this story — it has no route.)
- [x] **Task 4 — `IdempotencyService::remember()`** (AC: all) — `app/Shared/Idempotency/IdempotencyService.php`, `final class`. State machine:
  - [x] **Claim:** attempt `INSERT` of `(scope, key, fingerprint, status=claimed, locked_at=now, expires_at=now+ttl)`. Catch `UniqueConstraintViolationException` → go to **Existing** branch (this is the proven concurrency guard from Story 2.1).
  - [x] **Owned (insert succeeded):** run `fn`; on success serialize the result, enforce the **size cap** (AC-10: oversize → release key + throw `ResponseTooLargeException`), persist `response_snapshot` + `status=completed`, return the result. On `fn` throwing → **release the claim** (delete the row, AC-9) and rethrow.
  - [x] **Existing branch:** reload the row. If a **stale claim** (status=claimed AND (`locked_at` < now−lockTimeout OR past `expires_at`)) → **reclaim** (take ownership, AC-11) and run as owned. Else: fingerprint mismatch → `IdempotencyConflictException` (AC-3); status=claimed (fresh) → `IdempotencyInFlightException` (AC-8); status=completed AND past `expires_at` → treat as new/re-run (AC-12); status=completed AND fresh → **replay `response_snapshot`** (AC-2).
  - [x] All branch transitions are concurrency-safe (the unique insert is the claim; reclaim uses a guarded `UPDATE … WHERE status='claimed' AND locked_at=<seen>` so two reclaimers don't both win).
  - [x] **Signature is generic:** `remember(string $scope, string $key, string $fingerprint, \Closure $fn): mixed`. Nothing submission/payment-specific.
- [x] **Task 5 — Fingerprint helper** (AC: 7)
  - [x] `app/Shared/Idempotency/RequestFingerprint.php` (or a static method) that composes a fingerprint from **actor + canonical request shape** (`hash('sha256', actor . '|' . canonicalJson(payload))`), so different actors never collide on a key. Document that callers MUST route actor through this.
- [x] **Task 6 — Config** (AC: 10, 11, 12)
  - [x] `config/idempotency.php`: `ttl_seconds` (expires_at horizon; default e.g. 86400), `lock_timeout_seconds` (stale-claim reclaim threshold; default e.g. 60), `max_response_bytes` (cap; default e.g. 65536).
- [x] **Task 7 — Tests** (AC: all) — see Testing Requirements.

## Dev Notes

### Where this fits
Second E2.0 gate story (after 2.1 blob storage, which is in review). The gate order is 2.1 → **2.2** → 2.3 outbox table+producer → 2.4 relay → 2.5 audit; feature stories 2.6–2.8 are `blocked-by: GATE-E2.0`. [Source: epics.md#Epic 2 / E2.0; GATE-E2.0 checklist]

### Architecture & conventions (verified in Story 2.1 — reuse them)
- New shared kernel at **`app/Shared/Idempotency/`** (sibling to `Storage`, `Versioning`, `Audit`). Confirmed **empty** (`.gitkeep` only) — net-new, **not** reuse. [Source: architecture.md#Foundation Stress-Test; verified 2026-06-20]
- **Class style:** `declare(strict_types=1);`, `final class`, explicit types. Service to mirror: `App\Shared\Versioning\VersionPublisher` (final, `DB::transaction`). Model to mirror: `App\Shared\Audit\AuditLog`. Enum to mirror: `App\Shared\Versioning\VersionStatus`. [Source: backend/app/Shared/]
- **Migration style:** anon class, `Schema::create`, `$t->jsonb()`, `$t->timestampTz()->useCurrent()`, `$t->unique([...])`. Date-ordered filename after `2026_06_20_000100`. [Source: backend/database/migrations/2026_06_18_000100_create_audit_logs_table.php, 2026_06_20_000100_create_blobs_table.php]
- ADR-2: build `idempotency_keys` fresh in **postgres**; `UNIQUE(scope,key)` + stored fingerprint + stored response; same key + diff fingerprint → 422; same+same → replay. **Durable replay > redis TTL** (callbacks must survive a redis flush). Do **not** stretch the versioning/immutability kernel to cover this — "did this operation already happen?" ≠ "is this value frozen?". [Source: architecture.md ADR-2]
- AR-7 FMA tripwire: **claim the key (insert-first) before doing work** — never do-work-then-record. [Source: epics.md AR-7]

### Previous-story intelligence (from Story 2.1 — apply directly)
- **Test env is SQLite `:memory:`** (phpunit.xml) — `jsonb`/`timestampTz` map cleanly via Laravel grammar; no postgres needed for tests. [Learned 2.1]
- **The concurrency guard pattern is proven:** insert-first on the UNIQUE constraint; the loser catches `Illuminate\Database\UniqueConstraintViolationException` and branches to the existing-row path. Reuse this exact approach for the claim. [Learned 2.1: `ContentAddressedStore::store`]
- **SQLite `:memory:` cannot truly run two parallel connections** — model the concurrency AC via the deterministic claim/loser path (insert succeeds vs unique-violation branch) and assert "exactly one effect". Note in completion that deep two-connection chaos lands with the integration env (2.4 relay / later). [Learned 2.1]
- **Pint** must pass (run `vendor/bin/pint <paths>`). **PHPStan currently OOMs in this environment on every file** (128 MB worker limit, larastan reflection) — it is a pre-existing tooling issue, not a code defect; validate with `php -l` + the runtime suite, and flag the PHPStan memory limit for CI. [Learned 2.1]
- Tests are **PHPUnit class-style** (`final class XTest extends TestCase`, `use RefreshDatabase`, `test_*` methods), namespaces `Tests\Unit\…` / `Tests\Feature\…`. [Source: backend/tests/Unit/AuditLoggerTest.php]

### The state machine (build to this — it's the heart of the story)
```
remember(scope, key, fp, fn):
  try INSERT(scope,key,fp,status=claimed,locked_at=now,expires_at=now+ttl)   ← the claim
  caught UNIQUE violation → EXISTING:
        row = reload(scope,key)
        if row.status==claimed AND stale(row)   → reclaim → run as OWNED
        elif row.fingerprint != fp              → throw Conflict (422)
        elif row.status==claimed                → throw InFlight (409)
        elif row.status==completed AND expired  → run as OWNED (re-run, AC-12)
        else (completed, fresh)                 → return replay(row.response_snapshot)
  OWNED:
        try result = fn()
        catch → release(scope,key); rethrow              (AC-9 key-release)
        json = encode(result); if size>cap → release; throw TooLarge   (AC-10)
        UPDATE set response_snapshot=json, status=completed
        return result
  stale(row) = row.locked_at < now-lockTimeout OR now > row.expires_at      (AC-11)
```

### Project Structure Notes
- New under `app/Shared/Idempotency/`: `IdempotencyService.php`, `IdempotencyKey.php`, `IdempotencyStatus.php`, `RequestFingerprint.php`, `Exceptions/{IdempotencyConflictException,IdempotencyInFlightException,ResponseTooLargeException}.php`; one migration; `config/idempotency.php`. No existing files modified (no HTTP wiring here).
- **Deliberate:** no `organization_id` on `idempotency_keys` — tenancy (if any) rides in the `scope` string; consumer-agnostic per the design constraint. Document so review doesn't "fix" it.

### References
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.2: Idempotency primitive]
- [Source: _bmad-output/planning-artifacts/epics.md#Edge-Case Hardening — 2.2 ★ ; #Top-3 must-fix-before-green]
- [Source: _bmad-output/planning-artifacts/architecture.md — ADR-2, AR-7 (FMA tripwires)]
- [Source: _bmad-output/implementation-artifacts/2-1-content-addressed-blob-storage.md — concurrency pattern + env learnings]
- [Source: backend/app/Shared/Versioning/VersionPublisher.php, VersionStatus.php ; backend/app/Shared/Audit/AuditLog.php — patterns to mirror]

### Glossary (Dev-Story Handoff Contract)
- **idempotency_keys** — the durable claim/replay table; `UNIQUE(scope,key)`; stores fingerprint + response so a retry replays rather than re-runs. (This story.)
- **fingerprint** — an opaque hash the *caller* composes from **actor + canonical request shape**; the cross-actor and mismatch guards (AC-3/7) depend on the actor being inside it.
- **GATE-E2.0** — passes only with: idempotency replays on key+fingerprint match, **409 in-flight**, **422 on fingerprint mismatch**, recovers from crash-before-response; (plus the outbox/blob items from sibling stories). This story owns the idempotency clauses. [Source: epics.md#GATE-E2.0]
- **tenant isolation** — N/A directly (consumer-agnostic, scope-carried); the submit endpoint (2.7) supplies a tenant-derived scope.

## Testing Requirements

Per CLAUDE.md mandate + Per-story DoD. PHPUnit class-style, `RefreshDatabase`, throwaway closures (no HTTP/Applications):
- **Replay (AC-2):** same scope+key+fingerprint twice → `fn` runs once (use a counter), second call returns the first result.
- **Mismatch (AC-3):** same scope+key, different fingerprint → `IdempotencyConflictException` (assert intended 422), `fn` not re-run, original response intact.
- **Concurrency (AC-4):** simulate the loser path — pre-insert a claimed/completed row, then `remember` with the same key resolves without a second effect (counter stays 1). Note SQLite-memory single-connection caveat.
- **Scope isolation (AC-6):** same `key`, different `scope` → two independent effects (counter == 2).
- **Actor isolation (AC-7):** key K, actor A completes; actor B with the same key but actor-B fingerprint → `IdempotencyConflictException`, never A's response.
- **In-flight (AC-8):** a claimed, non-stale row present → second `remember` throws `IdempotencyInFlightException` (409).
- **`fn` failure (AC-9):** `fn` throws → exception propagates AND the key is released (a subsequent `remember` re-runs `fn`, counter increments).
- **Size cap (AC-10):** `fn` returns an oversize payload → `ResponseTooLargeException`, no `completed` row, nothing truncated/replayable.
- **Crash recovery (AC-11):** a claimed row with `locked_at` older than `lock_timeout` (or past `expires_at`) → next `remember` reclaims and runs `fn` (recovers, not locked-forever).
- **TTL (AC-12):** a completed row past `expires_at` → next `remember` re-runs as new.
- Lint: `vendor/bin/pint` pass. `php -l` clean. (PHPStan: attempt with raised memory; if it still OOMs env-wide, record per 2.1.)

## Dev Agent Record

### Agent Model Used

claude-opus-4-8[1m] (Claude Opus 4.8, 1M context)

### Debug Log References

- `php artisan test --filter Idempotency` → 11 passed, 26 assertions.
- `php artisan test` (full) → **288 passed, 911 assertions, 0 failures** (no regressions; +11 over 2.1's 277).
- `vendor/bin/pint` → passed (one auto-fix: phpdoc alignment).
- One test bug caught + fixed mid-run: `assertDatabaseCount`'s 3rd arg is the DB *connection*, not a message — removed the descriptive strings (implementation was correct).
- PHPStan: same environment-wide OOM as Story 2.1 (not a code defect) — validated via `php -l` (all 9 files clean) + runtime suite. CI must raise PHPStan's memory limit.

### Completion Notes List

- Ultimate context engine analysis completed — comprehensive developer guide created (2026-06-20).
- Built the consumer-agnostic `IdempotencyService::remember(scope, key, fingerprint, fn)` state machine over a durable `idempotency_keys` table — claim-first on `UNIQUE(scope,key)` (the same proven guard as Story 2.1's blob digest PK), with the full ★ hardening set:
  - **422 fingerprint-mismatch** and **409 in-flight** as distinct exceptions; mismatch is checked *before* stale-reclaim so a different actor can never hijack/replay a key (AC-3/7/8).
  - **Key-release on `fn` failure** (chosen failure semantics — no cached-failure replay) (AC-9).
  - **Response size cap** fails closed, never truncate-and-replay (AC-10).
  - **Crash-before-response recovery** via stale-lock reclaim (`lock_timeout_seconds`), optimistically guarded so two reclaimers can't both win (AC-11).
  - **Explicit TTL** — completed entries past `expires_at` re-run as new (AC-12).
- **Actor isolation** is a *contract*: `RequestFingerprint::for(actor, payload)` folds the actor into the hash, so the service stays opaque/generic while AC-7 is enforced + tested.
- **Decimal-free / generic:** `mixed` results wrapped as `{"value": …}` so scalars/null/arrays round-trip through jsonb (test-proven).
- **Deliberate:** no `organization_id` (consumer-agnostic; tenancy rides in `scope`) — documented in the migration, mirrors the 2.1 blob decision.
- **Concurrency caveat (carried from 2.1):** SQLite `:memory:` can't run two live connections, so AC-4 is proven via the deterministic loser path (claim insert → unique violation → replay); deep two-connection chaos lands with the integration env at Story 2.4 (relay).

### Change Log

| Date | Change |
|---|---|
| 2026-06-20 | Implemented Story 2.2 — durable consumer-agnostic idempotency service (`App\Shared\Idempotency`), `idempotency_keys` table, fingerprint helper, 3 exceptions, config, 11 tests. Status → review. |

### File List

**New:**
- `backend/config/idempotency.php`
- `backend/database/migrations/2026_06_20_000200_create_idempotency_keys_table.php`
- `backend/app/Shared/Idempotency/IdempotencyService.php`
- `backend/app/Shared/Idempotency/IdempotencyKey.php`
- `backend/app/Shared/Idempotency/IdempotencyStatus.php`
- `backend/app/Shared/Idempotency/RequestFingerprint.php`
- `backend/app/Shared/Idempotency/Exceptions/IdempotencyConflictException.php`
- `backend/app/Shared/Idempotency/Exceptions/IdempotencyInFlightException.php`
- `backend/app/Shared/Idempotency/Exceptions/ResponseTooLargeException.php`
- `backend/tests/Unit/Idempotency/IdempotencyServiceTest.php`
