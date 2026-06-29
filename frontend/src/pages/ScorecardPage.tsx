import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getScorecard, getScoringModelVersion, saveScorecardDraft, submitScorecard } from '../api/assessments'
import { scoreCard } from '../lib/scoring'
import { ScorecardError } from '../schemas/assessments'
import type { ScoringCriterion } from '../schemas/assessments'

function CriterionRow({
  criterion,
  value,
  onChange,
  disabled,
}: {
  criterion: ScoringCriterion
  value: number | undefined
  onChange: (raw: string) => void
  disabled?: boolean
}) {
  const inputId = `criterion-${criterion.criterion_id}`
  const descId = `${inputId}-desc`
  const hasDescriptors = criterion.descriptors !== null && criterion.descriptors.length > 0
  return (
    <div className="grid gap-1">
      <label htmlFor={inputId} className="text-sm font-medium">
        {criterion.label}
        <span className="ml-1 text-xs text-muted-foreground">(0–{criterion.max_points} pts)</span>
      </label>
      {hasDescriptors && (
        <p id={descId} className="text-xs text-muted-foreground">
          {(criterion.descriptors as string[]).join(' · ')}
        </p>
      )}
      <input
        id={inputId}
        type="number"
        min={0}
        max={criterion.max_points}
        step={1}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
        aria-label={`Score for ${criterion.label}`}
        aria-describedby={hasDescriptors ? descId : undefined}
        className="w-24 rounded-md border border-input bg-background px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50"
      />
    </div>
  )
}

/**
 * Blind reviewer scorecard page.
 *
 * - Fetches the existing scorecard (if any) and the bound scoring model version.
 * - Shows ONE numeric input per criterion (0..max_points).
 * - Computes a live decimal total via scoreCard() from lib/scoring (never naive +).
 * - Autosaves the draft on every change (debounced 500 ms).
 * - Submit is DISABLED until scoreCard().complete is true.
 * - Applicant identity is intentionally hidden (blind evaluation).
 * - Peers' scores are never shown.
 */
