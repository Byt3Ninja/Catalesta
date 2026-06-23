# Task Planning and Delivery Protocol

## Before Editing

Report:

- Task classification
- Relevant story or issue identifier
- Authoritative documents read
- Current behaviour and evidence
- Affected modules and boundaries
- Planned files to add or modify
- Schema, migration, API, UI, and integration impact
- Security and privacy risks
- Tenant-isolation risks
- Compatibility risks
- Rollback strategy
- Acceptance-criteria-to-test mapping

Do not perform broad cleanup unrelated to the task.

## During Implementation

- Keep changes incremental and reviewable.
- Run focused tests after each material behaviour change.
- Preserve backward compatibility unless explicitly changed.
- Record unexpected constraints immediately.
- Stop affected implementation when a material contradiction appears.
- Continue independent, unaffected work where safe.
- Do not leave TODO behaviour, placeholder implementations, disconnected UI, or
  silent failures presented as complete.

## Before Completion

- Review the full diff.
- Confirm no secrets or generated junk were added.
- Run repository-defined checks.
- Verify database forward and rollback paths where applicable.
- Verify negative authorization and cross-tenant cases.
- Verify external integration failure behaviour.
- Update relevant documentation and contracts.
- Report unexecuted checks as `Not verified`.
