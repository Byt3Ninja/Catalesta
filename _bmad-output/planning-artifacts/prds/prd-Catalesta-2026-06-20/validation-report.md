# Validation Report — Catalesta PRD (revision 2)

- **PRD:** `_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md`
- **Rubric:** `.claude/skills/bmad-prd/assets/prd-validation-checklist.md`
- **Run at:** 2026-06-20 (re-validation)
- **Grade:** Good (improved from Fair)
- **Reviewers:** rubric-walker (PASS, borderline Strong) · adversarial (Fair → bottom of Good)

## Overall verdict

The revision **closes all 11 prior critical/high findings with real mechanism, not cosmetics** — both reviewers independently agree the grade moved **Fair → Good**. The 1a/1b split (instrument-first selection, billing gated on a quantified World-A band), the entitlement socket-vs-policy distinction, the "done = vs mock, not production-validated" scoping, the enumerated audit set and event taxonomy, and the residency-before-pilot move are genuine structural fixes. 6 of 7 rubric dimensions are now **strong** (Done-ness adequate).

It is **bottom-of-Good, not solid-Good**, because the fixes introduced second-order issues the revision didn't close: a **strategic hole (no World-B monetization path)**, **P1a exit criteria that depend on still-unratified values** (recreating the deadlock inside the gate), and a residual band of untestable predicates (RTL oracle, "band evaluable," FR-080 completeness, the 1a payment-callback contract). None is a regression; they are consequences of the new structure.

## Dimension verdicts (rubric walker)
Decision-readiness **strong** · Substance over theater **strong** · Strategic coherence **strong** · Done-ness clarity **adequate** · Scope honesty **strong** · Downstream usability **strong** · Shape fit **strong**

## Prior-issue closure (all 11 resolved)
Seam-vs-thesis · North Star · metric thresholds · World A/B band · entitlement-vs-packaging · "slice depth" · identity/consent-mocked · FR-020 fields · FR-040 decimal · residency · RTL — **all closed with mechanism** (see review-rubric.md / review-adversarial.md Part 1).

## Remaining findings by severity

### High (new second-order; not pre-existing regressions)
- **No World-B monetization path** (§3 band / §7 1b / G4) — if the selection thesis fails, "1b not built on selection" deletes the revenue mechanism with no replacement; G4 has no implementation under World-B. *Fix:* define a World-B fallback, or explicitly accept "thesis fails → pivot."
- **P1a exit criteria depend on unratified values** (§7; NFR-010/013/014; M3 baseline) — the gate hinges on [Proposed]/[ASSUMPTION] numbers that don't exist yet. *Fix:* ratify before they gate, or demote them from exit gates.

### Medium
- **FR-062 limit-blocking is really 1b, not 1a** — nothing produces a limit in 1a (allow-all), so "writes BLOCKED" is untestable there yet NFR-008 treats it as a P1a guarantee. *Fix:* phase-tag FR-062 (blocking UX → 1b; only read/export-always is a 1a property).
- **§7 roadmap NOTE-PM is an unresolved self-reconciliation TODO** — reconcile the roadmap's "Selection MVP + billing" entry with the 1a/1b split, or cite the delta; don't park it in the spec.
- **Validation chain has zero design partners** (§3/OQ6) — single point of failure for every gated decision. *Fix:* make 1a start contingent on ≥1 signed partner, or state 1a builds at risk.
- **Unratified thresholds presented as spec** (NFR-009/010/014, FR-040) — give each an owner + ratification trigger (as metrics got).
- **Residual untestable predicates** — NFR-011 RTL has no rendering oracle; §7 "band evaluable"/"instrumentation live" aren't pass/fail; FR-080 "queryable" lacks a completeness assertion; the 1a payment-callback (FR-051/070) has no contract to test against.

### Low
- Hidden costs smoothed: FR-100 "generalize" hides a multi-consumer-outbox rebuild; FR-031 "content-addressed file refs" implies blob-store infra; NFR-009 key-rotation premature in 1a; NFR-014 100k-application perf bar is gold-plating pre-partner.
- Residual hedge-adjectives (G2 "trustworthy/defensible", World-B "decide-in-seconds", "data not silently altered", FR-061 "building blind = rejected").
- FR-030 cohort-binding + FR-040 precision still `[ASSUMPTION-CONFIRM]` on core 1a parameters — confirm before 1a schema freeze.
- "Provisionally ratified" (1b gate / OQ3) underspecified — define the sign-off artifact.

## Mechanical notes
- ID continuity clean; cross-refs resolve; no dangling references (FR-052↔042/081, FR-062↔NFR-008, FR-157 from FR-001/006/§9/NFR-006/011, OQ3↔FR-061/130/131).
- A forward-ref from §0 (World A/B) to §3 (decision band) would help first-time readers.

## Reviewers
- `review-rubric.md` — PASS, borderline Strong; all 11 prior issues closed.
- `review-adversarial.md` — Fair → bottom of Good; flags second-order consequences of the fixes.
