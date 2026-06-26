# FE UI Slice 0 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up shadcn/ui + Tailwind 4 as the design system, a real `AppShell` (header + sidebar + context selector + theme toggle), and an MSW mock-data harness — proven on the Login and Programs pages so every later slice has a paved road.

**Architecture:** Tailwind 4 CSS-first (`@tailwindcss/vite`, theme in `src/index.css`). shadcn primitives owned in `src/components/ui/`. The 6 existing wrapper primitives (`Button/Field/Banner/Loading/StateBlock/Link`) are rewritten to wrap shadcn **at their current import paths and prop shapes**, so the ~13 unmigrated pages keep working and inherit the new look. The existing `DirectionProvider` (already owns dir + theme) is extended to toggle the `.dark` class and persist theme. Screens render from MSW fixtures in dev; MSW is bypassed with `VITE_USE_MOCKS=false` (slice 9 wires real APIs).

**Tech Stack:** React 19, Vite 8, TypeScript 6, Tailwind 4, shadcn/ui, `class-variance-authority`, `clsx`, `tailwind-merge`, `lucide-react`, `msw`, Vitest + Testing Library, Playwright, Storybook 10.

## Global Constraints

- **Tailwind 4 CSS-first.** No `tailwind.config.ts`, no `postcss.config`. Class-based dark via `@custom-variant dark (&:is(.dark *))`.
- **Preserve import paths + props** of the 6 wrapper primitives and `AppShell` — unmigrated pages must keep rendering.
- **Contrast-gated tokens (verbatim):** primary button bg `--primary: #4f46e5` (indigo-600, white text ≈6.3:1); focus ring `--ring: #6366f1` (indigo-500, non-text only); input border `--input: #71717a` (zinc-500, ≈4.8:1). Indigo-500 `#6366f1` must NOT be a text/background pair (≈4.47:1, fails 4.5).
- **Keep `src/styles/tokens.css`** imported through slice 0 — unmigrated pages still use raw `ds-*` classes. It is removed in a later slice.
- **No backend/auth/tenancy changes.** Purely presentation + a dev-only MSW harness. `X-Organization-Id` / Sanctum session auth untouched.
- **Run all npm commands from `frontend/`.** Commit trailer on every commit: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.
- **Branch:** work on `docs/fe-ui-rebuild` (already checked out) or a child branch; do not commit foundation code to `main`.

## File structure

| Path | Responsibility | Task |
|------|----------------|------|
| `frontend/package.json` | new deps | 1 |
| `frontend/vite.config.ts` | `@tailwindcss/vite` plugin + `@` alias | 1 |
| `frontend/tsconfig.app.json` | `baseUrl` + `@/*` paths | 1 |
| `frontend/components.json` | shadcn config (Tailwind v4 mode) | 1 |
| `frontend/src/lib/utils.ts` | `cn()` | 1 |
| `frontend/src/index.css` | Tailwind import + theme tokens (light/dark/RTL) | 1,2 |
| `frontend/src/tests/contrast.test.ts` | re-point to new tokens | 2 |
| `frontend/src/app/DirectionProvider.tsx` | toggle `.dark`, persist theme | 2 |
| `frontend/src/main.tsx` | initial theme, import index.css, start MSW | 2,5 |
| `frontend/src/components/ui/*` | shadcn primitives (generated) | 3 |
| `frontend/src/components/{Button,Field,Banner,Loading,StateBlock,Link}.tsx` | rewritten wrappers | 4 |
| `frontend/src/components/AppShell.tsx` | real app frame | 6 |
| `frontend/src/components/ThemeToggle.tsx` | dark/light toggle | 6 |
| `frontend/src/components/ContextSelector.tsx` | role/org/program/cohort switcher | 6 |
| `frontend/src/mocks/{handlers,browser}.ts` | MSW fixtures + worker | 5 |
| `frontend/public/mockServiceWorker.js` | MSW worker script (generated) | 5 |
| `frontend/src/pages/LoginPage.tsx` | re-skinned flagship | 7 |
| `frontend/src/pages/ProgramsPage.tsx` | re-skinned flagship | 8 |
| `frontend/tests/e2e/fe-ui-slice0.spec.ts` | flagship e2e against MSW | 8 |

---

### Task 1: Tooling — Tailwind 4 + shadcn config + `@` alias + `cn()`

**Files:**
- Modify: `frontend/package.json` (deps), `frontend/vite.config.ts`, `frontend/tsconfig.app.json`, `frontend/src/index.css`
- Create: `frontend/src/lib/utils.ts`, `frontend/components.json`, `frontend/src/lib/utils.test.ts`

**Interfaces:**
- Produces: `cn(...inputs: ClassValue[]): string` from `@/lib/utils`. The `@/*` → `src/*` alias resolves in app build, Vitest, and Storybook.

- [ ] **Step 1: Install dependencies**

```bash
cd frontend
npm install tailwindcss@^4 @tailwindcss/vite clsx tailwind-merge class-variance-authority lucide-react tw-animate-css
npm install -D msw
```

- [ ] **Step 2: Add Vite plugin + alias** — replace `frontend/vite.config.ts`:

```ts
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
})
```

- [ ] **Step 3: Add the path alias to `frontend/tsconfig.app.json`** — inside `compilerOptions`, add:

```jsonc
    "baseUrl": ".",
    "paths": { "@/*": ["src/*"] },
```

