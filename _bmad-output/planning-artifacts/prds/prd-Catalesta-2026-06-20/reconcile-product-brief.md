---
input: product-brief.md
input_path: /Users/byteninja/Downloads/GrowthLabs/Catalesta/docs/product/product-brief.md
prd_path: /Users/byteninja/Downloads/GrowthLabs/Catalesta/_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
reconcile_kind: qualitative-gap-survey
created: 2026-06-23
status: surfaced
addendum_signal: pending → recommend create
---

# Reconciliation — product-brief.md → PRD

The product brief is the "why/who/outcomes" layer (Status: Draft v2 · 2026-06-18). It is structured as a PR/FAQ with an explicit press release, customer FAQ, internal FAQ, and an ICP-voice testimonial. The PRD reduces this to FRs, NFRs, metrics, and journeys. Several qualitative load-bearing elements have **no FR-shaped echo** in the PRD. Listed below in declining order of preservation risk.

---

## Gap 1 — Brand voice and product personality are stripped

**Brief lines carrying personality (lines 22, 24, 26, 27, 28):**

- "Accelerators and incubators across Egypt and MENA can now run an entire program — from open call to alumni follow-up — in one configurable, auditable system instead of a patchwork of spreadsheets, forms, and inboxes." (l.22)
- "a stack that was never designed for the job" / "a manual scramble at reporting time" / "Every new program or cohort starts the assembly from scratch." (l.24)
- "Programs that ran on five tools and a shared drive now run on one." (l.26)
- The customer-quote testimonial at l.28: *"We used to lose the first week of every cohort just reconciling applications across tools… Selection took hours, not weeks — and the audit trail was already there."*

**PRD echo:** §2 ("Programs run on fragmented tooling… spreadsheet + form-builder + email patchwork.") — same content, drained of urgency and operator voice. The "five tools and a shared drive" / "selection took hours, not weeks" framing — which is the actual ICP pain language — does not appear anywhere in the PRD.

**Why it matters:** This voice is what UX copy, onboarding empty states, marketing landing pages, sales decks, and design-partner conversations will inherit. A spec without it produces neutral, generic surfaces.

---

## Gap 2 — "Getting started takes minutes — no implementation project required" (positioning vs metric)

**Brief line 32:** *"Getting started takes minutes: an organization signs up, starts a trial, configures and publishes its first program, and opens applications — no implementation project required."*

**PRD echo:** M2 = "Time signup → first published program ≤ 1 working day (median)" (§3).

**Drift:** The brief promises *minutes*, the PRD's provisional target is *≤ 1 working day*. These are not the same product feel. "Minutes" is a self-serve, no-implementation-project promise that differentiates Catalesta from enterprise SaaS in the segment; "≤ 1 working day" is a respectable but unremarkable activation metric. No FR or NFR forces the "minutes" experience (no zero-config defaults, no template-on-signup, no time-to-first-form NFR).

**[CONFLICT — soft]:** M2 target vs brief positioning. Either ratify "minutes" as the target (and write the FR that makes it possible), or accept the PRD's softer commitment and update sales/marketing copy.

---

## Gap 3 — "MENA-native, not MENA-translated" is a positioning bet, not a localization NFR

**Brief lines 30, 54, 134–135, 143–144:**

- "Catalesta is built MENA-first: fully bilingual Arabic/English with right-to-left support, integrated with Geidea for billing, and designed to meet regional data-protection expectations." (l.30)
- Internal FAQ Q1 (l.135): *"the existing build … is already shaped for this segment and region, so the product is differentiated and complete on day one rather than generic everywhere."*
- Internal FAQ Q4 (l.144): "MENA-first implies Egypt PDPL as the baseline, with GDPR-grade data-rights handling."

**PRD echo:** NFR-011 ("Phase 1a renders Arabic + RTL"), NFR-013 (Egypt PDPL baseline + GDPR-grade DSR), OQ4 (residency region). Geidea is FR-071 (P1b). 

**What's missing:** The brief's *strategic claim* is that MENA-native ≠ MENA-translated, and that this is **the moat vs US/EU-first competitors** (l.54: "Competitors are either generic … or US/EU-first — no Arabic/RTL, no Geidea, wrong compliance posture."). This competitive-positioning rationale is absent from the PRD — there is no §2 competitor frame, no NFR that distinguishes "RTL works" from "RTL feels native" (e.g. numerals, calendars, currency formatting, name ordering, address fields). The brief's bet is that the product *feels* Arabic-first; the PRD's spec only ensures it *renders* RTL.

