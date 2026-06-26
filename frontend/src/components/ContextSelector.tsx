import { getActiveOrganizationId } from '../api/tenant'

/**
 * Role / org / program / cohort context (docs/ux/navigation.md). Slice 0 shows the
 * active org; role/program/cohort are presentational stubs wired in later slices.
 */
export function ContextSelector() {
  const orgId = getActiveOrganizationId()
  return (
    <div className="flex items-center gap-2 text-sm text-muted-foreground" aria-label="Active context">
      <span className="font-medium text-foreground">{orgId ? 'Acme Incubator' : 'No organization'}</span>
    </div>
  )
}
