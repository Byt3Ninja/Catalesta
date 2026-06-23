# Package Manifest

Root CLAUDE.md line count: 182

## Files

- `.claude/rules/00-methodology.md`
- `.claude/rules/01-task-protocol.md`
- `.claude/rules/02-architecture-modules.md`
- `.claude/rules/03-laravel-backend.md`
- `.claude/rules/04-tenancy-authorization.md`
- `.claude/rules/05-identity-profiles-oidc.md`
- `.claude/rules/06-database-migrations.md`
- `.claude/rules/07-api-contracts.md`
- `.claude/rules/08-frontend-ux.md`
- `.claude/rules/09-testing-quality.md`
- `.claude/rules/10-security-privacy.md`
- `.claude/rules/11-workflows-scoring-versioning.md`
- `.claude/rules/12-saas-billing-domains.md`
- `.claude/rules/13-integrations-notifications.md`
- `.claude/rules/14-graphify-impact-analysis.md`
- `.claude/rules/15-git-delivery.md`
- `.claude/rules/16-documentation.md`
- `.claude/rules/17-observability-operations.md`
- `.claude/rules/18-performance-search-reporting.md`
- `.claude/rules/19-files-documents.md`
- `.claude/rules/20-administration-audit.md`
- `.claude/rules/README.md`
- `.gitignore-snippet.txt`
- `CLAUDE.md`
- `README.md`

## Doc authority map

Resolves the ambiguity between competing canonical homes (`_bmad-output/`,
`docs/`, `_bmad/`). Each artifact type has exactly one authoritative home; other
trees may reference it but must not hold a second copy of record.

| Artifact type | Canonical home |
|---|---|
| Product requirements (PRD) | `_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md` |
| UX design spec | `_bmad-output/planning-artifacts/` (BMAD UX artifacts) |
| Architecture (solution design) | `_bmad-output/planning-artifacts/architecture.md` |
| Architecture Decision Records | `adr/` |
| Epics & stories, decision logs, reviews, reconciliations | `_bmad-output/planning-artifacts/` |
| As-built implementation status | `docs/status/implementation-status.md` |
| Module scope register (intent) | `docs/product/scope-register.md` |
| Roadmap / phase sequence | `docs/plan/roadmap.md` |
| Product flows | `docs/product/flows.md` |
| AI/engineering project context | `docs/project-context.md` |
| Repository instructions & rules | `CLAUDE.md`, `.claude/rules/` |

**Split of responsibilities:** `_bmad-output/` is the home for BMAD *planning*
artifacts (what to build and why — PRD, architecture, epics, reviews). `docs/`
is the home for *operational and as-built* artifacts (status, scope register,
roadmap, flows, project context). `_bmad/` (if present) is tooling/templates
only and is never an artifact home of record.

**Conflict-resolution rule:** when two homes disagree on the same artifact, the
home named in this map wins; the losing copy must be removed or replaced with a
pointer (a one-line redirect to the canonical path), never left as a second
source of truth. See `.claude/rules/16-documentation.md` ("Maintain one
authoritative source for each decision or contract").
