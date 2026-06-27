import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { StateBlock } from '../components/StateBlock'
import { Spinner } from '../components/Loading'
import { Link } from '../components/Link'
import { useConsent } from '../app/consent-context'
import { profileDisplayName } from '../schemas/profile'

// Known profile fields we render when present (tolerant: profile is a string→unknown map).
const FIELDS: { key: string; label: string }[] = [
  { key: 'email', label: 'Email' },
  { key: 'organization', label: 'Organization' },
  { key: 'title', label: 'Title' },
  { key: 'location', label: 'Location' },
]

/**
 * Consent-aware profile view (Story 1.5). Reads the profile through the existing
 * ConsentProvider seam — never a raw fetch — so a CONSENT_REQUIRED renders a
 * neutral affordance pointing at the consent screen, not an error.
 */
export function ProfilePage() {
  const consent = useConsent()

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="profile-heading" className="grid max-w-xl gap-6">
        <h1 id="profile-heading" className="text-2xl font-semibold">Profile</h1>

        {consent.status === 'loading' ? (
          <Spinner label="Loading your profile…" />
        ) : consent.status === 'consent-required' ? (
          <StateBlock
            variant="empty"
            message="Your profile is managed externally and access requires your consent."
            action={<Link href="/consent">Manage consent</Link>}
          />
        ) : consent.status === 'error' ? (
          <StateBlock variant="error" message="We could not load your profile." />
        ) : (
          <dl className="grid gap-3">
            <div>
              <dt className="text-sm text-muted-foreground">Name</dt>
              <dd className="font-medium"><bdi>{profileDisplayName(consent.profile!) ?? '—'}</bdi></dd>
            </div>
            {FIELDS.map((f) => {
              const value = consent.profile![f.key]
              if (typeof value !== 'string' || value.trim() === '') return null
              return (
                <div key={f.key}>
                  <dt className="text-sm text-muted-foreground">{f.label}</dt>
                  <dd><bdi>{value}</bdi></dd>
                </div>
              )
            })}
          </dl>
        )}
      </section>
    </AppShell>
  )
}
