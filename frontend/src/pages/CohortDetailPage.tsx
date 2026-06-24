import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getCohort, updateCohort } from '../api/cohorts'
import { GetCohortError, UpdateCohortError, type Cohort } from '../schemas/cohorts'

/** Human-readable cohort status (text, never colour-alone). */
const STATUS_LABEL: Record<Cohort['status'], string> = {
  draft: 'Draft',
  open: 'Open',
  closed: 'Closed',
  completed: 'Completed',
}

/** Native <input type="date"> wants YYYY-MM-DD; the API returns ISO or a date. */
function toDateInput(value: string | null): string {
  return value ? value.slice(0, 10) : ''
}

/**
 * Cohort detail (FE-2). Shows one cohort's metadata and an inline editor for
 * name/capacity/dates. No open/close/status control — opening a cohort for
 * applications (form binding + entitlement + audit) is not wired in the backend
 * yet. A console surface → AppShell.
 */
export function CohortDetailPage({ cohortId }: { cohortId: string }) {
  const queryClient = useQueryClient()
  const cohortQuery = useQuery({
    queryKey: ['cohort', cohortId],
    queryFn: () => getCohort(cohortId),
    retry: false,
  })

  const [editing, setEditing] = useState(false)
  const [name, setName] = useState('')
  const [capacity, setCapacity] = useState('')
  const [opensAt, setOpensAt] = useState('')
  const [closesAt, setClosesAt] = useState('')
  const [startsAt, setStartsAt] = useState('')
  const [endsAt, setEndsAt] = useState('')

  const updateMutation = useMutation({
    mutationFn: () =>
      updateCohort(cohortId, {
        name: name.trim(),
        capacity: capacity.trim() === '' ? null : Number(capacity),
        enrollment_opens_at: opensAt || null,
        enrollment_closes_at: closesAt || null,
        starts_at: startsAt || null,
        ends_at: endsAt || null,
      }),
    onSuccess: async () => {
      setEditing(false)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['cohort', cohortId] }),
        queryClient.invalidateQueries({ queryKey: ['cohorts'] }),
      ])
    },
  })

  const cohort = cohortQuery.data

  const beginEdit = (c: Cohort) => {
    setName(c.name)
    setCapacity(c.capacity != null ? String(c.capacity) : '')
    setOpensAt(toDateInput(c.enrollment_opens_at))
    setClosesAt(toDateInput(c.enrollment_closes_at))
    setStartsAt(toDateInput(c.starts_at))
    setEndsAt(toDateInput(c.ends_at))
    setEditing(true)
  }

  return (
    <AppShell
      rail={
        <nav aria-label="Sections">
          <Link href="/programs">Programs</Link>
        </nav>
      }
    >
      <section aria-labelledby="cohort-heading">
        {cohortQuery.isLoading ? (
          <Spinner label="Loading cohort…" />
        ) : cohortQuery.isError ? (
          renderLoadError(cohortQuery.error, () => cohortQuery.refetch())
        ) : cohort ? (
          <>
            <p>
              <Link href={`/programs/${cohort.program_id}`}>← Program</Link>
            </p>
            <h1 id="cohort-heading">
              <bdi>{cohort.name}</bdi>
            </h1>
            <p>
              <span className="ds-badge" data-status={cohort.status}>
                {STATUS_LABEL[cohort.status]}
              </span>{' '}
              <span className="ds-muted">{cohort.slug}</span>
            </p>

            {renderUpdateError(updateMutation.error)}

            {editing ? (
              <form
                noValidate
                onSubmit={(event) => {
                  event.preventDefault()
                  if (name.trim().length > 0) updateMutation.mutate()
                }}
              >
                <FormLayout>
                  <Field label="Cohort name" name="cohort-name" required value={name} onChange={(e) => setName(e.target.value)} />
                  <Field label="Capacity" name="cohort-capacity" type="number" min={1} help="Optional." value={capacity} onChange={(e) => setCapacity(e.target.value)} />
                  <Field label="Enrollment opens" name="cohort-opens" type="date" value={opensAt} onChange={(e) => setOpensAt(e.target.value)} />
                  <Field label="Enrollment closes" name="cohort-closes" type="date" value={closesAt} onChange={(e) => setClosesAt(e.target.value)} />
                  <Field label="Starts" name="cohort-starts" type="date" value={startsAt} onChange={(e) => setStartsAt(e.target.value)} />
                  <Field label="Ends" name="cohort-ends" type="date" value={endsAt} onChange={(e) => setEndsAt(e.target.value)} />
                </FormLayout>
                <Button type="submit" loading={updateMutation.isPending} disabled={name.trim().length === 0}>
                  Save
                </Button>{' '}
                <Button variant="secondary" onClick={() => setEditing(false)}>
                  Cancel
                </Button>
              </form>
            ) : (
              <>
                <p className="ds-muted">
                  Capacity: {cohort.capacity != null ? cohort.capacity : 'No cap'} · Opens:{' '}
                  {cohort.enrollment_opens_at ?? '—'} · Closes: {cohort.enrollment_closes_at ?? '—'} ·
                  Starts: {cohort.starts_at ?? '—'} · Ends: {cohort.ends_at ?? '—'}
                </p>
                <p>
                  Submissions: {cohort.submissions_count ?? 0} —{' '}
                  <Link href={`/cohorts/${cohort.id}/submissions`}>View submissions</Link>
                </p>
                <Button variant="secondary" onClick={() => beginEdit(cohort)}>
                  Edit
                </Button>
                <p className="ds-muted">Opening a cohort for applications isn't available yet.</p>
              </>
            )}
          </>
        ) : null}
      </section>
    </AppShell>
  )
}

function renderLoadError(error: unknown, retry: () => void) {
  if (error instanceof GetCohortError && error.code === 'NOT_FOUND') {
    return (
      <StateBlock
        variant="error"
        message="That cohort no longer exists."
        action={<Link href="/programs">Back to Programs</Link>}
      />
    )
  }
  return (
    <StateBlock
      variant="error"
      message="We could not load this cohort."
      action={<Button onClick={retry}>Try again</Button>}
    />
  )
}

function renderUpdateError(error: unknown) {
  if (!(error instanceof UpdateCohortError)) {
    return error ? <Banner variant="error">Something went wrong. Please try again.</Banner> : null
  }
  switch (error.code) {
    case 'FORBIDDEN':
      return <Banner variant="error">You do not have permission to perform that action.</Banner>
    case 'NOT_FOUND':
      return <Banner variant="error">That cohort no longer exists.</Banner>
    case 'UNAUTHENTICATED':
      return <Banner variant="error">Your session expired. Please sign in again.</Banner>
    case 'VALIDATION':
      return <Banner variant="error">{error.message}</Banner>
    default:
      return <Banner variant="error">Something went wrong. Please try again.</Banner>
  }
}
