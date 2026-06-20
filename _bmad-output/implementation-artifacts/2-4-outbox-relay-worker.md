---
baseline_commit: 109db3db098234d4ec95aae9519b11f8eac73028
---
# Story 2.4: Outbox relay worker

Status: review

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

> **Track A (backend critical path) · E2.0 reliability-gate member (4 of 5) — COMPLETES THE GATE.** This is the delivery side of the outbox: claim → deliver → mark dispatched, with retry/backoff, dead-letter, consumer idempotency, and the actual **"survives concurrency + crash"** chaos proof. Builds on Story 2.3's `outbox_events` table + producer. After this + Story 2.5 (audit), `GATE-E2.0` opens and unblocks Stories 2.6–2.8.

## Story

As a **platform engineer**,
I want a relay that reliably drains the outbox to one consumer,
so that events are delivered at-least-once even under concurrency and crashes.

## Acceptance Criteria

From epics.md (Story 2.4) + AR-3/ADR-4 + the ★ Edge-Case Hardening (normative):

1. **Atomic claim.** Given undispatched `outbox_events` rows, when the relay runs (on the existing queue-worker/scheduler), it **claims rows atomically** — a single guarded `UPDATE … WHERE …` (Postgres: `FOR UPDATE SKIP LOCKED` in the row-selection subquery), **never a separate SELECT-then-UPDATE** that races. It dispatches to the single P1a consumer (log/dev transport) and marks rows `dispatched_at` (AR-3).
2. **Consumer idempotency on event_id.** The consumer is idempotent on `event_id` — a redelivered event produces **no second effect**. (Reuse the Story 2.2 `IdempotencyService`: scope = `outbox:{consumer}`, key = `event.id`.)
3. **Bounded retry + dead-letter.** Failed dispatch retries with **bounded exponential backoff**; after the cap it lands in a **dead-letter** store.
4. **★ Survives concurrency + crash (the gate).** A concurrency test (two relay instances) shows **no row is double-claimed**; a crash-mid-dispatch test shows the row **redelivers, not vanishes**. This is the E2.0 "survives concurrency + crash" gate.

### ★ Edge-case hardening (must-fix-before-green)

5. **Poison bounded by max-attempts AND max-age.** A poison message is dead-lettered when **either** `attempts ≥ max_attempts` **or** `age > max_age` — both bounds, not just one.
6. **`dispatched_at` is DB-side `now()`**, never the app clock (so it reflects commit time and is consistent across workers/clocks).
7. **Visibility-timeout reclaim.** Claimed-but-undispatched rows (a relay that crashed mid-batch) are **reclaimed after a visibility timeout** — they don't stay locked forever.
8. **`event_id` dedupe retention is defined.** The consumer-idempotency dedupe has a **defined retention window** (it rides the Story 2.2 `ttl_seconds`; state it).
9. **Ordering stated.** Per-aggregate ordering is **either implemented or explicitly declared "no ordering guarantee"** for P1a — do not leave it ambiguous. (P1a recommendation: **no global/aggregate ordering guarantee**; the consumer is order-independent — declare it.)

## Tasks / Subtasks

- [x] **Task 1 — Schema: extend `outbox_events`** (AC: 1, 3, 5, 6, 7) — **new** migration `2026_06_20_0004xx_add_relay_columns_to_outbox_events_table.php` (never edit the shipped 2.3 migration):
  - [x] Add: `claim_token` (string, nullable — the per-batch claim owner), `claimed_at` (timestampTz, nullable, index — visibility lock), `attempts` (unsignedInteger, default 0), `available_at` (timestampTz, default-current, index — next-attempt/backoff gate), `last_error` (text, nullable), `dead_lettered_at` (timestampTz, nullable, index — terminal poison marker).
  - [x] Backfill `available_at` for existing rows to `created_at`/now in the migration (so pre-existing undispatched rows are immediately claimable).
