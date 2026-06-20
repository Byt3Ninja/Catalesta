import type { ReactNode } from 'react'

type StateVariant = 'empty' | 'error' | 'offline'

/**
 * Empty / error / offline state — one component, three messages. Every list and
 * first-use screen explains the state + the single next action (a11y: text, not
 * color-alone).
 */
export function StateBlock({
  variant,
  message,
  action,
}: {
  variant: StateVariant
  message: string
  action?: ReactNode
}) {
  return (
    <div className="ds-state" role={variant === 'error' ? 'alert' : 'status'} data-variant={variant}>
      <p>{message}</p>
      {action}
    </div>
  )
}
