import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { cn } from '../lib/utils'
import { Banner } from './Banner'
import { Button } from './Button'
import { listScoringModels, listScoringModelVersions } from '../api/assessments'
import { bindCohortStageScoringModel } from '../api/cohorts'
import type { Cohort } from '../schemas/cohorts'

interface PublishedVersionOption {
  versionId: string
  label: string
}

interface ScoringModelBindingPickerProps {
  cohortId: string
  /** The program owning the scoring models to offer. */
  programId: string
  /** The stage this picker binds a scoring model to. */
  stageId: string
  /** The currently bound scoring-model version id for this stage, or null when unbound. */
  boundVersionId: string | null | undefined
  onBound: (cohort: Cohort) => void
}

/**
 * Displays a select of PUBLISHED scoring-model versions for a program and binds
 * one to a specific stage on a cohort. Cloned from StagePipelineBindingPicker —
 * only published versions are offered; replacing an existing binding requires a
 * confirm step.
 */
export function ScoringModelBindingPicker({ cohortId, programId, stageId, boundVersionId, onBound }: ScoringModelBindingPickerProps) {
  const [selectedVersionId, setSelectedVersionId] = useState('')
  const [confirming, setConfirming] = useState(false)
  const [bindError, setBindError] = useState<string | null>(null)

  const modelsQuery = useQuery({
    queryKey: ['scoring-models', programId],
    queryFn: () => listScoringModels(programId),
    retry: false,
  })

  const modelIds = modelsQuery.data?.map((m) => m.model_id) ?? []
  const versionsQuery = useQuery({
    queryKey: ['scoring-model-versions-all', modelIds],
    queryFn: async (): Promise<PublishedVersionOption[]> => {
      if (!modelsQuery.data) return []
      const options: PublishedVersionOption[] = []
      for (const model of modelsQuery.data) {
        const versions = await listScoringModelVersions(model.model_id)
        for (const v of versions) {
          // PUBLISHED versions only — never offer drafts
          if (v.status === 'published') options.push({ versionId: v.version_id, label: `${model.name} v${v.version}` })
        }
      }
      return options
    },
    enabled: !!modelsQuery.data,
    retry: false,
  })

  const publishedOptions = versionsQuery.data ?? []
  const boundLabel = boundVersionId
    ? (publishedOptions.find((o) => o.versionId === boundVersionId)?.label ?? boundVersionId)
    : null

  const bindMutation = useMutation({
    mutationFn: () => bindCohortStageScoringModel(cohortId, stageId, selectedVersionId),
    onSuccess: (cohort) => {
      setConfirming(false)
      setBindError(null)
      onBound(cohort)
    },
    onError: (err) => {
      setConfirming(false)
      setBindError(err instanceof Error ? err.message : 'Could not bind scoring model.')
    },
  })

  const isLoading = modelsQuery.isLoading || versionsQuery.isLoading
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
        <p className="text-sm text-muted-foreground" data-testid="bound-scoring-label">
          Currently bound: <span className="font-medium text-foreground">{boundLabel}</span>
        </p>
      )}

      {isLoading && <p className="text-sm text-muted-foreground">Loading scoring models…</p>}

      {!isLoading && (
        <div className="flex flex-wrap items-center gap-2">
          <label htmlFor="scoring-binding-select" className="text-sm font-medium">
            Published version
          </label>
          <select
            id="scoring-binding-select"
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
