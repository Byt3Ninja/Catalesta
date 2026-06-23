---
title: Adversarial Re-Review — Catalesta PRD (delta 2026-06-23)
created: 2026-06-23
predecessor: review-adversarial.md
mode: delta
scope: 2026-06-23 update only — §6.13 six-bucket overlay, §9.1 DB topology, §7 PM notes, OQ4/6/8/9 changes, FR-126 reclassification, FR-030 resolved-by-shipping, frontmatter status flip
---

# Adversarial Re-Review — Catalesta PRD (2026-06-23 delta)

**Verdict (one line):** **Fair**, regressing from the prior 2026-06-20 **Good** grade — the 2026-06-23 changes close one prior High (OQ6 strengthening) but introduce **at least three new contradictions** and one **process-integrity defect** (silent edit confessed and partially re-perpetrated) that a finalize-class artifact should not carry.

Prior baseline (`review-adversarial.md`, 2026-06-20) covered the seam/thesis, North Star, identity, residual untestable band, and the 1a/1b split. **This review attacks only the deltas applied 2026-06-23.** Findings already closed there are not re-litigated.

---

## NEW issues introduced by the 2026-06-23 update

### F-Δ1 — [Critical] Bucket classification contradicts §7 1a-entry gate at the source-signal level
**Where:** §6.13 vs §7 `[NOTE FOR PM 2026-06-23 — Phase 1a *entry* gate]`.

§7 strengthens Phase 1a *start* to require ≥1 **signed design partner** (OQ6 Option II). §6.13 then classifies 22 FRs as "Existing and verified," sourcing them to Epics 1+2 / SP-1 as already landed. But by §7's own logic, **engineering work that lands before OQ6 is satisfied is treated as instrument-build, not Phase 1a delivery.** That makes every "Existing and verified" row in §6.13 a category error: the artifacts exist but they are explicitly **not Phase 1a deliverables** under the strengthened gate. The overlay treats pre-gate code as gate-passing code. *PRD does not name the contradiction.*

### F-Δ2 — [Critical] Silent-edit confession is itself silent: PRD body never tags the retroactive resolution
**Where:** decision log "Findings on intake" #2 and #5 (FR-030); PRD §6.4 FR-030 and §6.13.

The 2026-06-23 log retroactively records that **the identity-ownership inversion was applied to §6.1/§9/FR-157 between 2026-06-21 and 2026-06-23 with no log entry** — a direct violation of skill discipline as the log itself states. The remediation is also incomplete:
- FR-030 is logged as "resolved-by-shipping," but the PRD body still carries `[ASSUMPTION-CONFIRM]` at FR-030 line and §6.13's source-signal cell openly admits this ("PRD body still carries the `[ASSUMPTION-CONFIRM]` tag; per skill discipline, this log entry retroactively records the resolution. A separate hygiene task should drop the tag from the PRD body in a future minor edit").
- **§10 Assumptions still enumerates "applications bind to cohort (FR-030, confirm)"** as live. Two of three locations contradict one log entry.

This isn't a hygiene nit — it is the same skill-discipline failure being knowingly repeated in the same session that confessed the prior one. A finalize-class artifact cannot be left with an FR that is simultaneously "open assumption" (§10), "tagged for confirm" (§6.4), and "resolved-by-shipping" (log + §6.13). **No way to know which is canonical.**

### F-Δ3 — [High] §9.1 introduces hard constraints with no matching NFR enforcement clauses
**Where:** §9.1 (new); §8 NFRs (unchanged).

§9.1 declares as canonical:
- "Strongly-consistent reads … **target the writer**" — a **writer-pinning** constraint on every authorization check, idempotency lookup, OIDC callback verification, post-write read.
- "Out-of-band analytics warehouse … **never** as a product-code read path (controllers, services, jobs, Policies never read from it)."
- "Per-tenant database is **forbidden**."

None of these surface in §8 as testable NFRs, acceptance gates, or architecture-test obligations. NFR-001 / NFR-012 are silent on connection routing; NFR-005's "no arbitrary code in rules" validator pattern is not extended to a "no warehouse reads in product code" arch test. The §9.1 rules are policy in name only — **no mechanism, no FR-126 wiring, no NFR ID, no acceptance criterion.** "Pending ADR-0005" is correct but doesn't substitute for an NFR. Compare to NFR-001's `BelongsToTenant` architecture test — that is what enforcement looks like; §9.1 has none.

### F-Δ4 — [High] OQ4/OQ6 strengthening creates downstream contradictions §3, §7, §6.13 do not acknowledge
**Where:** OQ6 → §7 entry gate; OQ4 severity → §6.13; §3 metrics.

