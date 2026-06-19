# Personalized Tracks

> Owner: Product · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

Build-spec `06` (Stages module). Defines a previously-undefined scope item.

## What a track is

A **track** is a named subset of a program's stages that a participant follows,
letting one program serve differentiated paths (e.g. "B2B" vs "B2C", or
"pre-seed" vs "growth") without cloning the whole program.

A track is **not** a separate program and **not** a single stage path — it is an
applicability filter layered over the existing stage engine:

- A program has 0..N tracks.
- Each stage is marked **applicable to** all tracks (default) or a specific set
  of tracks (`program_stage_track` pivot — see `../../plan/phases/2026-06-19-phase2-completion.md`).
- A participant is assigned to exactly one track per cohort (or none = the
  default/all-stages path).

## Who assigns

Program staff (`programs.manage` / a participant-management permission) assign a
participant to a track at enrollment or during the program. Assignment is an
attribute of the participant's cohort enrollment, not of the application.

## Interaction with the stage engine

When the stage engine evaluates whether a participant may enter a stage, it
checks track applicability **first** (before prerequisites and entry rules): a
stage not applicable to the participant's track is skipped for them. This keeps
tracks declarative and inside the existing rule kernel — no parallel engine.

## Lifecycle

- Tracks are created/edited while the program is a draft or published (tracks are
  not versioned artifacts themselves).
- Deleting a track cascades: removes its stage applicability rows and nulls
  affected participants' `track_id` (they fall back to the default path).
