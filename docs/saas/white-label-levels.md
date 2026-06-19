# White-Label / Branding Levels

> Owner: SaaS · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

> Status: **Proposed — pending owner ratification.** Defines the `branding_level`
> entitlement dimension (referenced by `plans-entitlements-usage.md` and the
> commercial model) that was previously named but undefined.

All levels operate within the hard rule: **controlled tokens and assets only — no
arbitrary CSS or scripts** (CLAUDE.md SaaS rule 11). Levels widen *which* tokens
are configurable, never *how* they are applied.

| Level | Unlocks | "Powered by Catalesta" |
|---|---|---|
| **Basic** (default) | logo, primary color, organization name on subdomain | shown |
| **Standard** | + full color palette, accent tokens, favicon, email sender display name | shown |
| **Pro** | + custom verified domain (see `domains-branding.md`), custom email-sending domain (DKIM/SPF), login-page branding | reduced |
| **White-label** | + remove "Powered by Catalesta", custom transactional email templates within the token system, branded PDF/formal-document headers | hidden |

## Rules

- A level is an **entitlement** resolved via `EntitlementService` — modules never
  check plan names (CLAUDE.md SaaS rule 2).
- Custom domain and custom email-sending domain require ownership verification and
  active TLS / DKIM-SPF (see `domains-branding.md`).
- No level permits arbitrary CSS, JavaScript, or HTML injection; all branding is
  token/asset substitution rendered by the platform.
