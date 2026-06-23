---
title: Catalesta PRD — Addendum
status: addendum-created
created: 2026-06-23
trigger: 'reconcile-product-brief.md flagged 5 qualitative gaps that FR + NFR + metric structure cannot carry; per skill discipline, content of that shape belongs here'
parent_prd: prd.md
---

# Addendum

This file preserves user-contributed depth that belongs adjacent to the PRD but does not fit the PRD's main FR/NFR/metric narrative — rejected-alternative rationale, qualitative tone / voice / posture content, in-depth competitive positioning, founder-stance framing, mechanism choices. The PRD remains the testable spec; this file carries the why-it-feels-right that the spec cannot.

---

## Qualitative gaps surfaced 2026-06-23

Five gaps were extracted from `docs/product/product-brief.md` during input reconciliation (see `reconcile-product-brief.md`). Each is preserved here without being "translated" into FRs — translating these into FRs is what flattened them in the first place.

### A1. ICP-operator language and brand voice

The product brief carries lines like "five tools and a shared drive", "selection took hours, not weeks", "the manual scramble at reporting time." This is operator speech — what an accelerator ops lead actually says about their current state. The PRD §2 (Problem & Goals) reduces this to neutral spec text. UX copy, sales decks, demo narration, and onboarding should pull from the brief's voice, not the PRD's.

**How to apply:** When writing customer-facing surfaces (UX strings, sales collateral, onboarding flows, partner conversations), prefer the brief's verbatim ICP language over PRD §2 paraphrasing. The PRD's role is the testable spec; the brief's role is the *language of the buyer*.

### A2. The "minutes-to-start" promise vs M2 "≤ 1 working day"

The product brief promises that an operator can start in **minutes** with "no implementation project required." The PRD's M2 commits to **≤ 1 working day** as the activation metric. These are not numerically inconsistent — minutes is faster than a working day — but the *posture* differs. The brief frames Catalesta as zero-setup. The PRD frames it as same-day-setup. UX must clear the higher (brief's) bar even though only M2 is the measurable test.

**How to apply:** Treat M2 as the *floor* for activation. Onboarding UX, sample data, default templates, and the first cohort should be reachable in minutes — measure it, but do not weaken M2 to match a slower reality.

### A3. "MENA-native, not MENA-translated"

The brief positions Catalesta against US/EU-first competitors as **MENA-native**: Arabic numerals, calendars (Hijri + Gregorian), currency, name-ordering, weekend conventions, RTL-native screens — not RTL added later to an LTR product. NFR-011 commits to "renders Arabic + RTL." That's strictly correct but loses the differentiating posture.

**How to apply:** When choosing between an LTR-first library/component that adds RTL support and a tool built RTL-first, choose RTL-first. When designing screens, default to Hijri-Gregorian dual display where dates appear. Currency: EGP (Egyptian pound) default with USD optional, not the inverse. This is the moat, not just the localization.

### A4. North Star drift (cross-reference to PRD §3 M1 and product-brief l.60)

**[CONFLICT — explicit, requires resolution]** The product brief's stated North Star is "Programs published per active tenant per quarter." The PRD §3 M1 is "Cohorts run to decision per active tenant per quarter." The PRD's M1 is the stronger lagging-outcome metric (run to decision implies a full selection cycle, not just configuration), but the change from the brief was not flagged or justified.

**How to apply:** Either (a) update the product brief to match the PRD's stronger framing, with a one-line note on why "run to decision" is the truer indicator of value delivered; or (b) revert the PRD M1 to match the brief; or (c) declare M1 the *long-game* North Star while keeping the brief's framing for narrative purposes ("published programs grow into run cohorts"). This belongs in OQ2 (M1–M5 ratification) and should be resolved before first-partner conversations to avoid two SSOT-class docs disagreeing on the primary success measure.

### A5. Founder pivot-stance (Internal FAQ Q2/Q3)

The brief's Internal FAQ records the founder's posture: *"willing to re-sequence later prompts based on usage,"* and *"the first sellable slice is for design partners, not the broad market."* The PRD captures the mechanism (the World A/B decision band, OQ6 / OQ7 carrying the resequencing path) but not the stance. The stance matters: it is the orientation that says *the plan will change* — and a PRD that ratifies the plan can read as if it won't.

**How to apply:** When external readers (investors, advisors, design partners) consume the PRD, attach this addendum or summarize from it. The PRD's job is to be testable; the founder's job is to be honest about the plan changing — both at once.

---

## Notes

- This addendum was created on 2026-06-23 during the bmad-prd Update finalize step. The user's intake election was option **B (addendum-pending)**; the product-brief reconciliation surfaced real overflow, flipping the election to **C (create now)**.
- Future additions should preserve the rule: content that is qualitative, posture-bearing, or rejected-alternative rationale lands here; testable behavior lands in `prd.md`; decisions and overrides land in `.decision-log.md`.
- Cross-references kept thin on purpose — this file is not an index of the PRD, it is a sibling carrying what the PRD's shape cannot.
