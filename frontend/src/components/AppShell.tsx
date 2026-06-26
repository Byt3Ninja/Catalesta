import { useState, type ReactNode } from 'react'
import { Menu } from 'lucide-react'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from './ui/sheet'
import { Button } from './ui/button'
import { ThemeToggle } from './ThemeToggle'
import { ContextSelector } from './ContextSelector'
import { useDirection } from '../app/direction-context'

/**
 * Application frame: sticky header (brand + context + theme), a sidebar that holds
 * `rail` content (collapses to a Sheet on mobile), and the page container with an
 * optional `pageHeader` slot. Uses logical spacing so it mirrors under RTL.
 */
export function AppShell({ rail, pageHeader, children }: { rail?: ReactNode; pageHeader?: ReactNode; children: ReactNode }) {
  const [open, setOpen] = useState(false)
  const { dir } = useDirection()
  return (
    <div className="min-h-dvh bg-background text-foreground">
      <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur">
        {rail ? (
          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon" className="md:hidden" aria-label="Open navigation">
                <Menu className="size-4" />
              </Button>
            </SheetTrigger>
            <SheetContent side={dir === 'rtl' ? 'right' : 'left'} className="w-64 p-4">
                <SheetTitle className="sr-only">Navigation</SheetTitle>
                {rail}
              </SheetContent>
          </Sheet>
        ) : null}
        <span className="font-semibold">Catalesta</span>
        <div className="ms-2 flex-1"><ContextSelector /></div>
        <ThemeToggle />
      </header>
      <div className="mx-auto flex w-full max-w-screen-xl gap-6 px-4 py-6">
        {rail ? <aside className="hidden w-56 shrink-0 md:block" aria-label="Sections">{rail}</aside> : null}
        <main className="min-w-0 flex-1">
          {pageHeader ? <div className="mb-6">{pageHeader}</div> : null}
          {children}
        </main>
      </div>
    </div>
  )
}