- [ ] **Step 4: Write `frontend/src/lib/utils.ts`**

```ts
import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

/** Merge class names, de-duplicating conflicting Tailwind utilities. */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs))
}
```

- [ ] **Step 5: Write `frontend/components.json`** (shadcn, Tailwind v4 mode)

```json
{
  "$schema": "https://ui.shadcn.com/schema.json",
  "style": "new-york",
  "rsc": false,
  "tsx": true,
  "tailwind": {
    "config": "",
    "css": "src/index.css",
    "baseColor": "zinc",
    "cssVariables": true,
    "prefix": ""
  },
  "iconLibrary": "lucide",
  "aliases": {
    "components": "@/components",
    "utils": "@/lib/utils",
    "ui": "@/components/ui",
    "lib": "@/lib",
    "hooks": "@/hooks"
  }
}
```

- [ ] **Step 6: Make `src/index.css` a valid Tailwind v4 entry** — replace the entire file with just:

```css
@import 'tailwindcss';
```

(The full theme lands in Task 2; this keeps the build green now.)

- [ ] **Step 7: Write the alias smoke test** `frontend/src/lib/utils.test.ts`

```ts
import { expect, test } from 'vitest'
import { cn } from '@/lib/utils'

test('cn merges and de-dupes conflicting tailwind classes', () => {
  expect(cn('px-2', 'px-4')).toBe('px-4')
  expect(cn('text-sm', false && 'hidden', 'font-bold')).toBe('text-sm font-bold')
})
```

- [ ] **Step 8: Verify build, typecheck, and the alias test**

Run: `cd frontend && npm run typecheck && npm run test -- src/lib/utils.test.ts && npm run build`
Expected: typecheck clean; the `cn` test PASSES (proves `@/*` resolves in Vitest); build succeeds.

- [ ] **Step 9: Commit**

```bash
git add frontend/package.json frontend/package-lock.json frontend/vite.config.ts frontend/tsconfig.app.json frontend/components.json frontend/src/lib/utils.ts frontend/src/lib/utils.test.ts frontend/src/index.css
git commit -m "feat(fe): FE-UI-0 — Tailwind 4 + shadcn config, @/ alias, cn() helper

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Theme tokens + dark mode + RTL fonts + contrast gate

**Files:**
- Modify: `frontend/src/index.css`, `frontend/src/app/DirectionProvider.tsx`, `frontend/src/main.tsx`, `frontend/src/tests/contrast.test.ts`

**Interfaces:**
- Consumes: `DirectionProvider` from `@/app/DirectionProvider` (existing `useDirection().setTheme`).
- Produces: `.dark` class on `<html>` when theme is dark; theme persisted to `localStorage['catalesta.theme']`. Theme CSS variables (`--background`, `--foreground`, `--primary`, `--ring`, `--input`, …) + `@theme inline` color tokens.

- [ ] **Step 1: Write the full theme** — replace `frontend/src/index.css`:

```css
@import 'tailwindcss';
@import 'tw-animate-css';

@custom-variant dark (&:is(.dark *));

:root {
  --background: #ffffff;
  --foreground: #09090b;
  --card: #ffffff;
  --card-foreground: #09090b;
  --popover: #ffffff;
  --popover-foreground: #09090b;
  --primary: #4f46e5; /* indigo-600 — white text ≈6.3:1 (passes 4.5) */
  --primary-foreground: #ffffff;
  --secondary: #f4f4f5;
  --secondary-foreground: #18181b;
  --muted: #f4f4f5;
  --muted-foreground: #52525b; /* zinc-600 — ≈7.6:1 on white */
  --accent: #f4f4f5;
  --accent-foreground: #18181b;
  --destructive: #dc2626;
  --destructive-foreground: #ffffff;
  --border: #e4e4e7; /* zinc-200 — decorative dividers only (not contrast-gated) */
  --input: #71717a; /* zinc-500 — ≈4.8:1 on white (passes 3:1) */
  --ring: #6366f1; /* indigo-500 — focus ring only (non-text) */
  --radius: 0.5rem;
}

.dark {
  --background: #09090b;
  --foreground: #fafafa;
  --card: #18181b;
  --card-foreground: #fafafa;
  --popover: #18181b;
  --popover-foreground: #fafafa;
  --primary: #4f46e5;
  --primary-foreground: #ffffff;
  --secondary: #27272a;
  --secondary-foreground: #fafafa;
  --muted: #27272a;
  --muted-foreground: #a1a1aa; /* zinc-400 — ≈6.4:1 on #18181b */
  --accent: #27272a;
  --accent-foreground: #fafafa;
  --destructive: #ef4444;
  --destructive-foreground: #fafafa;
  --border: #27272a;
  --input: #71717a; /* zinc-500 — ≈3.3:1 on #18181b (passes 3:1) */
  --ring: #6366f1;
}

