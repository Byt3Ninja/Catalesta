# DevOps

> Owner: Architecture · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

Local services: nginx, laravel-api, queue-worker, scheduler, react-web, postgres, redis, minio, mailpit, startup-gate-mock.

CI gates: PHP lint, static analysis, tests, frontend lint and type checks, contract tests, container build, security scan, and end-to-end tests.

## API documentation

Scramble serves an interactive (Swagger-style) API reference and the raw OpenAPI spec:

- Viewer: `http://localhost:8080/docs/api`
- Spec: `http://localhost:8080/docs/api.json` (also exported to `backend/openapi/openapi.json`, the contract baseline CI diffs against)

Access is restricted to `local` and `staging` (via the `viewApiDocs` gate in `AppServiceProvider`); production returns 403.
