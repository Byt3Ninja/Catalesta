import type { ButtonHTMLAttributes, ReactNode } from 'react'

type Variant = 'primary' | 'secondary'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  loading?: boolean
  children: ReactNode
}

/**
 * Primary button — one per screen by convention. While loading it is disabled and
 * marked aria-busy so it never double-fires (pairs with idempotent submit).
 */
export function Button({ variant = 'primary', loading = false, disabled, children, ...rest }: ButtonProps) {
  return (
    <button
      type="button"
      className={`ds-btn ds-btn--${variant} ds-focusable`}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      {...rest}
    >
      {loading ? '…' : children}
    </button>
  )
}
