# Data Residency & Retention

> Owner: Product · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

> Status: **Proposed — pending owner ratification.** Resolves two open questions
> from the product brief (Internal FAQ Q4, Open Question #3). The values below are
> defaults to ratify, not yet committed.

Companion to the rights/lifecycle definitions in
`../architecture/data-privacy-rights.md` (which lists *what* categories exist;
this doc sets *where* data lives and *how long* it is kept).

## Data residency (proposed)

- **Baseline regime:** Egypt PDPL, with GDPR-grade data-subject rights.
- **Primary residency:** a MENA / EU-adjacent region to ratify (e.g. a GCC or EU
  region depending on provider availability and customer commitments).
- **Single-region at MVP**; per-tenant residency pinning is deferred.
- Sub-processors (storage, email, Geidea) must be compatible with the chosen
  regime; list maintained with the DPA.

## Retention values (proposed, per category)

| Category | Proposed retention | Basis |
|---|---|---|
| Applications (accepted) | program lifetime + 3 y | program record |
| Rejected applications | 1 y after cycle close | dispute window |
| Evaluations / scores | program lifetime + 3 y | auditability |
| Interview notes | 1 y after decision | minimization |
| Documents | program lifetime + 1 y | participant records |
| Mentor notes | program lifetime | delivery record |
| Training records | program lifetime + 3 y | certification |
| Certificates | indefinite (or until erasure request) | proof of completion |
| Audit logs | 6 y | compliance |
| Analytics (aggregated) | indefinite | de-identified |
| Consent records | life of identity + 3 y | legal basis evidence |

- **Erasure requests** override retention except where a legal hold applies
  (retention exceptions per `../architecture/data-privacy-rights.md`).
- Retention enforcement is server-side; reaching a limit never deletes data
  silently (CLAUDE rule 4 / SaaS rule).
