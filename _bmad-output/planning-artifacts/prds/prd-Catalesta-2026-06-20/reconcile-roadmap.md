---
input_name: roadmap.md
input_path: /Users/byteninja/Downloads/GrowthLabs/Catalesta/docs/plan/roadmap.md
prd_path: /Users/byteninja/Downloads/GrowthLabs/Catalesta/_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
date: 2026-06-23
oq_reference: OQ9 (Medium → High, 2026-06-23 staleness bump)
status: surfaced-not-adjudicated
authoritative_for_sequence: roadmap.md (per its own header) — but PRD §7 explicitly disagrees with it
adjudicator: PM (per OQ9)
---

# Roadmap ↔ PRD §7 reconciliation (surface-only)

> Scope: surface the diff between `docs/plan/roadmap.md` and PRD §7 / §6.13 /
> §10 OQ9. Does **not** adjudicate OQ9 — that is owned by PM at the next
> roadmap edit. PRD already carries three `[NOTE FOR PM]` callouts in §7
> instructing the roadmap to be updated; this file enumerates exactly what
> would need to change.

## A. Verbatim — the contested entry

Roadmap `## MVP cut line — first sellable slice` (lines 14–22):

> **Selection MVP, then billing — split into 1a → 1b (per PRD §7):**
> - **1a (Selection MVP, no billing):** `signup → publish program → applications → selection/scoring → export`.
> - **1b (Billing seam):** `→ Geidea billing`, **gated on the World-A result** (§3 band) and OQ3 packaging ratification.
>
> *(Reconciles PRD OQ9 — the two SSOT docs no longer disagree on the 1a/1b split.)*

**Status of that "reconciles" claim:** the *MVP cut line* prose **does** describe
the 1a/1b split. However, the **Phases table** immediately below (roadmap
lines 31–38) re-fragments the same work into six numbered phases (0–5) that do
not map 1:1 onto PRD §7's four phases (1a, 1b, 2, 3, 4). PRD §7's `[NOTE FOR PM]`
still reads as if the roadmap entry says "Selection MVP + billing" as one unit
(line 219) — that specific string no longer appears in the roadmap, but the
underlying inconsistency (different phase decomposition) does. OQ9's
"two SSOT docs disagree" condition remains true at the **Phases table** level.

## B. Phase-by-phase mismatch matrix

| Concern | PRD §7 phase | Roadmap phase | Match? |
|---|---|---|---|
| Foundation (identity, tenancy, RBAC) | folded into 1a (FR-001–006) | **Phase 0** separate | Mismatch — roadmap carves Phase 0 out; PRD includes in 1a |
| Program/cohort/stage config | folded into 1a (FR-010–013) | **Phase 1** separate | Mismatch — roadmap carves Phase 1 (program-config kernel) out; PRD includes in 1a |
| Substrate (outbox, idempotency, audit, entitlement socket) | **inside 1a** at "slice depth" (FR-050/051/052/060 socket, FR-070 primitive) — PRD §7 Phase rule explicitly justifies this | **Phase 2** separate, before selection | **Sequencing conflict.** PRD: substrate is part of 1a, not a prerequisite phase. Roadmap: substrate is its own phase between program-config and selection. Same intent (build substrate before features), different framing — but PRD §7's "Phase rule" and roadmap's "Phase rule" both make load-bearing claims about *which phase* substrate lives in |
| Selection MVP (forms, applications, assessment/scoring, export) | core of 1a (FR-020–022, 030–034, 040–043, 052, 080–081) | **Phase 3** | Naming/numbering mismatch only — content aligned |
| Billing seam (Geidea) | **1b**, gated on World-A + OQ3 | **Phase 4 (commercial plane)** | Roadmap says "tenant can subscribe + pay via Geidea" as Phase 4 exit, conflating PRD's 1b (Geidea sandbox e2e, no real charge, FR-061/071–073) with PRD's P3 production commercial plane (FR-130–133, production billing). **Material conflict — see [CONFLICT-1] below.** |
| Sale-readiness (offboarding, secrets, data-residency, impersonation+audit) | distributed across PRD NFRs + P4 (FR-150–159) | **Phase 5** as its own phase | Roadmap invents a Phase 5 not present in PRD §7's four-phase model. Maps loosely to P4 + NFR-013 (OQ4 residency) but is not a PRD phase |
| P2 substrate generalization + delivery core (FR-100–108: documents, workflows, mentorship, training, graduation) | **PRD Phase 2** | **roadmap Deferred backlog** (rows 53–60) | Mismatch — PRD treats P2 as the next phase after 1b; roadmap treats most P2 capabilities as "deferred backlog" with no phase placement |
| P3 platform services (FR-120–127 notifications/search/admin + FR-130–133 production billing/domains/branding) | **PRD Phase 3** | partially in roadmap Phase 4 (Geidea), rest in Deferred backlog | Mismatch — PRD P3 is fragmented across roadmap Phase 4 + Deferred |
| P4 extended capabilities (FR-150–159 incl. optional SG SSO cutover, DR) | **PRD Phase 4** | Deferred backlog rows 67–71 | Mismatch — roadmap demotes the whole PRD P4 to "deferred backlog" with no exit criterion |

