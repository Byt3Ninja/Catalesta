# Claude Code Repository Instructions

## Mission

Build a production-grade, secure, configurable, multi-tenant platform for
incubation, acceleration, mentorship, training, evaluation, graduation, and
post-graduation programs.

The platform must remain operational without Startup Gate or any optional
external integration.

## Instruction Authority

Apply instructions in this order:

1. Explicit instructions for the current task
2. Approved BMAD story and acceptance criteria
3. Approved Architecture Decision Records
4. This `CLAUDE.md`
5. `docs/project-context.md`
6. Approved BMAD product and architecture documents
7. Existing verified application behaviour
8. Assumptions

Do not silently resolve material contradictions.

When a contradiction exists:

1. Record the contradiction.
2. Mark the affected work as blocked.
3. Continue unaffected work where possible.
4. Do not invent business rules, security decisions, or architecture decisions.

Source code, database constraints, tests, and deployed configuration take
precedence over generated documentation when determining current behaviour.

Approved product and architecture documents define intended behaviour.

## Methodology Boundaries

### BMAD owns

- Product scope
- Business requirements
- Domain rules
- Functional requirements
- Non-functional requirements
- Architecture
- Data ownership
- UX requirements
- Epics
- Stories
- Acceptance criteria
- Release scope
- Traceability

### Superpowers owns

- Story-level implementation planning
- Git branch or worktree isolation
- Test-driven development
- Systematic debugging
- Incremental implementation
- Refactoring
- Specification-compliance review
- Code-quality review
- Verification before completion

Do not use Superpowers brainstorming to reopen an approved BMAD story unless the
story is contradictory, incomplete, unsafe, or technically impossible.

Do not use implementation activity to redefine approved product scope.

## Architecture Ownership

1. Use a Laravel modular monolith.
2. Catalesta owns:
   - Global identity
   - Local accounts
   - General profiles
   - Role profiles
   - Organization memberships
   - Consent records
   - External identity links
3. Catalesta is the system of record for locally stored identity and profile
   information.
4. Startup Gate is an optional external identity provider and consent-based
   profile-import source.
5. Startup Gate is never the system of record for Catalesta-owned data.
6. The program platform owns:
   - Organizations
   - Programs
   - Cohorts
   - Stages
   - Applications
   - Evaluations
   - Assignments
   - Mentorship
   - Training
   - Graduation
   - Reporting

Keep module boundaries explicit.

Do not bypass module boundaries through uncontrolled direct database access.

## Identity Rules

1. The primary user identifier is the local `Account` ULID.
2. Never use email as:
   - A cross-system identifier
   - A cross-tenant identifier
   - An external identity linkage key
   - A permanent ownership identifier
3. Email may be used only as a local login credential and verified contact
   attribute.
4. A linked Startup Gate `sub` is:
   - Immutable
   - Unique per issuer
   - Never reassigned
   - Never the local primary key
5. External identities must be identified by at least:
   - Issuer
   - Subject
6. Do not create duplicate local accounts for the same verified external
   identity.
7. Account-linking operations must require authenticated confirmation and must
   be audited.
8. External authentication failure must not block local authentication.

## Startup Gate Integration

Startup Gate integration is optional.

Users must be able to:

- Register locally
- Authenticate locally
- Complete local profiles
- Operate while Startup Gate is unavailable
- Link Startup Gate later
- Unlink Startup Gate
- Revoke integration access
- Import selected profile fields after explicit consent
- Review imported values before persistence
- Edit imported local copies

Rules:

1. Validate OIDC issuer, subject, audience, state, nonce, redirect URI, token
   expiry, and signature.
2. Use PKCE where applicable.
3. Store external tokens securely.
4. Never log tokens, authorization codes, secrets, or sensitive claims.
5. Apply timeouts to external calls.
6. Define retry behaviour explicitly.
7. Do not retry permanent authentication or authorization failures.
8. Imported values are local editable copies.
9. Store import provenance where required.
10. Never automatically overwrite a locally modified field.
11. Record consent, import, linkage, unlinkage, and revocation events.
12. Startup Gate must never become a runtime dependency for normal platform
    operation.

## Profile Access and Consent

Locally owned profile access is governed by:

- Authentication
- Authorization
- Tenant membership
- Role
- Purpose
- Privacy settings
- Data minimization

Explicit consent is required for:

- Importing fields from Startup Gate
- Sharing fields with external systems
- Cross-organization disclosure when not otherwise contractually authorized
- Optional profile exposure to programs, mentors, investors, or service
  providers
- Processing that exceeds the original declared purpose

