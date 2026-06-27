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

/** Strips a stored ISO timestamp down to the YYYY-MM-DD an <input type="date"> expects. */
function toDateInput(iso: string | null): string {
  return iso ? iso.slice(0, 10) : ''
}
/** Expands a date input back to a midnight-UTC ISO string, or null when cleared. */
function toIso(date: string): string | null {
  return date ? `${date}T00:00:00+00:00` : null
}

export function EnrollmentWindowEditor({ cohortId }: { cohortId: string }) {
  const queryClient = useQueryClient()
  const cohortQuery = useQuery({ queryKey: ['cohort', cohortId], queryFn: () => getCohort(cohortId), retry: false })

  const [opens, setOpens] = useState('')
  const [closes, setCloses] = useState('')
  const [capacity, setCapacity] = useState('')
  const [validationError, setValidationError] = useState<string | null>(null)
  const [seededId, setSeededId] = useState<string | null>(null)

  // Seed the form from the loaded cohort by resetting state during render when the
  // cohort changes (React's "adjust state when a prop changes" pattern) — not in an
  // effect, which would trip react-hooks/set-state-in-effect and cascade renders.
  if (cohortQuery.data && cohortQuery.data.id !== seededId) {
    setSeededId(cohortQuery.data.id)
    setOpens(toDateInput(cohortQuery.data.enrollment_opens_at))
    setCloses(toDateInput(cohortQuery.data.enrollment_closes_at))
    setCapacity(cohortQuery.data.capacity == null ? '' : String(cohortQuery.data.capacity))
  }

  const saveMutation = useMutation({
    mutationFn: () =>
      updateCohort(cohortId, {
        enrollment_opens_at: toIso(opens),
        enrollment_closes_at: toIso(closes),
        capacity: capacity.trim() === '' ? null : Number(capacity),
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['cohort', cohortId] })
      await queryClient.invalidateQueries({ queryKey: ['cohorts'] })
    },
  })

  function onSave(event: React.FormEvent) {
    event.preventDefault()
    setValidationError(null)
    if (opens && closes && toIso(closes)! <= toIso(opens)!) {
      setValidationError('The close date must be after the open date.')
      return
    }
    saveMutation.reset()
    saveMutation.mutate()
  }

  const rail = (
    <nav aria-label="Sections" className="grid gap-1 text-sm">
      <Link href="/programs">Programs</Link>
      <Link href={`/cohorts/${cohortId}`}>Cohort</Link>
    </nav>
  )

  return (
    <AppShell
      rail={rail}
      pageHeader={<h1 id="window-heading" className="text-2xl font-semibold">Enrollment window</h1>}
    >
      <section aria-labelledby="window-heading" className="grid max-w-xl gap-6">
        {cohortQuery.isLoading ? (
          <Spinner label="Loading cohort…" />
        ) : cohortQuery.isError ? (
          <StateBlock variant="error" message="Could not load this cohort." />
        ) : (
          <form onSubmit={onSave} noValidate className="grid gap-4 rounded-lg border border-border p-4">
            {validationError && <Banner variant="error">{validationError}</Banner>}
            {saveMutation.isError && <Banner variant="error">Could not save the window. Try again.</Banner>}
            {saveMutation.isSuccess && <Banner variant="success">Window saved.</Banner>}
            <FormLayout>
              <Field label="Opens" name="opens" type="date" value={opens} onChange={(e) => setOpens(e.target.value)} />
              <Field label="Closes" name="closes" type="date" value={closes} onChange={(e) => setCloses(e.target.value)} />
              <Field
                label="Capacity"
                name="capacity"
                type="number"
                min={0}
                step={1}
                value={capacity}
                help="Leave blank for unlimited."
                onChange={(e) => setCapacity(e.target.value)}
              />
            </FormLayout>
            <div>
              <Button type="submit" loading={saveMutation.isPending}>Save window</Button>
            </div>
          </form>
        )}
      </section>
    </AppShell>
  )
}
