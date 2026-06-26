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
      {loading ? (
        <>
          <Loader2 className="size-4 animate-spin" aria-hidden />
          <span className="sr-only">{children}</span>
        </>
      ) : (
        children
      )}
    </UiButton>
  )
}
