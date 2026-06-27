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
