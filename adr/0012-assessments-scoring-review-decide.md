# ADR 0012: Assessments Module — Decimal-Authoritative Scoring, Blind Review, Immutable Decisions

## Status

Accepted

## Context

The Assessments capability (scoring / review / decide) is a 0-file empty module
scaffold, while the frontend for it is already built and tested
(`ReviewQueuePage`, `ScorecardPage`, `ScoringModelBuilder/Preview/VersionsPage`,
`SubmissionsPage`) against MSW. It is the single largest "FE built, backend
missing" gap and blocks the evaluation loop and the per-stage scoring binding
(ADR-0011 Phase 3).

The frontend contract (`frontend/src/schemas/assessments.ts`,
`frontend/src/api/assessments.ts`) already fixes the domain shape the backend
must satisfy:

- **ScoringModel** (per-program, `latest_version`, `published_version_ids[]`,
  `current_draft_version_id`) + **ScoringModelVersion** (`status` draft|published,
  embedded `criteria[]`). A **ScoringCriterion** is `{criterion_id, label,
  max_points, descriptors[]}` — points-based, **no separate weight field**.
- **ReviewerAssignment** (cohort+stage+application+reviewer, pending|submitted).
- **Scorecard** (one reviewer's `values: {criterion_id → number}` for one
  application at a stage; `disqualified`; draft|submitted).
- **Decision** (`outcome` advance|reject|waitlist) with an immutable `snapshot`
  `{model_version_id, scorecards[], mean: string, decided_at}` + `decided_by`.
  The `mean` being a **string** signals decimal-authoritative scoring.

CLAUDE.md § Versioning and Historical Integrity mandates: decimal arithmetic for
authoritative scoring with defined precision/scale/rounding/weight-normalization/
missing-value; immutable, versioned scoring models and evaluation templates;
formal submissions/executions retain immutable snapshots + exact version
references; reproducible historical results; blind review / reviewer
independence + conflict-of-interest; deny-by-default authz + tenant isolation;
no arbitrary code in scoring.

This is architecture-tier (a new module + scoring invariants), so it needs an
ADR and must be decomposed, not built as one spec.

## Decision

Create an **Assessments** module that owns scoring-model authoring, reviewer
assignment, scorecards, and decisions, governed by a decimal-authoritative
scoring law and the shared versioning kernel.

1. **Ownership.** Assessments owns `ScoringModel`/`ScoringModelVersion`,
   `ReviewerAssignment`, `Scorecard`, `Decision`. Submissions stay owned by
   Applications (Assessments references an `application_id`). The per-stage
   scoring binding lives in **Cohorts** (twin of bind-form / bind-stage-pipeline),
   keyed on the ADR-0011 snapshot `ProgramStage` ULID
   (`cohorts.stage_scoring_model_version_ids[stageId]`).

2. **Scoring models are immutable + versioned** via the existing
   `VersionPublisher`/`ImmutableWhenPublished`/`Versionable` kernel — identical
   machinery to `FormVersion` and `StagePipelineVersion`: persistent draft,
   `draft`/`publish`/`fork`, content-addressed, frozen when published.

