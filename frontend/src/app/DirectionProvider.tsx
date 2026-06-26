import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { DirectionContext, type Direction, type Theme } from './direction-context'

/**
 * Owns LTR<->RTL direction + light/dark theme for the whole app (Story 1.0). Sets
 * `dir`/`lang`/`data-theme` on <html> so layout mirrors via logical properties and
 * tokens switch — feature screens consume this (useDirection), never re-decide RTL.
 */
export function DirectionProvider({
  children,
  initialDir = 'ltr',
  initialTheme = 'light',
}: {
  children: ReactNode
  initialDir?: Direction
  initialTheme?: Theme
}) {
  const [dir, setDir] = useState<Direction>(initialDir)
  const [theme, setTheme] = useState<Theme>(initialTheme)

  useEffect(() => {
    const root = document.documentElement
    root.dir = dir
    root.lang = dir === 'rtl' ? 'ar' : 'en'
    root.dataset.theme = theme // kept for back-compat with legacy tokens.css
    root.classList.toggle('dark', theme === 'dark')
  }, [dir, theme])

  useEffect(() => {
    try {
      localStorage.setItem('catalesta.theme', theme)
    } catch {
      /* storage unavailable (private mode) — ignore */
    }
  }, [theme])

  const value = useMemo(() => ({ dir, theme, setDir, setTheme }), [dir, theme])

  return <DirectionContext.Provider value={value}>{children}</DirectionContext.Provider>
}
