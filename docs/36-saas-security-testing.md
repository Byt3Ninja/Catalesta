# SaaS, Billing, Domain, and Branding Security Tests

## Entitlement Tests

- plan checks cannot be bypassed from frontend
- direct API calls enforce limits
- manual overrides are audited
- grandfathered plans remain stable
- usage counters reconcile correctly

## Billing Tests

- Geidea callback signature validation
- duplicate callback idempotency
- invalid callback rejection
- browser return cannot activate subscription alone
- tenant payment records are isolated
- no raw card data is stored
- refund and cancellation authorization

## Domain Tests

- unknown hosts rejected
- unverified domains cannot route
- cross-tenant domain takeover blocked
- duplicate domain assignment blocked
- DNS re-verification behavior
- certificate failure behavior
- host-header injection blocked

## Branding Tests

- unsafe files rejected
- scripts and arbitrary CSS rejected
- contrast validation
- tenant branding isolation
- fallback branding works
