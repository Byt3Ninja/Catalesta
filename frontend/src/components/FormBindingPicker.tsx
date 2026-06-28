import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { cn } from '../lib/utils'
import { Banner } from './Banner'
import { Button } from './Button'
import { listForms, listFormVersions } from '../api/forms'
import { bindCohortForm } from '../api/cohorts'
import type { Cohort } from '../schemas/cohorts'
import type { FormVersion } from '../schemas/forms'

interface PublishedVersionOption {
  versionId: string
  label: string
}

interface FormBindingPickerProps {
  cohortId: string
  /** The currently bound form version id, or null when unbound. */
  boundVersionId: string | null | undefined
  onBound: (cohort: Cohort) => void
}

/**
 * Displays a select of PUBLISHED form versions and allows an operator to bind
 * one to a cohort. Only published versions are offered — draft versions are
 * never bindable. When replacing an existing binding a warning banner appears
 * before the mutation fires.
 */
export function FormBindingPicker({ cohortId, boundVersionId, onBound }: FormBindingPickerProps) {
  const [selectedVersionId, setSelectedVersionId] = useState('')
  const [confirming, setConfirming] = useState(false)
  const [bindError, setBindError] = useState<string | null>(null)

  // Load all forms
  const formsQuery = useQuery({
    queryKey: ['forms'],
    queryFn: listForms,
    retry: false,
  })

  // Load versions for each form and derive a flat list of published options
  const formIds = formsQuery.data?.map((f) => f.id) ?? []
  const versionsQueries = useQuery({
    queryKey: ['form-versions-all', formIds],
    queryFn: async (): Promise<PublishedVersionOption[]> => {
      if (!formsQuery.data) return []
      const allVersions: (FormVersion & { formName: string })[] = []
      for (const form of formsQuery.data) {
        const versions = await listFormVersions(form.id)
        for (const v of versions) {
          allVersions.push({ ...v, formName: form.name })
        }
      }
      // PUBLISHED versions only — never offer drafts
      return allVersions
        .filter((v) => v.status === 'published')
        .map((v) => ({ versionId: v.id, label: `${v.formName} v${v.version}` }))
    },
    enabled: !!formsQuery.data,
    retry: false,
  })

  const publishedOptions = versionsQueries.data ?? []

  // Find the label for the currently bound version (if any)
  const boundLabel = boundVersionId
    ? (publishedOptions.find((o) => o.versionId === boundVersionId)?.label ?? boundVersionId)
    : null

  const bindMutation = useMutation({
    mutationFn: () => bindCohortForm(cohortId, selectedVersionId),
    onSuccess: (cohort) => {
      setConfirming(false)
      setBindError(null)
      onBound(cohort)
    },
    onError: (err) => {
      setConfirming(false)
      setBindError(err instanceof Error ? err.message : 'Could not bind form.')
    },
  })

  const isLoading = formsQuery.isLoading || versionsQueries.isLoading
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
        <p className="text-sm text-muted-foreground" data-testid="bound-label">
          Currently bound: <span className="font-medium text-foreground">{boundLabel}</span>
        </p>
      )}

      {isLoading && (
        <p className="text-sm text-muted-foreground">Loading forms…</p>
      )}

      {!isLoading && (
        <div className="flex items-center gap-2 flex-wrap">
          <select
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
              <option key={opt.versionId} value={opt.versionId}>
                {opt.label}
              </option>
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
            <Button
              type="button"
              variant="secondary"
              onClick={() => setConfirming(false)}
            >
              Cancel
            </Button>
          )}
        </div>
      )}

      {confirming && (
        <Banner variant="info">
          Replacing the existing binding will affect applicants on this cohort. Confirm to proceed.
        </Banner>
      )}

      {bindError && (
        <Banner variant="error">{bindError}</Banner>
      )}
    </div>
  )
}
