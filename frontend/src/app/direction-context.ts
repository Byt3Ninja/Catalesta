import { createContext, useContext } from 'react'

export type Direction = 'ltr' | 'rtl'
export type Theme = 'light' | 'dark'

export interface DirectionContextValue {
  dir: Direction
  theme: Theme
  setDir: (dir: Direction) => void
  setTheme: (theme: Theme) => void
}

export const DirectionContext = createContext<DirectionContextValue | null>(null)

export function useDirection(): DirectionContextValue {
  const ctx = useContext(DirectionContext)
  if (ctx === null) {
    throw new Error('useDirection must be used within a DirectionProvider')
  }
  return ctx
}
