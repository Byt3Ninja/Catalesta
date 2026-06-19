# Tenant Custom Domains and Basic Branding

> Owner: SaaS · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

## Objective

Allow each SaaS customer to connect:

- a platform-provided subdomain
- one or more verified custom domains

Examples:

```text
acme.platform.example
programs.acme.org
accelerator.acme.org
```

## Domain Ownership

Entities:

- tenant_domains
- domain_verification_challenges
- domain_certificates
- domain_routing_status
- domain_audit_logs

## Domain Types

- platform_subdomain
- custom_subdomain
- custom_apex_domain, only when supported operationally

## Domain Lifecycle

```text
Requested
→ Reserved
→ DNS Instructions Issued
→ Verification Pending
→ Verified
→ Certificate Provisioning
→ Active
→ Failed
→ Suspended
→ Removed
```

## Verification

Support DNS-based verification using:

- CNAME for routing
- TXT ownership challenge where required

The platform must verify ownership before activating routing.

## TLS

- automatic certificate provisioning
- automatic renewal
- certificate status monitoring
- no HTTP-only tenant domains
- failed renewal alerting
- safe fallback to platform domain

## Routing

Resolve tenant context from the validated host header.

Security rules:

- reject unknown hosts
- prevent host-header injection
- map only active verified domains
- never trust tenant ID from frontend input
- preserve canonical URLs
- support safe redirects

## Basic Branding

Each tenant may configure:

- organization logo
- favicon
- primary brand color
- secondary or accent color
- portal display name
- email sender display name
- login or landing illustration
- certificate logo
- footer text
- support email
- public program-page branding

## Branding Safety

- validate image type and size
- sanitize text
- enforce accessible color contrast
- define safe fallback tokens
- prevent arbitrary CSS or JavaScript
- preview before publishing
- version branding changes
- maintain audit history

## Plan Entitlements

Possible plan controls:

- platform subdomain
- custom domain count
- remove platform branding
- custom email branding
- custom certificate branding
- advanced theme controls

## Email deliverability for branded senders

> Status: **Proposed — pending owner ratification.**

When a tenant sets a custom email sender (per `white-label-levels.md` Pro/White-label):

- The tenant's sending domain must pass **SPF** and **DKIM** (and ideally DMARC)
  before branded sending is enabled — verified like custom-domain ownership.
- Until verified, mail is sent from the platform's default authenticated domain
  with the tenant name as display only (no spoofed envelope).
- Bounce/complaint handling and sender-reputation monitoring apply per sending
  domain; abusive patterns disable branded sending.
