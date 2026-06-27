# FE UI Slice 2b — Forms Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the form authoring subsystem — a three-pane form builder with conditional-visibility logic, a reusable `FormRenderer` + preview, form binding to a cohort, and generic version-history + version-compare — UI-first on shadcn/Tailwind + MSW.

**Architecture:** Reuse the existing `ApplyField` atom and `apply.ts`'s `formFieldSchema` (extended, not duplicated). A new `forms.ts` schema/api/MSW layer models forms, immutable published versions, and drafts. A pure `evaluateVisibility` engine drives both the live preview and the renderer. The builder holds a draft `FormVersion` in local state and autosaves via `saveFormDraft`. **No new shadcn primitives** — build with existing `Button`/`Field`/`Input`/`Card`/`DropdownMenu` + native controls (the pattern `ApplyField` already uses).

**Tech Stack:** React 19, Vite, TypeScript, shadcn/Tailwind, @tanstack/react-query, Zod, MSW, react-router-dom, Vitest + Testing Library, Playwright, Storybook.

## Global Constraints

- **Design system:** shadcn/Tailwind only; theme tokens (`bg-card`, `border-border`, `bg-secondary`, `text-secondary-foreground`, `text-muted-foreground`, `bg-primary`, `text-primary-foreground`, `bg-accent`) + `cn()` from `../lib/utils`. Reuse the status-badge pattern `<span data-status={...} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">`. **No new shadcn `ui/` primitives** — use existing ones + native controls styled with tokens (as `ApplyField` does).
- **Reuse, don't duplicate:** `forms.ts` extends `apply.ts`'s `formFieldSchema`; `FormRenderer` composes the existing `ApplyField` (which reads `type/label/options/required/help`). Do NOT fork `ApplyField`.
- **snake_case schemas** to match the existing `apply.ts`/`cohorts.ts`/Laravel convention: `form_id`, `created_at`, `published_at`, `latest_version`, `published_version_ids`, `current_draft_version_id`, `field_id`, `min_length`, etc.
- **UI-first:** pages call real `src/api/` clients; MSW intercepts `fetch`. Unit tests mock `fetch` directly via `vi.spyOn(globalThis,'fetch')` + `jsonResponse` from `../tests/test-utils`. MSW state is module-mutable and persists within a session.
- **AppShell no-idle-fetch invariant:** AppShell-rendered components must not fetch at mount beyond the roles query. Builder/preview/versions pages fetch on route entry (their own `useQuery`) — fine. Every test rendering a page containing `AppShell` includes `vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))`.
- **Test render helper:** `<DirectionProvider><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>…</QueryClientProvider></DirectionProvider>`. **No MemoryRouter** — pages take props, not `useParams`. Seed XSRF cookie in `beforeEach` when a mutation runs. `afterEach(() => vi.restoreAllMocks())`.
- **Versioning rule:** Publish snapshots the current draft into an immutable numbered version (`status:'published'`, `published_at` set). Published versions are read-only; "Edit" forks a new draft. Binding offers published versions only.
- **`aria-label` caution:** never add an `aria-label` where visible text should be the accessible name.
- **Gate sweep includes `npm run lint`** (the CI step that failed 2a) alongside vitest/build/build-storybook/e2e.
- **Commits** end with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Run all commands from `cd frontend`; verify `git branch --show-current` is the 2b feature branch before each commit; `git add` only the task's files (never `-A`).

## File Structure

| File | Responsibility | Task |
|------|----------------|------|
| `src/schemas/forms.ts` | Zod: field validation, visibility rule, `FormField` (extends apply base), `FormVersion`, `Form` + error classes | 1 |
| `src/api/forms.ts` | `listForms`/`getForm`/`getFormVersion`/`createForm`/`saveFormDraft`/`publishForm`/`forkFormDraft`/`listFormVersions` | 1 |
| `src/api/forms.test.ts` | api unit tests | 1 |
| `src/mocks/handlers.ts` | + forms/versions/binding handlers on a module-mutable `forms`/`formVersions` store | 1, 9 |
| `src/mocks/handlers.forms.test.ts` | handler-registration guard | 1 |
| `src/lib/visibility.ts` | pure `evaluateVisibility(field, answers, fields)` engine | 2 |
| `src/lib/visibility.test.ts` | engine unit tests | 2 |
| `src/components/FormRenderer.tsx` | composes `ApplyField`, live visibility-aware | 2 |
| `src/components/FormRenderer.test.tsx` | renderer tests | 2 |
| `src/pages/FormPreviewPage.tsx` | read-only applicant render + LTR/RTL toggle | 3 |
| `src/pages/FormPreviewPage.test.tsx` / `.stories.tsx` | tests + story | 3 |
| `src/pages/FormBuilderPage.tsx` | three-pane builder shell + palette + canvas | 4, 5, 6, 7 |
| `src/components/FieldInspector.tsx` | selected-field config (label/help/required/options/validation) | 5 |
| `src/components/VisibilityEditor.tsx` | conditional-logic editor (cycle-prevented) | 6 |
| `src/pages/FormBuilderPage.test.tsx` / `.stories.tsx` | builder tests + story | 4, 5, 6, 7 |
| `src/components/VersionHistoryList.tsx` + `VersionCompare.tsx` | generic, reused by 2c | 8 |
| `src/pages/FormVersionsPage.tsx` (+ test) | history + compare for a form | 8 |
| `src/components/FormBindingPicker.tsx` (+ test) | bind a published version to a cohort | 9 |
| `src/schemas/cohorts.ts` / `src/api/cohorts.ts` | + `bound_form_version_id` + `bindCohortForm` | 9 |
| `src/app/App.tsx` | + `/forms/:formId/edit\|preview\|versions` routes | 10 |
| `src/pages/CohortDetailPage.tsx` | wire the real bound-form row + binding entry | 9 |
| `src/tests/a11y.test.tsx` | + cases for renderer/builder/preview/versions | 10 |
| `tests/e2e/fe-ui-slice2b.spec.ts` + `playwright.config.ts` | build → publish → bind e2e | 10 |

---

### Task 1: Forms data layer — schema + api + MSW

**Files:** Create `src/schemas/forms.ts`, `src/api/forms.ts`, `src/api/forms.test.ts`, `src/mocks/handlers.forms.test.ts`; Modify `src/mocks/handlers.ts`.

**Interfaces — Produces:**
- Schema types `FormField`, `FormVersion`, `Form`; errors `GetFormError`, `SaveFormError`, `PublishFormError`.
- `listForms(): Promise<Form[]>`, `getForm(id): Promise<Form>`, `getFormVersion(versionId): Promise<FormVersion>`, `createForm(name): Promise<Form>`, `saveFormDraft(formId, fields): Promise<FormVersion>`, `publishForm(formId): Promise<FormVersion>`, `forkFormDraft(formId, fromVersionId): Promise<FormVersion>`, `listFormVersions(formId): Promise<FormVersion[]>`.
- MSW: `GET *​/api/v1/forms`, `POST *​/api/v1/forms`, `GET *​/api/v1/forms/:id`, `GET *​/api/v1/forms/:id/versions`, `GET *​/api/v1/form-versions/:versionId`, `PATCH *​/api/v1/forms/:id/draft`, `POST *​/api/v1/forms/:id/publish`, `POST *​/api/v1/forms/:id/fork`.

- [ ] **Step 1: Write the schema**

Create `src/schemas/forms.ts`:

```ts
import { z } from 'zod'
import { ApiError } from '../api/errors'
import { formFieldSchema as applyFieldSchema } from './apply'

export const fieldValidationSchema = z
  .object({
    min_length: z.number().int(),
    max_length: z.number().int(),
    pattern: z.string(),
    min_selections: z.number().int(),
    max_selections: z.number().int(),
  })
  .partial()
export type FieldValidation = z.infer<typeof fieldValidationSchema>

export const visibilityOperatorSchema = z.enum(['equals', 'not_equals', 'includes', 'is_empty'])
export type VisibilityOperator = z.infer<typeof visibilityOperatorSchema>

export const visibilityConditionSchema = z.object({
  field_id: z.string(),
  operator: visibilityOperatorSchema,
  value: z.string().nullable(),
})
export type VisibilityCondition = z.infer<typeof visibilityConditionSchema>

export const visibilityRuleSchema = z.object({
  match: z.enum(['all', 'any']),
  conditions: z.array(visibilityConditionSchema),
})
export type VisibilityRule = z.infer<typeof visibilityRuleSchema>

/** A builder field = the apply base field + a stable id + optional validation/visibility.
 *  ApplyField only reads type/label/options/required/help, so this is structurally
 *  compatible with <ApplyField field={...} />. */
export const formFieldSchema = applyFieldSchema.extend({
  id: z.string(),
  validation: fieldValidationSchema.optional(),
  visibility: visibilityRuleSchema.optional(),
})
export type FormField = z.infer<typeof formFieldSchema>

export const formVersionSchema = z.object({
  id: z.string(),
  form_id: z.string(),
  version: z.number().int(),
  status: z.enum(['draft', 'published']),
  fields: z.array(formFieldSchema),
  created_at: z.string(),
  published_at: z.string().nullable(),
})
export type FormVersion = z.infer<typeof formVersionSchema>

export const formSchema = z.object({
  id: z.string(),
  name: z.string(),
  description: z.string().nullable(),
  latest_version: z.number().int(),
  published_version_ids: z.array(z.string()),
  current_draft_version_id: z.string().nullable(),
})
export type Form = z.infer<typeof formSchema>

export const formListResponseSchema = z.object({ data: z.array(formSchema) })
export const formResponseSchema = z.object({ data: formSchema })
export const formVersionResponseSchema = z.object({ data: formVersionSchema })
export const formVersionListResponseSchema = z.object({ data: z.array(formVersionSchema) })

export type FormErrorCode = 'NOT_FOUND' | 'FORBIDDEN' | 'VALIDATION' | 'CONFLICT' | 'UNAUTHENTICATED' | 'UNKNOWN'
export class GetFormError extends ApiError<FormErrorCode> {}
export class SaveFormError extends ApiError<FormErrorCode> {}
export class PublishFormError extends ApiError<FormErrorCode> {}
```

