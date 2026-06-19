# Subscription and Billing Lifecycle

> Owner: SaaS · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

## Subscription Statuses

- trial
- active
- past_due
- grace_period
- restricted
- suspended
- cancelled
- expired

## Trial

Support:

- start and end dates
- trial plan
- conversion reminders
- usage limits
- trial extension with audit

## Past Due and Grace Period

- retry collection
- notify billing contacts
- retain service temporarily
- prevent selected new resource creation after grace threshold
- preserve exports and data access

## Cancellation

- cancellation at period end by default
- export window
- retention policy
- scheduled anonymization or deletion
- cancellation reason

## Enterprise Contract Billing

Support:

- manual invoices
- purchase orders
- contract references
- payment terms
- bank transfer
- manual payment reconciliation
- negotiated entitlements

## Subscription status: "restricted" (definition)

> Status: **Proposed — pending owner ratification.** Defines the previously-undefined
> `restricted` status relative to its neighbors.

Status order: `trialing → active → past_due → grace_period → restricted →
suspended → cancelled → expired`.

| Status | Tenant access | Trigger |
|---|---|---|
| `grace_period` | full access | payment failed, within grace window |
| **`restricted`** | **read-only + export; in-progress critical workflows allowed to finish; no new programs/cohorts/writes** | grace window elapsed without payment |
| `suspended` | no access except billing/export | restricted period elapsed |

`restricted` exists so a lapsed tenant **never loses data and can always export**
(CLAUDE.md SaaS rule 4) while write access is paused to prompt payment. Restoring
payment returns the tenant directly to `active`.
