# Adversarial Re-Review — Catalesta PRD (revision 2)

**Verdict:** grade earns **Fair → bottom of Good.** Four of five critical/high findings are substantively fixed (identity/consent scoping is exemplary; the entitlement 1a-socket/1b-policy split is the right architecture; North Star is now an outcome; the OQ deadlock is broken). It does not reach solid-Good because the fixes introduced second-order consequences the revision didn't close.

## Prior findings — closure
- (1) Seam-vs-thesis — **substantially fixed** (FR-060 allow-all socket / FR-061 gated on OQ3+World-A). Residual: the 1a payment-callback primitive (FR-051/070) has no defined payload/verification contract to test against.
- (2) North Star activity metric — **fixed** (M1 "cohorts run to decision"; M1↔M3 linkage).
- (3) ~25% untestable — **partially fixed**; worst offenders now testable (NFR-005 validator, FR-022, FR-052 audit set, FR-060 arch test). Residual band remains (below).
- (4) Identity/consent mocked-but-done — **fixed** (cleanest fix; "done = vs mock, not production-validated", cutover FR-157).
- (5) Self-deadlocked OQs — **mostly fixed** (owners + routing to OQ6 with a deadline); but the chain is now serially dependent on one unscheduled sales action.

## NEW issues introduced by the 1a/1b split
- **[high] No World-B monetization path** (§3 band, §7 1b, G4) — if the thesis fails, "1b not built on selection" deletes the entire revenue mechanism with no replacement; G4 has no implementation under World-B. *Fix:* define a World-B monetization fallback, or explicitly accept "thesis fails → pivot, no revenue model yet."
- **[high] P1a exit criteria depend on unratified values** (§7; NFR-010/013/014; M3 baseline) — the gate depends on [Proposed]/[ASSUMPTION] numbers that don't exist yet, recreating the deadlock pattern inside the gate. *Fix:* ratify before declaring them exit gates, or demote them from gates.
- **[medium] FR-062 limit-blocking is really 1b, not 1a** — nothing produces a limit in 1a (FR-060 allow-all; FR-061 is 1b), so "writes BLOCKED" is untestable in 1a yet NFR-008 treats it as a P1a guarantee. *Fix:* phase-tag FR-062 (blocking UX → 1b; only read/export-always is a weak 1a property).
- **[medium] §7 roadmap NOTE-PM is an unresolved self-reconciliation TODO** — a spec carrying an open instruction to reconcile itself with its parent roadmap is not internally closed. *Fix:* reconcile the roadmap entry or cite the delta; don't park it in §7.

## Residual untestable / smoothed (cite IDs)
- **NFR-011 "renders Arabic+RTL"** — no rendering oracle (which screens, mirrored layout, bidi, no LTR leakage, pass/fail). Arabic glyphs shown LTR would "render."
- **§7 1a exit "band evaluable" / "instrumentation live"** — not pass/fail predicates. Define: all FR-080 events emitted with required attributes for ≥1 full cohort, band query returns all four inputs.
- **FR-080 "queryable for band"** — no completeness/correctness/computability assertion.
- **1a payment-callback contract (FR-051+FR-070)** — tested against what schema/signature? Otherwise the 1a test is vacuous.
- **M3 "≥30% faster than baseline"** — no baseline source defined; the North-Star-linked metric floats. M4 "≥50 floor" — consequence of missing the floor undefined.

## Hidden costs still smoothed
- **FR-100 "generalize substrate"** hides a real distributed-systems rebuild (multi-consumer outbox ordering + replay ≫ 1a single-log consumer). Estimate as new work, not "extension."
- **FR-031 "content-addressed file refs"** introduces a blob store with dedup/integrity/GC/residency in three words — hidden 1a infra, and residency is OQ4.
- **NFR-009 key rotation in 1a** — rotating provider keys that authorize nothing yet (Geidea is 1b). Defer the provider-key clause to 1b.
- **NFR-014 "1000 cohorts/100k applications/50 operators"** as the 1a perf bar — gold-plating for a product with zero partners and an M4 floor of 50. Right-size to pre-partner reality.

## Highest-leverage remaining fixes (ranked)
1. Define a World-B monetization path (or explicitly accept pivot). — strategic
2. De-deadlock P1a exit: ratify M3 baseline + NFR-010/013/014 before they gate, or demote them.
3. Confirm [ASSUMPTION-CONFIRM] core params before 1a: FR-030 cohort-binding, FR-040 precision/tie-rule.
4. Phase-tag/split FR-062; reconcile the §7 roadmap NOTE so the spec is internally closed.
5. Make the residual band testable: RTL oracle, "band evaluable" predicate, FR-080 completeness, 1a callback contract.
6. Right-size invented NFRs (drop 100k perf bar + provider-key rotation from 1a).
