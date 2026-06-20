/**
 * Loading primitives. Spinner announces politely; Skeleton is decorative
 * (aria-hidden + aria-busy) and its shimmer respects prefers-reduced-motion
 * (handled in tokens.css). A non-animated text fallback is the Spinner.
 */
export function Spinner({ label = 'Loading…' }: { label?: string }) {
  return (
    <span className="ds-spinner" role="status" aria-live="polite">
      {label}
    </span>
  )
}

export function Skeleton({ lines = 1 }: { lines?: number }) {
  return (
    <div className="ds-skeleton" aria-busy="true" aria-hidden="true">
      {Array.from({ length: lines }).map((_, i) => (
        <span key={i} className="ds-skeleton__line" />
      ))}
    </div>
  )
}
