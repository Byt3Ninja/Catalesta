import { AppShell } from '../components/AppShell'
import type { Organization } from '../schemas/organizations'

/**
 * Minimal operator Home (Story 1.1, AC-1). Lands here once the user has an org —
 * proves the no-org gate passed. This is a console surface, so it renders inside
 * AppShell (unreachable without an org).
 *
 * Story 1.5: build the real Home content here — cohort list, next-action prompt,
 * and day-one empty states. Deliberately deferred; do not build it in this story.
 */
export function HomePage({ organization }: { organization: Organization }) {
  return (
    <AppShell rail={<nav aria-label="Sections">Home</nav>}>
      <section aria-labelledby="home-heading">
        <h1 id="home-heading">{organization.name}</h1>
        <p>Welcome to your operator console.</p>
      </section>
    </AppShell>
  )
}
