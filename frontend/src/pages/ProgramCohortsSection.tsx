import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { createCohort, listCohorts } from '../api/cohorts'
import { CreateCohortError } from '../schemas/cohorts'

const STATUS_LABEL: Record<string, string> = {
  draft: 'Draft',
  open: 'Open',
  closed: 'Closed',
  completed: 'Completed',
}

function renderCreateError(error: unknown) {
  if (!error) return null
  const message = error instanceof CreateCohortError ? error.message : 'Could not create the cohort.'
  return <Banner variant="error">{message}</Banner>
}

export function ProgramCohortsSection({ programId }: { programId: string }) {
  const queryClient = useQueryClient()
  const cohortsQuery = useQuery({ queryKey: ['cohorts'], queryFn: listCohorts, retry: false })
  const [name, setName] = useState('')

  const createMutation = useMutation({
    mutationFn: () => createCohort(programId, { name: name.trim() }),
    onSuccess: () => {
      setName('')
      return queryClient.invalidateQueries({ queryKey: ['cohorts'] })
    },
  })

  const cohorts = (cohortsQuery.data ?? []).filter((c) => c.program_id === programId)

  function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (name.trim()) createMutation.mutate()
  }

  return (
    <section aria-labelledby="cohorts-heading" className="grid gap-4">
      <h2 id="cohorts-heading" className="text-lg font-medium">Cohorts</h2>
      {renderCreateError(createMutation.error)}
      <form onSubmit={onSubmit} noValidate className="grid gap-3 rounded-lg border border-border p-4">
        <FormLayout>
          <Field label="Cohort name" name="cohort-name" required value={name} onChange={(e) => setName(e.target.value)} />
        </FormLayout>
        <div className="flex gap-2">
          <Button type="submit" loading={createMutation.isPending} disabled={!name.trim()}>Create cohort</Button>
          <Link href={`/programs/${programId}/cohorts/new`}>Set up with wizard</Link>
        </div>
      </form>

      {cohortsQuery.isLoading ? (
        <Spinner label="Loading cohorts…" />
      ) : cohortsQuery.isError ? (
        <StateBlock variant="error" message="Could not load cohorts." />
      ) : cohorts.length === 0 ? (
        <StateBlock variant="empty" message="No cohorts yet. Create one to begin intake." />
      ) : (
        <ul aria-labelledby="cohorts-heading" className="grid gap-2">
          {cohorts.map((cohort) => (
            <li key={cohort.id} className="flex items-center justify-between rounded-md border border-border px-4 py-3">
              <Link href={`/cohorts/${cohort.id}`}><bdi>{cohort.name}</bdi></Link>
              <span
                data-status={cohort.status}
                className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
              >
                {STATUS_LABEL[cohort.status] ?? cohort.status}
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
