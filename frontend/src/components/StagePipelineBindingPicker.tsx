import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { cn } from '../lib/utils'
import { Banner } from './Banner'
import { Button } from './Button'
import { listStagePipelines, listStagePipelineVersions } from '../api/stages'
import { bindCohortStagePipeline } from '../api/cohorts'
import type { Cohort } from '../schemas/cohorts'

interface PublishedVersionOption {
  versionId: string
  label: string
}

interface StagePipelineBindingPickerProps {
  cohortId: string
  /** The program owning the pipelines to offer. */
  programId: string
  /** The currently bound stage-pipeline version id, or null when unbound. */
  boundVersionId: string | null | undefined
  onBound: (cohort: Cohort) => void
}

/**
 * Displays a select of PUBLISHED stage-pipeline versions for a program and binds
 * one to a cohort. Cloned from FormBindingPicker — only published versions are
 * offered; replacing an existing binding requires a confirm step.
 */
export function StagePipelineBindingPicker({ cohortId, programId, boundVersionId, onBound }: StagePipelineBindingPickerProps) {
  const [selectedVersionId, setSelectedVersionId] = useState('')
  const [confirming, setConfirming] = useState(false)
  const [bindError, setBindError] = useState<string | null>(null)

  const pipelinesQuery = useQuery({
    queryKey: ['stage-pipelines', programId],
    queryFn: () => listStagePipelines(programId),
    retry: false,
  })

  const pipelineIds = pipelinesQuery.data?.map((p) => p.pipeline_id) ?? []
  const versionsQuery = useQuery({
    queryKey: ['stage-pipeline-versions-all', pipelineIds],
    queryFn: async (): Promise<PublishedVersionOption[]> => {
      if (!pipelinesQuery.data) return []
      const options: PublishedVersionOption[] = []
      for (const pipeline of pipelinesQuery.data) {
        const versions = await listStagePipelineVersions(pipeline.pipeline_id)
        for (const v of versions) {
          // PUBLISHED versions only — never offer drafts
          if (v.status === 'published') options.push({ versionId: v.version_id, label: `${pipeline.name} v${v.version}` })
        }
      }
      return options
    },
    enabled: !!pipelinesQuery.data,
    retry: false,
  })

  const publishedOptions = versionsQuery.data ?? []
  const boundLabel = boundVersionId
    ? (publishedOptions.find((o) => o.versionId === boundVersionId)?.label ?? boundVersionId)
    : null

  const bindMutation = useMutation({
    mutationFn: () => bindCohortStagePipeline(cohortId, selectedVersionId),
    onSuccess: (cohort) => {
      setConfirming(false)
      setBindError(null)
      onBound(cohort)
    },
    onError: (err) => {
      setConfirming(false)
      setBindError(err instanceof Error ? err.message : 'Could not bind stage pipeline.')
    },
  })

  const isLoading = pipelinesQuery.isLoading || versionsQuery.isLoading
  const isReplacing = !!boundVersionId && selectedVersionId !== '' && selectedVersionId !== boundVersionId

  function handleBind() {
    if (!selectedVersionId) return
    if (isReplacing && !confirming) {
      setConfirming(true)
      return
    }
    bindMutation.mutate()
  }

  return (
    <div className={cn('grid gap-3')}>
      {boundLabel && (
        <p className="text-sm text-muted-foreground" data-testid="bound-stage-label">
          Currently bound: <span className="font-medium text-foreground">{boundLabel}</span>
        </p>
      )}

      {isLoading && <p className="text-sm text-muted-foreground">Loading stage pipelines…</p>}

      {!isLoading && (
        <div className="flex flex-wrap items-center gap-2">
          <label htmlFor="stage-binding-select" className="text-sm font-medium">
            Published version
          </label>
          <select
            id="stage-binding-select"
            className={cn(
              'rounded-md border border-input bg-background px-3 py-1.5 text-sm shadow-sm',
              'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
            )}
            value={selectedVersionId}
            onChange={(e) => {
              setSelectedVersionId(e.target.value)
              setConfirming(false)
              setBindError(null)
            }}
          >
            <option value="">Select a published version…</option>
            {publishedOptions.map((opt) => (
              <option key={opt.versionId} value={opt.versionId}>{opt.label}</option>
            ))}
          </select>

          <Button
            type="button"
            variant="secondary"
            disabled={!selectedVersionId || bindMutation.isPending}
            loading={bindMutation.isPending}
            onClick={handleBind}
          >
            {confirming ? 'Confirm replace' : 'Bind'}
          </Button>

          {confirming && (
            <Button type="button" variant="secondary" onClick={() => setConfirming(false)}>Cancel</Button>
          )}
        </div>
      )}

      {confirming && (
        <Banner variant="info">
          Replacing the existing binding will affect participants on this cohort. Confirm to proceed.
        </Banner>
      )}

      {bindError && <Banner variant="error">{bindError}</Banner>}
    </div>
  )
}
