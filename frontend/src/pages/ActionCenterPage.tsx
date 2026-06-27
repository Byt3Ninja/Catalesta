import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { ActionCard } from '../components/ActionCard'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { Button } from '../components/Button'
import { useActiveRole } from '../app/active-role'
import { getActionCenter } from '../api/actionCenter'
import { ACTION_SECTIONS, SECTION_LABEL } from '../schemas/actionCenter'
import { useConsent } from '../app/consent-context'
import { profileDisplayName } from '../schemas/profile'
import type { Organization } from '../schemas/organizations'

/** Role-scoped Action Center home. The single home surface for every role. */
export function ActionCenterPage({ organization }: { organization: Organization }) {
  const role = useActiveRole()
  const consent = useConsent()
  const query = useQuery({ queryKey: ['action-center', role], queryFn: () => getActionCenter(role), retry: false })
  const items = query.data ?? []
  const name = consent.status === 'ready' && consent.profile ? profileDisplayName(consent.profile) : undefined

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="home-heading" className="grid gap-6">
        <div>
          <h1 id="home-heading" className="text-2xl font-semibold"><bdi>{organization.name}</bdi></h1>
          <p className="text-muted-foreground">
            {name ? <>Welcome back, <bdi>{name}</bdi>.</> : 'Your action center.'}
          </p>
        </div>

        {query.isLoading ? (
          <Spinner label="Loading your action center…" />
        ) : query.isError ? (
          <StateBlock variant="error" message="We could not load your action center." action={<Button onClick={() => query.refetch()}>Try again</Button>} />
        ) : items.length === 0 ? (
          <StateBlock variant="empty" message="Nothing needs your attention right now." />
        ) : (
          ACTION_SECTIONS.map((section) => {
            const sectionItems = items.filter((i) => i.section === section)
            if (sectionItems.length === 0) return null
            return (
              <div key={section} className="grid gap-2">
                <h2 className="text-lg font-medium">{SECTION_LABEL[section]}</h2>
                <div className="grid gap-2">
                  {sectionItems.map((item) => <ActionCard key={item.id} item={item} />)}
                </div>
              </div>
            )
          })
        )}
      </section>
    </AppShell>
  )
}