Normal authorized internal access to locally owned profile data does not require
a new consent record for every read.

Consent records must define:

- Subject account
- Recipient or processing context
- Purpose
- Granted fields or scopes
- Grant timestamp
- Expiry where applicable
- Revocation timestamp
- Consent version

Revocation must stop future access or synchronization without corrupting
historical records that must legally or operationally remain immutable.

## Multi-Tenancy Rules

1. Every tenant-owned aggregate must have an enforceable organization boundary.
2. Tenant ownership may be represented:
   - Directly by `organization_id`
   - Through a mandatory parent aggregate with an enforced organization boundary
3. Prefer direct `organization_id` on high-risk, frequently queried, exported,
   audited, or independently authorized records.
4. Every tenant query must enforce tenant isolation.
5. Resolve tenant context through trusted server-side mechanisms.
6. Never trust a client-supplied `organization_id` without membership and
   authorization validation.
7. Cross-tenant access must be denied by default.
8. Apply tenant isolation to:
   - Reads
   - Creates
   - Updates
   - Deletes
   - Exports
   - Reports
   - Search
   - Background jobs
   - Notifications
   - File access
9. Background jobs must restore and validate tenant context.
10. Shared platform records must be explicitly modelled as global or shared.
11. Tenant isolation requires automated negative tests.
12. Unknown tenant hosts must be rejected.
13. No fallback tenant may be selected for an unknown host.

## Authorization Rules

1. Enforce authorization server-side.
2. Frontend visibility is not authorization.
3. Deny access by default.
4. Use centralized policies, gates, guards, or permission services.
5. Validate:
   - Organization membership
   - Membership status
   - Role
   - Permission
   - Resource ownership
   - Tenant context
6. Audit privileged and security-sensitive operations.
7. Never authorize based only on request-provided role, owner, account, or
   organization identifiers.

## Required Modules

- Identity
- Organizations
- Profiles
- Startups
- Programs
- Cohorts
- Stages
- Forms
- Applications
- Documents
- Assessments
- Workflows
- RoleAssignments
- Tasks
- Mentorship
- Training
- FinalEvaluation
- Graduation
- Notifications
- Integrations
- Reporting
- Search
- Administration
- Audit
- Billing
- Entitlements
- TenantDomains
- Branding

New modules require architecture justification.

Do not introduce overlapping modules for capabilities already owned by an
existing module.

## Versioning and Immutability

Published domain definitions are immutable.

This includes:

- Forms
- Form schemas
- Workflows
- Assessments
- Scoring models
- Program stages
- Plan definitions
- Formal evaluation templates

Changes require a new version.

Existing submissions and executions must continue referencing the exact version
used at creation or submission time.

Formal submissions must capture immutable snapshots of relevant definitions and
submitted data.

Do not reconstruct historical submissions from mutable current records.

## Workflow and State Integrity

1. Represent lifecycle changes as explicit state transitions.
2. Reject invalid transitions server-side.
3. Validate actor permission and tenant context before transitions.
4. Execute related state changes transactionally.
5. Audit:
   - Actor
   - Organization
   - Previous state
   - New state
   - Timestamp
   - Reason where applicable
6. Retryable transitions must be idempotent.
7. Do not update lifecycle states through unrestricted model updates.
8. Do not allow arbitrary code execution in workflow rules.
9. Use controlled operators and validated rule definitions.

## Scoring Rules

1. Use decimal arithmetic for scoring.
2. Never use binary floating-point arithmetic for authoritative scores.
3. Define:
   - Precision
   - Scale
   - Rounding mode
   - Weight normalization
   - Missing-value behaviour
4. Store score calculation inputs and version references.
5. Historical scores must remain reproducible.
6. Do not silently recalculate historical scores using a newer scoring model.

## SaaS, Plans, and Entitlements

1. Plans are versioned.
2. Published plan versions are immutable.
3. Domain modules must use `EntitlementService`.
4. Never check plan names directly.
5. Never scatter billing or plan logic across domain modules.
6. Usage limits must be enforced server-side.
7. Entitlement checks must occur before creating or expanding limited resources.
8. Reaching a limit must not hide, corrupt, or delete existing tenant data.
9. Downgrades require explicit over-limit behaviour.
10. Usage counters must be consistent and recoverable.
11. Entitlement decisions must be auditable where they affect paid access.

## Payments