## C. Gaps (5)

1. **Phase 1a *entry gate* not in roadmap.** PRD §7 (2026-06-23 strengthening,
   line 223) requires **≥1 signed design partner** before Phase 1a *starts*.
   Roadmap has **no entry gate** on any phase — the Phases table only lists
   "Entry → Exit" content gates, none of which reference OQ6 or design-partner
   signature. Engineering work landing before OQ6 is satisfied is treated by
   PRD as *instrument-build, not Phase 1a delivery* — the roadmap currently
   gives no signal of this distinction.

2. **Reliability/Audit epic carve-out has no roadmap slot.** PRD §7 (line 225)
   and §6.13 FR-126 row reclassify FR-126 + outbox + idempotency + signed
   webhooks as a **new Reliability/Audit epic inserted before P2**. Roadmap's
   Phase 2 is "Cross-cutting substrates — transactional outbox + idempotency;
   entitlement-enforcement seam" — which **partially overlaps** but does not
   name FR-126, audit-enforced, or signed-webhooks, and sits in a different
   place in the sequence (before selection MVP, not before P2). The PRD §7
   note explicitly states *"Phase-table row for this epic to be added once the
   epic is scoped"* — roadmap still lacks that row.

3. **Roadmap Phase 4 conflates PRD 1b with PRD P3** on billing. PRD 1b is
   *Geidea sandbox e2e, `active_programs` counter enforced, no real charge*
   (FR-061, 071–073). PRD P3 is *production Geidea + production commercial
   plane* (FR-130–133). Roadmap Phase 4 reads "tenant can subscribe + pay via
   Geidea" — ambiguous between the two, and the build-spec ID range `58`–`62`
   does not disambiguate. See [CONFLICT-1].

4. **Roadmap demotes PRD P2/P3/P4 to "Deferred backlog" without exit gates.**
   The roadmap explicitly lists P2 modules (Documents, Workflows, Mentorship,
   Training, Final Evaluation, Graduation, Notifications, Reporting, Search,
   Localization, UX, Extended, Custom Domains, Branding, Federated SG SSO,
   Full DR) as "Deferred backlog (documented, not dropped)" with **no phase
   placement and no exit criteria**. PRD §6.13 and §7 give each of these a
   phase (P2/P3/P4) with named FRs and capability-level gates. The roadmap's
   "Deferred backlog" framing is reasonable for a build-order doc but loses
   the PRD's phase grouping — which is what OQ9 calls SSOT-level disagreement.

5. **Roadmap `Last-updated: 2026-06-19` predates the 2026-06-23 PRD revisions.**
   None of the four 2026-06-23 strengthenings — (a) OQ6 design-partner entry
   gate on Phase 1a, (b) FR-126 Reliability/Audit epic carve-out, (c) OQ9
   severity bump to High, (d) §6.13 six-bucket overlay — appear in the
   roadmap. By the PRD's own `[NOTE FOR PM 2026-06-23]` (line 221), the
   readiness report now treats `roadmap.md` as authoritative for sequence;
   every passing day in this stale state widens the disagreement.

## D. [CONFLICT] items the PM must adjudicate

- **[CONFLICT-1] Geidea phase boundary.** PRD 1b (Geidea sandbox, no real
  charge, FR-061/071–073) vs PRD P3 (production Geidea, FR-130–133). Roadmap
  Phase 4 says "tenant can subscribe + pay via Geidea" — wording fits *either*
  interpretation. Resolution: PM must either (a) split roadmap Phase 4 into 4a
  (sandbox / 1b) + 4b (production / P3), or (b) state explicitly which
  interpretation Phase 4 carries and where the other lives.

- **[CONFLICT-2] Substrate phase placement.** PRD §7 Phase rule: substrate is
  *part of* Phase 1a at defined slice depth. Roadmap Phase rule: substrate is
  *Phase 2*, before selection MVP (Phase 3). Same underlying engineering
  judgement ("build substrate before features") expressed two different ways.
  Resolution: PM must pick one framing; the readiness report cannot
  unambiguously cite "Phase 2" without disambiguation.

- **[CONFLICT-3] Whether the roadmap's MVP cut-line prose claim ("Reconciles
  PRD OQ9 — the two SSOT docs no longer disagree on the 1a/1b split")
  actually closes OQ9.** It closes the *headline* split but not the
  Phases-table decomposition mismatch enumerated above. PRD OQ9 (line 291)
  still treats this as open. PM to decide whether the prose alone closes OQ9
  or whether the Phases table must also be reworked.

## File path written

`/Users/byteninja/Downloads/GrowthLabs/Catalesta/_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/reconcile-roadmap.md`
