---
paths:
  - "**/*.md"
  - "docs/**/*"
  - "openapi/**/*"
  - "README*"
  - "CHANGELOG*"
  - "adr/**/*"
---

# Documentation Rules

- Maintain one authoritative source for each decision or contract. The canonical
  home for each artifact type is declared in `MANIFEST.md` § Doc authority map;
  when two homes disagree, the home named there wins and the loser must redirect.
- Do not duplicate full requirements across multiple documents.
- Update documentation in the same change as architecture, API, schema,
  environment, integration, operational, or security-sensitive changes.
- Use exact domain terminology from `docs/project-context.md`.
- Use Catalesta consistently as the local platform/system-of-record name unless
  an approved rename decision states otherwise.
- Distinguish current behaviour from target behaviour.
- Mark assumptions, unresolved decisions, and deprecated content explicitly.
- ADRs must include context, decision, alternatives, consequences, and status.
- Stories must contain testable acceptance criteria.
- API documentation must match executable routes and contracts.
- Operational instructions must include prerequisites, validation, failure
  handling, and rollback.
- Never include secrets, live credentials, private keys, or sensitive user data.
- Link to authoritative artifacts rather than copying large blocks.
- Verify examples against current code and repository commands.
