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
