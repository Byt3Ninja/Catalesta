# Spec: Canonical-state docs currency audit (Phase 1, report-only)

**Date:** 2026-06-23
**Status:** Approved — ready for plan
**Phase:** 1 of 2 (Phase 2 = edit PR, specced separately after Phase-1 review)

## Goal

Identify every stale claim in the project's ~10 canonical state docs after the recent merges (Epic 1 close, Epic 2 close, SP-1a identity inversion, SP-1b-i native auth backend, SP-1b-ii native auth frontend). Output is a findings report the user reviews; **edits happen in a separate Phase-2 PR** so policy calls stay with the user, not buried in a 10-doc diff.

## Non-goals

- **Not an edit pass.** No docs are modified in Phase 1.
- **Not a coverage audit** ("every shipped feature has docs, every doc references real code") — separate slice.
- **Not a cross-reference / broken-link audit** — separate slice.
- **Not a style / format consistency pass** — separate slice.
- **Not a re-architecture of the doc tree** — purely currency.

## In-scope docs (the canonical set)

The 10 docs that authoritatively describe project state:

1. `CLAUDE.md`
2. `_bmad-output/implementation-artifacts/sprint-status.yaml`
3. `_bmad-output/planning-artifacts/epics.md`
4. `_bmad-output/planning-artifacts/architecture.md`
5. `docs/status/implementation-status.md`
6. `docs/status/bootstrap.md`
7. `docs/status/phase-2-notes.md`
8. `docs/plan/roadmap.md`
9. `docs/product/scope-register.md`
10. `docs/architecture/overview.md`

Adjacent docs are added to scope only if a finding genuinely requires it (e.g., a roadmap link that points to a moved file). Cap: at most **3** adjacent docs may be pulled in without re-asking the user — beyond that, the doc is logged in "out-of-scope observations" instead. No speculative scope creep.

## Ground truth

**Main branch HEAD** at the time of execution. For each claim in a doc, compared against:

- `git log --oneline` + merged PR titles (what shipped, when)
- `php artisan route:list` (what backend exposes)
- Filesystem reality (`grep`-confirmed class/route/table/migration existence)
- Test suite passing status (green baseline at session start: 384 passed)

The audit does NOT compare against unmerged branches or WIP plans. "Currency" means "agrees with what's on `main` right now."

## Severity rubric

| Tier | Name | Definition | Phase-2 action |
|---|---|---|---|
| **S1** | Stale | Claim disagrees with shipped code (e.g., "ExternalUser owns identity" post-SP-1a; sprint-status says story is `review` when merged). | Must fix |
| **S2** | Drift | Claim was true but is now ambiguous/misleading after a refactor (e.g., a story description mentioning a route by old path that still works via a new path). | Should fix |
| **S3** | Cosmetic | Non-functional staleness (e.g., `last_updated:` header behind actual commit date; "Epic 1 closed" inline comment when Epic 2 also closed). | Nice to fix |

Out-of-scope severity: formatting, future-tense planning of unbuilt work, prose style.

## Audit method (per doc)

1. Read the doc end-to-end once.
2. For every factual claim that names a concrete artifact — class, route, table, migration, story id, status, count, commit, file path — verify against ground truth using graphify first, then targeted Read / Bash / `route:list` / `git log`.
3. Record finding as a row: `{doc, line range, claim, reality, severity, suggested-fix}`.
4. If a cross-doc contradiction is incidentally surfaced (one doc disagrees with another), log it in a separate "Cross-doc contradictions" section — do not hunt for them (that's a separate audit slice).

No parallel subagents — the docs aren't independent enough; findings on one inform expected claims in another.

## Output: the audit report

**Path:** `docs/superpowers/audits/2026-06-23-canonical-docs-currency.md` (new directory `audits/` sibling to `specs/` and `plans/`; first audit, sets the convention).

**Structure:**

1. **Summary**
   - Counts per severity (S1 / S2 / S3)
   - Top-3 biggest deltas (the highest-signal findings)
   - Ground-truth commit SHA (so the report is reproducible)
2. **Per-doc sections** (one per in-scope doc)
   - Markdown table with columns: `Line(s)` · `Claim` · `Reality` · `Severity` · `Suggested fix`
   - Empty table is fine — "no findings" is itself a finding
3. **Cross-doc contradictions** (only populated if incidentally surfaced)
4. **Out-of-scope observations** (anything noticed that belongs in a different audit slice; one-liners only)

**Suggested-fix column is mechanical** — exact text to change. Phase 2's edit PR should be apply-suggested-fixes-verbatim, low-risk.

## Phase 2 preview (not part of this spec)

After the Phase-1 report is reviewed, a follow-up spec covers:
- Apply approved S1 and S2 suggested edits in one PR
- Drop S3 fixes unless trivial
- Single commit per doc for clean review
- Re-run a sanity sweep on each touched doc after edit

That spec is written when Phase 1 lands.

## Effort estimate

~10 docs × ~5–15 minutes each (read + cross-check) = ~1.5–2.5 hours total. Mostly graphify queries, targeted file reads, and a few `git log` / `route:list` lookups. No external services, no migrations, no code changes.

## Risks

- **Risk:** I miscall a fresh claim as stale because I missed an even-newer doc that supersedes it.
  **Mitigation:** When two docs disagree, prefer code-as-truth, and log the disagreement in the cross-doc section so the user can disambiguate.
- **Risk:** The "canonical set" is wrong — a doc I excluded is actually canonical for something.
  **Mitigation:** Out-of-scope observations section captures surprises; user can expand scope in Phase 2 if needed.
- **Risk:** Report bloats with S3 cosmetic noise.
  **Mitigation:** S3 rows are allowed but should be one-liners; they're triaged for drop at Phase 2 unless trivial.

## Success criteria

- Phase-1 report exists at the specified path, committed.
- Every in-scope doc has a section (even if empty).
- Every S1/S2 finding has a concrete `Suggested fix` cell, not a description of how to fix.
- User can read the summary in under 2 minutes and decide which findings to greenlight.