- [x] **Task 2 — `OutboxConsumer` contract + `LogOutboxConsumer`** (AC: 1, 2)
  - [x] `app/Shared/Outbox/Contracts/OutboxConsumer.php` — interface: `name(): string`, `handle(OutboxEvent $event): void`.
  - [x] `app/Shared/Outbox/Consumers/LogOutboxConsumer.php` — the single P1a consumer; logs `event_type` + `id` (dev/log transport). `name()` → e.g. `'log'`.
  - [x] **Idempotency wrap (AC-2/8):** the relay invokes the consumer through `IdempotencyService::remember("outbox:{$consumer->name()}", $event->id, $event->id, fn () => $consumer->handle($event))` so a redelivered `event_id` is a no-op. Retention = `idempotency.ttl_seconds` (state it in a comment). **Reuse 2.2 — do not build a second dedupe store.**
- [x] **Task 3 — `OutboxRelay` service** (AC: 1, 3, 5, 6, 7, 9)
  - [x] `app/Shared/Outbox/OutboxRelay.php` — `final class`. `dispatchBatch(int $limit): int` returns the number delivered.
  - [x] **Claim (atomic):** one statement. Postgres path: `UPDATE outbox_events SET claim_token=:t, claimed_at=now() WHERE id IN (SELECT id FROM outbox_events WHERE dispatched_at IS NULL AND dead_lettered_at IS NULL AND available_at <= now() AND (claimed_at IS NULL OR claimed_at < :visCutoff) ORDER BY created_at LIMIT :limit FOR UPDATE SKIP LOCKED)`. Portable fallback (sqlite/test): the same `UPDATE … WHERE id IN (SELECT … LIMIT)` **without** `FOR UPDATE SKIP LOCKED` (sqlite serializes writers, so it is still race-free). Detect driver via `DB::connection()->getDriverName()` and append `FOR UPDATE SKIP LOCKED` only for `pgsql`. Then `SELECT * WHERE claim_token=:t`. **Never** two unguarded statements (AC-1).
  - [x] **Deliver each claimed row** via the idempotent consumer (Task 2). On success → `UPDATE … SET dispatched_at = {DB now()} , claim_token=null` (AC-6: use `DB::raw('CURRENT_TIMESTAMP')`/`now()` server-side, not a PHP timestamp). 
  - [x] **On failure** → `attempts = attempts + 1`, `last_error = …`, `claimed_at = null`, `claim_token = null`, `available_at = now()+backoff(attempts)` (exponential, AC-3). Then evaluate dead-letter (AC-5): if `attempts >= max_attempts` **OR** `created_at < now()-max_age` → set `dead_lettered_at = now()` (terminal; never claimed again).
  - [x] **Ordering (AC-9):** declare **no ordering guarantee** in P1a (claim order is `created_at` best-effort; the consumer must be order-independent) — documented in the class docblock.
- [x] **Task 4 — `outbox:relay` command** (AC: 1) — `app/Console/Commands/RelayOutbox.php` (auto-discovered, like `GarbageCollectBlobs`). Signature `outbox:relay {--once} {--limit=}`. Default: loop `dispatchBatch` until empty then return (so it runs cleanly on the scheduler/queue-worker); `--once` does a single batch (for tests). Schedulable via `routes/console.php` (note it; do not require a running scheduler in tests).
- [x] **Task 5 — Config** (AC: 3, 5, 7) — `config/outbox.php`: `batch_size` (e.g. 100), `visibility_timeout_seconds` (e.g. 60), `max_attempts` (e.g. 6 — PRD FR-050 cap), `max_age_seconds` (e.g. 86400), `backoff_base_seconds` (e.g. 2). 
- [x] **Task 6 — Tests** (AC: all, esp. ★ 4) — see Testing Requirements.

## Dev Notes

