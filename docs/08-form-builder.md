# Form Builder

## Goals

- Create configurable forms
- Support reusable templates
- Support versioning
- Support conditional logic
- Support Startup Gate profile field mapping
- Preserve submission snapshots

## Field Types

- text
- textarea
- number
- decimal
- date
- datetime
- select
- multiselect
- boolean
- file
- url
- email
- phone
- table
- repeater
- user_reference
- startup_reference
- calculated

## Field Mapping Modes

### Live Read-Only

Displays current Startup Gate data.

### Snapshot

Copies data into the application snapshot.

### Editable Snapshot

Allows editing in the application only.

### Editable and Propose Sync

Allows editing and creates a proposed update for Startup Gate.

### Program-Only

Never synchronized outside the program platform.

## Field Mapping Configuration

```json
{
  "source_system": "startup_gate",
  "source_path": "founder_profile.biography",
  "display_mode": "snapshot",
  "edit_mode": "editable_and_propose_sync",
  "consent_scope": "profile.founder.read"
}
```

## Versioning Rules

- Draft versions are editable.
- Published versions are immutable.
- New changes create a new version.
- Existing submissions remain linked to their original version.

## Conditional Logic

```json
{
  "when": {
    "field": "company_registered",
    "operator": "equals",
    "value": true
  },
  "then": {
    "show": ["registration_number", "commercial_registration"]
  }
}
```
