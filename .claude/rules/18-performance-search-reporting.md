---
paths:
  - "Modules/Search/**/*.php"
  - "Modules/Reporting/**/*.php"
  - "modules/search/**/*.php"
  - "modules/reporting/**/*.php"
  - "app/**/*.php"
  - "database/**/*.php"
  - "tests/**/*Search*.php"
  - "tests/**/*Report*.php"
---

# Performance, Search, Reporting, and Export Rules

- Establish query patterns before adding indexes.
- Prevent N+1 queries and unbounded result sets.
- Paginate interactive listings.
- Stream or queue large exports.
- Apply tenant and authorization scope before aggregation, search, reporting, or
  export.
- Do not index or expose fields beyond their approved privacy purpose.
- Search documents must carry tenant and visibility boundaries.
- Rebuild and deletion processes must preserve tenant isolation.
- Reports must define data freshness, timezone, filters, and calculation rules.
- Snapshot or version report inputs when formal historical reproducibility is
  required.
- Avoid loading entire datasets into application memory.
- Use caching only with correct tenant, permission, locale, and version keys.
- Define invalidation; do not cache sensitive data globally.
- Add representative performance tests or query-plan evidence for high-volume
  changes.
- Exports require auditability and secure, expiring access.
