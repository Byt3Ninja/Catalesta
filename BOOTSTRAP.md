# Bootstrap (Phase 0) — Repository Foundation

This document records what the Phase 0 bootstrap created and how to run it.
See `prompts/00-bootstrap.md` for the task spec and `docs/plan/roadmap.md`
for the phase plan.

## Layout

```text
backend/                 Laravel 13 modular monolith (PHP 8.3 target)
  app/Modules/           20 module folders (scaffold only — no business logic yet)
  app/Shared/            Shared kernel scaffold (Rules, Versioning, Tenancy, Outbox,
                         Idempotency, Audit, Support) — implemented in Phase 1/1.5
  app/Http/Controllers/HealthController.php   GET /api/v1/health
frontend/                React 19 + TypeScript (strict), Vite, React Query, Zod
services/startup-gate-mock/   Placeholder mock identity service (full impl: Phase 1)
docker/nginx/            Nginx site config
docker-compose.yml       Full local stack
.github/workflows/ci.yml CI pipeline
```

## Run locally

```bash
# Backend env
cp backend/.env.example backend/.env && (cd backend && php artisan key:generate)

# Whole stack
docker compose up -d --build

# API health:  http://localhost:8080/api/v1/health
# Web app:     http://localhost:3000
# MinIO:       http://localhost:9001  (minioadmin / minioadmin)
# Mailpit:     http://localhost:8025
# Mock IdP:    http://localhost:8081/health
```

## Tests

```bash
# Backend
cd backend
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=512M

# Frontend
cd frontend
npm run typecheck
npm run lint
npm run test
```

## Deferred (intentionally) to later phases

- Business modules (Phase 1+). Module folders exist but contain no logic.
- Full mock OIDC/profile provider (Phase 1, docs/10) — only a placeholder here.
- CI "security scan" and "publish artifacts" steps from docs/13 — added when there
  is code to scan/publish; the bootstrap CI runs lint, static analysis, tests, and
  a container build + mock smoke test.
- The orphaned root-level `.env` / `.env.example` are superseded by
  `backend/.env.example` and can be removed in a follow-up cleanup.
