import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getScoringModelVersion } from '../api/assessments'
import { sumPoints } from '../lib/decimal'
import type { ScoringCriterion } from '../schemas/assessments'

function CriterionCard({ criterion, index }: { criterion: ScoringCriterion; index: number }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center justify-between gap-2">
        <h2 className="font-medium">{index + 1}. {criterion.label}</h2>
        <span data-status="points" className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
          max {criterion.max_points} pts
        </span>
      </div>
      {criterion.descriptors && criterion.descriptors.length > 0 && (
        <ul className="mt-2 grid gap-1 list-disc list-inside text-sm text-muted-foreground">
          {criterion.descriptors.map((d, i) => (
            <li key={i}>{d}</li>
          ))}
        </ul>
      )}
    </div>
  )
}

export function ScoringModelPreviewPage({ versionId }: { versionId: string }) {
  const versionQuery = useQuery({ queryKey: ['scoring-model-version', versionId], queryFn: () => getScoringModelVersion(versionId), retry: false })
  const v = versionQuery.data
  const total = v ? sumPoints(v.criteria.map((c) => c.max_points)) : 0

  return (
    <AppShell
      rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><Link href="/programs">Programs</Link></nav>}
      pageHeader={<h1 id="preview-heading" className="text-2xl font-semibold">Scoring model{v ? ` — v${v.version}` : ''}</h1>}
    >
      <section aria-labelledby="preview-heading" className="grid max-w-2xl gap-4">
        {versionQuery.isLoading ? (
          <Spinner label="Loading scoring model…" />
        ) : versionQuery.isError || !v ? (
          <StateBlock variant="error" message="Could not load this scoring model version." />
        ) : v.criteria.length === 0 ? (
          <StateBlock variant="empty" message="This version has no criteria yet." />
        ) : (
          <>
            {v.criteria.map((criterion, index) => (
              <CriterionCard key={criterion.criterion_id} criterion={criterion} index={index} />
            ))}
            <p className="text-sm font-medium text-foreground">Total possible: {total} pts</p>
          </>
        )}
      </section>
    </AppShell>
  )
}
