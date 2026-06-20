import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { useDirection } from '../app/direction-context'
import { fetchApplyForm, submitApplication } from '../api/apply'
import { SubmitError, type FormField, type Receipt, type SubmitErrorCode } from '../schemas/apply'
import { ApplyField } from './ApplyField'
import { useApplyDraft } from './useApplyDraft'

/** Stable per-field answer key: prefer an explicit key, else derive deterministically. */
function answerKey(field: FormField, index: number): string {
  return field.key ?? `field_${index}_${field.type}`
}

const SUBMIT_ERROR_COPY: Record<Exclude<SubmitErrorCode, 'UNAUTHENTICATED' | 'COHORT_CLOSED'>, string> =
  {
    IDEMPOTENCY_IN_FLIGHT: 'Still processing your earlier submission… please wait a moment.',
    IDEMPOTENCY_CONFLICT:
      'This submission conflicts with an earlier one. Refresh and review before resubmitting.',
    VALIDATION_ERROR: 'Some answers need attention. Please review your entries and try again.',
    UNKNOWN: 'Something went wrong submitting your application. Please try again.',
  }

export function ApplyPage({ cohortId }: { cohortId: string }) {
  const { dir, setDir } = useDirection()
  const { answers, idempotencyKey, setAnswer, clear } = useApplyDraft(cohortId)
  const [files, setFiles] = useState<Record<string, File[]>>({})
  const [step, setStep] = useState(0)
  const [receipt, setReceipt] = useState<Receipt | null>(null)

  const query = useQuery({
    queryKey: ['apply-form', cohortId],
    queryFn: () => fetchApplyForm(cohortId),
    retry: false,
  })

  const mutation = useMutation({
    mutationFn: () =>
      submitApplication(cohortId, {
        answers,
        files: Object.values(files).flat(),
        idempotencyKey,
      }),
    onSuccess: (data) => {
      setReceipt(data)
      clear()
    },
  })

  const toggleDir = () => setDir(dir === 'rtl' ? 'ltr' : 'rtl')

  const langToggle = (
    <div className="apply-toolbar">
      <Button variant="secondary" onClick={toggleDir} aria-label="Switch language and direction">
        {dir === 'rtl' ? 'English' : 'العربية'}
      </Button>
    </div>
  )

  // ---- Loading ----
  if (query.isLoading) {
    return (
      <section aria-labelledby="apply-heading">
        <h1 id="apply-heading">Application</h1>
        <Spinner label="Loading application form…" />
      </section>
    )
  }

  // ---- Fetch error / offline (unknown cohort, 404, network) ----
  if (query.isError || !query.data) {
    const offline = typeof navigator !== 'undefined' && navigator.onLine === false
    return (
      <section aria-labelledby="apply-heading">
        <h1 id="apply-heading">Application</h1>
        <StateBlock
          variant={offline ? 'offline' : 'error'}
          message={
            offline
              ? 'You appear to be offline. Reconnect and try again.'
              : 'We could not load this application. The link may be invalid.'
          }
          action={<Button onClick={() => query.refetch()}>Try again</Button>}
        />
      </section>
    )
  }

  // ---- Cohort closed (open:false, distinct from unknown cohort) ----
  if (!query.data.open) {
    return (
      <section aria-labelledby="apply-heading">
        <h1 id="apply-heading">Application</h1>
        {langToggle}
        <StateBlock
          variant="empty"
          message="This cohort is no longer accepting applications."
        />
      </section>
    )
  }

  const fields = query.data.form ?? []

  // ---- Receipt (success) ----
  if (receipt) {
    return (
      <section aria-labelledby="apply-heading">
        <h1 id="apply-heading">Application received</h1>
        <Banner variant="success">
          Thank you. Your application has been submitted successfully.
        </Banner>
        <p>Your reference number:</p>
        <p className="apply-reference" data-testid="reference-number">
          <strong>{receipt.reference_number}</strong>
        </p>
        <p>Status: {receipt.status}</p>
        <p>Please keep this reference number — you will need it for any follow-up.</p>
      </section>
    )
  }

  // ---- Empty form definition ----
  if (fields.length === 0) {
    return (
      <section aria-labelledby="apply-heading">
        <h1 id="apply-heading">Application</h1>
        {langToggle}
        <StateBlock variant="empty" message="This application has no questions yet." />
      </section>
    )
  }

  const totalSteps = fields.length + 1 // +1 confirm step
  const isConfirm = step === fields.length
  const currentField = isConfirm ? null : fields[step]
  const currentKey = currentField ? answerKey(currentField, step) : ''

  return (
    <section aria-labelledby="apply-heading">
      <h1 id="apply-heading">Application</h1>
      {langToggle}
      <p className="apply-progress" role="status">
        Step {step + 1} of {totalSteps}
      </p>

      {renderSubmitBanner(mutation.error)}

      <FormLayout>
        {currentField ? (
          <ApplyField
            field={currentField}
            value={answers[currentKey]}
            onChange={(value) => setAnswer(currentKey, value)}
            onFiles={(list) => setFiles((prev) => ({ ...prev, [currentKey]: list }))}
          />
        ) : (
          <div className="apply-confirm">
            <h2>Review and submit</h2>
            <Banner variant="info">
              You can&apos;t edit your application after submitting.
            </Banner>
            <ul aria-label="answers-summary" className="apply-summary">
              {fields.map((field, i) => (
                <li key={answerKey(field, i)}>
                  <span className="apply-summary__label">{field.label}:</span>{' '}
                  <span dir="auto">{summarize(answers[answerKey(field, i)])}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </FormLayout>

      <div className="apply-nav">
        <Button
          variant="secondary"
          onClick={() => setStep((s) => Math.max(0, s - 1))}
          disabled={step === 0 || mutation.isPending}
        >
          Back
        </Button>
        {isConfirm ? (
          <Button onClick={() => mutation.mutate()} loading={mutation.isPending}>
            {mutation.isPending ? 'Submitting…' : 'Submit application'}
          </Button>
        ) : (
          <Button onClick={() => setStep((s) => Math.min(fields.length, s + 1))}>Next</Button>
        )}
      </div>
    </section>
  )
}

function renderSubmitBanner(error: unknown) {
  if (!error) return null
  if (error instanceof SubmitError) {
    if (error.code === 'UNAUTHENTICATED') {
      return (
        <Banner variant="error">
          Please sign in to submit your application.{' '}
          <Link href="/login">Sign in</Link>
        </Banner>
      )
    }
    if (error.code === 'COHORT_CLOSED') {
      return (
        <Banner variant="error">
          This cohort is no longer accepting applications.
        </Banner>
      )
    }
    return <Banner variant="error">{SUBMIT_ERROR_COPY[error.code]}</Banner>
  }
  return <Banner variant="error">{SUBMIT_ERROR_COPY.UNKNOWN}</Banner>
}

function summarize(value: unknown): string {
  if (value == null || value === '') return '—'
  if (value === true) return 'Yes'
  if (value === false) return 'No'
  if (Array.isArray(value)) return value.length ? value.join(', ') : '—'
  return String(value)
}
