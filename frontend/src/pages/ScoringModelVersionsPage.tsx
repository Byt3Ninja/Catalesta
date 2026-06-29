import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { StateBlock } from '../components/StateBlock'
import { VersionHistoryList } from '../components/VersionHistoryList'
import { VersionCompare } from '../components/VersionCompare'
import { getScoringModel, listScoringModelVersions, getScoringModelVersion, forkScoringModelDraft } from '../api/assessments'
import type { ScoringCriterion } from '../schemas/assessments'

/** Ordered, human-readable criterion descriptions — the comparable unit for the diff. */
function criteriaToLines(criteria: ScoringCriterion[]): string[] {
  return criteria.map((c, i) => `${i + 1}. ${c.label} — max ${c.max_points}`)
}

export function ScoringModelVersionsPage({ modelId }: { modelId: string }) {
  const [selected, setSelected] = useState<string[]>([])

  const modelQuery = useQuery({ queryKey: ['scoring-model', modelId], queryFn: () => getScoringModel(modelId), retry: false })
  const versionsQuery = useQuery({ queryKey: ['scoring-model-versions', modelId], queryFn: () => listScoringModelVersions(modelId), retry: false })

  const leftId = selected[0] ?? null
  const rightId = selected[1] ?? null
  const leftQuery = useQuery({ queryKey: ['scoring-model-version', leftId], queryFn: () => getScoringModelVersion(leftId!), enabled: selected.length === 2 && !!leftId, retry: false })
  const rightQuery = useQuery({ queryKey: ['scoring-model-version', rightId], queryFn: () => getScoringModelVersion(rightId!), enabled: selected.length === 2 && !!rightId, retry: false })

  const latestPublishedId = modelQuery.data?.published_version_ids.at(-1) ?? null
  const forkMutation = useMutation({
    mutationFn: () => forkScoringModelDraft(modelId, latestPublishedId!),
    onSuccess: () => {
      window.location.assign(`/programs/${modelQuery.data!.program_id}/scoring/${modelId}/edit`)
    },
  })

  function handleSelect(id: string) {
    setSelected((prev) => {
      if (prev.includes(id)) return prev.filter((s) => s !== id)
      if (prev.length >= 2) return [prev[1], id]
      return [...prev, id]
    })
  }

  const versions = versionsQuery.data ?? []
  const versionItems = versions.map((v) => ({ id: v.version_id, version: v.version, status: v.status, published_at: v.published_at }))
  const showCompare = selected.length === 2 && leftQuery.data && rightQuery.data

  return (
    <AppShell>
      <div className="grid gap-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-xl font-semibold">Version history{modelQuery.data ? ` — ${modelQuery.data.name}` : ''}</h1>
          {latestPublishedId && (
            <Button
              variant="secondary"
              loading={forkMutation.isPending}
              disabled={forkMutation.isPending || modelQuery.isRefetching}
              onClick={() => forkMutation.mutate()}
            >
              Edit (new draft)
            </Button>
          )}
        </div>

        {forkMutation.isError && <StateBlock variant="error" message="Could not create a new draft. Try again." />}

        {versionsQuery.isLoading && <p>Loading versions…</p>}
        {versionsQuery.isError && <StateBlock variant="error" message="Could not load versions. Please try again." />}

        {!versionsQuery.isLoading && !versionsQuery.isError && (
          <VersionHistoryList
            versions={versionItems}
            selectedIds={selected.length === 2 ? [selected[0], selected[1]] : null}
            onSelect={handleSelect}
          />
        )}

        {selected.length === 2 && (leftQuery.isLoading || rightQuery.isLoading) && <p>Loading comparison…</p>}

        {showCompare && (
          <VersionCompare
            left={{ label: `Version ${leftQuery.data!.version}`, lines: criteriaToLines(leftQuery.data!.criteria) }}
            right={{ label: `Version ${rightQuery.data!.version}`, lines: criteriaToLines(rightQuery.data!.criteria) }}
          />
        )}
      </div>
    </AppShell>
  )
}
