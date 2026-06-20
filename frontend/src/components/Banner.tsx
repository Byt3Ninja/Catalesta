import type { ReactNode } from 'react'

type BannerVariant = 'info' | 'error' | 'success'

/**
 * Inline alert. Status is conveyed by text + role (not color alone); errors use
 * role="alert" so they're announced.
 */
export function Banner({ variant = 'info', children }: { variant?: BannerVariant; children: ReactNode }) {
  return (
    <div className={`ds-banner ds-banner--${variant}`} role={variant === 'error' ? 'alert' : 'status'} data-variant={variant}>
      {children}
    </div>
  )
}
