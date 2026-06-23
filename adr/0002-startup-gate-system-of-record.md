# ADR 0002: Startup Gate as Identity and Profile System of Record

## Status

**Superseded by [ADR-0004](0004-catalesta-identity-system-of-record.md)** — decision 2026-06-21, ADR file landed 2026-06-23. The identity-ownership inversion made Catalesta the system of record for accounts, identity, profiles, memberships, and consent; Startup Gate is now an optional linked SSO + consented field-level import source only. See ADR-0004 for the current authoritative decision and rationale.

## Decision (historical — no longer in effect)

Startup Gate owns global identity, profiles, startup memberships, consent, and shared achievements. The program platform stores projections and immutable snapshots only where required.
