---
paths:
  - "Modules/Identity/**/*.php"
  - "Modules/Profiles/**/*.php"
  - "Modules/Integrations/**/*StartupGate*"
  - "modules/identity/**/*.php"
  - "modules/profiles/**/*.php"
  - "app/**/*Identity*.php"
  - "app/**/*Oidc*.php"
  - "app/**/*OAuth*.php"
  - "config/**/*auth*.php"
  - "config/**/*oidc*.php"
  - "routes/**/*auth*.php"
  - "tests/**/*Identity*.php"
  - "tests/**/*Profile*.php"
  - "tests/**/*Oidc*.php"
---

# Identity, Profiles, Consent, and Startup Gate Rules

## Local Identity

- `Account` ULID is the local canonical user identifier.
- Email is a credential/contact attribute only.
- Do not use email to link accounts, tenants, profiles, or external identities.
- Normalize and verify credentials according to repository security policy.
- Prevent account enumeration in public authentication responses.

## External Identity Links

- Uniqueness is based on issuer plus subject.
- Store the immutable external `sub`; never reuse or reassign it.
- Linking requires an authenticated local account and explicit confirmation.
- Prevent confused-deputy linking and session fixation.
- Audit link, relink attempt, unlink, and revocation.
- Do not silently merge local accounts.
- Local login must remain usable when Startup Gate is unavailable.

## OIDC Validation

Validate:

- HTTPS and approved issuer
- Discovery/JWKS source
- Signature and algorithm
- Issuer
- Audience and authorized party where applicable
- State
- Nonce
- Redirect URI
- Authorization-code expiry and one-time use
- Token expiry and clock skew
- PKCE where applicable

Never log authorization codes, access tokens, refresh tokens, ID tokens, client
secrets, or sensitive claims.

## Profile Import

- Require explicit field-level consent before import.
- Show selected fields and source before persistence.
- Imported values become local editable copies.
- Store provenance and import timestamp where required.
- Never auto-overwrite a locally modified field.
- Treat re-import as an explicit user-controlled comparison and merge.
- Do not make normal local profile reads dependent on Startup Gate.

## Consent

Consent records must identify:

- Account subject
- Recipient or processing context
- Purpose
- Fields or scopes
- Consent text/version
- Granted timestamp
- Expiry when applicable
- Revocation timestamp

Revocation stops future access or synchronization while preserving immutable
historical records that must legitimately remain.
