import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { StateBlock } from '../components/StateBlock'
import { VersionHistoryList } from '../components/VersionHistoryList'
import { VersionCompare } from '../components/VersionCompare'
import { getStagePipeline, listStagePipelineVersions, getStagePipelineVersion, forkStagePipelineDraft } from '../api/stages'
import type { Stage, StageRule } from '../schemas/stages'

function describeRule(rule: StageRule | null): string | null {
  if (!rule || rule.conditions.length === 0) return null
  return rule.conditions
    .map((c) => (c.operator === 'is_empty' ? `${c.field_id} is empty` : `${c.field_id} ${c.operator} ${c.value ?? ''}`.trim()))
    .join(rule.match === 'all' ? ' and ' : ' or ')
}

/** Ordered, human-readable stage descriptions — the comparable unit for the diff. */
function stagesToLines(stages: Stage[]): string[] {
  return [...stages]
    .sort((a, b) => a.order - b.order)
    .map((s, i) => {
      const extra = [describeRule(s.entry_rule) && `entry: ${describeRule(s.entry_rule)}`, describeRule(s.exit_rule) && `exit: ${describeRule(s.exit_rule)}`]
        .filter(Boolean)
        .join('; ')
      return `${i + 1}. ${s.name} — ${s.type}${extra ? ` (${extra})` : ''}`
    })
}

export function StagePipelineVersionsPage({ pipelineId }: { pipelineId: string }) {
  const [selected, setSelected] = useState<string[]>([])

  const pipelineQuery = useQuery({ queryKey: ['stage-pipeline', pipelineId], queryFn: () => getStagePipeline(pipelineId), retry: false })
  const versionsQuery = useQuery({ queryKey: ['stage-pipeline-versions', pipelineId], queryFn: () => listStagePipelineVersions(pipelineId), retry: false })

  const leftId = selected[0] ?? null
  const rightId = selected[1] ?? null
  const leftQuery = useQuery({ queryKey: ['stage-pipeline-version', leftId], queryFn: () => getStagePipelineVersion(leftId!), enabled: selected.length === 2 && !!leftId, retry: false })
  const rightQuery = useQuery({ queryKey: ['stage-pipeline-version', rightId], queryFn: () => getStagePipelineVersion(rightId!), enabled: selected.length === 2 && !!rightId, retry: false })

  const latestPublishedId = pipelineQuery.data?.published_version_ids.at(-1) ?? null
  const forkMutation = useMutation({
    mutationFn: () => forkStagePipelineDraft(pipelineId, latestPublishedId!),
    onSuccess: () => {
      // route to the builder for the freshly forked draft (Task 10 wires the route)
      window.location.assign(`/programs/${pipelineQuery.data!.program_id}/stages/${pipelineId}/edit`)
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
          <h1 className="text-xl font-semibold">Version history{pipelineQuery.data ? ` — ${pipelineQuery.data.name}` : ''}</h1>
          {latestPublishedId && (
            <Button variant="secondary" loading={forkMutation.isPending} onClick={() => forkMutation.mutate()}>
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
            left={{ label: `Version ${leftQuery.data!.version}`, lines: stagesToLines(leftQuery.data!.stages) }}
            right={{ label: `Version ${rightQuery.data!.version}`, lines: stagesToLines(rightQuery.data!.stages) }}
          />
        )}
      </div>
    </AppShell>
  )
}
