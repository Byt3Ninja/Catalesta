# DevOps and Deployment

## Local Development

Use Docker Compose.

Services:

```text
nginx
laravel-api
queue-worker
scheduler
react-web
postgres
redis
minio
mailpit
startup-gate-mock
```

## Environments

- local
- test
- development
- staging
- production

## CI Pipeline

1. Install dependencies
2. Lint PHP
3. Run PHP static analysis
4. Run unit tests
5. Run feature tests
6. Run TypeScript checks
7. Run frontend tests
8. Run contract tests
9. Build containers
10. Run security scans
11. Publish artifacts

## Deployment

Recommended production components:

- Managed PostgreSQL
- Managed Redis
- S3-compatible storage
- Containerized Laravel API
- Separate queue workers
- Scheduler process
- Static React frontend
- Centralized logs
- Error tracking
- Metrics and tracing

## Database Migrations

- Backward-compatible when possible
- No destructive migration without backup and rollback plan
- Large changes require staged migrations
- Data backfills must be queueable and resumable

## Observability

Track:

- API latency
- Error rate
- Queue depth
- Failed jobs
- Authentication failures
- Webhook failures
- Workflow failures
- Tenant isolation violations
- Slow queries
