import { Moon, Sun } from 'lucide-react'
import { useDirection } from '../app/direction-context'
import { Button } from './ui/button'

/** Toggles light/dark; persisted by DirectionProvider. */
export function ThemeToggle() {
  const { theme, setTheme } = useDirection()
  return (
    <Button
      variant="ghost"
      size="icon"
      aria-label={theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'}
      onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
    >
      {theme === 'dark' ? <Sun className="size-4" /> : <Moon className="size-4" />}
    </Button>
  )
}
