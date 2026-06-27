import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { StateBlock } from '../components/StateBlock'
import { Spinner } from '../components/Loading'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { getConsents, setConsent } from '../api/consent'
import { CONSENT_CATEGORY_LABEL, type ConsentCategory } from '../schemas/consent'

/**
 * Consent management: grant/revoke per data category. A change invalidates the
 * profile query too, so the consent-gated ProfilePage re-reads through the seam.
 */
export function ConsentManagementPage() {
  const queryClient = useQueryClient()
  const query = useQuery({ queryKey: ['consents'], queryFn: getConsents, retry: false })

  const mutation = useMutation({
    mutationFn: ({ category, granted }: { category: ConsentCategory; granted: boolean }) => setConsent(category, granted),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['consents'] }),
        queryClient.invalidateQueries({ queryKey: ['profile'] }),
      ])
    },
  })

  const entries = query.data ?? []

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="consent-heading" className="grid max-w-xl gap-6">
        <div>
          <h1 id="consent-heading" className="text-2xl font-semibold">Consent</h1>
          <p className="text-muted-foreground">Choose what this workspace may access. <Link href="/profile">View profile</Link></p>
        </div>

        {query.isLoading ? (
          <Spinner label="Loading consent settings…" />
        ) : query.isError ? (
          <StateBlock variant="error" message="We could not load your consent settings." action={<Button onClick={() => query.refetch()}>Try again</Button>} />
        ) : (
          <ul className="grid gap-3">
            {entries.map((e) => (
              <li key={e.category} className="flex items-center justify-between gap-3 rounded-md border border-border p-3">
                <label htmlFor={`consent-${e.category}`} className="font-medium">{CONSENT_CATEGORY_LABEL[e.category]}</label>
                <input
                  id={`consent-${e.category}`}
                  type="checkbox"
                  checked={e.granted}
                  disabled={mutation.isPending}
                  onChange={(ev) => mutation.mutate({ category: e.category, granted: ev.target.checked })}
                />
              </li>
            ))}
          </ul>
        )}
      </section>
    </AppShell>
  )
}
