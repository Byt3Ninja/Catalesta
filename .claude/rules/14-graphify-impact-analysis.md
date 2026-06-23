# Graphify and Blast-Radius Analysis Rules

Graphify is a navigation aid, not an authoritative source.

Before architecture analysis, dependency analysis, module discovery, broad
refactoring, or repository-wide searching:

1. Check for `graphify-out/GRAPH_REPORT.md`.
2. Determine whether it predates substantial structural changes.
3. Read the report when present and sufficiently current.
4. Use Graphify queries to identify modules, files, symbols, dependencies,
   communities, and highly connected nodes.
5. Inspect actual source, tests, schema, and configuration before conclusions.
6. Perform blast-radius analysis before changing shared models, services,
   middleware, tenancy, authorization, schemas, events, or public APIs.
7. Regenerate Graphify after substantial structural changes.

When Graphify conflicts with source code, constraints, configuration, or tests,
the actual implementation defines current behaviour.

When Graphify is absent, invalid, or stale:

- Continue with normal repository inspection.
- Record that Graphify was not relied upon.
- Do not allow the Graphify requirement to block urgent localized defect work.

Avoid broad Glob/Grep/rg/find operations before reading a usable graph report for
tasks where the report is required.
