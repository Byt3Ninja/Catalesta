import type { ReactNode } from 'react'
import { Card, CardContent } from './ui/card'

type StateVariant = 'empty' | 'error' | 'offline'

/** Empty / error / offline state — message + single next action (a11y: text, not colour-alone). */
export function StateBlock({ variant, message, action }: { variant: StateVariant; message: string; action?: ReactNode }) {
  return (
    <Card role={variant === 'error' ? 'alert' : 'status'} data-variant={variant} className="border-dashed">
      <CardContent className="flex flex-col items-center gap-3 py-8 text-center">
        <p className="text-sm text-muted-foreground">{message}</p>
        {action}
      </CardContent>
    </Card>
  )
}