- [ ] **Step 2: Write the failing api test**

Create `src/api/forms.test.ts` (mirror `cohorts.test.ts`: fetch-spy + `jsonResponse` from `../tests/test-utils`, XSRF cookie in `beforeEach`, `afterEach(() => vi.restoreAllMocks())`):

```ts
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { jsonResponse } from '../tests/test-utils'
import { createForm, saveFormDraft, publishForm, getFormVersion, PublishFormError } from './forms'

const FORM = { id: 'frm_1', name: 'Intake', description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: 'fv_1' }
const DRAFT = { id: 'fv_1', form_id: 'frm_1', version: 1, status: 'draft', fields: [], created_at: 'x', published_at: null }

beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

test('createForm POSTs the name and returns the form', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: FORM }, 201))
  const form = await createForm('Intake')
  expect(form.id).toBe('frm_1')
  expect(spy.mock.calls[0][1]?.method).toBe('POST')
})

test('saveFormDraft PATCHes fields and returns the draft version', async () => {
  const fields = [{ id: 'f1', type: 'short_text', label: 'Name' }]
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, fields } }))
  const v = await saveFormDraft('frm_1', fields as never)
  expect(v.fields).toHaveLength(1)
  const body = JSON.parse((spy.mock.calls[0][1]?.body as string) ?? '{}')
  expect(body.fields[0].id).toBe('f1')
})

test('publishForm returns a published version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, status: 'published', published_at: 'y' } }))
  const v = await publishForm('frm_1')
  expect(v.status).toBe('published')
})

test('publishForm 409 throws CONFLICT', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 409 }))
  await expect(publishForm('frm_1')).rejects.toMatchObject({ code: 'CONFLICT' })
})

test('getFormVersion parses a version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: DRAFT }))
  const v = await getFormVersion('fv_1')
  expect(v.version).toBe(1)
})
```

- [ ] **Step 3: Run it — verify it fails** — `cd frontend && npx vitest run src/api/forms.test.ts` → FAIL (module not found).

- [ ] **Step 4: Implement the api client**

Create `src/api/forms.ts`:

```ts
import { apiFetch } from './tenant'
import { csrfFetch } from './csrf'
import {
  GetFormError, SaveFormError, PublishFormError,
  formListResponseSchema, formResponseSchema, formVersionResponseSchema, formVersionListResponseSchema,
  type Form, type FormField, type FormVersion,
} from '../schemas/forms'

export async function listForms(): Promise<Form[]> {
  const res = await apiFetch('/forms')
  if (res.status !== 200) throw new GetFormError(res.status === 401 ? 'UNAUTHENTICATED' : 'UNKNOWN')
  return formListResponseSchema.parse(await res.json()).data
}

export async function getForm(id: string): Promise<Form> {
  const res = await apiFetch(`/forms/${id}`)
  if (res.status === 200) return formResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new GetFormError('NOT_FOUND')
  if (res.status === 401) throw new GetFormError('UNAUTHENTICATED')
  throw new GetFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function listFormVersions(formId: string): Promise<FormVersion[]> {
  const res = await apiFetch(`/forms/${formId}/versions`)
  if (res.status !== 200) throw new GetFormError(res.status === 404 ? 'NOT_FOUND' : 'UNKNOWN')
  return formVersionListResponseSchema.parse(await res.json()).data
}

export async function getFormVersion(versionId: string): Promise<FormVersion> {
  const res = await apiFetch(`/form-versions/${versionId}`)
  if (res.status === 200) return formVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new GetFormError('NOT_FOUND')
  throw new GetFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function createForm(name: string): Promise<Form> {
  const res = await csrfFetch('/forms', { method: 'POST', body: JSON.stringify({ name }) })
  if (res.status === 201) return formResponseSchema.parse(await res.json()).data
  if (res.status === 422) throw new SaveFormError('VALIDATION', 'The form name is required.')
  throw new SaveFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function saveFormDraft(formId: string, fields: FormField[]): Promise<FormVersion> {
  const res = await csrfFetch(`/forms/${formId}/draft`, { method: 'PATCH', body: JSON.stringify({ fields }) })
  if (res.status === 200) return formVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new SaveFormError('NOT_FOUND')
  if (res.status === 409) throw new SaveFormError('CONFLICT', 'This version is published and read-only.')
  throw new SaveFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function publishForm(formId: string): Promise<FormVersion> {
  const res = await csrfFetch(`/forms/${formId}/publish`, { method: 'POST' })
  if (res.status === 200) return formVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new PublishFormError('NOT_FOUND')
  if (res.status === 409) throw new PublishFormError('CONFLICT', 'Nothing to publish.')
  throw new PublishFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function forkFormDraft(formId: string, fromVersionId: string): Promise<FormVersion> {
  const res = await csrfFetch(`/forms/${formId}/fork`, { method: 'POST', body: JSON.stringify({ from_version_id: fromVersionId }) })
  if (res.status === 200 || res.status === 201) return formVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new SaveFormError('NOT_FOUND')
  throw new SaveFormError('UNKNOWN', `Unexpected status ${res.status}`)
}
```

- [ ] **Step 5: Run it — verify pass** — `npx vitest run src/api/forms.test.ts` → PASS.

- [ ] **Step 6: Write the MSW handler-registration guard**

Create `src/mocks/handlers.forms.test.ts`:

```ts
import { expect, test } from 'vitest'
import { handlers } from './handlers'

function hasRoute(method: string, frag: string): boolean {
  return handlers.some((h) => (h.info?.method as string) === method && String(h.info?.path ?? '').includes(frag))
}

test('forms handlers are registered', () => {
  expect(hasRoute('GET', '/forms')).toBe(true)
  expect(hasRoute('POST', '/forms')).toBe(true)
  expect(hasRoute('PATCH', '/forms/:id/draft')).toBe(true)
  expect(hasRoute('POST', '/forms/:id/publish')).toBe(true)
  expect(hasRoute('POST', '/forms/:id/fork')).toBe(true)
  expect(hasRoute('GET', '/forms/:id/versions')).toBe(true)
  expect(hasRoute('GET', '/form-versions/:versionId')).toBe(true)
})
```

- [ ] **Step 7: Run it — verify fail** — `npx vitest run src/mocks/handlers.forms.test.ts` → FAIL.

- [ ] **Step 8: Add the MSW handlers + seed**

In `src/mocks/handlers.ts`, add a module-mutable forms store near the existing `cohorts` array, and add the handlers to the exported `handlers` array. Seed: one published form (`frm_pub` with versions `fv_pub_1` published + a current draft `fv_pub_2`) and one fresh draft form (`frm_draft`).

