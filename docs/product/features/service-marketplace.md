# Service Requests & Marketplace

> Owner: Product · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

Build-spec `47`. Defines a previously-undefined scope item.

> Status: **Proposed — pending owner ratification.** Commercial mechanics (whether
> the platform takes a fee, settlement) are explicitly deferred (see Out of MVP).

## Concept

A **marketplace** of program-related services (legal, accounting, design,
cloud credits, etc.) that participants can request and providers can fulfil,
scoped to a tenant's program.

## Provider model

- A **provider** is a directory entry within a tenant (a partner/sponsor or
  vetted vendor), with a profile and a catalog of **service listings**.
- A **service listing** has: title, description, category, eligibility
  (which programs/cohorts/tracks), and an indicative price or "request a quote".

## Request → fulfilment flow

1. Participant submits a **service request** against a listing (or open-ended).
2. Staff/provider triages: accept, decline, or request info.
3. Fulfilment tracked through states: `requested → accepted → in_progress →
   delivered → closed`.
4. Feedback/rating captured on close (reuses `surveys-hackathons-knowledge.md`).

## Out of MVP (deferred)

- **Payments/settlement** — no money moves through the platform at MVP; pricing is
  indicative and transactions settle off-platform.
- Provider self-onboarding and public provider discovery — staff curate providers
  at MVP.