1. Keep Geidea behind payment-provider interfaces.
2. Domain modules must not depend directly on Geidea SDKs or payload formats.
3. Browser payment returns are not authoritative.
4. Verify server-to-server callbacks.
5. Process callbacks idempotently.
6. Persist provider event identifiers.
7. Reject duplicate or replayed events safely.
8. Never store raw card numbers or CVV.
9. Define payment states explicitly.
10. Reconcile ambiguous or delayed payment events.
11. Audit subscription and payment state changes.
12. Treat provider timeouts as unknown outcomes until reconciled.

## Tenant Domains and Branding

1. Custom domains require ownership verification.
2. Custom domains require active TLS.
3. Tenant resolution from host names must reject unknown hosts.
4. Domain ownership changes must be audited.
5. Branding permits controlled tokens and validated assets only.
6. Do not permit arbitrary CSS, JavaScript, HTML, or executable templates.
7. Validate uploaded assets by:
   - File type
   - MIME type
   - Size
   - Dimensions where applicable
   - Storage location
8. Branding must not override security-critical or accessibility-critical UI.

## External Integrations

Keep every external integration behind an interface.

Each integration must define:

- Authentication
- Secret storage
- Timeout
- Retry policy
- Idempotency
- Rate-limit handling
- Error mapping
- Observability
- Degraded behaviour
- Recovery procedure
- Contract tests

Do not leak provider-specific objects into unrelated domain modules.

Do not retry permanent validation, authentication, or authorization failures.

## Task Classification

Classify work before implementation.

### Product or architecture change

Use the relevant BMAD workflow.

Do not implement until scope, architecture impact, acceptance criteria, tenant
impact, and security impact are defined.

### Approved story implementation

Use the Superpowers execution workflow.

Do not alter approved product scope.

### Defect

Use systematic debugging.

Prove the root cause before changing production behaviour.

Add a regression test that fails before the fix.

### Refactoring

Preserve observable behaviour.

Add characterization tests before changing untested legacy behaviour.

Do not mix unrelated refactoring with a feature or defect fix.

### Documentation-only work

Verify documentation against source code, tests, database constraints, and
approved architecture.

## Story Readiness

Do not implement a story unless it defines, where applicable:

- Business objective
- Actor
- Business rules
- Functional requirements
- Acceptance criteria
- Authorization requirements
- Tenant-isolation requirements
- Data changes
- API changes
- UI states
- Validation rules
- Error scenarios
- Audit requirements
- Test scenarios
- Dependencies
- Migration impact
- Rollback considerations

Mark incomplete stories as blocked.

Do not fill material requirement gaps through assumptions.

## Task Protocol

Before code, schema, API, or architecture changes:

1. Read the assigned story.
2. Read `docs/project-context.md`.
3. Read relevant architecture documents and ADRs.
4. Inspect the current implementation.
5. Inspect existing tests.
6. Identify affected modules and boundaries.
7. Perform blast-radius analysis.
8. Summarize:
   - Implementation plan
   - Expected files
   - Schema changes
   - API changes
   - Assumptions
   - Security risks
   - Tenant risks
   - Migration impact
   - Rollback concerns
9. Map every acceptance criterion to one or more tests.

Do not begin broad implementation before completing this analysis.

## Test-Driven Development

For every behaviour change:

1. Write or update a test first.
2. Run the test.
3. Confirm it fails for the expected reason.
4. Implement the smallest correct change.
5. Run the focused test.
6. Refactor while preserving behaviour.
7. Run relevant regression tests.

Do not:

- Implement production behaviour before its test
- Weaken assertions to make tests pass
- Remove valid tests without documented justification
- Mock the behaviour being tested instead of exercising it
- Claim success without executed evidence

Tests must cover, where applicable:

- Happy path
- Invalid input
- Unauthenticated access
- Unauthorized access
- Cross-tenant access
- Invalid state transition
- Duplicate requests
- Replay attempts
- External service failure
- Timeout
- Idempotency
- Audit-log creation
- Migration rollback

## Code Quality

1. Follow established repository conventions.
2. Keep controllers and route handlers thin.
3. Keep business rules in cohesive domain or application services.
4. Centralize validation.
5. Centralize authorization.
6. Use explicit domain terminology.
7. Avoid duplicated business rules.
8. Avoid hidden global state.
9. Do not swallow exceptions silently.
10. Do not add hardcoded production values.
11. Do not add placeholder implementations presented as complete.
12. Do not leave disconnected buttons or UI actions.
13. Avoid unbounded queries.
14. Prevent N+1 queries.
15. Do not place business logic in views or presentation components.
16. Do not add dependencies without documented justification.
17. Do not bypass service or module boundaries for convenience.

## Database Changes

