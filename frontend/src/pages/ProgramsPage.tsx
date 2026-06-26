import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { Link } from '../components/Link'
import { createProgram, listPrograms } from '../api/programs'
import { CreateProgramError, type Program } from '../schemas/programs'
import type { Organization } from '../schemas/organizations'

/** Human-readable label for a program status (text, never colour-alone). */
const STATUS_LABEL: Record<Program['status'], string> = {
  draft: 'Draft',
  published: 'Published',
  archived: 'Archived',
  closed: 'Closed',
}

/**
 * Programs console (Story 1.2, Task 3). Lists the tenant's programs and creates
 * new draft programs. Each row links to the program detail page where lifecycle
 * actions (publish, clone) live. A console surface, so it renders inside AppShell.
 */
export function ProgramsPage({ organization }: { organization: Organization }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')

  const programsQuery = useQuery({
    queryKey: ['programs'],
    queryFn: listPrograms,
    retry: false,
  })

  const createMutation = useMutation({
    mutationFn: () => createProgram(name.trim(), description.trim() || undefined),
    onSuccess: () => {
      setName('')
      setDescription('')
      return queryClient.invalidateQueries({ queryKey: ['programs'] })
    },
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (name.trim().length === 0) return
    createMutation.mutate()
  }

  const programs = programsQuery.data ?? []

  return (
    <AppShell
      rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><a href="/programs" className="font-medium">Programs</a></nav>}
      pageHeader={
        <div className="flex items-center justify-between">
          <h1 id="programs-heading" className="text-2xl font-semibold"><bdi>{organization.name}</bdi> — Programs</h1>
        </div>
      }
    >
      <section aria-labelledby="programs-heading" className="grid gap-6">
        <Banner variant="info">
          Publishing a program records an immutable version. Editing a published program changes the live program (and is audited).
        </Banner>

        {renderCreateError(createMutation.error)}

        <form onSubmit={onSubmit} noValidate className="grid gap-4 rounded-lg border border-border p-4">
          <FormLayout>
            <Field label="Program name" name="program-name" required value={name} onChange={(e) => setName(e.target.value)} />
            <Field label="Description" name="program-description" help="Optional." value={description} onChange={(e) => setDescription(e.target.value)} />
          </FormLayout>
          <div>
            <Button type="submit" loading={createMutation.isPending} disabled={name.trim().length === 0}>Create program</Button>
          </div>
        </form>

        <div className="grid gap-3">
          <h2 id="programs-list-heading" className="text-lg font-medium">Your programs</h2>
          {programsQuery.isLoading ? (
            <Spinner label="Loading programs…" />
          ) : programsQuery.isError ? (
            <StateBlock variant="error" message="We could not load your programs." action={<Button onClick={() => programsQuery.refetch()}>Try again</Button>} />
          ) : programs.length === 0 ? (
            <StateBlock variant="empty" message="No programs yet. Create your first program above." />
          ) : (
            <ul aria-labelledby="programs-list-heading" className="grid gap-2">
              {programs.map((program) => (
                <li key={program.id} className="flex items-center justify-between rounded-md border border-border px-4 py-3">
                  <Link href={`/programs/${program.id}`}><bdi>{program.name}</bdi></Link>
                  <span data-status={program.status} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">
                    {STATUS_LABEL[program.status]}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>
    </AppShell>
  )
}

function renderCreateError(error: unknown) {
  if (!error) return null
  if (error instanceof CreateProgramError) {
    if (error.code === 'VALIDATION') {
      return <Banner variant="error">{error.message}</Banner>
    }
    if (error.code === 'UNAUTHENTICATED') {
      return <Banner variant="error">Your session expired. Please sign in again.</Banner>
    }
  }
  return <Banner variant="error">We could not create the program. Please try again.</Banner>
}
