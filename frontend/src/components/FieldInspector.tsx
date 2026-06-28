import { Field } from './Field'
import { Button } from './Button'
import { VisibilityEditor } from './VisibilityEditor'
import type { FormField } from '../schemas/forms'

const SELECT_TYPES = ['single_select', 'multi_select']
const TEXT_TYPES = ['short_text', 'long_text']

export function FieldInspector({
  field,
  onChange,
  priorFields = [],
  readOnly = false,
}: {
  field: FormField
  onChange: (patch: Partial<FormField>) => void
  priorFields?: FormField[]
  readOnly?: boolean
}) {
  const options = field.options ?? []
  const validation = field.validation ?? {}
  function setOption(i: number, v: string) { const next = [...options]; next[i] = v; onChange({ options: next }) }
  function addOption() { onChange({ options: [...options, `Option ${options.length + 1}`] }) }
  function removeOption(i: number) { onChange({ options: options.filter((_, j) => j !== i) }) }
  function setValidation(patch: Partial<FormField['validation']>) { onChange({ validation: { ...validation, ...patch } }) }

  return (
    <div className="grid gap-4 text-sm">
      <Field label="Field label" name="field-label" value={field.label} disabled={readOnly} onChange={(e) => onChange({ label: e.target.value })} />
      <div className="grid gap-1.5">
        <label htmlFor="field-help" className="font-medium">Help text</label>
        <textarea id="field-help" rows={2} className="rounded-md border border-input bg-card p-2" disabled={readOnly} value={field.help ?? ''} onChange={(e) => onChange({ help: e.target.value })} />
      </div>
      <label className="flex items-center gap-2">
        <input type="checkbox" checked={field.required ?? false} disabled={readOnly} onChange={(e) => onChange({ required: e.target.checked })} />
        Required
      </label>

      {SELECT_TYPES.includes(field.type) && (
        <fieldset className="grid gap-2 rounded-md border border-border p-2">
          <legend className="px-1 text-xs text-muted-foreground">Options</legend>
          {options.map((opt, i) => (
            <span key={i} className="flex gap-1">
              <Field label={`Option ${i + 1}`} name={`option-${i}`} value={opt} disabled={readOnly} onChange={(e) => setOption(i, e.target.value)} />
              <Button variant="secondary" aria-label={`Remove #${i + 1}`} disabled={readOnly} onClick={() => removeOption(i)}>✕</Button>
            </span>
          ))}
          <Button variant="secondary" disabled={readOnly} onClick={addOption}>Add option</Button>
          {field.type === 'multi_select' && (
            <span className="flex gap-2">
              <Field label="Min selections" name="min-sel" type="number" min={0} value={validation.min_selections ?? ''} disabled={readOnly} onChange={(e) => setValidation({ min_selections: e.target.value === '' ? undefined : Number(e.target.value) })} />
              <Field label="Max selections" name="max-sel" type="number" min={0} value={validation.max_selections ?? ''} disabled={readOnly} onChange={(e) => setValidation({ max_selections: e.target.value === '' ? undefined : Number(e.target.value) })} />
            </span>
          )}
        </fieldset>
      )}

      {TEXT_TYPES.includes(field.type) && (
        <fieldset className="grid gap-2 rounded-md border border-border p-2">
          <legend className="px-1 text-xs text-muted-foreground">Validation</legend>
          <span className="flex gap-2">
            <Field label="Min length" name="min-len" type="number" min={0} value={validation.min_length ?? ''} disabled={readOnly} onChange={(e) => setValidation({ min_length: e.target.value === '' ? undefined : Number(e.target.value) })} />
            <Field label="Max length" name="max-len" type="number" min={0} value={validation.max_length ?? ''} disabled={readOnly} onChange={(e) => setValidation({ max_length: e.target.value === '' ? undefined : Number(e.target.value) })} />
          </span>
          <Field label="Pattern (regex)" name="pattern" value={validation.pattern ?? ''} disabled={readOnly} onChange={(e) => setValidation({ pattern: e.target.value || undefined })} />
        </fieldset>
      )}

      <VisibilityEditor field={field} priorFields={priorFields} onChange={(visibility) => onChange({ visibility })} readOnly={readOnly} />
    </div>
  )
}
