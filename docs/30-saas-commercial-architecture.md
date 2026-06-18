# SaaS Commercial Architecture

## Objective

Enable the platform to operate as a multi-tenant SaaS product with versioned subscription plans, feature entitlements, usage limits, add-ons, billing, invoicing, trials, upgrades, downgrades, suspension, and enterprise contracts.

## Recommended Commercial Model

```text
Base subscription by active-program allowance
+ internal staff seats
+ annual application volume
+ storage allowance
+ premium integrations
+ communication usage
+ optional add-ons
```

Do not use total registered users as the primary billing metric because applicants, mentors, external evaluators, and alumni have different commercial value.

## Core Modules

- Plans
- Plan Versions
- Features
- Limits
- Entitlements
- Subscriptions
- Usage Metering
- Add-ons
- Coupons
- Billing
- Payments
- Invoices
- Tax
- Dunning
- SaaS Administration

## Plan Dimensions

- active programs
- cohorts per year
- internal staff seats
- annual applications
- active participants
- external collaborator pool
- storage
- automation executions
- custom reports
- API requests
- integrations
- custom domains
- white-label level
- audit retention
- support SLA

## Rule

No domain module may check plan names directly.

Use:

- EntitlementService
- UsageMeter
- SubscriptionGuard
- BillingPolicy