---

## Gap 4 — North Star drift: brief said "Programs published per active tenant per quarter"; PRD says "Cohorts run to decision"

**Brief line 60:** *"North Star (candidate): Programs published per active tenant per quarter."*

**PRD M1 (§3):** *"Cohorts run to decision per active tenant per quarter."*

**Drift, with rationale:** The PRD's M1 is *measuring different behavior*. "Programs published" measures configuration/activation reuse — the brief's hypothesis that operators run the platform repeatedly because it's configurable. "Cohorts run to decision" measures the **selection** loop the PRD's Phase-1a/World-A bet specifically tests. The PRD has implicitly shifted the North Star to match its Phase-1a hypothesis, which is internally consistent — but it has *not flagged* this as a deliberate divergence from the brief.

**[CONFLICT — explicit]:** Brief North Star ≠ PRD M1. Either (a) update the brief, or (b) record in the PRD §3 that the North Star was reframed when the World-A hypothesis was adopted, with rationale. As-is, the two SSOT-class documents disagree on the primary success measure.

---

## Gap 5 — "First sellable slice" framing and the World-B/pivot narrative are flattened

**Brief lines 138, 141** (Internal FAQ Q2/Q3):

- Q2: *"We should still name an internal 'first sellable slice' for design partners — proposed: signup → publish program → applications → selection → billing."*
- Q3: *"Building all 68 units in dependency order before the market validates which third matters most. Mitigation: recruit 2–3 design-partner accelerators now; treat each release gate as a learning checkpoint; be willing to re-sequence later prompts based on usage."*

**PRD echo:** §7 Phase 1a/1b split with World-A/B decision band (§3); OQ6 (signed design partner gate); §10 OQ7 (World-B monetization fallback).

**Where the qualitative voice is lost:** The brief frames this as a **founder-stance** — "we're willing to re-sequence," "treat each release gate as a learning checkpoint," "be willing to pivot." The PRD encodes the *mechanism* (decision band, gates, OQ7) but loses the *posture*. A team reading only the PRD sees a phased plan with conditional gates; a team reading the brief sees a founder hypothesis that is *expected to be tested and possibly invalidated*. That posture matters for how design-partner conversations are framed and how engineering trade-offs are surfaced. It is also the cultural anchor for OQ7 — without it, OQ7 reads as a technicality rather than a real strategic fork.

---

## Summary block (for caller)

- **Input name:** product-brief.md
- **Gaps (5):**
  1. Brand voice and ICP-quote operator language stripped from PRD §2.
  2. "Minutes to start, no implementation project" positioning vs M2's "≤ 1 working day" — soft conflict.
  3. "MENA-native, not MENA-translated" competitive positioning + Arabic-first *feel* absent from NFR-011/013.
  4. North Star drift — brief = "programs published"; PRD M1 = "cohorts run to decision" — undeclared reframe.
  5. Founder posture ("willing to re-sequence / pivot") flattened into mechanism (decision band, OQ7).
- **File path written:** `/Users/byteninja/Downloads/GrowthLabs/Catalesta/_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/reconcile-product-brief.md`
- **Addendum needed?** **YES.** All five gaps are tone/voice/positioning/posture content that FR + NFR + metric structure cannot carry without distorting them. The `addendum-pending` status should advance to `addendum-created` covering: (a) brand voice and ICP language excerpts to preserve; (b) "minutes-not-days" positioning + the FRs/defaults that would have to back it; (c) MENA-native feel beyond RTL render; (d) explicit note that PRD M1 reframed the brief's North Star, with rationale; (e) the founder posture text from Internal FAQ Q2/Q3, anchored to OQ6/OQ7 as cultural context.
- **[CONFLICT] list:**
  - **[CONFLICT — soft]** Brief l.32 "minutes" vs PRD M2 "≤ 1 working day."
  - **[CONFLICT — explicit]** Brief l.60 North Star "Programs published per active tenant per quarter" vs PRD §3 M1 "Cohorts run to decision per active tenant per quarter." Undeclared reframe between two SSOT-class documents.
