# Catalesta Documentation Map

> Owner: Platform · Last-updated: 2026-06-19 · Source-of-truth: this file (for navigation)

## Purpose

This is the documentation map. **Two files are authoritative:**

- **`product/scope-register.md`** defines *what* we build.
- **`plan/roadmap.md`** decides *when and in what order*.

Every other doc references these two and restates **neither scope nor sequence**.

## Folder semantics

| Folder | Owns | Question |
|---|---|---|
| `product/` | functional scope, brief, lifecycle, feature definitions | **WHAT** (intent) |
| `architecture/` | technical decisions, boundaries, data ownership, security, resilience | **HOW** |
| `saas/` | commercial plane: plans, entitlements, billing, domains, branding | **HOW (commercial)** |
| `ux/` | experience layer: strategy, design system, navigation, flows | **HOW (experience)** |
| `quality/` | testing strategy, integration testing | **VERIFICATION** |
| `plan/` | roadmap, dependency graph, release gates, build specs, engineering phases | **WHEN / order** |
| `status/` | as-built implementation status, bootstrap, engineering notes | **AS-BUILT** |

## Conventions

- **No global filename numbers.** Semantic folders + descriptive kebab-case names. The only allowed numbering is the build-spec IDs `00`–`68` under `plan/build-specs/`.
- **Canonical module count: 24** (the `CLAUDE.md` "Required Modules" list). Stated identically everywhere.
- **Canonical numbering: build-spec IDs `00`–`68`.** The register, dependency-graph, and brief reference these IDs; no competing numbered list exists.
- Every doc starts with a header block: `Owner · Last-updated · Source-of-truth: <link>`.

## Update rules

1. Changing scope → edit **`product/scope-register.md`** first; downstream docs reference it.
2. Changing build order → edit **`plan/roadmap.md`** first.
3. **Never** put implementation status in scope/plan docs — status lives only in `status/`.
4. Adding a doc → add a row to the **Doc map** below.

## Doc map

| Path | Purpose | References |
|---|---|---|
| `product/scope-register.md` | ★ **AUTHORITATIVE — scope.** Canonical functional surface, 24 modules, build-spec IDs | — |
| `plan/roadmap.md` | ★ **AUTHORITATIVE — sequence.** Phases, MVP cut line, deferred backlog | scope-register, dependency-graph, release-gates |
<!-- Subsequent tasks append one row per relocated/new doc. -->
