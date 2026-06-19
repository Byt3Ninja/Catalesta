# Architecture

> Owner: Architecture · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

## Stack

- Laravel modular monolith
- React + TypeScript
- PostgreSQL
- Redis
- S3-compatible storage
- REST APIs
- Transactional outbox
- Signed webhooks
- Docker
- GitHub Actions
- OpenTelemetry-compatible observability

## Platform Boundary

Startup Gate owns identity and reusable profile data.

The Program Platform owns program execution data.

No direct database sharing.
