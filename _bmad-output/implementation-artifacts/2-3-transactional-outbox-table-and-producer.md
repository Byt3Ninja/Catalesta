---
baseline_commit: 72666b820ad6ef2d384af2ad776dbb7d15528ae2
---
# Story 2.3: Transactional outbox — table and producer

Status: review

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

> **Track A (backend critical path) · E2.0 reliability-gate member (3 of 5).** This story builds **only the table + the producer** — the write side. The relay worker (claim/dispatch/retry/dead-letter) is **Story 2.4** and is explicitly out of scope here. Tested with a hand-inserted / rolled-back row; **no relay, no real consumer**. `GATE-E2.0` member.

## Story

As a **platform engineer**,
I want domain events written in the same transaction as the state change,
so that an event is never lost or orphaned relative to its data.

## Acceptance Criteria

From epics.md (Story 2.3) + FR-050/AR-3/AR-7 + the ★ Edge-Case Hardening:

1. **Schema.** An `outbox_events` table exists with at least: `id`, `event_type`, `payload`, `dispatched_at`.
2. **Same-transaction write.** When a domain write occurs, the outbox row is inserted **inside the same DB transaction** as the domain write (FR-050, AR-3): if the transaction rolls back, **neither the data nor the event exists**.
3. **Producer is the only writer.** Request handlers **never dispatch to redis (or any transport) directly** — only the producer writes the outbox row (AR-7 code-review tripwire). No event is "fired" in this story; it is only recorded.
4. **Scope.** Tested with a hand-inserted and a rolled-back row; **no relay or real consumer is built or required yet** (that is Story 2.4).

### ★ Edge-case hardening (must-fix-before-green)

5. **Rollback invariant (the proof).** A test must prove: a domain transaction that **aborts** leaves **no orphan outbox row** (and no domain row) — this is the test that proves the in-transaction invariant, not an afterthought.

## Tasks / Subtasks

