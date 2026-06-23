---
paths:
  - "Modules/Billing/**/*.php"
  - "Modules/Entitlements/**/*.php"
  - "Modules/TenantDomains/**/*.php"
  - "Modules/Branding/**/*.php"
  - "modules/billing/**/*.php"
  - "modules/entitlements/**/*.php"
  - "modules/tenant-domains/**/*.php"
  - "config/**/*payment*.php"
  - "config/**/*domain*.php"
  - "tests/**/*Billing*.php"
  - "tests/**/*Entitlement*.php"
  - "tests/**/*Domain*.php"
---

# SaaS, Entitlements, Payments, Domains, and Branding Rules

## Plans and Entitlements

- Published plan versions are immutable.
- Domain modules call `EntitlementService`; never compare plan names.
- Enforce limits server-side before creating or expanding limited resources.
- Existing data remains visible and intact after a limit is reached.
- Define downgrade and over-limit behaviour explicitly.
- Usage measurements must be consistent, recoverable, and reconcilable.
- Audit entitlement decisions that affect paid access.

## Geidea and Payments

- Keep provider logic behind payment interfaces and adapters.
- Browser return URLs are not authoritative.
- Verify server-to-server callbacks.
- Persist provider event identifiers and process idempotently.
- Reject replayed events safely.
- Model payment/subscription states explicitly.
- Treat timeout as an unknown outcome until reconciliation.
- Do not grant entitlements from an unverified client response.
- Never store raw card numbers or CVV.

## Tenant Domains

- Verify ownership before activation.
- Require active TLS.
- Reject unknown, unverified, suspended, or conflicting hosts.
- Audit domain add, verify, activate, deactivate, and transfer operations.
- Prevent domain takeover after tenant deletion or downgrade.

## Branding

- Allow only controlled design tokens and validated assets.
- Never permit arbitrary CSS, JavaScript, executable HTML, or remote scripts.
- Validate file type, MIME, size, dimensions, and storage path.
- Branding cannot weaken accessibility or security-critical interface elements.
