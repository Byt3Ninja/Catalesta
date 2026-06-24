import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { createCohort, listCohorts } from '../api/cohorts'
import { CreateCohortError, type Cohort } from '../schemas/cohorts'

/** Human-readable cohort status (text, never colour-alone). */
const STATUS_LABEL: Record<Cohort['status'], string> = {
  draft: 'Draft',
  open: 'Open',
  closed: 'Closed',
  completed: 'Completed',
}

/**
 * Cohorts for one program (FE-2), rendered inside ProgramDetailPage. Reuses the
 * tenant ['cohorts'] list and filters by program_id (there is no per-program index
 * endpoint). Create is a name-only draft under this program; opening a cohort is
 * not available yet (backend not wired).
 */
export function ProgramCohortsSection({ programId }: { programId: string }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')

  const cohortsQuery = useQuery({ queryKey: ['cohorts'], queryFn: listCohorts, retry: false })

  const createMutation = useMutation({
    mutationFn: () => createCohort(programId, { name: name.trim() }),
    onSuccess: () => {
      setName('')
      return queryClient.invalidateQueries({ queryKey: ['cohorts'] })
    },
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (name.trim().length > 0) createMutation.mutate()
  }

  const cohorts = (cohortsQuery.data ?? []).filter((c) => c.program_id === programId)

  return (
    <section aria-labelledby="cohorts-heading">
      <h2 id="cohorts-heading">Cohorts</h2>

      {renderCreateError(createMutation.error)}

      <form onSubmit={onSubmit} noValidate>
        <FormLayout>
          <Field
            label="Cohort name"
            name="cohort-name"
            required
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
        </FormLayout>
        <Button type="submit" loading={createMutation.isPending} disabled={name.trim().length === 0}>
          Create cohort
        </Button>
      </form>

      {cohortsQuery.isLoading ? (
        <Spinner label="Loading cohorts…" />
      ) : cohortsQuery.isError ? (
        <StateBlock
          variant="error"
          message="We could not load cohorts."
          action={<Button onClick={() => cohortsQuery.refetch()}>Try again</Button>}
        />
      ) : cohorts.length === 0 ? (
        <StateBlock variant="empty" message="No cohorts yet. Create the first one above." />
      ) : (
        <ul aria-labelledby="cohorts-heading">
          {cohorts.map((cohort) => (
            <li key={cohort.id}>
              <Link href={`/cohorts/${cohort.id}`}>
                <bdi>{cohort.name}</bdi>
              </Link>{' '}
              <span className="ds-badge" data-status={cohort.status}>
                {STATUS_LABEL[cohort.status]}
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

function renderCreateError(error: unknown) {
  if (!(error instanceof CreateCohortError)) {
    return error ? <Banner variant="error">Something went wrong. Please try again.</Banner> : null
  }
  switch (error.code) {
    case 'FORBIDDEN':
      return <Banner variant="error">You do not have permission to create a cohort here.</Banner>
    case 'VALIDATION':
      return <Banner variant="error">{error.message}</Banner>
    case 'UNAUTHENTICATED':
      return <Banner variant="error">Your session expired. Please sign in again.</Banner>
    default:
      return <Banner variant="error">We could not create the cohort. Please try again.</Banner>
  }
}
