import { useEffect, useState } from 'react'
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

  // Render-time reset keyed on version id (React "adjust state when source changes" pattern).
  // Calling setState during render is explicitly allowed when guarded by a condition —
  // React re-renders immediately without committing the intermediate state.
  // This avoids both useEffect+setState (react-hooks/set-state-in-effect) and useRef-in-render
  // (react-hooks/refs) lint violations.
  if (draftQuery.data && draftQuery.data.id !== seededId) {
    setSeededId(draftQuery.data.id)
    setFields(draftQuery.data.fields)
  }

  // debounced autosave whenever fields change after seeding
  useEffect(() => {
    if (!draftId || seededId === null) return
    const t = setTimeout(() => { void saveFormDraft(formId, fields).catch(() => {}) }, 400)
    return () => clearTimeout(t)
  }, [fields, formId, draftId, seededId])

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
            </section>
          </>
        )}
      </div>
    </AppShell>
  )
}
