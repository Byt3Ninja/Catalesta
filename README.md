# Catalesta Claude Code Configuration

This package contains:

- `CLAUDE.md`: concise repository-wide invariants and workflow controls
- `.claude/rules/`: modular unconditional and path-scoped rules
- `.gitignore-snippet.txt`: local Claude files that should not be committed

## Installation

Copy `CLAUDE.md` and the `.claude` directory to the repository root.

Review path patterns against the actual repository layout, especially whether
modules live under `Modules/`, `modules/`, or `app/`.

Do not copy over existing `.claude/settings.json`, commands, agents, or plugin
files without reviewing and merging them.

## Validation

Inside Claude Code:

```text
/memory
/status
/doctor
```

Confirm the root file and expected rules are loaded.

## Methodology

- BMAD governs product, requirements, architecture, stories, and readiness.
- Superpowers governs implementation discipline, TDD, debugging, review, and
  verification.
- Graphify supports navigation and impact analysis but is not authoritative.