```ts
// ---- forms store ----
type FormRec = { id: string; name: string; description: string | null; latest_version: number; published_version_ids: string[]; current_draft_version_id: string | null }
type FormVersionRec = { id: string; form_id: string; version: number; status: 'draft' | 'published'; fields: unknown[]; created_at: string; published_at: string | null }

const forms: FormRec[] = [
  { id: 'frm_pub', name: 'Application form', description: 'Main intake', latest_version: 2, published_version_ids: ['fv_pub_1'], current_draft_version_id: 'fv_pub_2' },
  { id: 'frm_draft', name: 'New form', description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: 'fv_draft_1' },
]
const formVersions: FormVersionRec[] = [
  { id: 'fv_pub_1', form_id: 'frm_pub', version: 1, status: 'published', fields: [
    { id: 'f_name', type: 'short_text', label: 'Startup name', required: true },
    { id: 'f_stage', type: 'single_select', label: 'Stage', options: ['Idea', 'MVP'] },
  ], created_at: NOW, published_at: NOW },
  { id: 'fv_pub_2', form_id: 'frm_pub', version: 2, status: 'draft', fields: [
    { id: 'f_name', type: 'short_text', label: 'Startup name', required: true },
    { id: 'f_stage', type: 'single_select', label: 'Stage', options: ['Idea', 'MVP', 'Growth'] },
  ], created_at: NOW, published_at: null },
  { id: 'fv_draft_1', form_id: 'frm_draft', version: 1, status: 'draft', fields: [], created_at: NOW, published_at: null },
]
let formSeq = 3
let versionSeq = 3

const formHandlers = [
  http.get('*/api/v1/forms', () => HttpResponse.json({ data: forms })),
  http.post('*/api/v1/forms', async ({ request }) => {
    const body = (await request.json()) as { name?: string }
    const name = (body.name ?? '').trim()
    if (!name) return HttpResponse.json({ error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } }, { status: 422 })
    const fid = `frm_${formSeq++}`, vid = `fv_${versionSeq++}`
    formVersions.push({ id: vid, form_id: fid, version: 1, status: 'draft', fields: [], created_at: new Date().toISOString(), published_at: null })
    const rec: FormRec = { id: fid, name, description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: vid }
    forms.push(rec)
    return HttpResponse.json({ data: rec }, { status: 201 })
  }),
  http.get('*/api/v1/forms/:id', ({ params }) => {
    const f = forms.find((x) => x.id === params.id)
    return f ? HttpResponse.json({ data: f }) : new HttpResponse(null, { status: 404 })
  }),
  http.get('*/api/v1/forms/:id/versions', ({ params }) => HttpResponse.json({ data: formVersions.filter((v) => v.form_id === params.id).sort((a, b) => b.version - a.version) })),
  http.get('*/api/v1/form-versions/:versionId', ({ params }) => {
    const v = formVersions.find((x) => x.id === params.versionId)
    return v ? HttpResponse.json({ data: v }) : new HttpResponse(null, { status: 404 })
  }),
  http.patch('*/api/v1/forms/:id/draft', async ({ params, request }) => {
    const f = forms.find((x) => x.id === params.id)
    if (!f || !f.current_draft_version_id) return new HttpResponse(null, { status: 404 })
    const draft = formVersions.find((v) => v.id === f.current_draft_version_id)
    if (!draft) return new HttpResponse(null, { status: 404 })
    if (draft.status === 'published') return new HttpResponse(null, { status: 409 })
    const body = (await request.json()) as { fields?: unknown[] }
    draft.fields = body.fields ?? []
    return HttpResponse.json({ data: draft })
  }),
  http.post('*/api/v1/forms/:id/publish', ({ params }) => {
    const f = forms.find((x) => x.id === params.id)
    if (!f || !f.current_draft_version_id) return new HttpResponse(null, { status: 404 })
    const draft = formVersions.find((v) => v.id === f.current_draft_version_id)
    if (!draft || draft.status === 'published') return new HttpResponse(null, { status: 409 })
    draft.status = 'published'
    draft.published_at = new Date().toISOString()
    f.published_version_ids.push(draft.id)
    f.current_draft_version_id = null
    return HttpResponse.json({ data: draft })
  }),
  http.post('*/api/v1/forms/:id/fork', ({ params }) => {
    const f = forms.find((x) => x.id === params.id)
    if (!f) return new HttpResponse(null, { status: 404 })
    const from = formVersions.find((v) => v.id === f.published_version_ids[f.published_version_ids.length - 1])
    const vid = `fv_${versionSeq++}`
    const next = f.latest_version + 1
    formVersions.push({ id: vid, form_id: f.id, version: next, status: 'draft', fields: from ? JSON.parse(JSON.stringify(from.fields)) : [], created_at: new Date().toISOString(), published_at: null })
    f.latest_version = next
    f.current_draft_version_id = vid
    return HttpResponse.json({ data: formVersions[formVersions.length - 1] })
  }),
]
```

Then spread `...formHandlers` into the exported `handlers` array.

- [ ] **Step 9: Run both — verify pass + typecheck** — `npx vitest run src/mocks/handlers.forms.test.ts src/api/forms.test.ts && npx tsc -b` → PASS + clean.

- [ ] **Step 10: Commit** — `git add src/schemas/forms.ts src/api/forms.ts src/api/forms.test.ts src/mocks/handlers.ts src/mocks/handlers.forms.test.ts && git commit -m "feat(fe): Slice 2b — forms schema + api + MSW handlers" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

### Task 2: Visibility engine + `FormRenderer`

**Files:** Create `src/lib/visibility.ts`, `src/lib/visibility.test.ts`, `src/components/FormRenderer.tsx`, `src/components/FormRenderer.test.tsx`.

**Interfaces:**
- Consumes: `FormField`, `VisibilityRule` from `../schemas/forms`; `ApplyField` from `../pages/ApplyField`.
- Produces: `isFieldVisible(field: FormField, answers: Record<string, unknown>): boolean`; `<FormRenderer fields={FormField[]} answers={Record<string,unknown>} onChange={(id, value) => void} />`.

- [ ] **Step 1: Write the engine test** — `src/lib/visibility.test.ts`:

```ts
import { expect, test } from 'vitest'
import { isFieldVisible } from './visibility'
import type { FormField } from '../schemas/forms'

const base: FormField = { id: 'x', type: 'short_text', label: 'X' }

test('no visibility rule → always visible', () => {
  expect(isFieldVisible(base, {})).toBe(true)
})

test('equals operator shows only when the trigger matches', () => {
  const f: FormField = { ...base, visibility: { match: 'all', conditions: [{ field_id: 't', operator: 'equals', value: 'yes' }] } }
  expect(isFieldVisible(f, { t: 'yes' })).toBe(true)
  expect(isFieldVisible(f, { t: 'no' })).toBe(false)
})

test('not_equals, includes (array), is_empty', () => {
  const ne: FormField = { ...base, visibility: { match: 'all', conditions: [{ field_id: 't', operator: 'not_equals', value: 'a' }] } }
  expect(isFieldVisible(ne, { t: 'b' })).toBe(true)
  const inc: FormField = { ...base, visibility: { match: 'all', conditions: [{ field_id: 't', operator: 'includes', value: 'Fintech' }] } }
  expect(isFieldVisible(inc, { t: ['Health', 'Fintech'] })).toBe(true)
  const emp: FormField = { ...base, visibility: { match: 'all', conditions: [{ field_id: 't', operator: 'is_empty', value: null }] } }
  expect(isFieldVisible(emp, { t: '' })).toBe(true)
  expect(isFieldVisible(emp, { t: 'x' })).toBe(false)
})

test('match all vs any', () => {
  const all: FormField = { ...base, visibility: { match: 'all', conditions: [
    { field_id: 'a', operator: 'equals', value: '1' }, { field_id: 'b', operator: 'equals', value: '2' }] } }
  expect(isFieldVisible(all, { a: '1', b: '2' })).toBe(true)
  expect(isFieldVisible(all, { a: '1', b: 'x' })).toBe(false)
  const any: FormField = { ...all, visibility: { ...all.visibility!, match: 'any' } }
  expect(isFieldVisible(any, { a: '1', b: 'x' })).toBe(true)
})
```

- [ ] **Step 2: Run — verify fail** — `npx vitest run src/lib/visibility.test.ts` → FAIL.

- [ ] **Step 3: Implement the engine** — `src/lib/visibility.ts`:

```ts
import type { FormField, VisibilityCondition } from '../schemas/forms'

function matchOne(cond: VisibilityCondition, answers: Record<string, unknown>): boolean {
  const v = answers[cond.field_id]
  switch (cond.operator) {
    case 'equals': return String(v ?? '') === String(cond.value ?? '')
    case 'not_equals': return String(v ?? '') !== String(cond.value ?? '')
    case 'includes': return Array.isArray(v) ? v.map(String).includes(String(cond.value ?? '')) : String(v ?? '').includes(String(cond.value ?? ''))
    case 'is_empty': return v == null || v === '' || (Array.isArray(v) && v.length === 0)
    default: return true
  }
}

/** A field with no visibility rule is always shown. With a rule, evaluate its
 *  conditions against the current answers under match=all|any. */
export function isFieldVisible(field: FormField, answers: Record<string, unknown>): boolean {
  const rule = field.visibility
  if (!rule || rule.conditions.length === 0) return true
  return rule.match === 'all'
    ? rule.conditions.every((c) => matchOne(c, answers))
    : rule.conditions.some((c) => matchOne(c, answers))
}
```

- [ ] **Step 4: Run — verify pass** — `npx vitest run src/lib/visibility.test.ts` → PASS.

- [ ] **Step 5: Write the renderer test** — `src/components/FormRenderer.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import { useState } from 'react'
import { expect, test } from 'vitest'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormRenderer } from './FormRenderer'
import type { FormField } from '../schemas/forms'

const FIELDS: FormField[] = [
  { id: 'has_team', type: 'single_select', label: 'Do you have a team?', options: ['Yes', 'No'], required: true },
  { id: 'team_size', type: 'short_text', label: 'Team size', visibility: { match: 'all', conditions: [{ field_id: 'has_team', operator: 'equals', value: 'Yes' }] } },
]

function Harness() {
  const [answers, setAnswers] = useState<Record<string, unknown>>({})
  return <DirectionProvider><FormRenderer fields={FIELDS} answers={answers} onChange={(id, v) => setAnswers((a) => ({ ...a, [id]: v }))} /></DirectionProvider>
}

