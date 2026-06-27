import { useParams } from 'react-router-dom'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'

/** Placeholder for nav destinations whose screens arrive in a later slice. */
export function ComingSoonPage() {
  const { section } = useParams()
  const title = (section ?? 'This screen').replace(/-/g, ' ')
  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="coming-soon-heading" className="grid gap-2">
        <h1 id="coming-soon-heading" className="text-2xl font-semibold capitalize">{title}</h1>
        <p className="text-muted-foreground">
          This screen arrives in a later slice of the UI rebuild. It is a navigable
          placeholder in the current prototype.
        </p>
      </section>
    </AppShell>
  )
}
