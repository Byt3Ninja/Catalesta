import { useId, type InputHTMLAttributes } from 'react'

interface FieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'id'> {
  label: string
  error?: string
  help?: string
}

/**
 * Labelled text input with accessible error association: the error/help text is
 * linked via aria-describedby and aria-invalid (not visual adjacency alone).
 * dir="auto" so an Arabic answer renders RTL even in an LTR UI and vice-versa.
 */
export function Field({ label, error, help, ...input }: FieldProps) {
  const id = useId()
  const describedById = error ? `${id}-error` : help ? `${id}-help` : undefined

  return (
    <div className="ds-field">
      <label className="ds-field__label" htmlFor={id}>
        {label}
      </label>
      <input
        id={id}
        className="ds-input ds-focusable"
        dir="auto"
        aria-invalid={error ? true : undefined}
        aria-describedby={describedById}
        {...input}
      />
      {error ? (
        <span id={`${id}-error`} className="ds-field__help ds-field__help--error">
          {error}
        </span>
      ) : help ? (
        <span id={`${id}-help`} className="ds-field__help">
          {help}
        </span>
      ) : null}
    </div>
  )
}