- [x] **Task 1 — Schema: `outbox_events`** (AC: 1, 2)
  - [x] Migration `database/migrations/2026_06_20_0003xx_create_outbox_events_table.php` (after `…000200` idempotency).
  - [x] Columns: `id` (**ulid, primary** — this is the `event_id` the Story 2.4 consumer dedupes on), `event_type` (string), `payload` (jsonb), `dispatched_at` (timestampTz, **nullable**, **indexed** — the relay's claim marker in 2.4; null = undispatched), `created_at` (timestampTz useCurrent). **No `organization_id`** unless a tenant scope is genuinely needed — keep the substrate generic (any tenant context rides in the `payload`); document the choice. [Source: epics.md Story 2.3 schema; ADR-4]
  - [x] **Forward-compat note (do not over-build):** Story 2.4 needs per-aggregate ordering and `event_id` idempotency. `id` (ulid) already serves as `event_id`. Ordering columns (`aggregate_type`/`aggregate_id`) are a **2.4** concern — add them then unless trivially free now; if you add them, make them nullable and document. ponytail: don't speculatively model 2.4's relay needs.
- [x] **Task 2 — `OutboxEvent` model** (AC: 1)
  - [x] `app/Shared/Outbox/OutboxEvent.php` — `final class … extends Model`, `use HasUlids`, `$guarded=[]`, casts (`payload`=>'array', `dispatched_at`=>'datetime', `created_at`=>'datetime'), `$timestamps=false`. Mirror `App\Shared\Idempotency\IdempotencyKey`.
- [x] **Task 3 — `OutboxProducer` (the recorder)** (AC: 2, 3)
  - [x] `app/Shared/Outbox/OutboxProducer.php` — `final class`. Method `record(string $eventType, array $payload): OutboxEvent` that **inserts** an `OutboxEvent` (`dispatched_at = null`). It performs **no transport dispatch** — it only writes the row.
  - [x] **In-transaction contract:** `record()` MUST be called **within the caller's `DB::transaction`** (alongside the domain write) so atomicity holds. The producer does **not** open its own transaction — wrapping it in one of its own would defeat the "same transaction as the domain write" guarantee. Document this loudly in the method docblock (the call site owns the transaction; the producer just enlists in it).
  - [x] (Optional convenience, only if it reads cleanly) a typed event-name approach is **not** required here; `string $eventType` is the P1a contract.
- [x] **Task 4 — Tests** (AC: all, esp. ★ 5) — see Testing Requirements.

## Dev Notes

### Where this fits
Third E2.0 gate story. Order: 2.1 blob (review) → 2.2 idempotency (review) → **2.3 outbox table+producer** → 2.4 relay worker (completes the gate) → 2.5 audit. The split is deliberate: **2.3 = durable write side (this), 2.4 = at-least-once delivery side**. Keeping them separate keeps each independently testable. [Source: epics.md#Epic 2 / E2.0]

### Architecture & conventions (verified — reuse from 2.1/2.2)
- New kernel at **`app/Shared/Outbox/`** — confirmed **empty** (`.gitkeep` only); net-new. [verified 2026-06-20]
- **Class/model/migration/enum style** identical to Stories 2.1/2.2: `declare(strict_types=1)`, `final class`, `HasUlids`, `$guarded=[]`, anon migration with `$t->jsonb()`/`$t->timestampTz()->useCurrent()`. Mirror `App\Shared\Idempotency\IdempotencyKey` (model) and the `2026_06_20_000200_create_idempotency_keys_table.php` migration (just committed). [Source: backend/app/Shared/Idempotency/]
- **The integration pattern the producer plugs into:** domain actions are `final class …Application\X { public function handle(...) { return DB::transaction(fn () => { /* domain write + $this->audit->record(...) */ }); } }` with collaborators constructor-injected (e.g. `AuditLogger`). The `OutboxProducer` is injected the **same way** and `record()` is called **inside** that same `DB::transaction` closure. [Source: backend/app/Modules/Organizations/Application/CreateOrganization.php]
- ADR-4: build `outbox_events` (`dispatched_at`); the relay (2.4) claims rows **atomically** (`UPDATE … SET dispatched_at WHERE dispatched_at IS NULL RETURNING`, never SELECT-then-UPDATE) + consumer-side `event_id` idempotency. **This story stops at the table + producer** — do not build the relay. [Source: architecture.md ADR-4]
- AR-7 FMA tripwire: **the outbox insert lives inside the domain DB transaction; handlers never dispatch directly.** This is the whole point of the story. [Source: epics.md AR-7]

### Previous-story intelligence (from 2.1 / 2.2 — apply directly)
- **Test env is SQLite `:memory:`**; `jsonb`/`timestampTz` map cleanly; `RefreshDatabase`; PHPUnit class-style. [Learned 2.1]
- **`DB::transaction` rollback works in tests** — wrap a write + a throw and assert nothing persisted (this is exactly the ★ rollback proof). Laravel rolls back on any exception escaping the closure.
- **Pint must pass; PHPStan OOMs environment-wide** (not a code defect — validate via `php -l` + suite, flag for CI). [Learned 2.1/2.2]
- Migrations are date-ordered; the latest is `2026_06_20_000200` — use `2026_06_20_000300`. [Source: backend/database/migrations/]

### Project Structure Notes
- New: `app/Shared/Outbox/OutboxEvent.php`, `app/Shared/Outbox/OutboxProducer.php`; one migration. **No existing files modified** — no domain module wires the producer yet (the first real producer call site is the application-submit flow in Story 2.7; here it is exercised by tests only, matching "no real consumer required yet").
- **Anti-scope:** do NOT build the relay worker, retries, dead-letter, or any transport — all Story 2.4.

### References
- [Source: _bmad-output/planning-artifacts/epics.md#Story 2.3: Transactional outbox — table and producer]
- [Source: _bmad-output/planning-artifacts/epics.md#Edge-Case Hardening — 2.3]
- [Source: _bmad-output/planning-artifacts/architecture.md — ADR-4, AR-7]
- [Source: _bmad-output/implementation-artifacts/2-2-idempotency-primitive.md — patterns + env learnings]
- [Source: backend/app/Modules/Organizations/Application/CreateOrganization.php — DB::transaction domain-action pattern]

### Glossary
- **outbox_events** — durable record of domain events, written in the same transaction as the state change; drained at-least-once by the relay (Story 2.4). (This story: the table + the write side.)
- **producer** — the only component allowed to write an outbox row; handlers never dispatch to a transport directly (AR-7).
- **dispatched_at** — null until the relay (2.4) claims + delivers the row; the claim marker.
- **GATE-E2.0** — passes only when (among others) the outbox insert is inside the domain txn (rollback leaves no orphan). This story owns that clause. [Source: epics.md#GATE-E2.0]

## Testing Requirements

PHPUnit class-style, `RefreshDatabase`:
- **Happy write (AC-2):** inside a committed `DB::transaction`, `OutboxProducer::record('order.placed', [...])` → exactly one `outbox_events` row with `dispatched_at` null and `payload` intact (jsonb round-trip).
- **★ Rollback invariant (AC-5):** a `DB::transaction` that writes a domain row (use any existing model, or a throwaway) **and** calls `record()` then **throws** → assert **zero** outbox rows **and** zero domain rows (neither persisted). This is the gate proof.
- **event_id (AC-1):** `id` is a populated ULID (the 2.4 consumer-dedupe key).
- **Producer writes no transport (AC-3):** assert (structurally / by code) the producer only persists a row — no Bus/Queue/Redis dispatch. (A simple `Bus::fake()`/`Queue::fake()` asserting nothing was dispatched is a clean way to encode the tripwire.)
- Lint: `vendor/bin/pint` pass; `php -l` clean. PHPStan per 2.1/2.2 note.

## Dev Agent Record

### Agent Model Used

claude-opus-4-8[1m] (Claude Opus 4.8, 1M context)

### Debug Log References

- `php artisan test --filter Outbox` → 4 passed, 12 assertions.
- `php artisan test` (full) → **292 passed, 923 assertions, 0 failures** (no regressions; +4 over 2.2's 288).
- `vendor/bin/pint` → passed (no fixes needed).
- PHPStan: same env-wide OOM as 2.1/2.2 (flagged; validated via `php -l` + suite).

### Completion Notes List

- Ultimate context engine analysis completed — comprehensive developer guide created (2026-06-20).
- Built the **write side** only: `outbox_events` table + `OutboxProducer::record(eventType, payload)`. The relay/claim/retry/dead-letter is deliberately **left for Story 2.4** — kept this story to the durable-write invariant.
- **★ Rollback invariant proven:** a transaction that writes a domain row (idempotency_keys stand-in) **and** records an event, then throws, leaves **zero** rows in *both* tables — the test that proves the in-transaction guarantee (AR-7).
- **AR-7 tripwire encoded as a test:** `Bus::fake()` + `Queue::fake()` assert the producer dispatches to **no transport** — it only writes a row. This guards the "handlers never dispatch directly" rule in CI, not just in prose.
- `id` is a ULID = the `event_id` the 2.4 consumer will dedupe on (asserted via `Str::isUlid`).
- **Deliberate:** no `organization_id` (generic substrate; tenant context rides in `payload`); aggregate ordering columns deferred to 2.4 — documented in the migration to prevent a premature "fix".

### Change Log

| Date | Change |
|---|---|
| 2026-06-20 | Implemented Story 2.3 — transactional outbox table + producer (`App\Shared\Outbox`), 4 tests incl. the rollback invariant + no-transport tripwire. Status → review. |

### File List

**New:**
- `backend/database/migrations/2026_06_20_000300_create_outbox_events_table.php`
- `backend/app/Shared/Outbox/OutboxEvent.php`
- `backend/app/Shared/Outbox/OutboxProducer.php`
- `backend/tests/Unit/Outbox/OutboxProducerTest.php`