test('conditional field shows only after the trigger answer is set', () => {
  render(<Harness />)
  expect(screen.queryByText('Team size')).not.toBeInTheDocument()
  fireEvent.click(screen.getByLabelText('Yes'))
  expect(screen.getByText('Team size')).toBeInTheDocument()
})
```

- [ ] **Step 6: Run — verify fail** — FAIL (module not found).

- [ ] **Step 7: Implement `FormRenderer`** — `src/components/FormRenderer.tsx`:

```tsx
import { ApplyField } from '../pages/ApplyField'
import { isFieldVisible } from '../lib/visibility'
import type { FormField } from '../schemas/forms'

interface FormRendererProps {
  fields: FormField[]
  answers: Record<string, unknown>
  onChange: (fieldId: string, value: unknown) => void
}

/** Renders all currently-visible fields at once (unlike ApplyPage's one-at-a-time
 *  flow), re-evaluating each field's visibility against the live answers. The
 *  ApplyField atom is reused verbatim — it reads only type/label/options/required/help. */
export function FormRenderer({ fields, answers, onChange }: FormRendererProps) {
  return (
    <div className="grid gap-6">
      {fields.filter((f) => isFieldVisible(f, answers)).map((field) => (
        <ApplyField
          key={field.id}
          field={field}
          value={answers[field.id] ?? (field.type === 'multi_select' ? [] : '')}
          onChange={(v) => onChange(field.id, v)}
          onFiles={() => {}}
        />
      ))}
    </div>
  )
}
```

- [ ] **Step 8: Run — verify pass** — `npx vitest run src/components/FormRenderer.test.tsx` → PASS.

- [ ] **Step 9: Commit** — `git add src/lib/visibility.ts src/lib/visibility.test.ts src/components/FormRenderer.tsx src/components/FormRenderer.test.tsx && git commit -m "feat(fe): Slice 2b — visibility engine + FormRenderer" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

### Task 3: Form preview page

**Files:** Create `src/pages/FormPreviewPage.tsx`, `src/pages/FormPreviewPage.test.tsx`, `src/pages/FormPreviewPage.stories.tsx`.

**Interfaces:** Consumes `getFormVersion` from `../api/forms`; `FormRenderer`; `useDirection`/`DirectionProvider` from `../app/DirectionProvider`. Produces `export function FormPreviewPage({ versionId }: { versionId: string })`.

- [ ] **Step 1: Write the test** — `src/pages/FormPreviewPage.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormPreviewPage } from './FormPreviewPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const VERSION = { id: 'fv_pub_1', form_id: 'frm_pub', version: 1, status: 'published', published_at: 'x', created_at: 'x', fields: [
  { id: 'f_name', type: 'short_text', label: 'Startup name', required: true },
] }

function renderPreview(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><FormPreviewPage versionId="fv_pub_1" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
afterEach(() => vi.restoreAllMocks())

test('renders the version fields read-only and toggles RTL', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({ data: VERSION }))
  renderPreview()
  expect(await screen.findByText('Startup name')).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: /right-to-left|rtl/i }))
  expect(document.querySelector('[dir="rtl"]')).not.toBeNull()
})
```

- [ ] **Step 2: Run — verify fail.**

- [ ] **Step 3: Implement the page** — `src/pages/FormPreviewPage.tsx`:

```tsx
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { FormRenderer } from '../components/FormRenderer'
import { getFormVersion } from '../api/forms'

export function FormPreviewPage({ versionId }: { versionId: string }) {
  const versionQuery = useQuery({ queryKey: ['form-version', versionId], queryFn: () => getFormVersion(versionId), retry: false })
  const [answers, setAnswers] = useState<Record<string, unknown>>({})
  const [rtl, setRtl] = useState(false)
  const v = versionQuery.data

  return (
    <AppShell
      rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><Link href="/programs">Programs</Link></nav>}
      pageHeader={
        <div className="flex items-center justify-between">
          <h1 id="preview-heading" className="text-2xl font-semibold">Form preview{v ? ` — v${v.version}` : ''}</h1>
          <Button variant="secondary" onClick={() => setRtl((r) => !r)}>{rtl ? 'Left-to-right' : 'Right-to-left (RTL)'}</Button>
        </div>
      }
    >
      <section aria-labelledby="preview-heading" className="grid max-w-2xl gap-6">
        {versionQuery.isLoading ? (
          <Spinner label="Loading form…" />
        ) : versionQuery.isError || !v ? (
          <StateBlock variant="error" message="Could not load this form version." />
        ) : (
          <div dir={rtl ? 'rtl' : 'ltr'} className="rounded-lg border border-border p-6">
            <FormRenderer fields={v.fields} answers={answers} onChange={(id, val) => setAnswers((a) => ({ ...a, [id]: val }))} />
          </div>
        )}
      </section>
    </AppShell>
  )
}
```

- [ ] **Step 4: Run — verify pass.**

- [ ] **Step 5: Add a story** — `src/pages/FormPreviewPage.stories.tsx`:

```tsx
import type { Meta, StoryObj } from '@storybook/react-vite'
import { FormPreviewPage } from './FormPreviewPage'

const meta = { title: 'Pages/FormPreviewPage', component: FormPreviewPage, args: { versionId: 'fv_pub_1' } } satisfies Meta<typeof FormPreviewPage>
export default meta
export const Default: StoryObj<typeof meta> = {}
```

- [ ] **Step 6: Verify storybook builds** — `npm run build-storybook`.
- [ ] **Step 7: Commit** — `git add src/pages/FormPreviewPage.* && git commit -m "feat(fe): Slice 2b — form preview page" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

### Task 4: Form builder — palette + canvas (structure)

**Files:** Create `src/pages/FormBuilderPage.tsx`, `src/pages/FormBuilderPage.test.tsx`.

**Interfaces:** Consumes `getForm`, `getFormVersion`, `saveFormDraft` from `../api/forms`; `FormField`, `FormVersion` from `../schemas/forms`. Produces `export function FormBuilderPage({ formId }: { formId: string })` and the internal field-list state shape `{ fields: FormField[]; selectedId: string | null }`.

The builder loads the form's current draft version, holds `fields` in local state, autosaves via `saveFormDraft` (debounced), and renders three regions: **palette** (left, the 8 `fieldType` options as add buttons), **canvas** (center, ordered field list with select / move up / move down / remove), **inspector** (right — a placeholder in this task; filled in Tasks 5–6).

- [ ] **Step 1: Write the test** — `src/pages/FormBuilderPage.test.tsx`:

```tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import type { ReactElement } from 'react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DirectionProvider } from '../app/DirectionProvider'
import { FormBuilderPage } from './FormBuilderPage'
import { jsonResponse } from '../tests/test-utils'

vi.mock('../api/roles', () => ({ listMyRoles: () => Promise.resolve([]) }))

const FORM = { id: 'frm_draft', name: 'New form', description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: 'fv_draft_1' }
const DRAFT = { id: 'fv_draft_1', form_id: 'frm_draft', version: 1, status: 'draft', fields: [], created_at: 'x', published_at: null }

function mockApi() {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/forms/frm_draft/draft')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/form-versions/fv_draft_1')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/forms/frm_draft')) return Promise.resolve(jsonResponse({ data: FORM }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
}
function renderBuilder(): void {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const ui: ReactElement = <DirectionProvider><QueryClientProvider client={client}><FormBuilderPage formId="frm_draft" /></QueryClientProvider></DirectionProvider>
  render(ui)
}
beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

test('adds a field from the palette and shows it on the canvas', async () => {
  mockApi(); renderBuilder()
  await screen.findByRole('heading', { name: /form builder|new form/i })
  fireEvent.click(screen.getByRole('button', { name: /add short text/i }))
  await waitFor(() => expect(screen.getByText(/short text/i)).toBeInTheDocument())
})

test('reorders fields with move up', async () => {
  mockApi(); renderBuilder()
  await screen.findByRole('heading', { name: /form builder|new form/i })
  fireEvent.click(screen.getByRole('button', { name: /add short text/i }))
  fireEvent.click(screen.getByRole('button', { name: /add date/i }))
  const ups = screen.getAllByRole('button', { name: /move up/i })
  fireEvent.click(ups[ups.length - 1]) // move the date field above the text field
  const items = screen.getAllByRole('listitem')
  expect(items[0]).toHaveTextContent(/date/i)
})
```

- [ ] **Step 2: Run — verify fail.**

- [ ] **Step 3: Implement the builder shell + palette + canvas** — `src/pages/FormBuilderPage.tsx`:

```tsx
import { useEffect, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getForm, getFormVersion, saveFormDraft } from '../api/forms'
import type { FormField } from '../schemas/forms'
import { fieldType as fieldTypeEnum } from '../schemas/apply'

const TYPE_LABEL: Record<string, string> = {
  short_text: 'Short text', long_text: 'Long text', single_select: 'Single select',
  multi_select: 'Multi select', number: 'Number', date: 'Date', file_upload: 'File upload', consent: 'Consent',
}
let fieldSeq = 0
function newField(type: FormField['type']): FormField {
  fieldSeq += 1
  return { id: `field_${type}_${fieldSeq}`, type, label: TYPE_LABEL[type] ?? type, required: false }
}