OQ6 strengthening + OQ4 severity bump = Phase 1a cannot start without **(a) signed design partner AND (b) committed residency region.** The cascade hits §3 and §6.13 but they are not reconciled:
- **§3 M3 target** is "≥ 30% faster than the tenant's prior baseline" — the baseline measurement now depends on a signed partner that hasn't been recruited. The validation-status paragraph in §3 still names OQ6 as "owner = founder, **this week**" which is the *pre*-strengthening framing; the §3 paragraph wasn't updated when §7 was.
- **§6.13 FR-001…052 marked "Existing and verified"** while the gating partner does not exist — see F-Δ1. The classification is taken against a baseline (current code state) that the very same update declares is **pre-gate, not gate-passing.**
- **OQ8 "P1a exit gating" set** still depends on baseline = partner data — F-Δ1 in `review-adversarial.md` was not closed; OQ6 strengthening makes it **worse** because the dependency chain is now longer (signed partner → first cohort → baseline → exit gate ratification) but no §3/§7 text reflects this elongation.

### F-Δ5 — [High] FR-126 reclassification silently expands Phase 1a scope without a phase-table row
**Where:** §6.13 FR-126 row; §7 phase table; §7 `[NOTE FOR PM 2026-06-23 — Reliability/Audit epic carve-out]`.

FR-126 ("Audit enforced platform-wide") moved P3 → "Required for initial release — Reliability/Audit epic carve-out, inserted before P2." §7 admits the phase-table row "**to be added once the epic is scoped**." Net effect:
- §6.13 declares FR-126 part of initial release.
- §7 phase table still shows Phase 1a exit criterion as "World-A/B band can be evaluated" — **no audit-enforcement criterion.**
- The new epic is described as owning "outbox + idempotency + audit-enforced + signed-webhooks" — three of those (FR-050, FR-051, FR-052) are **already Phase 1a deliverables.** The carve-out epic and Phase 1a overlap on three FRs with no phase boundary defined.

This is the textbook silent-scope-expansion shape: a P3 item gets pulled forward into "before P2" and re-bundled with three FRs that already live in 1a, but the phase table that gates exit doesn't see it. Either Phase 1a now includes platform-wide audit enforcement (massive expansion) or the new epic floats outside the phase rail with overlapping ownership (governance collapse). PRD does not pick.

### F-Δ6 — [Medium] OQ6 strengthening invalidates a §3 metric without flagging it
**Where:** §3 validation-status paragraph; OQ6 text.

§3 reads: "[ASSUMPTION] no design partner is yet recruited; recruiting one is a **pre-Phase-1 action (OQ6, owner = founder, this week)**." OQ6 was strengthened on 2026-06-23 from "≥1 operator call this week" to "≥1 signed design partner." **The §3 paragraph was not updated** — it still implies a one-week timeline and a low-cost recruiting action. Signed-partner sales cycles are not one-week actions. Either the §3 paragraph is stale, or OQ6 isn't really a one-week ask — both can't be true.

### F-Δ7 — [Medium] OQ8 "(q) split" creates a forward-reference orphan
**Where:** OQ4 text; OQ8 text; NFR-013.

OQ4 text now says: "Retention values moved to OQ8 per the 2026-06-23 (q) split — OQ4 = region only; OQ8 = ratification of retention values + the other Phase-1a-exit numbers."
OQ8 text now says NFR-013 ownership is "retention values only — region commitment lives in OQ4 per the 2026-06-23 (q) split."

NFR-013 itself reads unchanged: "Residency region must be decided before the first pilot (OQ4) … Retention values per `product/data-residency-retention.md` [Proposed]." The NFR conflates region + retention, but two OQs now claim the split. **NFR-013 was not split to match.** A reader looking only at §8 cannot see the split; only the §10 reader can. SSOT violation against the NFR.

### F-Δ8 — [Medium] §6.13 admits its source signal is partially stale, then defers anyway
**Where:** §6.13 preface.

§6.13 itself names the problem: "`docs/status/implementation-status.md` (status doc dated 2026-06-19 — refresh tracked as `docs/repository-audit.md` F-003; readiness report is authoritative where they disagree)." Yet the overlay rows do not annotate which "Existing and verified" cells depend on the stale doc vs the readiness report. **Six FR rows cite "S1.3 (status doc stale on Forms — F-003)" but other rows just cite "Implemented" with no audit hint.** A reader can't tell which "verified" rows are verified by the still-stale source. The overlay should either ground every row in the readiness report or annotate the source explicitly.

### F-Δ9 — [Low] Frontmatter `grade: Good` stayed put while status went `final → update-in-progress`
**Where:** frontmatter.

