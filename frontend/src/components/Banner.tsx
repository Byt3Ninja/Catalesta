import type { ReactNode } from 'react'
import { Alert, AlertDescription } from './ui/alert'

type BannerVariant = 'info' | 'error' | 'success'
const VARIANT_MAP = { info: 'default', error: 'destructive', success: 'default' } as const

/** Inline alert. Status conveyed by text + role (not colour alone); errors use role="alert". */
export function Banner({ variant = 'info', children }: { variant?: BannerVariant; children: ReactNode }) {
  return (
    <Alert
      variant={VARIANT_MAP[variant]}
      role={variant === 'error' ? 'alert' : 'status'}
      data-variant={variant}
      className={variant === 'success' ? 'border-green-600 text-green-700 dark:text-green-400' : undefined}
    >
      <AlertDescription>{children}</AlertDescription>
    </Alert>
  )
}
