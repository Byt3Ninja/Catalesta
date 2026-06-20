import { useId } from 'react'
import { Field } from '../components/Field'
import type { FormField } from '../schemas/apply'

interface ApplyFieldProps {
  field: FormField
  value: unknown
  onChange: (value: unknown) => void
  onFiles: (files: File[]) => void
}

/**
 * Renders ONE apply-form field with an accessible native control. Free-text
 * inputs get dir="auto" so an Arabic answer renders RTL even in an LTR UI.
 * Reuses the Field primitive for short_text/number; everything else is a
 * label-wired native control.
 */
export function ApplyField({ field, value, onChange, onFiles }: ApplyFieldProps) {
  const id = useId()
  const helpId = field.help ? `${id}-help` : undefined
  const required = field.required ?? false

  switch (field.type) {
    case 'short_text':
      return (
        <Field
          label={labelText(field)}
          help={field.help}
          required={required}
          value={asString(value)}
          onChange={(e) => onChange(e.target.value)}
        />
      )

    case 'number':
      return (
        <Field
          label={labelText(field)}
          help={field.help}
          type="number"
          required={required}
          value={asString(value)}
          onChange={(e) => onChange(e.target.value)}
        />
      )

    case 'long_text':
      return (
        <div className="ds-field">
          <label className="ds-field__label" htmlFor={id}>
            {labelText(field)}
          </label>
          <textarea
            id={id}
            className="ds-input ds-focusable"
            dir="auto"
            rows={5}
            required={required}
            aria-describedby={helpId}
            value={asString(value)}
            onChange={(e) => onChange(e.target.value)}
          />
          {field.help ? (
            <span id={helpId} className="ds-field__help">
              {field.help}
            </span>
          ) : null}
        </div>
      )

    case 'single_select':
      return (
        <fieldset className="ds-field">
          <legend className="ds-field__label">{labelText(field)}</legend>
          {(field.options ?? []).map((opt) => (
            <label key={opt} className="ds-choice">
              <input
                type="radio"
                name={id}
                value={opt}
                checked={asString(value) === opt}
                required={required}
                onChange={() => onChange(opt)}
              />
              <span dir="auto">{opt}</span>
            </label>
          ))}
          {field.help ? <span className="ds-field__help">{field.help}</span> : null}
        </fieldset>
      )

    case 'multi_select': {
      const selected = asStringArray(value)
      return (
        <fieldset className="ds-field">
          <legend className="ds-field__label">{labelText(field)}</legend>
          {(field.options ?? []).map((opt) => (
            <label key={opt} className="ds-choice">
              <input
                type="checkbox"
                value={opt}
                checked={selected.includes(opt)}
                onChange={(e) => {
                  const next = e.target.checked
                    ? [...selected, opt]
                    : selected.filter((v) => v !== opt)
                  onChange(next)
                }}
              />
              <span dir="auto">{opt}</span>
            </label>
          ))}
          {field.help ? <span className="ds-field__help">{field.help}</span> : null}
        </fieldset>
      )
    }

    case 'date':
      return (
        <div className="ds-field">
          <label className="ds-field__label" htmlFor={id}>
            {labelText(field)}
          </label>
          <input
            id={id}
            type="date"
            className="ds-input ds-focusable"
            required={required}
            aria-describedby={helpId}
            value={asString(value)}
            onChange={(e) => onChange(e.target.value)}
          />
          {field.help ? (
            <span id={helpId} className="ds-field__help">
              {field.help}
            </span>
          ) : null}
        </div>
      )

    case 'file_upload':
      return (
        <div className="ds-field">
          <label className="ds-field__label" htmlFor={id}>
            {labelText(field)}
          </label>
          <input
            id={id}
            type="file"
            className="ds-input ds-focusable"
            required={required}
            aria-describedby={helpId}
            onChange={(e) => {
              const list = e.target.files ? Array.from(e.target.files) : []
              onFiles(list)
              onChange(list.map((f) => f.name))
            }}
          />
          {field.help ? (
            <span id={helpId} className="ds-field__help">
              {field.help}
            </span>
          ) : null}
        </div>
      )

    case 'consent':
      return (
        <div className="ds-field">
          <label className="ds-choice" htmlFor={id}>
            <input
              id={id}
              type="checkbox"
              required
              checked={value === true}
              onChange={(e) => onChange(e.target.checked)}
            />
            <span dir="auto">{labelText(field)}</span>
          </label>
          {field.help ? <span className="ds-field__help">{field.help}</span> : null}
        </div>
      )

    default:
      return null
  }
}

function labelText(field: FormField): string {
  return field.required ? `${field.label} *` : field.label
}

function asString(value: unknown): string {
  if (value == null) return ''
  if (typeof value === 'string') return value
  if (typeof value === 'number') return String(value)
  return ''
}

function asStringArray(value: unknown): string[] {
  return Array.isArray(value) ? value.filter((v): v is string => typeof v === 'string') : []
}