export function ScorecardPage({
  cohortId,
  stageId,
  applicationId,
  reviewerId,
  modelVersionId,
}: {
  cohortId: string
  stageId: string
  applicationId: string
  reviewerId: string
  modelVersionId: string
}) {
  // ── data fetching ──────────────────────────────────────────────────────────
  const scorecardQuery = useQuery({
    queryKey: ['scorecard', cohortId, stageId, applicationId, reviewerId],
    queryFn: async () => {
      try {
        return await getScorecard(cohortId, stageId, applicationId, reviewerId)
      } catch (e) {
        // A 404 means no scorecard yet — the reviewer is scoring for the first time.
        if (e instanceof ScorecardError && e.code === 'NOT_FOUND') return null
        throw e
      }
    },
    retry: false,
  })

  const modelVersionQuery = useQuery({
    queryKey: ['scoring-model-version', modelVersionId],
    queryFn: () => getScoringModelVersion(modelVersionId),
    retry: false,
  })

  const criteria = modelVersionQuery.data?.criteria ?? []

  // ── local form state ───────────────────────────────────────────────────────
  const [values, setValues] = useState<Record<string, number>>({})
  const [disqualified, setDisqualified] = useState(false)
  const [seeded, setSeeded] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [submittedOk, setSubmittedOk] = useState(false)
  // dirtyRef: true only after a user edit — never triggered during seeding.
  // Guards autosave so it never fires on initial load.
  const dirtyRef = useRef(false)

  // Seed from existing scorecard on first successful load.
  // React "adjust state during render" pattern — triggers an immediate re-render.
  if (!seeded && scorecardQuery.data !== undefined) {
    setSeeded(true)
    if (scorecardQuery.data !== null) {
      setValues(scorecardQuery.data.values)
      setDisqualified(scorecardQuery.data.disqualified)
    }
  }

  // ── live scoring (decimal via lib/scoring, not naive +) ───────────────────
  const result = scoreCard(criteria, values)

  // ── debounced autosave ─────────────────────────────────────────────────────
  useEffect(() => {
    if (!seeded || !dirtyRef.current || submittedOk || scorecardQuery.data?.status === 'submitted') return
    const t = setTimeout(() => {
      void saveScorecardDraft(cohortId, stageId, applicationId, reviewerId, {
        values,
        disqualified,
        model_version_id: modelVersionId,
      }).catch(() => {
        // Autosave failures are silent — the user can still submit manually.
      })
    }, 500)
    return () => clearTimeout(t)
  }, [values, disqualified, seeded, submittedOk, scorecardQuery.data, cohortId, stageId, applicationId, reviewerId, modelVersionId])

  // ── submit mutation ────────────────────────────────────────────────────────
  const submitMutation = useMutation({
    mutationFn: () => submitScorecard(cohortId, stageId, applicationId, reviewerId),
    onSuccess: () => setSubmittedOk(true),
    onError: (e: unknown) => {
      if (e instanceof ScorecardError && e.code === 'VALIDATION') {
        setSubmitError('All criteria must be scored before submission.')
      } else {
        setSubmitError('Submission failed. Please try again.')
      }
    },
  })

  // ── event handlers ─────────────────────────────────────────────────────────
  function handleValueChange(criterionId: string, raw: string) {
    dirtyRef.current = true
    if (raw === '') {
      setValues((prev) => {
        const next = { ...prev }
        delete next[criterionId]
        return next
      })
    } else {
      const num = Number(raw)
      setValues((prev) => ({ ...prev, [criterionId]: num }))
    }
  }

  function handleDisqualifiedChange(checked: boolean) {
    dirtyRef.current = true
    setDisqualified(checked)
  }

  // ── derived flags ──────────────────────────────────────────────────────────
  const isLoading = scorecardQuery.isLoading || modelVersionQuery.isLoading
  const isError = scorecardQuery.isError || modelVersionQuery.isError
  const isAlreadySubmitted = scorecardQuery.data?.status === 'submitted'

  // ── render ─────────────────────────────────────────────────────────────────
  return (
    <AppShell
      rail={
        <nav aria-label="Scorecard navigation" className="grid gap-1 text-sm">
          <Link href="/review-queue">Review Queue</Link>
        </nav>
      }
      pageHeader={
        <h1 id="scorecard-heading" className="text-2xl font-semibold">
          Scorecard
        </h1>
      }
    >
      <section aria-labelledby="scorecard-heading" className="grid max-w-2xl gap-6">
        {isLoading ? (
          <Spinner label="Loading scorecard…" />
        ) : isError ? (
          <StateBlock variant="error" message="Could not load the scorecard." />
        ) : submittedOk || isAlreadySubmitted ? (
          /* Submitted state */
          <Banner variant="success">Scorecard submitted successfully.</Banner>
        ) : (
          <>
            {/*
             * BLIND context banner — applicant identity is intentionally absent.
             * Never render application_id, reviewer_id, name, email, or any
             * applicant-identifying attribute.
             */}
            <p
              data-testid="blind-banner"
              className="rounded-md bg-secondary px-4 py-2 text-sm text-secondary-foreground"
            >
              Application under review — identity masked for blind evaluation
            </p>

            {/* Criterion inputs */}
            <div className="grid gap-4" role="group" aria-label="Scoring criteria">
              {criteria.map((criterion) => (
                <CriterionRow
                  key={criterion.criterion_id}
                  criterion={criterion}
                  value={values[criterion.criterion_id]}
                  onChange={(raw) => handleValueChange(criterion.criterion_id, raw)}
                  disabled={submitMutation.isPending || submittedOk || isAlreadySubmitted}
                />
              ))}
            </div>

            {/* Live decimal total — updates on every keystroke */}
            <p
              className="text-sm font-medium"
              aria-live="polite"
              aria-atomic="true"
              data-testid="score-display"
            >
              {'Score: '}
              <span data-testid="score-earned">{result.earned}</span>
              {' / '}
              <span data-testid="score-max">{result.max}</span>
            </p>

            {/* Disqualify checkbox — carried in every draft PATCH body */}
            <label className="flex cursor-pointer items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={disqualified}
                onChange={(e) => handleDisqualifiedChange(e.target.checked)}
                aria-label="Disqualify this application"
              />
              Disqualify this application
            </label>

            {submitError !== null && (
              <p role="alert" className="text-sm text-destructive">
                {submitError}
              </p>
            )}

            {/* Submit — disabled until scoreCard().complete (every criterion in range) */}
            <Button
              onClick={() => {
                setSubmitError(null)
                submitMutation.mutate()
              }}
              disabled={!result.complete}
              loading={submitMutation.isPending}
            >
              Submit scorecard
            </Button>
          </>
        )}
      </section>
    </AppShell>
  )
}