@theme inline {
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-card: var(--card);
  --color-card-foreground: var(--card-foreground);
  --color-popover: var(--popover);
  --color-popover-foreground: var(--popover-foreground);
  --color-primary: var(--primary);
  --color-primary-foreground: var(--primary-foreground);
  --color-secondary: var(--secondary);
  --color-secondary-foreground: var(--secondary-foreground);
  --color-muted: var(--muted);
  --color-muted-foreground: var(--muted-foreground);
  --color-accent: var(--accent);
  --color-accent-foreground: var(--accent-foreground);
  --color-destructive: var(--destructive);
  --color-destructive-foreground: var(--destructive-foreground);
  --color-border: var(--border);
  --color-input: var(--input);
  --color-ring: var(--ring);
  --radius-lg: var(--radius);
  --radius-md: calc(var(--radius) - 2px);
  --radius-sm: calc(var(--radius) - 4px);
  --font-sans: Inter, system-ui, 'Segoe UI', Roboto, sans-serif;
}

body {
  background-color: var(--color-background);
  color: var(--color-foreground);
  font-family: var(--font-sans);
}
[dir='rtl'] body {
  font-family: Tajawal, system-ui, sans-serif;
}
```

- [ ] **Step 2: Extend `DirectionProvider` to toggle `.dark` + persist** — in `frontend/src/app/DirectionProvider.tsx`, replace the first `useEffect` and add a persistence effect:

```tsx
  useEffect(() => {
    const root = document.documentElement
    root.dir = dir
    root.lang = dir === 'rtl' ? 'ar' : 'en'
    root.dataset.theme = theme // kept for back-compat with legacy tokens.css
    root.classList.toggle('dark', theme === 'dark')
  }, [dir, theme])

  useEffect(() => {
    try {
      localStorage.setItem('catalesta.theme', theme)
    } catch {
      /* storage unavailable (private mode) — ignore */
    }
  }, [theme])
```

- [ ] **Step 3: Resolve initial theme in `frontend/src/main.tsx`** — import only `./index.css` plus the legacy tokens, and pass the resolved theme. Replace the file body with:

```tsx
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import './styles/tokens.css' // legacy — removed in a later slice
import { App } from './app/App'
import { DirectionProvider } from './app/DirectionProvider'
import type { Theme } from './app/direction-context'

function initialTheme(): Theme {
  try {
    const saved = localStorage.getItem('catalesta.theme')
    if (saved === 'dark' || saved === 'light') return saved
  } catch {
    /* ignore */
  }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <DirectionProvider initialTheme={initialTheme()}>
      <App />
    </DirectionProvider>
  </StrictMode>,
)
```

- [ ] **Step 4: Re-point the contrast gate** — replace the `light`/`dark` token objects in `frontend/src/tests/contrast.test.ts` with the new palette (keep the rest of the file):

```ts
const light = {
  bg: '#ffffff',
  surface: '#ffffff',
  surfaceAlt: '#f4f4f5',
  ink: '#09090b',
  inkMuted: '#52525b',
  accentBtn: '#4f46e5',
  inputBorder: '#71717a',
  onAccent: '#ffffff',
}

const dark = {
  bg: '#09090b',
  surface: '#18181b',
  surfaceAlt: '#27272a',
  ink: '#fafafa',
  inkMuted: '#a1a1aa',
  accentBtn: '#4f46e5',
  inputBorder: '#71717a',
  onAccent: '#ffffff',
}
```

- [ ] **Step 5: Run the contrast gate + typecheck**

Run: `cd frontend && npm run test -- src/tests/contrast.test.ts && npm run typecheck`
Expected: both contrast specs PASS (every pair clears its floor); typecheck clean.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/index.css frontend/src/app/DirectionProvider.tsx frontend/src/main.tsx frontend/src/tests/contrast.test.ts
git commit -m "feat(fe): FE-UI-0 — zinc/indigo theme, .dark class + persistence, contrast gate re-pointed

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Generate shadcn UI primitives

**Files:**
- Create: `frontend/src/components/ui/{button,input,label,alert,card,sheet,dropdown-menu}.tsx` (generated)

**Interfaces:**
- Produces: `Button`/`buttonVariants` from `@/components/ui/button`; `Input`; `Label`; `Alert`/`AlertTitle`/`AlertDescription`; `Card`/`CardHeader`/`CardTitle`/`CardContent`; `Sheet`/`SheetTrigger`/`SheetContent`; `DropdownMenu*`.

- [ ] **Step 1: Pull the components via the shadcn CLI**

```bash
cd frontend
npx shadcn@latest add button input label alert card sheet dropdown-menu --yes --overwrite
```

If the CLI errors on the v4/React-19 toolchain, fall back to copying each component from https://ui.shadcn.com/docs/components into `src/components/ui/` (they are plain TSX using `cn` from `@/lib/utils`). Do not add a `tailwind.config.ts`.

- [ ] **Step 2: Typecheck the generated components**

Run: `cd frontend && npm run typecheck`
Expected: clean. If a generated file uses an `enum`/namespace (blocked by `erasableSyntaxOnly`), rewrite it to a `const` map — none of these seven normally do.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/components/ui frontend/package.json frontend/package-lock.json
git commit -m "feat(fe): FE-UI-0 — add shadcn ui primitives (button/input/label/alert/card/sheet/dropdown-menu)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Rewrite wrapper primitives in place (same paths + props)

**Files:**
- Modify: `frontend/src/components/{Button,Field,Banner,Loading,StateBlock,Link}.tsx`
- Modify: their `*.test.tsx` (assert roles/text/behavior, not `ds-*` classes)

**Interfaces:**
- Produces (unchanged signatures): `Button({variant?: 'primary'|'secondary', loading?, ...})`; `Field({label, error?, help?, ...input})`; `Banner({variant?: 'info'|'error'|'success', children})`; `Spinner({label?})` + `Skeleton({lines?})`; `StateBlock({variant: 'empty'|'error'|'offline', message, action?})`; `Link({...anchorProps, children})`.

- [ ] **Step 1: Rewrite `Button.tsx`**

```tsx
import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Loader2 } from 'lucide-react'
import { Button as UiButton } from './ui/button'

