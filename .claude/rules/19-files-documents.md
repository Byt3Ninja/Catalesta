---
paths:
  - "Modules/Documents/**/*.php"
  - "modules/documents/**/*.php"
  - "app/**/*Upload*.php"
  - "app/**/*File*.php"
  - "config/**/*filesystems*.php"
  - "tests/**/*Document*.php"
  - "tests/**/*Upload*.php"
---

# Files and Documents Rules

- Store tenant ownership and authorized visibility for every tenant document.
- Generate storage keys server-side; never trust user-provided paths.
- Prevent path traversal and cross-tenant object access.
- Validate size, extension, declared MIME, detected content type, and allowed
  purpose.
- Rename files on storage; preserve original filename only as metadata.
- Store outside the public web root unless deliberate public access is approved.
- Use signed, short-lived access URLs for private objects.
- Scan uploads where required by risk.
- Reject executable or active content unless explicitly required and safely
  processed.
- Record checksums for formal immutable submissions where required.
- Define retention, legal hold, deletion, and orphan-cleanup behaviour.
- Do not delete historical submission evidence when a mutable profile document
  changes.
- Queue expensive conversion, preview, OCR, or antivirus work.
- Treat generated previews as derived data with the same tenant/privacy scope.
- Add negative tests for forged paths, MIME mismatch, oversized upload, and
  cross-tenant access.