The grade asserted at 2026-06-20 finalize is preserved in the frontmatter even though three new High findings (this review) and an OQ9 severity bump (Medium → High) have been applied since. Grade is a snapshot, not a perpetual claim — leaving it at "Good" during update-in-progress is an unintentional re-assertion.

---

## Process-integrity findings

### P-Δ1 — [Critical] Skill-discipline failure was confessed and partially repeated in the same session
The decision log narrates: "Silent edit between 2026-06-20 finalize and 2026-06-23. … Direct violation of skill discipline ('every decision, change, and override is recorded here'). Retroactive log entry recorded …" This is the right disclosure. But:
1. The PRD body still carries the contradiction (F-Δ2 above).
2. §3 still carries the pre-strengthening OQ6 timeline (F-Δ6).
3. §8 NFR-013 was not split to match OQ4/OQ8 (F-Δ7).

Three same-class defects survived the same audit cycle that surfaced the original one. A finalize-class PRD that announces "we noticed we did the wrong thing" and then ships with three more instances of the same wrong thing should not pass a finalize gate.

### P-Δ2 — [Medium] Other body-level `[ASSUMPTION]` / `[Proposed]` tags not audited for stale-resolution
The decision log calls out FR-030 specifically. There are at least **six** body-level tags not enumerated:
- FR-040 `[ASSUMPTION precision]` (DECIMAL(6,2)) — referenced in OQ8 as still-unratified ("M3 baseline" / "perf" / "retention") but FR-040 itself is not enumerated. Is it ratified? Unknown.
- FR-050 `[ASSUMPTION] 6 attempts` outbox retry — not enumerated anywhere.
- FR-080 `[ASSUMPTION] 24h` export-then-leave window — not enumerated.
- NFR-009 `[ASSUMPTION ceilings]` rate limits — not enumerated.
- NFR-010 `[Proposed]` RPO/RTO — *is* enumerated (OQ8) — good.
- NFR-014 `[ASSUMPTION load model]` — *is* enumerated (OQ8) — good.

The audit was selective. If the principle is "log every resolution," the audit should be exhaustive.

---

## What the 2026-06-23 update did correctly (acknowledgements)

- OQ6 strengthening to "signed design partner" is the right call and closes the prior PRD-vs-rubric divergence cleanly **at the OQ level** (the unfixed cascades are F-Δ4 / F-Δ6).
- OQ4 (q) split (region vs retention) is the right modeling move at the OQ level (the unfixed cascade is F-Δ7).
- §9.1 introducing canonical DB-topology rules with explicit "forbidden" clauses is the right direction (the unfixed gap is F-Δ3).
- The six-bucket overlay is well-shaped *as a discovery artifact* (bucket totals, sub-tag distinction, source-signal column) — the failure is at the boundary with §7 phase rails (F-Δ1, F-Δ5).
- The retroactive silent-edit confession itself is good practice — the failure is the incomplete remediation (P-Δ1).

---

## Severity summary

| # | Severity | Finding (one line) | Section |
|---|---|---|---|
| F-Δ1 | Critical | Six-bucket "Existing and verified" rows contradict §7's strengthened entry gate | §6.13 vs §7 |
| F-Δ2 | Critical | FR-030 is simultaneously open assumption, tagged-for-confirm, and resolved-by-shipping | §6.4, §6.13, §10, log |
| F-Δ3 | High | §9.1 introduces hard constraints with no NFR / arch-test enforcement | §9.1 vs §8 |
| F-Δ4 | High | OQ6/OQ4 strengthening uncascaded to §3, §6.13, OQ8 | §3, §6.13, §10 |
| F-Δ5 | High | FR-126 reclassification expands Phase 1a scope with no phase-table row | §6.13 vs §7 |
| F-Δ6 | Medium | §3 paragraph still names pre-strengthening OQ6 timeline ("this week") | §3 |
| F-Δ7 | Medium | NFR-013 not split to match OQ4/OQ8 (q) split | §8 vs §10 |
| F-Δ8 | Medium | §6.13 admits status-doc staleness then doesn't annotate per-row | §6.13 |
| F-Δ9 | Low | Frontmatter `grade: Good` preserved through update-in-progress | frontmatter |
| P-Δ1 | Critical (process) | Skill-discipline failure confessed and partially repeated in same session | log + body |
| P-Δ2 | Medium (process) | Body-level `[ASSUMPTION]` audit was selective, not exhaustive | body |

**Aggregate verdict.** Three Criticals (two content + one process), three Highs, three Mediums, one Low in the 2026-06-23 delta alone. The update introduces more open contradictions than it closes. **Fair, regressing from Good** — the right re-grade until the cascades are closed and the §10/§6.4/§6.13 contradiction on FR-030 is resolved to a single canonical statement.

— end —
