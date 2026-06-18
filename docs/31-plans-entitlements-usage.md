# Plans, Entitlements, and Usage

## Plan Versioning

Plans are immutable after publication.

Examples:

- Starter v1
- Growth v1
- Growth v2
- Enterprise Contract 2026

Existing tenants may remain on grandfathered versions.

## Entitlement Types

### Boolean Feature

Examples:

- advanced workflows
- API access
- custom domain
- SSO
- white-label
- scheduled reporting

### Numeric Limit

Examples:

- active_programs = 5
- internal_staff_seats = 15
- annual_applications = 2500
- storage_gb = 100
- monthly_api_requests = 100000

### Configuration Entitlement

Examples:

- audit_retention_days
- allowed_integrations
- support_tier
- branding_level

## Usage Behavior

Support:

- monthly reset
- annual reset
- lifetime
- concurrent
- soft limit
- hard limit
- warning thresholds
- manual adjustments
- usage reconciliation

## Limit Policy

At 80 percent:

- notify tenant billing administrators

At 100 percent:

- preserve existing data
- preserve read access
- prevent only new limit-consuming actions
- do not interrupt in-progress critical workflows
- provide structured upgrade guidance