3. **Scoring is points-based and decimal-authoritative.** A criterion carries
   `max_points`; its weight is `max_points / Σ max_points` (no separate weight
   field). The **scoring law** (binding from Phase B/C onward):
   - Authoritative arithmetic uses PHP decimal (BCMath / `brick/math`), **never
     float**.
   - **Scale 2, rounding ROUND_HALF_UP** for stored/returned authoritative values.
   - Per-application reviewer score = `Σ(scorecard values)` as decimal.
   - Cross-reviewer **mean** = decimal string (matches `Decision.snapshot.mean`).
   - **Missing values are disallowed at scorecard submit** — every criterion in
     the bound `model_version` must have a value (the FE's 422 "all criteria must
     be scored"); drafts may be partial.
   - `disqualified` scorecards are excluded from the aggregate mean (Phase C).
   - Values are bounded `0 ≤ value ≤ criterion.max_points`.

4. **Decisions are immutable and reproducible.** A `Decision` embeds the exact
   `model_version_id`, the contributing scorecards, and the computed `mean` at
   decision time, so a historical result reproduces regardless of later model or
   roster changes.

5. **Blind review + independence.** A reviewer can read only their own scorecard
   for an assignment; aggregates/other reviewers' scores are hidden until a
   decision stage. Conflict-of-interest exclusion is enforced at assignment time.

6. **Authorization & tenancy.** Deny-by-default; tenant-scoped via
   `BelongsToTenant`; cross-tenant `{id}` → neutral 404 (ADR-0009). A new
   `assessments.manage` permission gates authoring/decisions; reviewers act
   through assignment ownership. No arbitrary code in scoring rules.

### Phasing

- **Phase A — Scoring-model authoring + versioning.** `ScoringModel` +
  `ScoringModelVersion` + criteria; create / draft-PATCH / publish / fork;
  immutable-when-published. A direct twin of the Forms authoring slice. **No
  scoring math** — it only defines criteria. (Unblocks Phase D binding.)
- **Phase B — Reviewer assignment + scorecard submission + blind review.**
  Assignment generation (with COI exclusion), scorecard draft/submit with
  per-criterion decimal validation and missing-value enforcement, blind
  visibility.
- **Phase C — Leaderboard + decision propose/record + export.** Decimal mean
  aggregation, threshold-assisted proposal engine, immutable `Decision`
  snapshots, decisions export.
- **Phase D — Per-stage scoring binding.** `cohorts.stage_scoring_model_version_ids`
  + `POST /cohorts/{id}/bind-stage-scoring-model`, keyed on the snapshot
  `ProgramStage` id (ADR-0011 Phase 3). Depends on Phase A.

## Alternatives Considered

- **Weighted-percentage criteria (explicit weight field).** Rejected — the
  shipped FE contract models criteria as `max_points` with no weight; introducing
  a parallel weight field would diverge from the approved UI and double the
  authoring surface. Proportional `max_points` expresses the same weighting.
- **Float scoring with display rounding.** Rejected — violates the decimal
  mandate and makes historical results non-reproducible across platforms.
- **One monolithic Assessments spec.** Rejected — four distinct subsystems
  (authoring, review, decide, bind) with very different risk; decomposition lets
  the low-risk authoring twin ship first and unblock Phase D.
- **Assessments owns submissions.** Rejected — Applications already owns
  submissions; Assessments references them to preserve module boundaries.

## Consequences

- **Positive:** Reuses the proven Forms/StagePipeline versioning machinery for
  Phase A (low risk, fast); gives the built FE a real backend; establishes a
  single decimal scoring law before any scoring code exists; unblocks ADR-0011
  Phase 3; ships in independent, individually-reviewable phases.
- **Constraint:** All authoritative scoring must route through the decimal engine
  (no float) — a discipline enforced by tests from Phase B onward.
- **Constraint:** Published `ScoringModelVersion` rows are immutable; a cohort/
  stage binds an exact version; decisions snapshot their inputs.
- **Negative / debt:** Until Phases B–D land, the FE review/score/decide pages
  stay on MSW (only authoring becomes real in Phase A). The blind-review and
  decimal-aggregation seams are real surface area to maintain.

## References

- Phase A spec: `docs/superpowers/specs/2026-07-01-be-assessments-scoring-model-authoring-design.md`
- FE contract: `frontend/src/schemas/assessments.ts`, `frontend/src/api/assessments.ts`
- Versioning kernel: `backend/app/Shared/Versioning/*`; pattern precedents:
  Forms authoring slice (#66), StagePipeline snapshot (ADR-0011 / #68)
- Binding: `frontend/src/api/cohorts.ts` `bindCohortStageScoringModel`,
  `cohorts.stage_scoring_model_version_ids`; ADR-0011 Phase 3
- CLAUDE.md § Versioning and Historical Integrity, § Authorization and Privacy
- ADR-0009 (cross-tenant → 404)