type Variant = 'primary' | 'secondary'
const VARIANT_MAP = { primary: 'default', secondary: 'secondary' } as const

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  loading?: boolean
  children: ReactNode
}

/** Primary action button. While loading it is disabled + aria-busy so it never double-fires. */
export function Button({ variant = 'primary', loading = false, disabled, type = 'button', children, ...rest }: ButtonProps) {
  return (
    <UiButton type={type} variant={VARIANT_MAP[variant]} disabled={disabled || loading} aria-busy={loading || undefined} {...rest}>
      {loading ? <Loader2 className="size-4 animate-spin" aria-hidden /> : children}
    </UiButton>
  )
}
```

- [ ] **Step 2: Rewrite `Field.tsx`**

```tsx
import { useId, type InputHTMLAttributes } from 'react'
import { Label } from './ui/label'
import { Input } from './ui/input'

interface FieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'id'> {
  label: string
  error?: string
  help?: string
}

/** Labelled input with accessible error association (aria-describedby + aria-invalid). */
export function Field({ label, error, help, ...input }: FieldProps) {
  const id = useId()
  const describedById = error ? `${id}-error` : help ? `${id}-help` : undefined
  return (
    <div className="grid gap-1.5">
      <Label htmlFor={id}>{label}</Label>
      <Input id={id} dir="auto" aria-invalid={error ? true : undefined} aria-describedby={describedById} {...input} />
      {error ? (
        <span id={`${id}-error`} className="text-sm text-destructive">{error}</span>
      ) : help ? (
        <span id={`${id}-help`} className="text-sm text-muted-foreground">{help}</span>
      ) : null}
    </div>
  )
}
```

- [ ] **Step 3: Rewrite `Banner.tsx`**

```tsx
import type { ReactNode } from 'react'
import { Alert, AlertDescription } from './ui/alert'

type BannerVariant = 'info' | 'error' | 'success'
const VARIANT_MAP = { info: 'default', error: 'destructive', success: 'default' } as const

/** Inline alert. Status conveyed by text + role (not colour alone); errors use role="alert". */
export function Banner({ variant = 'info', children }: { variant?: BannerVariant; children: ReactNode }) {
  return (
    <Alert
      variant={VARIANT_MAP[variant]}
      role={variant === 'error' ? 'alert' : 'status'}
      data-variant={variant}
      className={variant === 'success' ? 'border-green-600 text-green-700 dark:text-green-400' : undefined}
    >
      <AlertDescription>{children}</AlertDescription>
    </Alert>
  )
}
```

- [ ] **Step 4: Rewrite `Loading.tsx`**

```tsx
import { Loader2 } from 'lucide-react'

/** Polite-announcing spinner. */
export function Spinner({ label = 'Loading…' }: { label?: string }) {
  return (
    <span role="status" aria-live="polite" className="inline-flex items-center gap-2 text-sm text-muted-foreground">
      <Loader2 className="size-4 animate-spin" aria-hidden />
      {label}
    </span>
  )
}

/** Decorative skeleton (aria-hidden + aria-busy). */
export function Skeleton({ lines = 1 }: { lines?: number }) {
  return (
    <div aria-busy="true" aria-hidden="true" className="grid gap-2">
      {Array.from({ length: lines }).map((_, i) => (
        <span key={i} className="h-4 w-full animate-pulse rounded bg-muted" />
      ))}
    </div>
  )
}
```

- [ ] **Step 5: Rewrite `StateBlock.tsx`**

```tsx
import type { ReactNode } from 'react'
import { Card, CardContent } from './ui/card'

type StateVariant = 'empty' | 'error' | 'offline'

/** Empty / error / offline state — message + single next action (a11y: text, not colour-alone). */
export function StateBlock({ variant, message, action }: { variant: StateVariant; message: string; action?: ReactNode }) {
  return (
    <Card role={variant === 'error' ? 'alert' : 'status'} data-variant={variant} className="border-dashed">
      <CardContent className="flex flex-col items-center gap-3 py-8 text-center">
        <p className="text-sm text-muted-foreground">{message}</p>
        {action}
      </CardContent>
    </Card>
  )
}
```

- [ ] **Step 6: Rewrite `Link.tsx`** (keep the `<a href>` API — RouterLink migration deferred)

```tsx
import type { AnchorHTMLAttributes, ReactNode } from 'react'

/** Styled anchor with a visible focus ring (never removed). */
export function Link({ children, className, ...rest }: AnchorHTMLAttributes<HTMLAnchorElement> & { children: ReactNode }) {
  return (
    <a
      className={
        'rounded text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ' +
        (className ?? '')
      }
      {...rest}
    >
      {children}
    </a>
  )
}
```

- [ ] **Step 7: Fix the primitive tests** — open each of `Button/Field/Banner/Loading/StateBlock/Link.test.tsx`, remove any assertion on `ds-*` class names, and assert behavior instead. Example for `Button.test.tsx` (loading state):

```tsx
import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { Button } from './Button'

