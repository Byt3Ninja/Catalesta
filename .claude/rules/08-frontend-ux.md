---
paths:
  - "resources/js/**/*.{js,jsx,ts,tsx,vue}"
  - "resources/views/**/*.blade.php"
  - "src/**/*.{js,jsx,ts,tsx,vue}"
  - "frontend/**/*.{js,jsx,ts,tsx,vue}"
  - "tests/**/*.{js,jsx,ts,tsx}"
  - "package.json"
  - "pnpm-lock.yaml"
  - "package-lock.json"
  - "yarn.lock"
---

# Frontend and UX Rules

Every interactive feature must implement where applicable:

- Real action
- Loading state
- Success state
- Empty state
- Validation state
- Recoverable error state
- Disabled state
- Permission-aware rendering
- Accessible name and description
- Keyboard operation
- Focus management
- Responsive behaviour

Additional rules:

- Frontend checks never replace server-side authorization.
- Do not hardcode live counts, statuses, plan entitlements, role permissions, or
  API data.
- Do not leave dead buttons, placeholder links, mock actions, or silent failures.
- Use the established design system and component library.
- Do not introduce a second design system without approval.
- Keep API/server state in the established query or state-management layer.
- Avoid duplicated derived state.
- Handle stale and concurrent updates explicitly.
- Avoid exposing internal identifiers unnecessarily.
- Sanitize untrusted rich content.
- Validate uploads on both client and server; server validation is authoritative.
- Preserve accessibility-critical UI from tenant branding overrides.
- Add interaction tests for critical workflows and state transitions.
