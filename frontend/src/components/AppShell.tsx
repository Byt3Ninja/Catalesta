import type { ReactNode } from 'react'

/**
 * Two-zone operator console layout (context rail + work area). Uses logical CSS
 * properties so it mirrors automatically under RTL (DirectionProvider).
 */
export function AppShell({ rail, children }: { rail?: ReactNode; children: ReactNode }) {
  return (
    <div className="ds-shell">
      {rail ? <aside className="ds-shell__rail">{rail}</aside> : null}
      <main className="ds-shell__main">{children}</main>
    </div>
  )
}