test('loading button is disabled and aria-busy', () => {
  render(<Button loading>Save</Button>)
  const btn = screen.getByRole('button')
  expect(btn).toBeDisabled()
  expect(btn).toHaveAttribute('aria-busy', 'true')
})
```

Repeat per component: Field → `getByLabelText` + `aria-invalid`/`aria-describedby`; Banner → `getByRole('alert')` for error; StateBlock → `getByText(message)`; Loading → `getByRole('status')`; Link → `getByRole('link')`.

- [ ] **Step 8: Run the primitive suites + typecheck**

Run: `cd frontend && npm run typecheck && npm run test -- src/components`
Expected: typecheck clean; all primitive + AppShell/FormLayout tests pass (AppShell test may need Task 6 — if it fails on the old `ds-shell` markup, leave it; Task 6 fixes it).

- [ ] **Step 9: Commit**

```bash
git add frontend/src/components/Button.tsx frontend/src/components/Field.tsx frontend/src/components/Banner.tsx frontend/src/components/Loading.tsx frontend/src/components/StateBlock.tsx frontend/src/components/Link.tsx frontend/src/components/*.test.tsx
git commit -m "feat(fe): FE-UI-0 — rewrite wrapper primitives on shadcn (same paths + props)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: MSW mock-data harness

**Files:**
- Create: `frontend/src/mocks/handlers.ts`, `frontend/src/mocks/browser.ts`, `frontend/public/mockServiceWorker.js` (generated)
- Modify: `frontend/src/main.tsx` (start MSW), `frontend/.env.example` (document `VITE_USE_MOCKS`)

**Interfaces:**
- Consumes: types `Program`/`Organization`/`Cohort`/`SessionUser` from `@/schemas/*` (fixtures typed against them → drift fails typecheck).
- Produces: `worker` from `@/mocks/browser`. Serves `GET */api/v1/auth/session`, `*/api/v1/organizations`, `*/api/v1/programs`, `*/api/v1/cohorts`.

- [ ] **Step 1: Generate the worker script**

```bash
cd frontend && npx msw init public/ --save
```

Expected: creates `frontend/public/mockServiceWorker.js`.

- [ ] **Step 2: Write `frontend/src/mocks/handlers.ts`** (fixtures typed against the real schemas)

```ts
import { http, HttpResponse } from 'msw'
import type { SessionUser } from '@/schemas/session'
import type { Organization } from '@/schemas/organizations'
import type { Program } from '@/schemas/programs'
import type { Cohort } from '@/schemas/cohorts'

const NOW = '2026-06-01T00:00:00Z'

const user: SessionUser = {
  id: 'acc_demo',
  email: 'alice@catalesta.test',
  display_name: 'Alice',
  email_verified: true,
  startup_gate_subject_id: null,
  linked_providers: [],
  has_password: true,
}

const org: Organization = {
  id: 'org_demo',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: NOW,
  updated_at: NOW,
}

const programs: Program[] = [
  { id: 'prog_1', name: 'FinTech Accelerator 2026', slug: 'fintech-2026', status: 'published', description: 'Spring cohort intake.', settings: null, created_at: NOW, updated_at: NOW },
  { id: 'prog_2', name: 'HealthTech (draft)', slug: 'healthtech', status: 'draft', description: null, settings: null, created_at: NOW, updated_at: NOW },
]

const cohorts: Cohort[] = [
  { id: 'coh_1', organization_id: 'org_demo', program_id: 'prog_1', name: 'Spring 2026', slug: 'spring-2026', status: 'open', capacity: 20, enrollment_opens_at: NOW, enrollment_closes_at: null, starts_at: null, ends_at: null, timeline: null, submissions_count: 3, created_at: NOW, updated_at: NOW },
]

export const handlers = [
  http.get('*/api/v1/auth/session', () => HttpResponse.json({ user })),
  http.get('*/api/v1/organizations', () => HttpResponse.json({ data: [org] })),
  http.get('*/api/v1/programs', () => HttpResponse.json({ data: programs })),
  http.get('*/api/v1/cohorts', () => HttpResponse.json({ data: cohorts })),
]
```

- [ ] **Step 3: Write `frontend/src/mocks/browser.ts`**

```ts
import { setupWorker } from 'msw/browser'
import { handlers } from './handlers'

export const worker = setupWorker(...handlers)
```

- [ ] **Step 4: Start MSW from `frontend/src/main.tsx`** — wrap the render in an async enabler. Replace the `createRoot(...).render(...)` call with:

```tsx
async function enableMocks(): Promise<void> {
  if (!import.meta.env.DEV) return
  if (import.meta.env.VITE_USE_MOCKS === 'false') return
  const { worker } = await import('./mocks/browser')
  await worker.start({ onUnhandledRequest: 'bypass' })
}