1. Inspect existing schema and representative data first.
2. Make migrations deterministic.
3. Make migrations reversible where technically possible.
4. Define an explicit rollback strategy.
5. Preserve existing data unless destructive change is explicitly approved.
6. Do not edit previously deployed migrations.
7. Add database constraints for critical invariants where appropriate.
8. Add indexes based on established query patterns.
9. Test migrations against representative data.
10. Verify both migration and rollback behaviour.
11. Use safe staged migrations for large or high-risk tables.

## API Changes

1. Follow established API conventions.
2. Validate every external input.
3. Enforce authorization and tenant context before record access.
4. Return structured errors without internal stack traces.
5. Paginate collection endpoints.
6. Define idempotency where duplicate execution is possible.
7. Document compatibility impact.
8. Do not introduce breaking changes without explicit approval and migration
   planning.
9. Update API contracts and contract tests.

## Frontend Changes

Every interactive element must implement, where applicable:

- Real action
- Loading state
- Success state
- Empty state
- Validation state
- Error state
- Disabled state
- Permission-aware rendering
- Accessible label
- Keyboard behaviour

Do not:

- Use frontend checks as a security boundary
- Hardcode live business data
- Leave placeholder actions
- Suppress errors silently
- Introduce inconsistent duplicate state

## Security Rules

Never:

- Commit secrets
- Display secrets
- Log passwords, tokens, private keys, or authorization codes
- Disable security controls to make tests pass
- Trust user-supplied ownership or tenant identifiers
- Bypass authorization through direct model access
- Execute destructive commands without explicit authorization
- Store raw card numbers or CVV
- Permit arbitrary executable rules, branding, or templates

Review changes for:

- Injection
- Cross-site scripting
- CSRF
- Broken access control
- Insecure direct object reference
- Mass assignment
- Unsafe file uploads
- Sensitive-data exposure
- Token leakage
- Race conditions
- Replay attacks
- Cross-tenant data leakage

## Graphify Knowledge Graph

This project uses Graphify for codebase and architecture navigation.

Before architecture analysis, dependency analysis, impact analysis, large
refactoring, module discovery, or broad codebase searching:

1. Check whether `graphify-out/GRAPH_REPORT.md` exists.
2. Check whether the report appears current relative to substantial structural
   changes.
3. Read `graphify-out/GRAPH_REPORT.md`.
4. Use Graphify queries to identify relevant:
   - Modules
   - Files
   - Symbols
   - Dependencies
   - Communities
   - Highly connected nodes
5. Inspect actual source files before making conclusions or changes.
6. Perform blast-radius analysis before modifying:
   - Shared services
   - Models
   - Middleware
   - Authorization
   - Tenant resolution
   - Database schemas
   - Public APIs
7. Regenerate the graph after substantial architectural or structural changes.

Graphify is a navigation aid, not an authoritative source.

When Graphify conflicts with source code, database constraints, configuration, or
tests, the actual implementation is authoritative for current behaviour.

When the graph is absent, invalid, or materially stale, use normal repository
inspection and record that Graphify could not be relied upon.

Do not allow Graphify requirements to block urgent localized defect analysis.

## Git Rules

1. Do not work directly on a protected default branch.
2. Use one branch or worktree per coherent story or defect.
3. Keep commits scoped.
4. Do not mix unrelated cleanup into feature commits.
5. Do not force-push or rewrite shared history without explicit instruction.
6. Review the final diff before completion.
7. Do not commit:
   - Secrets
   - Local environment files
   - Dependency directories
   - Caches
   - Temporary reports
   - Generated files not intended for source control

## Verification

After implementation, run repository-defined commands for:

- Formatting verification
- Linting
- Static analysis
- Unit tests
- Feature tests
- Authorization tests
- Tenant-isolation tests
- Contract tests
- Frontend tests
- Type checking
- Build verification
- Security checks
- Migration verification
- Rollback verification

Read commands from repository manifests, scripts, documentation, and CI
configuration.

Do not invent verification commands when repository-defined commands exist.

Do not claim that work is complete, fixed, secure, production-ready, or fully
tested based only on code inspection.

## Completion Report

Every completed implementation must report:

- Story or issue identifier
- Summary of implemented behaviour
- Acceptance-criteria matrix
- Files added
- Files modified
- Database migrations
- API changes
- UI changes
- Tests added or modified
- Commands executed
- Actual command results
- Authorization validation
- Tenant-isolation validation
- Security validation
- Migration and rollback results
- Documentation updates
- Known limitations
- Remaining risks

Use `Not verified` for checks that could not be executed.

Never represent an unverified result as passed.
