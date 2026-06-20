import type { ReactNode } from 'react'

/** Vertical field-group container with consistent spacing for forms. */
export function FormLayout({ children }: { children: ReactNode }) {
  return <div className="ds-form">{children}</div>
}