enableMocks().then(() => {
  createRoot(document.getElementById('root')!).render(
    <StrictMode>
      <DirectionProvider initialTheme={initialTheme()}>
        <App />
      </DirectionProvider>
    </StrictMode>,
  )
})
```

- [ ] **Step 5: Document the flag** — append to `frontend/.env.example` (create if absent):

```bash
# Frontend dev mocks: set to "false" to bypass MSW and hit the real backend.
VITE_USE_MOCKS=true
```

- [ ] **Step 6: Verify the worker serves data with NO backend**

Stop any backend, then: `cd frontend && npm run dev` in the background, and:
```bash
# the app boots and the gate resolves to a console surface via mocked session+orgs.
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:3000/
```
Expected: `200`. Then confirm typecheck still clean: `npm run typecheck`.
(Full UI proof comes in Task 8's Playwright run.)

- [ ] **Step 7: Commit**

```bash
git add frontend/src/mocks frontend/public/mockServiceWorker.js frontend/src/main.tsx frontend/.env.example
git commit -m "feat(fe): FE-UI-0 — MSW harness (session/orgs/programs/cohorts fixtures, dev-gated)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: AppShell + ThemeToggle + ContextSelector

**Files:**
- Modify: `frontend/src/components/AppShell.tsx`, `frontend/src/components/AppShell.test.tsx`
- Create: `frontend/src/components/ThemeToggle.tsx`, `frontend/src/components/ContextSelector.tsx`

**Interfaces:**
- Consumes: `useDirection()` from `@/app/direction-context` (provides `theme`/`setTheme`); `getActiveOrganizationId` from `@/api/tenant`; shadcn `Sheet`, `DropdownMenu`, `Button`.
- Produces: `AppShell({ rail?: ReactNode, pageHeader?: ReactNode, children })` — header (brand + `ContextSelector` + `ThemeToggle`) + collapsible sidebar (`rail` content) + page container. Preserves the existing `rail`/`children` call sites.

- [ ] **Step 1: Write `ThemeToggle.tsx`**

```tsx
import { Moon, Sun } from 'lucide-react'
import { useDirection } from '../app/direction-context'
import { Button } from './ui/button'

/** Toggles light/dark; persisted by DirectionProvider. */
export function ThemeToggle() {
  const { theme, setTheme } = useDirection()
  return (
    <Button
      variant="ghost"
      size="icon"
      aria-label={theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'}
      onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
    >
      {theme === 'dark' ? <Sun className="size-4" /> : <Moon className="size-4" />}
    </Button>
  )
}
```

(If `useDirection` is not exported from `@/app/direction-context`, import it from `@/app/DirectionProvider` — check the existing export site and use whichever is correct.)

- [ ] **Step 2: Write `ContextSelector.tsx`** (org real, role/program/cohort stubbed this slice)

```tsx
import { getActiveOrganizationId } from '../api/tenant'

/**
 * Role / org / program / cohort context (docs/ux/navigation.md). Slice 0 shows the
 * active org; role/program/cohort are presentational stubs wired in later slices.
 */
export function ContextSelector() {
  const orgId = getActiveOrganizationId()
  return (
    <div className="flex items-center gap-2 text-sm text-muted-foreground" aria-label="Active context">
      <span className="font-medium text-foreground">{orgId ? 'Acme Incubator' : 'No organization'}</span>
    </div>
  )
}
```

- [ ] **Step 3: Rewrite `AppShell.tsx`**

```tsx
import { useState, type ReactNode } from 'react'
import { Menu } from 'lucide-react'
import { Sheet, SheetContent, SheetTrigger } from './ui/sheet'
import { Button } from './ui/button'
import { ThemeToggle } from './ThemeToggle'
import { ContextSelector } from './ContextSelector'

/**
 * Application frame: sticky header (brand + context + theme), a sidebar that holds
 * `rail` content (collapses to a Sheet on mobile), and the page container with an
 * optional `pageHeader` slot. Uses logical spacing so it mirrors under RTL.
 */
export function AppShell({ rail, pageHeader, children }: { rail?: ReactNode; pageHeader?: ReactNode; children: ReactNode }) {
  const [open, setOpen] = useState(false)
  return (
    <div className="min-h-dvh bg-background text-foreground">
      <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur">
        {rail ? (
          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon" className="md:hidden" aria-label="Open navigation">
                <Menu className="size-4" />
              </Button>
            </SheetTrigger>
            <SheetContent side="start" className="w-64 p-4">{rail}</SheetContent>
          </Sheet>
        ) : null}
        <span className="font-semibold">Catalesta</span>
        <div className="ms-2 flex-1"><ContextSelector /></div>
        <ThemeToggle />
      </header>
      <div className="mx-auto flex w-full max-w-screen-xl gap-6 px-4 py-6">
        {rail ? <aside className="hidden w-56 shrink-0 md:block" aria-label="Sections">{rail}</aside> : null}
        <main className="min-w-0 flex-1">
          {pageHeader ? <div className="mb-6">{pageHeader}</div> : null}
          {children}
        </main>
      </div>
    </div>
  )
}
```

- [ ] **Step 4: Fix `AppShell.test.tsx`** — render inside `DirectionProvider` (ThemeToggle needs it) and assert structure by role/text, not `ds-shell`:

```tsx
import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { AppShell } from './AppShell'
import { DirectionProvider } from '../app/DirectionProvider'

test('renders brand, rail content, and children', () => {
  render(
    <DirectionProvider>
      <AppShell rail={<nav aria-label="Sections">Programs</nav>}>
        <p>Body</p>
      </AppShell>
    </DirectionProvider>,
  )
  expect(screen.getByText('Catalesta')).toBeInTheDocument()
  expect(screen.getByText('Body')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /switch to (light|dark) theme/i })).toBeInTheDocument()
})
```

- [ ] **Step 5: Run shell + primitive suites + typecheck**

