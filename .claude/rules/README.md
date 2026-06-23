# Claude Code Rules Index

Claude Code discovers Markdown files recursively under `.claude/rules/`.
Rules without `paths` frontmatter load for every session. Rules with `paths`
frontmatter load when Claude works with matching files.

## Unconditional Rules

- `00-methodology.md`
- `01-task-protocol.md`
- `14-graphify-impact-analysis.md`

## Scoped Rules

- `02-architecture-modules.md`
- `03-laravel-backend.md`
- `04-tenancy-authorization.md`
- `05-identity-profiles-oidc.md`
- `06-database-migrations.md`
- `07-api-contracts.md`
- `08-frontend-ux.md`
- `09-testing-quality.md`
- `10-security-privacy.md`
- `11-workflows-scoring-versioning.md`
- `12-saas-billing-domains.md`
- `13-integrations-notifications.md`
- `15-git-delivery.md`
- `16-documentation.md`
- `17-observability-operations.md`
- `18-performance-search-reporting.md`
- `19-files-documents.md`
- `20-administration-audit.md`

## Maintenance

- Keep the root `CLAUDE.md` concise and stable.
- Put file-specific implementation controls in scoped rules.
- Put repeatable multi-step procedures in Claude skills rather than permanent
  instructions.
- Remove contradictions and obsolete rules promptly.
- Verify loaded instructions with Claude Code's `/memory` command.
