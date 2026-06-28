import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormBindingPicker } from '../components/FormBindingPicker'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { getCohort, updateCohort } from '../api/cohorts'
import { UpdateCohortError, type Cohort } from '../schemas/cohorts'

const STATUS_LABEL: Record<Cohort['status'], string> = {
  draft: 'Draft',
  open: 'Open',
  closed: 'Closed',
  completed: 'Completed',
}

function formatWindow(opens: string | null, closes: string | null): string {
  if (!opens && !closes) return 'Not scheduled'
  const fmt = (iso: string | null) => (iso ? iso.slice(0, 10) : '—')
  return `${fmt(opens)} → ${fmt(closes)}`
}

export function CohortDetailPage({ cohortId }: { cohortId: string }) {
  const queryClient = useQueryClient()
  const cohortQuery = useQuery({ queryKey: ['cohort', cohortId], queryFn: () => getCohort(cohortId), retry: false })

  const [editing, setEditing] = useState(false)
  const [name, setName] = useState('')

  const updateMutation = useMutation({
    mutationFn: () => updateCohort(cohortId, { name: name.trim() }),
    onSuccess: async () => {
      setEditing(false)
      await queryClient.invalidateQueries({ queryKey: ['cohort', cohortId] })
      await queryClient.invalidateQueries({ queryKey: ['cohorts'] })
    },
  })

  const cohort = cohortQuery.data
  const rail = (
    <nav aria-label="Sections" className="grid gap-1 text-sm">
      <Link href="/programs">Programs</Link>
    </nav>
  )

  return (
    <AppShell
      rail={rail}
      pageHeader={
        <h1 id="cohort-heading" className="text-2xl font-semibold">
          <bdi>{cohort?.name ?? 'Cohort'}</bdi>
        </h1>
      }
    >
      <section aria-labelledby="cohort-heading" className="grid max-w-2xl gap-6">
        {cohortQuery.isLoading ? (
          <Spinner label="Loading cohort…" />
        ) : cohortQuery.isError ? (
          <StateBlock variant="error" message="Could not load this cohort." />
        ) : cohort ? (
          <>
            <div className="flex items-center gap-3">
              <span
                data-status={cohort.status}
                className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
              >
                {STATUS_LABEL[cohort.status] ?? cohort.status}
              </span>
              {!editing && (
                <Button variant="secondary" onClick={() => { setName(cohort.name); setEditing(true) }}>Edit</Button>
              )}
            </div>

            {editing ? (
              <form
                onSubmit={(e) => { e.preventDefault(); if (name.trim()) updateMutation.mutate() }}
                noValidate
                className="grid gap-4 rounded-lg border border-border p-4"
              >
                {updateMutation.isError && (
                  <Banner variant="error">
                    {updateMutation.error instanceof UpdateCohortError ? updateMutation.error.message : 'Could not save.'}
                  </Banner>
                )}
                <FormLayout>
                  <Field label="Cohort name" name="cohort-name" required value={name} onChange={(e) => setName(e.target.value)} />
                </FormLayout>
                <div className="flex gap-2">
                  <Button type="submit" loading={updateMutation.isPending} disabled={!name.trim()}>Save</Button>
                  <Button variant="secondary" type="button" onClick={() => setEditing(false)}>Cancel</Button>
                </div>
              </form>
            ) : (
              <dl className="grid gap-4 rounded-lg border border-border p-4 text-sm">
                <div className="flex items-center justify-between">
                  <dt className="text-muted-foreground">Enrollment window</dt>
                  <dd className="flex items-center gap-3">
                    <span>{formatWindow(cohort.enrollment_opens_at, cohort.enrollment_closes_at)}</span>
                    <Link href={`/cohorts/${cohortId}/enrollment`}>Edit enrollment window</Link>
                  </dd>
                </div>
                <div className="grid gap-2">
                  <dt className="text-muted-foreground">Application form</dt>
                  <dd>
                    {cohort.bound_form_version_id ? (
                      <span className="text-sm font-medium">Bound: {cohort.bound_form_version_id}</span>
                    ) : (
                      <span className="text-sm text-muted-foreground">Not bound yet</span>
                    )}
                    <FormBindingPicker
                      cohortId={cohortId}
                      boundVersionId={cohort.bound_form_version_id}
                      onBound={async (updated) => {
                        await queryClient.invalidateQueries({ queryKey: ['cohort', cohortId] })
                        await queryClient.invalidateQueries({ queryKey: ['cohorts'] })
                        // Update the cache immediately so the UI reflects the new binding
                        queryClient.setQueryData(['cohort', cohortId], updated)
                      }}
                    />
                  </dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-muted-foreground">Stage pipeline</dt>
                  <dd className="text-muted-foreground">Not configured yet</dd>
                </div>
              </dl>
            )}
          </>
        ) : null}
      </section>
    </AppShell>
  )
}
