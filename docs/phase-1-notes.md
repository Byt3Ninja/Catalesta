# Phase 1 — Identity & Tenancy: Operating Notes

## Running locally

```bash
cp backend/.env.example backend/.env
# Then set APP_KEY (generates one or copy from the artisan output):
cd backend && php artisan key:generate && cd ..
docker compose up -d --build
```

The platform API is available at `http://localhost:8080` and the mock provider at
`http://localhost:8081`.

## Dual-role model

The codebase runs the same Laravel image in two roles, controlled by `APP_ROLE`:

- `APP_ROLE=platform` (default): the main Catalesta platform. Registers all
  platform routes and modules. Does not load any mock-specific code.
- `APP_ROLE=mock`: the Startup Gate Mock. Registers only the OIDC and profile
  API routes from `routes/startup-gate-mock.php` via
  `StartupGateMockServiceProvider`. Exposes `/.well-known/*`, `/oauth/*`, and
  the profile API under `/sg/api/v1` (see below).

The `startup-gate-mock` service in `docker-compose.yml` runs the backend image
with `APP_ROLE=mock`, `command: php artisan serve --host=0.0.0.0 --port=8080`.

## Stable signing keys for the mock (`sg-mock:keys`)

`MockKeys` (in `App\StartupGateMock\Support\MockKeys`) generates a per-process
RS256 keypair and caches it in the Laravel cache store. Because `artisan serve`
is a single process, the keypair is consistent across all requests in the
default local Docker setup — no additional configuration is required.

For a multi-worker deployment (e.g. php-fpm behind nginx), all workers need to
share one keypair. Generate a stable keypair once and set the env vars:

```bash
cd backend && php artisan sg-mock:keys
# Prints two lines: SG_MOCK_PRIVATE_KEY="..." and SG_MOCK_PUBLIC_KEY="..."
```

Add those values to the `startup-gate-mock` service environment in
`docker-compose.yml` (or the host `.env`). The mock will load them instead of
generating a new pair.

## Mock profile API prefix (`/sg/api/v1`)

The mock exposes its profile API under `/sg/api/v1` (e.g.
`GET /sg/api/v1/me`), not `/api/v1`. This avoids a route collision with the
platform's own `/api/v1/me` endpoint when both roles boot in one process during
testing.

In `docker-compose.yml` and backend `.env`, `PROFILE_API_BASE_URL` is set to
`http://startup-gate-mock:8080/sg/api/v1`. The platform's `StartupGateProfile`
adapter uses this base URL for all profile API calls.

Note: `docs/10-startup-gate-mock.md` documents the canonical Startup Gate
contract at `/api/v1`. The `/sg/api/v1` prefix is a local single-codebase
deviation only; when the real Startup Gate is wired up (Phase 12),
`PROFILE_API_BASE_URL` will point at the real host and the prefix will revert
to `/api/v1`.

## Test strategy

- **Contract tests**: exercise the mock's OIDC and profile endpoints directly,
  asserting exact response shapes per `docs/10-startup-gate-mock.md`.
- **Platform feature tests**: use `Http::fake()` and `MockKeys` to generate
  valid RS256 tokens without a running mock container. The `testing` environment
  always uses the `MockKeys` keypair for determinism.
- The full suite (100 tests) covers unit, feature, contract, and
  tenant-isolation categories.

## Phase 1 deferrals

The following items are intentionally out of scope for Phase 1 and will be
addressed in later phases (see design spec §14):

- Achievement publication and profile-update-proposal consumption on the
  platform side (Profiles / Graduation phases).
- Inbound webhook delivery and processing pipeline (Integrations + transactional
  outbox).
- Postgres RLS hardening (defense-in-depth, later hardening pass).
- Real Startup Gate integration (Phase 12 — config and adapter swap only, no
  platform code changes required).
