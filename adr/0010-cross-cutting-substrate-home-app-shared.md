# ADR 0010: Cross-Cutting Substrate Home is `app/Shared/`

## Status

Accepted

## Context

Epic R/A Story RA.1 and `architecture.md` Step 6 ("Reliability/Audit home")
specified a greenfield top-level `backend/app/Reliability/` directory, "sibling
to `app/Tenancy/` and `app/Storage/`", as the cross-cutting home for the
Audit / Outbox / Idempotency / Webhooks substrate. The Step-6 comparative
analysis ("Opt 2 won") justified this by asserting "the repo already houses
substrate at `app/<concern>/` (`app/Tenancy/`, `app/Storage/`)".

Code inspection on 2026-06-23 shows that premise is false:

- There is **no** top-level `app/Tenancy/` or `app/Storage/`. Cross-cutting
  concerns live under **`app/Shared/`**: `Shared/Tenancy/`, `Shared/Storage/`,
  `Shared/Entitlement/`, `Shared/Telemetry/`, `Shared/Rules/`,
  `Shared/Versioning/`, `Shared/Support/`.
- The Reliability substrate **already exists** there: `app/Shared/Audit/`,
  `app/Shared/Outbox/`, `app/Shared/Idempotency/` were built and tested in
  Epic 2's P1a reliability gate (config: `config/outbox.php`,
  `config/idempotency.php`, `config/blob.php`).

So the *decision* Step 6 reached — the substrate is cross-cutting, not a domain
module — is correct, but the *path* is wrong, and the "to be created" framing is
stale (three of the four sub-areas already exist). Creating a new top-level
`app/Reliability/` would either duplicate or force a migration of tested code
for no behavioural gain.

## Decision

The cross-cutting substrate home is **`app/Shared/<concern>/`** (namespace
`App\Shared\*`). The Reliability/Audit substrate lives at
`app/Shared/{Audit, Outbox, Idempotency, Webhooks}`:

- No top-level `app/Reliability/` is created. All `architecture.md` Step 6 and
  Epic R/A references to `app/Reliability/*` / `App\Reliability\*` and to
  top-level `app/Tenancy/` / `app/Storage/` are read as `app/Shared/*` /
  `App\Shared\*`.
- `Audit/`, `Outbox/`, `Idempotency/` already exist under `app/Shared/` (Epic 2)
  and are **not** recreated. `Webhooks/` is added when Story RA.3 (signed-webhook
  substrate) needs it — not as an empty skeleton ahead of need.
- The Step-6 enforcement-vs-domain split stands: domain query/read lives in
  `app/Modules/Audit/`; enforcement (e.g. `RecordAuthDecision` middleware) lives
  in `app/Shared/Audit/`.
- deptrac is to enforce `App\Shared\*` as cross-cutting (no inbound dependency
  from `App\Modules\*` except via `Contracts/`). Standing up deptrac is a
  separate hygiene task (it is not yet a dependency or a CI step).

## Alternatives Considered

- **Create top-level `app/Reliability/` and migrate `app/Shared/{Audit,Outbox,
  Idempotency}` into it** (architecture Step 6 as literally written). Rejected —
  churns tested Epic 2 code, risks regressions in the P1a submission gate, and
  buys no behavioural or boundary benefit over the existing `app/Shared/` home.
- **`app/Modules/Reliability/` domain module.** Rejected for the same reason
  Step 6 rejected it: the substrate is not a domain; modelling it as one weakens
  the modules-as-domains discipline.

## Consequences

- **Positive:** No churn on tested code; the substrate home is the one already in
  use. The Opt-2 intent (substrate is cross-cutting, not a domain module) is
  preserved exactly — only the path is corrected to match reality.
- **Positive:** Epic R/A stories simplify — RA.1's "create the home" is largely
  already satisfied; remaining RA work targets `app/Shared/*`.
- **Negative (doc debt):** `architecture.md` Step 6 prose and Epic R/A story
  bodies carry `app/Reliability/` paths that are now superseded by this ADR
  (authority order: ADR > architecture doc). Step 6 is corrected to point here;
  story-body namespace strings are read as `App\Shared\*`.
- **Constraint:** deptrac standup and the `Webhooks/` area are deferred to their
  own carriers (a hygiene task and Story RA.3 respectively), not folded into "the
  home exists".

## References

- `_bmad-output/planning-artifacts/architecture.md` — Step 6 (Project Structure
  & Boundaries; Reliability/Audit home) — corrected to reference this ADR
- `_bmad-output/planning-artifacts/epics.md` — Epic R/A, Story RA.1 (re-scoped)
- ADR-0001 (modular monolith — `backend/app/Modules/` + cross-cutting siblings)
- ADR-0005 (single-DB row-level tenancy — `app/Shared/Tenancy/`)
- As-built tree: `backend/app/Shared/{Audit,Outbox,Idempotency,Tenancy,Storage,
  Entitlement,Telemetry,Rules,Versioning,Support}/`
- CLAUDE.md — § Architecture Ownership (module boundaries)