Run: `cd frontend && npm run typecheck && npm run test -- src/components`
Expected: typecheck clean; AppShell + all primitive tests pass.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/components/AppShell.tsx frontend/src/components/AppShell.test.tsx frontend/src/components/ThemeToggle.tsx frontend/src/components/ContextSelector.tsx
git commit -m "feat(fe): FE-UI-0 — real AppShell (header + sidebar + context selector + theme toggle)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Re-skin LoginPage (flagship)

**Files:**
- Modify: `frontend/src/pages/LoginPage.tsx`, `frontend/src/pages/LoginPage.test.tsx`

**Interfaces:**
- Consumes: shadcn `Card`/`CardHeader`/`CardTitle`/`CardContent`; existing `Button`/`Field`. Login form behavior/mutation is unchanged — presentation only.

- [ ] **Step 1: Wrap the existing form in a centered branded `Card`** — keep all existing state, handlers, and the two `Button`s (`Sign in`, `Sign in with Startup Gate`); change only the surrounding markup. Replace the outer `<section aria-labelledby="login-heading">…</section>` wrapper with:

```tsx
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card'
// …existing imports stay…

// inside the component's return:
<main className="grid min-h-dvh place-items-center bg-background px-4">
  <Card className="w-full max-w-sm">
    <CardHeader>
      <CardTitle id="login-heading">Sign in to Catalesta</CardTitle>
    </CardHeader>
    <CardContent>
      {/* the existing <form>…</form> and the Startup Gate button, unchanged */}
    </CardContent>
  </Card>
</main>
```

Keep the heading text as the level-1 landmark: render `<h1 id="login-heading">` via `CardTitle asChild` OR keep an `<h1 className="sr-only">Sign in</h1>` so the existing e2e (`getByRole('heading', { name: 'Sign in', level: 1 })`) and any test asserting the heading still pass. Confirm the visible title and the role-1 heading text are consistent.

- [ ] **Step 2: Update `LoginPage.test.tsx`** — keep behavioral assertions (submit disabled until email+password, error rendering); drop any `ds-*` class assertions. Verify the heading query still resolves.