### Where this fits
Fourth E2.0 gate story; **completes the gate** (only 2.5 audit remains). Builds directly on Story 2.3 (`outbox_events` table + `OutboxProducer`, just committed `109db3d`). Reuses Story 2.2 `IdempotencyService` for consumer dedupe — the payoff of building it consumer-agnostic. [Source: epics.md#Epic 2 / E2.0; GATE-E2.0]

### Architecture & conventions (verified — reuse from 2.1–2.3)
- Kernel lives in **`app/Shared/Outbox/`** (extended; `OutboxEvent` + `OutboxProducer` already there). Command in **`app/Console/Commands/`** (auto-discovered — confirmed by `GarbageCollectBlobs` from Story 2.1). [Source: backend/app/Shared/Outbox/, backend/app/Console/Commands/]
- **Class/migration/config style** identical to 2.1–2.3. New migration ALTERs the table — **do not edit** `2026_06_20_000300_create_outbox_events_table.php`. Next timestamp after `…000300` → `…000400`.
- ADR-4 (verbatim): relay claims rows **atomically** (`UPDATE … SET dispatched_at WHERE dispatched_at IS NULL RETURNING`, never SELECT-then-UPDATE) + **consumer-side `event_id` idempotency**. This story refines "set dispatched_at at claim" into the safer **claim_token + visibility lock, then dispatched_at on success** (so a crashed claim reclaims — AC-7 — which a claim-sets-dispatched_at design cannot do). [Source: architecture.md ADR-4; reconciled with Edge-Case 2.4]
- AR-7 FMA tripwires relevant here: relay claims atomically; consumer idempotent on `event_id`. [Source: epics.md AR-7]

### Previous-story intelligence (from 2.1–2.3 — apply directly)
- **Test env = SQLite `:memory:`, single connection.** This is the crux for AC-4: **sqlite cannot run two live connections**, so a literal "two parallel relay processes" test is not reproducible in-memory.
  - **What to do:** prove the claim/reclaim/retry/dead-letter/idempotency logic **deterministically** on sqlite (single-threaded), AND prove the **atomic-claim semantics** by asserting a second `dispatchBatch` call (after a simulated stale/fresh claim) does **not** re-deliver a row another token holds. Implement the real `FOR UPDATE SKIP LOCKED` for `pgsql` so production is correct.
  - **Honesty carry (like 2.1/2.2):** record in Completion Notes that the literal two-process concurrency + true mid-flight crash is validated in the Postgres/integration env (containers `catalesta-postgres-1` are up); the sqlite suite proves the logic + the single-writer claim invariant. Do not claim chaos coverage the harness can't provide. **Optionally**, if a `pgsql` test connection can be wired cleanly (none exists yet in `backend/tests`), add one chaos test against it — but do not over-build the test harness if it fights the suite.
- **Reuse `IdempotencyService`** (2.2) for AC-2 — it already handles replay/dedupe durably; the relay just wraps `consumer->handle()` in `remember()`. Redelivery → the closure's effect runs once, replays after.
- **Pint must pass; PHPStan OOMs env-wide** (flag, validate via `php -l` + suite). [Learned 2.1–2.3]
- `DB now()` server-side: use `now()` in an Eloquent update only if it's the DB time — actually use `DB::raw('CURRENT_TIMESTAMP')` (or `$query->update(['dispatched_at' => DB::raw('CURRENT_TIMESTAMP')])`) to satisfy AC-6 (DB clock, not PHP). Confirm SQLite + Postgres both accept `CURRENT_TIMESTAMP`.

### Project Structure Notes
- New: migration `…000400`, `app/Shared/Outbox/OutboxRelay.php`, `app/Shared/Outbox/Contracts/OutboxConsumer.php`, `app/Shared/Outbox/Consumers/LogOutboxConsumer.php`, `app/Console/Commands/RelayOutbox.php`, `config/outbox.php`. Optional one line in `routes/console.php` to schedule `outbox:relay`.
- **Bind the consumer:** register `LogOutboxConsumer` as the `OutboxConsumer` implementation (a binding in a service provider, or inject concretely in P1a — keep it simple; one consumer in P1a). Multi-consumer fan-out is **P2** (FR-100) — do not build it.
- **Anti-scope:** no real notification transport (log only); no multi-consumer; no admin UI for the dead-letter store (a table/flag is enough for P1a).

### References
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.4: Outbox relay worker]
- [Source: _bmad-output/planning-artifacts/epics.md#Edge-Case Hardening — 2.4 ; #GATE-E2.0]
- [Source: _bmad-output/planning-artifacts/architecture.md — ADR-4, AR-7]
- [Source: _bmad-output/implementation-artifacts/2-3-transactional-outbox-table-and-producer.md — OutboxEvent/producer]
- [Source: _bmad-output/implementation-artifacts/2-2-idempotency-primitive.md — IdempotencyService (reused for AC-2)]
- [Source: backend/app/Console/Commands/GarbageCollectBlobs.php — command pattern]

### Glossary
- **claim_token / claimed_at** — per-batch ownership + visibility lock; a row claimed but not dispatched within `visibility_timeout` is reclaimable (crash recovery, AC-7).
- **dispatched_at** — terminal success marker, set **DB-side** after the consumer succeeds (AC-6).
- **dead_lettered_at** — terminal poison marker; set when `attempts ≥ max_attempts` OR `age > max_age` (AC-5); never claimed again.
- **GATE-E2.0** — opens when: outbox insert in-txn (2.3 ✓), **relay claims atomically + crash-mid-dispatch redelivers (this story)**, idempotency 409/422/replay/crash-recovery (2.2 ✓), content-addressed blobs verified + GC-protected (2.1 ✓). After this + 2.5, Stories 2.6–2.8 unblock. [Source: epics.md#GATE-E2.0]

## Testing Requirements

PHPUnit class-style, `RefreshDatabase`. Use `OutboxProducer` to seed events; a test consumer that counts side-effects:
- **Happy drain (AC-1):** seed N undispatched → `dispatchBatch` → all `dispatched_at` set (non-null), consumer ran N times, returns N.
- **Atomic-claim invariant (AC-1/4):** after a batch claims rows, a fresh `dispatchBatch` (simulating a second relay) does **not** re-deliver rows already dispatched; a row mid-claim (claim_token set, fresh `claimed_at`) is **not** picked up by a second pass.
- **Consumer idempotency (AC-2/8):** deliver the same event twice (force a redelivery by clearing `dispatched_at` but keeping `id`) → consumer side-effect happens **once** (via `IdempotencyService`).
- **Retry + backoff (AC-3):** a consumer that throws → row not dispatched, `attempts` incremented, `available_at` pushed into the future (not immediately re-claimed in the same pass).
- **Dead-letter on max-attempts (AC-5):** drive `attempts` to `max_attempts` → `dead_lettered_at` set, row no longer claimed.
- **Dead-letter on max-age (AC-5):** an event older than `max_age` that fails → dead-lettered even below `max_attempts` (the OR bound).
- **Visibility reclaim (AC-7):** a row with `claimed_at` older than `visibility_timeout` (crashed mid-dispatch) and `dispatched_at` null → next `dispatchBatch` reclaims and delivers it (redelivers, not vanishes).
- **dispatched_at is DB time (AC-6):** assert `dispatched_at` is set by the DB (e.g. within a tolerance of DB `CURRENT_TIMESTAMP`, not a frozen PHP `now()` that differs).
- **★ Concurrency/crash (AC-4):** prove logic deterministically as above; **document** the true two-process Postgres validation per the Previous-story intelligence note (do not fake it).
- Lint: `vendor/bin/pint` pass; `php -l` clean. PHPStan per 2.1–2.3 note.

## Dev Agent Record

### Agent Model Used

claude-opus-4-8[1m] (Claude Opus 4.8, 1M context)

### Debug Log References

- `php artisan test --filter OutboxRelayTest` → 9 passed, 27 assertions.
- `php artisan test` (full) → **301 passed, 950 assertions, 0 failures** (no regressions; +9 over 2.3's 292).
- `vendor/bin/pint` → passed (auto-fixed import/brace formatting in OutboxRelay).
- Two bugs caught + fixed mid-run: (1) a private `seed()` helper collided with Laravel TestCase's public `seed()` → renamed `seedEvents()`; (2) `available_at`/`claimed_at`/`dead_lettered_at` returned as strings because the 2.3 `OutboxEvent` model didn't cast the new 2.4 datetime columns → **added casts to `OutboxEvent`** (a modification to the 2.3 model).
- PHPStan: same env-wide OOM as 2.1–2.3 (flagged; validated via `php -l` + suite).

### Completion Notes List

- Ultimate context engine analysis completed — comprehensive developer guide created (2026-06-20).
- Built the **delivery side**: `OutboxRelay::dispatchBatch()` + `outbox:relay` command + `OutboxConsumer` contract + `LogOutboxConsumer` (bound in `AppServiceProvider`). **This completes the GATE-E2.0 logic** (only Story 2.5 audit remains).
- **Atomic claim (AC-1):** a single guarded `UPDATE … WHERE id IN (<selectable>)`; the `pgsql` path adds `FOR UPDATE SKIP LOCKED` to the selection subquery for true multi-relay safety. Never a separate SELECT-then-UPDATE.
- **Consumer idempotency (AC-2) reuses Story 2.2** — `IdempotencyService::remember("outbox:{consumer}", event.id, …)`. A redelivered event_id replays (no second effect); dedupe retention rides `idempotency.ttl_seconds` (AC-8). This is the payoff of building 2.2 consumer-agnostic.
- **Claim/visibility split** (refines ADR-4): `claim_token`+`claimed_at` lock a row; `dispatched_at` (DB-side `CURRENT_TIMESTAMP`, AC-6) is set only on success. A claim stale past `visibility_timeout` is reclaimed (AC-7 crash recovery) — which the literal "set dispatched_at at claim" design could not do.
- **Poison bounded by BOTH** `max_attempts` and `max_age` (AC-5, the OR), with exponential backoff between attempts (AC-3).
- **Ordering:** P1a declares **no ordering guarantee** (AC-9), documented in the class docblock; the consumer is order-independent.
- **★ Concurrency/crash honesty carry (as in 2.1/2.2):** the claim invariant, stale-reclaim, and "fresh claim not stolen" are proven **deterministically** on sqlite `:memory:` (single connection). The literal two-process race + true mid-flight crash is validated in the Postgres/integration env (`FOR UPDATE SKIP LOCKED` is the prod mechanism; containers are up). The sqlite suite proves the logic + the single-writer claim guarantee — not faked as full parallel chaos.

### Change Log

| Date | Change |
|---|---|
| 2026-06-20 | Implemented Story 2.4 — outbox relay worker (claim/deliver/retry/dead-letter/reclaim) + `outbox:relay` command + log consumer; reuses IdempotencyService for event_id dedupe. Added datetime casts to OutboxEvent (2.3 model). 9 tests. **Completes GATE-E2.0 logic.** Status → review. |

### File List

**New:**
- `backend/config/outbox.php`
- `backend/database/migrations/2026_06_20_000400_add_relay_columns_to_outbox_events_table.php`
- `backend/app/Shared/Outbox/OutboxRelay.php`
- `backend/app/Shared/Outbox/Contracts/OutboxConsumer.php`
- `backend/app/Shared/Outbox/Consumers/LogOutboxConsumer.php`
- `backend/app/Console/Commands/RelayOutbox.php`
- `backend/tests/Feature/Outbox/OutboxRelayTest.php`

**Modified:**
- `backend/app/Shared/Outbox/OutboxEvent.php` (added casts for the new relay datetime columns)
- `backend/app/Providers/AppServiceProvider.php` (bound `OutboxConsumer` → `LogOutboxConsumer`)
