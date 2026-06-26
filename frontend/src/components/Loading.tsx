import { Loader2 } from 'lucide-react'

/** Polite-announcing spinner. */
export function Spinner({ label = 'Loading…' }: { label?: string }) {
  return (
    <span role="status" aria-live="polite" className="inline-flex items-center gap-2 text-sm text-muted-foreground">
      <Loader2 className="size-4 animate-spin" aria-hidden />
      {label}
    </span>
  )
}

/** Decorative skeleton (aria-hidden + aria-busy). */
export function Skeleton({ lines = 1 }: { lines?: number }) {
  return (
    <div aria-busy="true" aria-hidden="true" className="grid gap-2">
      {Array.from({ length: lines }).map((_, i) => (
        <span key={i} data-testid="skeleton-line" className="h-4 w-full animate-pulse rounded bg-muted" />
      ))}
    </div>
  )
}
