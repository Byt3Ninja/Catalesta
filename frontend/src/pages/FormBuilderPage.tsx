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
  const [seededId, setSeededId] = useState<string | null>(null)
  // dirtyRef: true only after a user edit — never set during seeding, never reset.
  // Autosave checks this so it never fires on initial load or re-seed, regardless of timing.
  // On version switch the user must make an edit before autosave triggers on the new version.
  const dirtyRef = useRef(false)

  // Render-time reset keyed on version id (React "adjust state when source changes" pattern).
  // Calling setState during render is explicitly allowed when guarded by a condition —
  // React re-renders immediately without committing the intermediate state.
  // This avoids both useEffect+setState (react-hooks/set-state-in-effect) and useRef-in-render
  // (react-hooks/refs) lint violations. Refs are NOT touched here for the same reason.
  if (draftQuery.data && draftQuery.data.id !== seededId) {
    setSeededId(draftQuery.data.id)
    setFields(draftQuery.data.fields)
  }

  // All user field edits go through here so autosave knows the draft is dirty.
  // Tasks 5-7 inspector edits must use this too.
  function updateFields(next: FormField[]) { dirtyRef.current = true; setFields(next) }

  // debounced autosave — only fires when dirtyRef is true (set by user edits via updateFields)
  // and only after seededId is populated (the draft has been fetched and seeded into state).
  useEffect(() => {
    if (!draftId || seededId === null || !dirtyRef.current) return
    const t = setTimeout(() => { void saveFormDraft(formId, fields).catch(() => {}) }, 400)
    return () => clearTimeout(t)
  }, [fields, formId, draftId, seededId])

  function addField(type: FormField['type']) { const f = newField(type); updateFields([...fields, f]); setSelectedId(f.id) }
  function move(idx: number, dir: -1 | 1) {
    updateFields((() => {
      const next = [...fields]; const j = idx + dir
      if (j < 0 || j >= next.length) return fields
      ;[next[idx], next[j]] = [next[j], next[idx]]; return next
    })())
  }
  function remove(id: string) { updateFields(fields.filter((f) => f.id !== id)); if (selectedId === id) setSelectedId(null) }

  const readOnly = !draftId // no current draft → published-only, read-only (Task 7 adds fork)

  return (
    <AppShell
      rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><Link href="/programs">Programs</Link></nav>}
    >
      <div className="grid gap-6">
        {formQuery.isLoading ? (
          <Spinner label="Loading form…" />
        ) : formQuery.isError ? (
          <StateBlock variant="error" message="Could not load this form." />
        ) : (
          <>
            {/* heading rendered only after data loads so findByRole waits for palette too */}
            <h1 id="builder-heading" className="text-2xl font-semibold">
              Form builder{formQuery.data ? ` — ${formQuery.data.name}` : ''}
            </h1>
            <section aria-labelledby="builder-heading" className="grid gap-4 lg:grid-cols-[200px_1fr_320px]">
              {/* palette */}
              <div aria-label="Field palette" className="grid h-fit gap-2 rounded-lg border border-border p-3">
                <h2 className="text-sm font-medium text-muted-foreground">Add field</h2>
                {fieldTypeEnum.options.map((t) => (
                  <Button key={t} variant="secondary" disabled={readOnly} onClick={() => addField(t as FormField['type'])}>
                    Add {TYPE_LABEL[t] ?? t}
                  </Button>
                ))}
              </div>
              {/* canvas — data-version-id reflects the seeded draft version (used by tests to
                  wait for seeding before interacting, preventing autosave timing races) */}
              <div className="rounded-lg border border-border p-3" data-version-id={seededId ?? ''}>
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
            </section>
          </>
        )}
      </div>
    </AppShell>
  )
}
