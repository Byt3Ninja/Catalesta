---
paths:
  - "app/**/*.php"
  - "Modules/**/*.php"
  - "modules/**/*.php"
  - "routes/**/*.php"
  - "config/**/*.php"
  - "resources/**/*"
  - "src/**/*"
  - "database/**/*"
  - "tests/**/*"
  - ".github/workflows/**/*"
  - "Dockerfile*"
  - "docker-compose*.yml"
  - "compose*.yml"
---

# Security and Privacy Rules

Never:

- Commit, print, or log secrets
- Read secret files without task necessity
- Disable security controls to make a test pass
- Trust client-provided role, owner, account, organization, entitlement, or
  payment status
- Store raw card numbers or CVV
- Permit arbitrary code, scripts, CSS, or executable templates
- Return internal exceptions to external clients
- Use insecure defaults for production

Review relevant changes for:

- Broken access control and IDOR
- Cross-tenant leakage
- Injection
- XSS and unsafe HTML
- CSRF
- SSRF
- Mass assignment
- Unsafe deserialization
- Path traversal
- Malicious file upload
- Sensitive-data exposure
- Token or credential leakage
- Weak session handling
- Replay attacks
- Race conditions
- Missing rate limits
- Dependency and supply-chain risk

Required controls where applicable:

- Secure cookies and session rotation
- CSRF protection
- Input allowlists and output encoding
- Rate limiting for authentication and sensitive operations
- Bounded request and upload sizes
- MIME/content verification
- Encryption in transit
- Secret-manager or environment-based secret injection
- Least-privilege service credentials
- Audit logging without sensitive payload leakage
- Data minimization and retention rules
- Safe deletion/anonymization processes
