---
paths:
  - ".gitignore"
  - ".gitattributes"
  - ".github/**/*"
  - "CHANGELOG*"
  - "RELEASE*"
  - "Dockerfile*"
  - "docker-compose*.yml"
  - "compose*.yml"
  - "deploy/**/*"
  - "infrastructure/**/*"
---

# Git, CI/CD, and Delivery Rules

- Do not work directly on a protected default branch.
- Use one isolated branch or worktree per coherent story or defect.
- Keep commits scoped and reviewable.
- Do not mix unrelated cleanup with feature or defect work.
- Do not force-push, reset shared history, or bypass protection without explicit
  instruction.
- Review staged changes and final diff before completion.
- Do not commit secrets, `.env` files, dependency directories, caches, temporary
  reports, local IDE state, or unintended generated output.
- Preserve repository lockfile strategy.
- Update CI when introducing a required verification command.
- Do not weaken CI/security gates merely to obtain a green pipeline.
- Pin or constrain production dependencies according to repository policy.
- Deployment changes require rollback instructions.
- Database deployment ordering must support the migration compatibility plan.
- Build artifacts must be reproducible.
- Treat deployment credentials and signing material as secrets.
- Report any check skipped locally but required in CI.