export function FormBuilderPage({ formId }: { formId: string }) {
  const formQuery = useQuery({ queryKey: ['form', formId], queryFn: () => getForm(formId), retry: false })
  const draftId = formQuery.data?.current_draft_version_id ?? null
  const draftQuery = useQuery({ queryKey: ['form-version', draftId], queryFn: () => getFormVersion(draftId!), enabled: !!draftId, retry: false })

  const [fields, setFields] = useState<FormField[]>([])
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const seeded = useRef<string | null>(null)

  // seed from the loaded draft (render-time reset keyed on version id, not an effect)
  if (draftQuery.data && seeded.current !== draftQuery.data.id) {
    seeded.current = draftQuery.data.id
    setFields(draftQuery.data.fields)
  }

  // debounced autosave whenever fields change after seeding
  useEffect(() => {
    if (!draftId || seeded.current === null) return
    const t = setTimeout(() => { void saveFormDraft(formId, fields).catch(() => {}) }, 400)
    return () => clearTimeout(t)
  }, [fields, formId, draftId])

  function addField(type: FormField['type']) { const f = newField(type); setFields((cur) => [...cur, f]); setSelectedId(f.id) }
  function move(idx: number, dir: -1 | 1) {
    setFields((cur) => {
      const next = [...cur]; const j = idx + dir
      if (j < 0 || j >= next.length) return cur
      ;[next[idx], next[j]] = [next[j], next[idx]]; return next
    })
  }
  function remove(id: string) { setFields((cur) => cur.filter((f) => f.id !== id)); if (selectedId === id) setSelectedId(null) }

  const readOnly = !draftId // no current draft → published-only, read-only (Task 7 adds fork)

  return (
    <AppShell
      rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><Link href="/programs">Programs</Link></nav>}
      pageHeader={<h1 id="builder-heading" className="text-2xl font-semibold">Form builder{formQuery.data ? ` — ${formQuery.data.name}` : ''}</h1>}
    >
      <section aria-labelledby="builder-heading" className="grid gap-6">
        {formQuery.isLoading ? (
          <Spinner label="Loading form…" />
        ) : formQuery.isError ? (
          <StateBlock variant="error" message="Could not load this form." />
        ) : (
          <div className="grid gap-4 lg:grid-cols-[200px_1fr_320px]">
            {/* palette */}
            <div aria-label="Field palette" className="grid h-fit gap-2 rounded-lg border border-border p-3">
              <h2 className="text-sm font-medium text-muted-foreground">Add field</h2>
              {fieldTypeEnum.options.map((t) => (
                <Button key={t} variant="secondary" disabled={readOnly} onClick={() => addField(t as FormField['type'])}>
                  Add {TYPE_LABEL[t] ?? t}
                </Button>
              ))}
            </div>
            {/* canvas */}
            <div className="rounded-lg border border-border p-3">
              {fields.length === 0 ? (
                <StateBlock variant="empty" message="No fields yet. Add one from the palette." />
              ) : (
                <ul className="grid gap-2">
                  {fields.map((f, idx) => (
                    <li key={f.id} className={`flex items-center justify-between rounded-md border px-3 py-2 ${selectedId === f.id ? 'border-primary bg-accent' : 'border-border'}`}>
                      <button type="button" className="text-left" onClick={() => setSelectedId(f.id)}>
                        <span className="font-medium"><bdi>{f.label}</bdi></span>
                        <span className="ml-2 text-xs text-muted-foreground">{TYPE_LABEL[f.type] ?? f.type}</span>
                      </button>
                      <span className="flex gap-1">
                        <Button variant="secondary" aria-label={`Move up ${f.label}`} disabled={readOnly || idx === 0} onClick={() => move(idx, -1)}>↑</Button>
                        <Button variant="secondary" aria-label={`Move down ${f.label}`} disabled={readOnly || idx === fields.length - 1} onClick={() => move(idx, 1)}>↓</Button>
                        <Button variant="secondary" aria-label={`Remove ${f.label}`} disabled={readOnly} onClick={() => remove(f.id)}>✕</Button>
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
            {/* inspector placeholder — filled in Tasks 5–6 */}
            <div aria-label="Field settings" className="rounded-lg border border-border p-3 text-sm text-muted-foreground">
              {selectedId ? 'Field settings' : 'Select a field to edit its settings.'}
            </div>
          </div>
        )}
      </section>
    </AppShell>
  )
}
```

> Note: the move-button `aria-label`s ("Move up <label>") are intentional — the visible content is an arrow glyph, so the icon button needs an accessible name. This is the allowed case, not the forbidden "override visible text" case.

- [ ] **Step 4: Run — verify pass.**
- [ ] **Step 5: Commit** — `git add src/pages/FormBuilderPage.tsx src/pages/FormBuilderPage.test.tsx && git commit -m "feat(fe): Slice 2b — form builder palette + canvas" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

### Task 5: Field inspector — config + validation

**Files:** Create `src/components/FieldInspector.tsx`; Modify `src/pages/FormBuilderPage.tsx` (mount the inspector for the selected field) + its test.

**Interfaces:** Consumes `FormField`, `FieldValidation` from `../schemas/forms`. Produces `<FieldInspector field={FormField} onChange={(patch: Partial<FormField>) => void} />`.

The inspector edits the selected field: `label` (Field/Input), `help` (native textarea like `ApplyField`), `required` (native checkbox), `options` (one text input per option + add/remove, shown for `single_select`/`multi_select`), and `validation` (number inputs `min_length`/`max_length`, text `pattern` for text types; `min_selections`/`max_selections` for `multi_select`). All edits call `onChange` with a partial patch; the builder merges it into the selected field and re-renders/autosaves.

- [ ] **Step 1: Write the test** — `src/components/FieldInspector.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import { useState } from 'react'
import { expect, test } from 'vitest'
import { DirectionProvider } from '../app/DirectionProvider'
import { FieldInspector } from './FieldInspector'
import type { FormField } from '../schemas/forms'

function Harness({ initial }: { initial: FormField }) {
  const [field, setField] = useState(initial)
  return <DirectionProvider><FieldInspector field={field} onChange={(p) => setField((f) => ({ ...f, ...p }))} /></DirectionProvider>
}

test('editing the label and toggling required patches the field', () => {
  render(<Harness initial={{ id: 'f1', type: 'short_text', label: 'X', required: false }} />)
  fireEvent.change(screen.getByLabelText(/field label/i), { target: { value: 'Email' } })
  expect(screen.getByLabelText(/field label/i)).toHaveValue('Email')
  fireEvent.click(screen.getByLabelText(/required/i))
  expect(screen.getByLabelText(/required/i)).toBeChecked()
})

test('select field shows an options editor', () => {
  render(<Harness initial={{ id: 'f2', type: 'single_select', label: 'Stage', options: ['Idea'] }} />)
  fireEvent.click(screen.getByRole('button', { name: /add option/i }))
  expect(screen.getAllByLabelText(/option \d+/i).length).toBe(2)
})
```

- [ ] **Step 2: Run — verify fail.**

- [ ] **Step 3: Implement `FieldInspector`** — `src/components/FieldInspector.tsx`:

```tsx
import { Field } from './Field'
import { Button } from './Button'
import type { FormField } from '../schemas/forms'

const SELECT_TYPES = ['single_select', 'multi_select']
const TEXT_TYPES = ['short_text', 'long_text']

export function FieldInspector({ field, onChange }: { field: FormField; onChange: (patch: Partial<FormField>) => void }) {
  const options = field.options ?? []
  const validation = field.validation ?? {}
  function setOption(i: number, v: string) { const next = [...options]; next[i] = v; onChange({ options: next }) }
  function addOption() { onChange({ options: [...options, `Option ${options.length + 1}`] }) }
  function removeOption(i: number) { onChange({ options: options.filter((_, j) => j !== i) }) }
  function setValidation(patch: Partial<FormField['validation']>) { onChange({ validation: { ...validation, ...patch } }) }

  return (
    <div className="grid gap-4 text-sm">
      <Field label="Field label" name="field-label" value={field.label} onChange={(e) => onChange({ label: e.target.value })} />
      <div className="grid gap-1.5">
        <label htmlFor="field-help" className="font-medium">Help text</label>
        <textarea id="field-help" rows={2} className="rounded-md border border-input bg-card p-2" value={field.help ?? ''} onChange={(e) => onChange({ help: e.target.value })} />
      </div>
      <label className="flex items-center gap-2">
        <input type="checkbox" checked={field.required ?? false} onChange={(e) => onChange({ required: e.target.checked })} />
        Required
      </label>

      {SELECT_TYPES.includes(field.type) && (
        <fieldset className="grid gap-2 rounded-md border border-border p-2">
          <legend className="px-1 text-xs text-muted-foreground">Options</legend>
          {options.map((opt, i) => (
            <span key={i} className="flex gap-1">
              <Field label={`Option ${i + 1}`} name={`option-${i}`} value={opt} onChange={(e) => setOption(i, e.target.value)} />
              <Button variant="secondary" aria-label={`Remove option ${i + 1}`} onClick={() => removeOption(i)}>✕</Button>
            </span>
          ))}
          <Button variant="secondary" onClick={addOption}>Add option</Button>
          {field.type === 'multi_select' && (
            <span className="flex gap-2">
              <Field label="Min selections" name="min-sel" type="number" min={0} value={validation.min_selections ?? ''} onChange={(e) => setValidation({ min_selections: e.target.value === '' ? undefined : Number(e.target.value) })} />
              <Field label="Max selections" name="max-sel" type="number" min={0} value={validation.max_selections ?? ''} onChange={(e) => setValidation({ max_selections: e.target.value === '' ? undefined : Number(e.target.value) })} />
            </span>
          )}
        </fieldset>
      )}

      {TEXT_TYPES.includes(field.type) && (
        <fieldset className="grid gap-2 rounded-md border border-border p-2">
          <legend className="px-1 text-xs text-muted-foreground">Validation</legend>
          <span className="flex gap-2">
            <Field label="Min length" name="min-len" type="number" min={0} value={validation.min_length ?? ''} onChange={(e) => setValidation({ min_length: e.target.value === '' ? undefined : Number(e.target.value) })} />
            <Field label="Max length" name="max-len" type="number" min={0} value={validation.max_length ?? ''} onChange={(e) => setValidation({ max_length: e.target.value === '' ? undefined : Number(e.target.value) })} />
          </span>
          <Field label="Pattern (regex)" name="pattern" value={validation.pattern ?? ''} onChange={(e) => setValidation({ pattern: e.target.value || undefined })} />
        </fieldset>
      )}
    </div>
  )
}
```

- [ ] **Step 4: Mount it in the builder** — in `FormBuilderPage.tsx`, replace the inspector placeholder `<div aria-label="Field settings">…</div>` with:

```tsx
<div aria-label="Field settings" className="rounded-lg border border-border p-3">
  {(() => {
    const selected = fields.find((f) => f.id === selectedId)
    if (!selected) return <p className="text-sm text-muted-foreground">Select a field to edit its settings.</p>
    return <FieldInspector field={selected} onChange={(patch) => setFields((cur) => cur.map((f) => (f.id === selected.id ? { ...f, ...patch } : f)))} />
  })()}
</div>
```

Add `import { FieldInspector } from '../components/FieldInspector'`. Add a builder test: select a field, change its label in the inspector, assert the canvas label updates.

- [ ] **Step 5: Run — verify pass** — `npx vitest run src/components/FieldInspector.test.tsx src/pages/FormBuilderPage.test.tsx`.
- [ ] **Step 6: Commit** — `git add src/components/FieldInspector.tsx src/components/FieldInspector.test.tsx src/pages/FormBuilderPage.tsx src/pages/FormBuilderPage.test.tsx && git commit -m "feat(fe): Slice 2b — field inspector (config + validation)" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

### Task 6: Conditional-logic editor (cycle-prevented)

**Files:** Create `src/components/VisibilityEditor.tsx`; Modify `src/components/FieldInspector.tsx` (mount it) + tests.

**Interfaces:** Consumes `FormField`, `VisibilityRule`, `VisibilityCondition`, `visibilityOperatorSchema` from `../schemas/forms`. Produces `<VisibilityEditor field={FormField} priorFields={FormField[]} onChange={(visibility: VisibilityRule | undefined) => void} />`. `priorFields` = fields ordered before this one (only they may be triggers — this structurally prevents cycles and forward references).

- [ ] **Step 1: Write the test** — `src/components/VisibilityEditor.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react'
import { useState } from 'react'
import { expect, test } from 'vitest'
import { VisibilityEditor } from './VisibilityEditor'
import type { FormField, VisibilityRule } from '../schemas/forms'

const PRIOR: FormField[] = [{ id: 'a', type: 'single_select', label: 'A', options: ['Yes', 'No'] }]

function Harness() {
  const [vis, setVis] = useState<VisibilityRule | undefined>(undefined)
  return <VisibilityEditor field={{ id: 'b', type: 'short_text', label: 'B', visibility: vis }} priorFields={PRIOR} onChange={setVis} />
}

test('adding a condition offers only prior fields as triggers', () => {
  render(<Harness />)
  fireEvent.click(screen.getByRole('button', { name: /add condition/i }))
  const trigger = screen.getByLabelText(/when field/i) as HTMLSelectElement
  const optionValues = Array.from(trigger.options).map((o) => o.value).filter(Boolean)
  expect(optionValues).toEqual(['a'])
})

test('first field (no prior fields) shows a no-triggers notice', () => {
  render(<VisibilityEditor field={{ id: 'a', type: 'short_text', label: 'A' }} priorFields={[]} onChange={() => {}} />)
  expect(screen.getByText(/no earlier fields/i)).toBeInTheDocument()
})
```

- [ ] **Step 2: Run — verify fail.**

- [ ] **Step 3: Implement `VisibilityEditor`** — `src/components/VisibilityEditor.tsx`:

```tsx
import { Button } from './Button'
import type { FormField, VisibilityCondition, VisibilityRule } from '../schemas/forms'
import { visibilityOperatorSchema } from '../schemas/forms'

const OPERATOR_LABEL: Record<string, string> = { equals: 'equals', not_equals: 'does not equal', includes: 'includes', is_empty: 'is empty' }

export function VisibilityEditor({ field, priorFields, onChange }: { field: FormField; priorFields: FormField[]; onChange: (v: VisibilityRule | undefined) => void }) {
  const rule = field.visibility ?? { match: 'all' as const, conditions: [] as VisibilityCondition[] }
  function emit(next: VisibilityRule) { onChange(next.conditions.length === 0 ? undefined : next) }
  function addCondition() {
    if (priorFields.length === 0) return
    emit({ ...rule, conditions: [...rule.conditions, { field_id: priorFields[0].id, operator: 'equals', value: '' }] })
  }
  function patch(i: number, p: Partial<VisibilityCondition>) { emit({ ...rule, conditions: rule.conditions.map((c, j) => (j === i ? { ...c, ...p } : c)) }) }
  function removeCondition(i: number) { emit({ ...rule, conditions: rule.conditions.filter((_, j) => j !== i) }) }

  if (priorFields.length === 0) {
    return <p className="text-xs text-muted-foreground">No earlier fields to depend on — add fields above this one to set visibility rules.</p>
  }

  return (
    <fieldset className="grid gap-2 rounded-md border border-border p-2">
      <legend className="px-1 text-xs text-muted-foreground">Show this field when</legend>
      {rule.conditions.length > 1 && (
        <label className="flex items-center gap-2 text-xs">
          Match
          <select value={rule.match} onChange={(e) => emit({ ...rule, match: e.target.value as 'all' | 'any' })} className="rounded-md border border-input bg-card p-1">
            <option value="all">all</option>
            <option value="any">any</option>
          </select>
          of:
        </label>
      )}
      {rule.conditions.map((c, i) => (
        <span key={i} className="flex flex-wrap items-center gap-1">
          <label className="sr-only" htmlFor={`when-${i}`}>When field</label>
          <select id={`when-${i}`} aria-label="When field" value={c.field_id} onChange={(e) => patch(i, { field_id: e.target.value })} className="rounded-md border border-input bg-card p-1">
            {priorFields.map((pf) => <option key={pf.id} value={pf.id}>{pf.label}</option>)}
          </select>
          <select aria-label="Operator" value={c.operator} onChange={(e) => patch(i, { operator: e.target.value as VisibilityCondition['operator'] })} className="rounded-md border border-input bg-card p-1">
            {visibilityOperatorSchema.options.map((op) => <option key={op} value={op}>{OPERATOR_LABEL[op]}</option>)}
          </select>
          {c.operator !== 'is_empty' && (
            <input aria-label="Value" value={c.value ?? ''} onChange={(e) => patch(i, { value: e.target.value })} className="rounded-md border border-input bg-card p-1" />
          )}
          <Button variant="secondary" aria-label={`Remove condition ${i + 1}`} onClick={() => removeCondition(i)}>✕</Button>
        </span>
      ))}
      <Button variant="secondary" onClick={addCondition}>Add condition</Button>
    </fieldset>
  )
}
```

- [ ] **Step 4: Mount it in the inspector** — in `FieldInspector.tsx`, accept a new prop `priorFields: FormField[]` and render `<VisibilityEditor field={field} priorFields={priorFields} onChange={(visibility) => onChange({ visibility })} />` at the bottom. In `FormBuilderPage.tsx`, pass `priorFields={fields.slice(0, fields.findIndex((f) => f.id === selected.id))}` to the inspector. Update the inspector test Harness to pass `priorFields={[]}`.

- [ ] **Step 5: Run — verify pass** — `npx vitest run src/components/VisibilityEditor.test.tsx src/components/FieldInspector.test.tsx src/pages/FormBuilderPage.test.tsx`.
- [ ] **Step 6: Commit** — `git add src/components/VisibilityEditor.* src/components/FieldInspector.tsx src/components/FieldInspector.test.tsx src/pages/FormBuilderPage.tsx && git commit -m "feat(fe): Slice 2b — conditional-logic editor (cycle-prevented)" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

### Task 7: Publish + version lifecycle

**Files:** Modify `src/pages/FormBuilderPage.tsx` + test; add a Storybook story `src/pages/FormBuilderPage.stories.tsx`.

**Interfaces:** Consumes `publishForm`, `forkFormDraft` from `../api/forms`. Adds a header action area: a version status badge (`Draft v{n}` / `Published v{n}`), a **Publish** button (draft only) that snapshots the draft → published version, and an **Edit (new draft)** button shown when the loaded form has no current draft (published-only) that calls `forkFormDraft` to start a new draft.

- [ ] **Step 1: Write the test** — append to `FormBuilderPage.test.tsx`:

```tsx
test('publish snapshots the draft into a published version', async () => {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = String(input)
    if (url.includes('/forms/frm_draft/publish')) return Promise.resolve(jsonResponse({ data: { ...DRAFT, status: 'published', published_at: 'y' } }))
    if (url.includes('/forms/frm_draft/draft')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/form-versions/fv_draft_1')) return Promise.resolve(jsonResponse({ data: DRAFT }))
    if (url.includes('/forms/frm_draft')) return Promise.resolve(jsonResponse({ data: FORM }))
    return Promise.resolve(new Response(null, { status: 404 }))
  })
  renderBuilder()
  await screen.findByRole('heading', { name: /form builder|new form/i })
  fireEvent.click(screen.getByRole('button', { name: /^publish$/i }))
  expect(await screen.findByText(/published/i)).toBeInTheDocument()
})
```

- [ ] **Step 2: Run — verify fail.**

- [ ] **Step 3: Implement** — in `FormBuilderPage.tsx`:
  - Add `const queryClient = useQueryClient()`, `const [published, setPublished] = useState<FormVersion | null>(null)`.
  - `const publishMutation = useMutation({ mutationFn: () => publishForm(formId), onSuccess: (v) => { setPublished(v); void queryClient.invalidateQueries({ queryKey: ['form', formId] }) } })`.
  - `const forkMutation = useMutation({ mutationFn: () => forkFormDraft(formId, formQuery.data!.published_version_ids.at(-1)!), onSuccess: (v) => { seeded.current = null; void queryClient.invalidateQueries({ queryKey: ['form', formId] }); setFields(v.fields) } })`.
  - In `pageHeader`, beside the title add a status badge + actions:

```tsx
<div className="flex items-center gap-3">
  <span data-status={published ? 'published' : draftId ? 'draft' : 'published'} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
    {published ? 'Published' : draftId ? `Draft v${draftQuery.data?.version ?? ''}` : 'Published (read-only)'}
  </span>
  {draftId && !published && <Button loading={publishMutation.isPending} onClick={() => publishMutation.mutate()}>Publish</Button>}
  {!draftId && <Button variant="secondary" loading={forkMutation.isPending} onClick={() => forkMutation.mutate()}>Edit (new draft)</Button>}
</div>
```

  - Add imports: `useMutation`, `useQueryClient` from `@tanstack/react-query`; `publishForm`, `forkFormDraft` from `../api/forms`; `type FormVersion`.

- [ ] **Step 4: Run — verify pass.**
- [ ] **Step 5: Add the builder story** — `src/pages/FormBuilderPage.stories.tsx` (title `Pages/FormBuilderPage`, args `{ formId: 'frm_draft' }`, `Default` story). Verify `npm run build-storybook`.
- [ ] **Step 6: Commit** — `git add src/pages/FormBuilderPage.tsx src/pages/FormBuilderPage.test.tsx src/pages/FormBuilderPage.stories.tsx && git commit -m "feat(fe): Slice 2b — publish + version lifecycle" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

### Task 8: Generic `VersionHistoryList` + `VersionCompare` + versions page

**Files:** Create `src/components/VersionHistoryList.tsx`, `src/components/VersionCompare.tsx`, `src/pages/FormVersionsPage.tsx` + tests.

**Interfaces (generic so 2c reuses them):**
- `interface VersionItem { id: string; version: number; status: 'draft' | 'published'; published_at: string | null }`
- `<VersionHistoryList versions={VersionItem[]} selectedIds={[string, string] | null} onSelect={(id) => void} />`
- `<VersionCompare left={{label,lines}} right={{label,lines}} />` where each side is `{ label: string; lines: string[] }` and the component diffs the two `lines` arrays (added/removed/unchanged) — content-agnostic so stages (2c) can pass stage descriptions.
- `FormVersionsPage` converts `FormVersion.fields` into `lines` (e.g. `` `${i+1}. ${f.label} (${f.type})` ``) and renders both components.

- [ ] **Step 1: Write the compare test** — `src/components/VersionCompare.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { VersionCompare } from './VersionCompare'

test('marks added and removed lines between two versions', () => {
  render(<VersionCompare left={{ label: 'v1', lines: ['1. Name (short_text)', '2. Stage (single_select)'] }} right={{ label: 'v2', lines: ['1. Name (short_text)', '2. Stage (single_select)', '3. Website (short_text)'] }} />)
  expect(screen.getByText(/3\. Website/)).toHaveAttribute('data-diff', 'added')
})
```

- [ ] **Step 2: Run — verify fail.**

- [ ] **Step 3: Implement `VersionCompare`** — `src/components/VersionCompare.tsx`:

```tsx
interface Side { label: string; lines: string[] }

/** Content-agnostic two-column diff. Lines present only on the right are 'added',
 *  only on the left are 'removed', present on both are 'unchanged'. */
export function VersionCompare({ left, right }: { left: Side; right: Side }) {
  const leftSet = new Set(left.lines)
  const rightSet = new Set(right.lines)
  function cls(diff: string) { return diff === 'added' ? 'bg-accent text-accent-foreground' : diff === 'removed' ? 'text-muted-foreground line-through' : '' }
  return (
    <div className="grid grid-cols-2 gap-4 text-sm">
      <div><h3 className="mb-2 font-medium">{left.label}</h3>
        <ul className="grid gap-1">{left.lines.map((l, i) => { const d = rightSet.has(l) ? 'unchanged' : 'removed'; return <li key={i} data-diff={d} className={`rounded px-2 py-1 ${cls(d)}`}>{l}</li> })}</ul>
      </div>
      <div><h3 className="mb-2 font-medium">{right.label}</h3>
        <ul className="grid gap-1">{right.lines.map((l, i) => { const d = leftSet.has(l) ? 'unchanged' : 'added'; return <li key={i} data-diff={d} className={`rounded px-2 py-1 ${cls(d)}`}>{l}</li> })}</ul>
      </div>
    </div>
  )
}
```

- [ ] **Step 4: Implement `VersionHistoryList`** — `src/components/VersionHistoryList.tsx`:

```tsx
interface VersionItem { id: string; version: number; status: 'draft' | 'published'; published_at: string | null }

export function VersionHistoryList({ versions, selectedIds, onSelect }: { versions: VersionItem[]; selectedIds: [string, string] | null; onSelect: (id: string) => void }) {
  return (
    <ul aria-label="Version history" className="grid gap-2">
      {versions.map((v) => {
        const checked = selectedIds?.includes(v.id) ?? false
        return (
          <li key={v.id} className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
            <label className="flex items-center gap-2">
              <input type="checkbox" checked={checked} onChange={() => onSelect(v.id)} />
              <span className="font-medium">Version {v.version}</span>
              <span data-status={v.status} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">{v.status === 'published' ? 'Published' : 'Draft'}</span>
            </label>
            <span className="text-xs text-muted-foreground">{v.published_at ? v.published_at.slice(0, 10) : '—'}</span>
          </li>
        )
      })}
    </ul>
  )
}
```

- [ ] **Step 5: Implement `FormVersionsPage`** — `src/pages/FormVersionsPage.tsx`: loads `listFormVersions(formId)`; holds `selected: string[]` (max 2); renders `VersionHistoryList` and, when two are picked, fetches both via `getFormVersion` and renders `VersionCompare` mapping each version's `fields` to `lines` via `` `${i + 1}. ${f.label} (${f.type})` ``. Wrap in `AppShell`. Add a test asserting the history lists 2 versions and selecting two shows the compare with an added field. (Use the URL-routing `mockImplementation` fetch pattern from `ActionCenterPage.test.tsx`.)

- [ ] **Step 6: Run — verify pass** — `npx vitest run src/components/VersionCompare.test.tsx src/pages/FormVersionsPage.test.tsx`.
- [ ] **Step 7: Commit** — `git add src/components/VersionHistoryList.tsx src/components/VersionCompare.tsx src/components/VersionCompare.test.tsx src/pages/FormVersionsPage.tsx src/pages/FormVersionsPage.test.tsx && git commit -m "feat(fe): Slice 2b — generic version history + compare" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

### Task 9: Form binding (published version → cohort)

**Files:** Create `src/components/FormBindingPicker.tsx` + test; Modify `src/schemas/cohorts.ts`, `src/api/cohorts.ts` (+ test), `src/mocks/handlers.ts`, `src/pages/CohortDetailPage.tsx` (+ test).

**Interfaces:**
- `cohortSchema` gains `bound_form_version_id: z.string().nullable()` (add with `.default(null)` is not used by the existing fixtures — instead add it as `.nullable().optional()` to stay backward-compatible with seed data, then default in code).
- `bindCohortForm(cohortId, formVersionId): Promise<Cohort>` (POST `/cohorts/:id/bind-form`, body `{ form_version_id }`).
- MSW: `POST *​/api/v1/cohorts/:id/bind-form` sets `bound_form_version_id` on the cohort.
- `<FormBindingPicker cohortId boundVersionId onBound={(cohort) => void} />` — lists published versions across forms (from `listForms` + `listFormVersions`, filtered to `status:'published'`), shows the current binding, binds on select, warns when replacing an existing binding.

- [ ] **Step 1: Write the api test** — append to `src/api/cohorts.test.ts`: `bindCohortForm` POSTs `{ form_version_id }` and returns the cohort with `bound_form_version_id` set.
- [ ] **Step 2: Run — verify fail.**
- [ ] **Step 3: Implement schema + api + MSW:**
  - `src/schemas/cohorts.ts`: add `bound_form_version_id: z.string().nullable().optional()` to `cohortSchema`; add `BindFormError` (codes `NOT_FOUND|FORBIDDEN|CONFLICT|UNAUTHENTICATED|UNKNOWN`).
  - `src/api/cohorts.ts`: `export async function bindCohortForm(id, formVersionId): Promise<Cohort>` via `csrfFetch('/cohorts/${id}/bind-form', { method: 'POST', body: JSON.stringify({ form_version_id: formVersionId }) })`, mapping 200→parse, 404→NOT_FOUND, 409→CONFLICT, else UNKNOWN.
  - `src/mocks/handlers.ts`: `http.post('*/api/v1/cohorts/:id/bind-form', async ({ params, request }) => { const c = cohorts.find(x => x.id === params.id); if (!c) return new HttpResponse(null,{status:404}); const b = await request.json() as {form_version_id?:string}; (c as Record<string,unknown>).bound_form_version_id = b.form_version_id ?? null; c.updated_at = new Date().toISOString(); return HttpResponse.json({ data: c }) })`. Add to `handlers.forms.test.ts` guard.
- [ ] **Step 4: Run — verify pass.**
- [ ] **Step 5: Implement `FormBindingPicker`** — lists published versions (label `${formName} v${version}`); current binding shown; selecting one calls `bindCohortForm` and `onBound`; replacing an existing binding shows a confirm Banner first. Test: only published versions are offered; binding calls the api with the chosen version id.
- [ ] **Step 6: Wire CohortDetailPage** — replace the static "Application form: Not bound yet" row (from 2a) with the real bound-version label (or "Not bound") + a `<FormBindingPicker>` entry. Update its test.
- [ ] **Step 7: Run — verify pass** — `npx vitest run src/api/cohorts.test.ts src/components/FormBindingPicker.test.tsx src/pages/CohortDetailPage.test.tsx`.
- [ ] **Step 8: Commit** — `git add src/schemas/cohorts.ts src/api/cohorts.ts src/api/cohorts.test.ts src/mocks/handlers.ts src/mocks/handlers.forms.test.ts src/components/FormBindingPicker.* src/pages/CohortDetailPage.tsx src/pages/CohortDetailPage.test.tsx && git commit -m "feat(fe): Slice 2b — form binding to cohort" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

### Task 10: Routes + a11y + e2e + gate sweep

**Files:** Modify `src/app/App.tsx`, `src/tests/a11y.test.tsx`, `playwright.config.ts`; Create `tests/e2e/fe-ui-slice2b.spec.ts`.

- [ ] **Step 1: Add routes** — in `App.tsx`, add gated routes mirroring the `CohortDetailRoute` render-prop pattern:

```tsx
<Route path="/forms/:formId/edit" element={<FormBuilderRoute />} />
<Route path="/forms/:formId/preview" element={<FormPreviewRoute />} />
<Route path="/forms/:formId/versions" element={<FormVersionsRoute />} />
```
```tsx
function FormBuilderRoute() { const { formId } = useParams(); return <ConsoleGate>{() => <FormBuilderPage formId={formId!} />}</ConsoleGate> }
function FormVersionsRoute() { const { formId } = useParams(); return <ConsoleGate>{() => <FormVersionsPage formId={formId!} />}</ConsoleGate> }
function FormPreviewRoute() {
  const { formId } = useParams()
  // preview the form's current draft (or latest published) — resolve versionId from the form, else pass formId through.
  return <ConsoleGate>{() => <FormPreviewResolver formId={formId!} />}</ConsoleGate>
}
```
`FormPreviewPage` takes a `versionId`; add a tiny `FormPreviewResolver` that `useQuery`s `getForm(formId)` and renders `<FormPreviewPage versionId={form.current_draft_version_id ?? form.published_version_ids.at(-1)!} />` (Spinner while loading). Define it in `App.tsx` or a small wrapper file.

- [ ] **Step 2: Add a11y cases** — in `a11y.test.tsx`, add `it(...)` cases via `withProviders`: `FormRenderer` (with a 2-field fixture), `FormPreviewPage` (versionId, fetch unmocked → spinner shell), `FormBuilderPage` (formId), `FormVersionsPage` (formId). If axe flags a real violation, STOP and fix the markup. Run `npx vitest run src/tests/a11y.test.tsx`.

- [ ] **Step 3: Create the e2e spec** — `tests/e2e/fe-ui-slice2b.spec.ts`: drive `build → publish → bind`:
```ts
import { test, expect } from '@playwright/test'
test('operator builds a form, publishes it, and binds it to a cohort', async ({ page }) => {
  await page.goto('/forms/frm_draft/edit')
  await expect(page.getByRole('heading', { name: /form builder|new form/i })).toBeVisible({ timeout: 15000 })
  await page.getByRole('button', { name: /add short text/i }).click()
  await page.getByRole('button', { name: /^publish$/i }).click()
  await expect(page.getByText(/published/i)).toBeVisible()
  // bind from cohort detail
  await page.goto('/cohorts/coh_1')
  await expect(page.getByRole('heading')).toBeVisible()
  // open the binding picker and choose a published version
  await page.getByRole('button', { name: /bind|change form/i }).click()
  await page.getByRole('button', { name: /application form v1|bind/i }).first().click()
  await expect(page.getByText(/application form/i)).toBeVisible()
})
```

- [ ] **Step 4: Register the spec** — add `'**/fe-ui-slice2b.spec.ts'` to BOTH the `chromium` project's `testIgnore` and the `msw-dev` project's `testMatch` in `playwright.config.ts`.

- [ ] **Step 5: Full gate sweep** — `npx vitest run && npm run lint && npx tsc -b && npm run build && npm run build-storybook && npm run test:e2e -- fe-ui-slice2b.spec.ts`. All must pass. (`lint` is included because it is a CI gate.)

- [ ] **Step 6: Commit** — `git add src/app/App.tsx src/tests/a11y.test.tsx tests/e2e/fe-ui-slice2b.spec.ts playwright.config.ts && git commit -m "feat(fe): Slice 2b — routes, a11y cases, e2e build/publish/bind flow" -m "Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"`

---

## Self-Review

**Spec coverage (against §2b):**
- Form builder (palette/canvas/inspector, validation) → Tasks 4, 5. ✅
- Conditional-logic editor (cycle-prevented, earlier-fields-only) → Task 6. ✅
- `FormRenderer` + live visibility + preview (LTR/RTL) → Tasks 2, 3. ✅
- Form binding (published version → cohort) → Task 9. ✅
- Generic `VersionHistoryList` + `VersionCompare` (reused by 2c) → Task 8. ✅
- Publish → immutable version; edit forks a new draft → Task 7. ✅
- forms.ts schema/api/MSW → Task 1. ✅
- Routes/a11y/e2e + lint gate → Task 10. ✅

**Deliberate deviations (documented):** (1) **No new shadcn primitives** — native controls + existing `ui/` (matches `ApplyField`); (2) snake_case schema fields (matches `apply.ts`/`cohorts.ts`); (3) `forms.ts` `FormField` extends `apply.ts`'s `formFieldSchema` (DRY, structurally compatible with `ApplyField`); (4) cycle prevention is structural (only earlier fields are selectable triggers) rather than runtime cycle detection.

**Type consistency:** `FormField`/`FormVersion`/`Form` snake_case used identically across schema, api, MSW, builder, renderer, versions, binding. `isFieldVisible(field, answers)` signature matches between engine, renderer, and preview. `VisibilityRule.match: 'all'|'any'` + operators `equals|not_equals|includes|is_empty` consistent across schema, engine, and editor. `saveFormDraft(formId, fields)` / `publishForm(formId)` / `forkFormDraft(formId, fromVersionId)` signatures match between api and builder.

**Placeholder scan:** every code step has complete code except Tasks 8 (FormVersionsPage), 9 (FormBindingPicker/CohortDetail wiring), and 10 (a11y/e2e), which give exact interfaces + behavior + the existing pattern to follow rather than a full reproduction — these are integration-of-known-pieces steps, not novel logic; the novel logic (engine, renderer, builder, inspector, conditional editor, compare) is fully coded.