- [ ] **Step 3: Run the Login suite + typecheck + lint**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test -- src/pages/LoginPage.test.tsx`
Expected: all clean/green.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/pages/LoginPage.tsx frontend/src/pages/LoginPage.test.tsx
git commit -m "feat(fe): FE-UI-0 — re-skin LoginPage as branded auth card

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 8: Re-skin ProgramsPage (flagship) + e2e against MSW

**Files:**
- Modify: `frontend/src/pages/ProgramsPage.tsx`, `frontend/src/pages/ProgramsPage.test.tsx`
- Create: `frontend/tests/e2e/fe-ui-slice0.spec.ts`

**Interfaces:**
- Consumes: `AppShell` (now with `pageHeader`), `Button`/`Field`/`Banner`/`StateBlock`/`Spinner`/`Link`, shadcn `Card`. Query/mutation behavior unchanged.

- [ ] **Step 1: Re-skin `ProgramsPage.tsx`** — keep all state/query/mutation/`renderCreateError` logic; restyle markup. Use `AppShell`'s `pageHeader` for the title + a "New program" affordance, drop the raw `ds-badge` (use a shadcn-styled status pill), and render the list as cards. Replace the returned JSX (keep the function bodies and `STATUS_LABEL`):

```tsx
return (
  <AppShell
    rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><a href="/programs" className="font-medium">Programs</a></nav>}
    pageHeader={
      <div className="flex items-center justify-between">
        <h1 id="programs-heading" className="text-2xl font-semibold"><bdi>{organization.name}</bdi> — Programs</h1>
      </div>
    }
  >
    <section aria-labelledby="programs-heading" className="grid gap-6">
      <Banner variant="info">
        Publishing a program records an immutable version. Editing a published program changes the live program (and is audited).
      </Banner>

      {renderCreateError(createMutation.error)}

      <form onSubmit={onSubmit} noValidate className="grid gap-4 rounded-lg border border-border p-4">
        <FormLayout>
          <Field label="Program name" name="program-name" required value={name} onChange={(e) => setName(e.target.value)} />
          <Field label="Description" name="program-description" help="Optional." value={description} onChange={(e) => setDescription(e.target.value)} />
        </FormLayout>
        <div>
          <Button type="submit" loading={createMutation.isPending} disabled={name.trim().length === 0}>Create program</Button>
        </div>
      </form>

      <div className="grid gap-3">
        <h2 id="programs-list-heading" className="text-lg font-medium">Your programs</h2>
        {programsQuery.isLoading ? (
          <Spinner label="Loading programs…" />
        ) : programsQuery.isError ? (
          <StateBlock variant="error" message="We could not load your programs." action={<Button onClick={() => programsQuery.refetch()}>Try again</Button>} />
        ) : programs.length === 0 ? (
          <StateBlock variant="empty" message="No programs yet. Create your first program above." />
        ) : (
          <ul aria-labelledby="programs-list-heading" className="grid gap-2">
            {programs.map((program) => (
              <li key={program.id} className="flex items-center justify-between rounded-md border border-border px-4 py-3">
                <Link href={`/programs/${program.id}`}><bdi>{program.name}</bdi></Link>
                <span data-status={program.status} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
                  {STATUS_LABEL[program.status]}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  </AppShell>
)
```

- [ ] **Step 2: Update `ProgramsPage.test.tsx`** — keep behavioral assertions (loading→list, empty state, create flow, error state); drop `ds-badge`/class assertions; status pill asserted via `getByText(STATUS_LABEL...)` or `data-status`.

- [ ] **Step 3: Run the Programs suite + full vitest + lint + typecheck**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test`
Expected: whole suite green.

- [ ] **Step 4: Write the flagship e2e** `frontend/tests/e2e/fe-ui-slice0.spec.ts` (runs against `npm run dev` with MSW — no backend)

```ts
import { test, expect } from '@playwright/test'

// Foundation proof: with MSW on, the app boots, the gate resolves via mocked
// session+orgs, and Programs renders the mocked list inside the new shell.
test('Programs renders from MSW with no backend', async ({ page }) => {
  await page.goto('/programs')
  await expect(page.getByRole('heading', { name: /Programs/, level: 1 })).toBeVisible({ timeout: 15000 })
  await expect(page.getByText('FinTech Accelerator 2026')).toBeVisible()
  await expect(page.getByRole('button', { name: /switch to (light|dark) theme/i })).toBeVisible()
})
```

- [ ] **Step 5: Run the flagship e2e**

Start the dev server (MSW on) in the background, then:
Run: `cd frontend && npx playwright test fe-ui-slice0 --reporter=list`
Expected: PASS — Programs heading + the mocked "FinTech Accelerator 2026" row + the theme toggle are visible, with **no Docker backend running**.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/pages/ProgramsPage.tsx frontend/src/pages/ProgramsPage.test.tsx frontend/tests/e2e/fe-ui-slice0.spec.ts
git commit -m "feat(fe): FE-UI-0 — re-skin ProgramsPage in the new shell + MSW-backed e2e

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 9: Stories, final gates, and cleanup

**Files:**
- Modify: `frontend/.storybook/preview.tsx` (load `index.css`), touched `*.stories.tsx` as needed

**Interfaces:** none new — this task proves the whole slice is green.

- [ ] **Step 1: Load the theme in Storybook** — ensure `frontend/.storybook/preview.tsx` imports the app stylesheet at the top:

```tsx
import '../src/index.css'
import '../src/styles/tokens.css'
```

- [ ] **Step 2: Fix any story that referenced removed `ds-*` markup** — open the touched primitives' `*.stories.tsx`; they should render the components as-is. Adjust only if a story hard-coded a `ds-*` class.

- [ ] **Step 3: Grep for stray `ds-*` in migrated files** — confirm the 6 primitives + 2 flagship pages carry none:

Run: `cd frontend && grep -rn "ds-" src/components/Button.tsx src/components/Field.tsx src/components/Banner.tsx src/components/Loading.tsx src/components/StateBlock.tsx src/components/Link.tsx src/components/AppShell.tsx src/pages/LoginPage.tsx src/pages/ProgramsPage.tsx`
Expected: no matches. (Other pages still legitimately use `ds-*` + `tokens.css` — left for later slices.)

- [ ] **Step 4: Run ALL gates**

Run: `cd frontend && npm run typecheck && npm run lint && npm run test && npm run build-storybook`
Expected: typecheck clean; lint clean; full vitest green (incl. contrast + a11y suites); Storybook builds.

- [ ] **Step 5: Run the flagship Playwright (MSW) once more for the record**

Run (dev server up, MSW on): `cd frontend && npx playwright test fe-ui-slice0 --reporter=list`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add frontend/.storybook/preview.tsx frontend/src/components/*.stories.tsx
git commit -m "chore(fe): FE-UI-0 — Storybook loads new theme; slice 0 gates green

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage (vs `2026-06-26-fe-ui-slice0-foundation-design.md`):**
- A Tooling/layout → Task 1. ✓  B Theme/dark/RTL/contrast → Task 2. ✓
- C Primitives in place → Tasks 3–4. ✓  D AppShell + context selector → Task 6. ✓
- E MSW harness → Task 5. ✓  F Flagship pages → Tasks 7–8. ✓
- G Tests/stories/gates → spread across each task + Task 9. ✓
- AC1 alias/cn → T1; AC2 theme + tokens.css retained → T2; AC3 dark/RTL → T2/T6;
  AC4 primitives+shell at original paths → T3/4/6; AC5 MSW renders w/o backend →
  T5/T8; AC6 Login+Programs re-skinned → T7/T8; AC7 gates → T9. ✓
- Deviations from spec, intentional: (1) extend `DirectionProvider` instead of a
  new `ThemeProvider` (provider already owns theme); (2) `Link` keeps the `<a href>`
  API (RouterLink deferred — avoids router-context churn); (3) `tokens.css` kept.

**Placeholder scan:** no TBD/TODO; every code step is complete. The only
conditional is Task 3's CLI-or-manual fallback (explicit, not a placeholder) and
Task 6 Step 1's note to confirm the `useDirection` export site.

**Type consistency:** wrapper prop shapes match current usage (`Button.variant`
`'primary'|'secondary'`, `Field.{label,error,help}`, `Banner.variant`,
`StateBlock.variant`, `Spinner.label`); MSW fixtures typed as `SessionUser`/
`Organization`/`Program`/`Cohort`; `AppShell({rail,pageHeader,children})` consumed
by ProgramsPage in Task 8; `useDirection().{theme,setTheme}` used by ThemeToggle.
