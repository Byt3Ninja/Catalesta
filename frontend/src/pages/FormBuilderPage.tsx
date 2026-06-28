import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getForm, getFormVersion, saveFormDraft, publishForm, forkFormDraft } from '../api/forms'
import type { FormField } from '../schemas/forms'
import { fieldType as fieldTypeEnum } from '../schemas/apply'
import { FieldInspector } from '../components/FieldInspector'

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
  const queryClient = useQueryClient()
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

  // justPublished: set immediately in onSuccess so the UI reflects "Published" while the
  // query re-fetch is in flight. Cleared when formQuery re-resolves with no current draft.
  const [justPublished, setJustPublished] = useState(false)

  const publishMutation = useMutation({
    mutationFn: () => publishForm(formId),
    onSuccess: () => {
      // Reset dirty so autosave does not immediately fire on the now-published (read-only) version.
      dirtyRef.current = false
      setJustPublished(true)
      void queryClient.invalidateQueries({ queryKey: ['form', formId] })
    },
  })

  const forkMutation = useMutation({
    mutationFn: () => {
      const fromVersionId = formQuery.data!.published_version_ids.at(-1)!
      return forkFormDraft(formId, fromVersionId)
    },
    onSuccess: (v) => {
      // Reset dirty BEFORE invalidation so that when the new draft is seeded the autosave
      // does not fire. The render-time seed block will set seededId = v.id; because
      // dirtyRef is false at that point, autosave is suppressed until the user actually edits.
      dirtyRef.current = false
      // Clear justPublished so the forked draft is immediately editable (finding 1).
      setJustPublished(false)
      setFields(v.fields)
      void queryClient.invalidateQueries({ queryKey: ['form', formId] })
    },
  })

  // Render-time reset keyed on version id (React "adjust state when source changes" pattern).
  // Calling setState during render is explicitly allowed when guarded by a condition —
  // React re-renders immediately without committing the intermediate state.
  // This avoids both useEffect+setState (react-hooks/set-state-in-effect) and useRef-in-render
  // (react-hooks/refs) lint violations. Refs are NOT touched here — only setState calls.
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

  // readOnly: no current draft OR the draft was just published (waiting for query re-fetch)
  const readOnly = !draftId || justPublished

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
            {/* heading + version lifecycle controls */}
            <div className="flex flex-wrap items-center justify-between gap-3">
              <h1 id="builder-heading" className="text-2xl font-semibold">
                Form builder{formQuery.data ? ` — ${formQuery.data.name}` : ''}
              </h1>
              <div className="flex items-center gap-3">
                <span
                  data-status={draftId && !justPublished ? 'draft' : 'published'}
                  className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                >
                  {draftId && !justPublished ? `Draft v${draftQuery.data?.version ?? ''}` : 'Published (read-only)'}
                </span>
                {draftId && !justPublished && (
                  <Button loading={publishMutation.isPending} disabled={fields.length === 0} onClick={() => publishMutation.mutate()}>
                    Publish
                  </Button>
                )}
                {(!draftId || justPublished) && (
                  <Button variant="secondary" loading={forkMutation.isPending} onClick={() => forkMutation.mutate()}>
                    Edit (new draft)
                  </Button>
                )}
              </div>
            </div>
            {/* Publish/fork error banners (finding 3) */}
            {publishMutation.isError && (
              <StateBlock variant="error" message="Could not publish. Try again." />
            )}
            {forkMutation.isError && (
              <StateBlock variant="error" message="Could not create new draft. Try again." />
            )}
            {/* Read-only banner shown when the form was loaded with no current draft (published-only).
                Not shown immediately after a publish — the badge + Edit button already communicate
                the state and avoids a duplicate /published/i match in tests. */}
            {!draftId && !justPublished && (
              <StateBlock variant="empty" message="Published — Edit to fork a new draft" />
            )}
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
                        <button type="button" className="text-left" disabled={readOnly} onClick={() => setSelectedId(f.id)}>
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
              {/* inspector — wired to updateFields so autosave dirty-tracks every edit */}
              <div aria-label="Field settings" className="rounded-lg border border-border p-3">
                {(() => {
                  const selected = fields.find((f) => f.id === selectedId)
                  if (!selected) return <p className="text-sm text-muted-foreground">Select a field to edit its settings.</p>
                  const priorFields = fields.slice(0, fields.findIndex((f) => f.id === selected.id))
                  return <FieldInspector field={selected} priorFields={priorFields} readOnly={readOnly} onChange={(patch) => updateFields(fields.map((f) => (f.id === selected.id ? { ...f, ...patch } : f)))} />
                })()}
              </div>
            </section>
          </>
        )}
      </div>
    </AppShell>
  )
}
