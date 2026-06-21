import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { createProgram, listPrograms, publishProgram } from '../api/programs'
import {
  CreateProgramError,
  PublishProgramError,
  type Program,
} from '../schemas/programs'
import type { Organization } from '../schemas/organizations'

/** Human-readable label for a program status (text, never colour-alone). */
const STATUS_LABEL: Record<Program['status'], string> = {
  draft: 'Draft',
  published: 'Published',
  archived: 'Archived',
  closed: 'Closed',
}

/**
 * Programs console (Story 1.2, Task 3). Lists the tenant's programs, creates new
 * draft programs, and publishes drafts. Publishing is versioned — not
 * destructive — so it is a single action with an explanatory banner, NOT a modal.
 * A console surface, so it renders inside AppShell.
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

  const publishMutation = useMutation({
    mutationFn: (id: string) => publishProgram(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['programs'] }),
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (name.trim().length === 0) return
    createMutation.mutate()
  }

  const programs = programsQuery.data ?? []

  return (
    <AppShell rail={<nav aria-label="Sections">Programs</nav>}>
      <section aria-labelledby="programs-heading">
        <h1 id="programs-heading">{organization.name} — Programs</h1>

        <Banner variant="info">
          Publishing a program records an immutable version. Editing a published
          program later creates a new version — it never changes what was published.
        </Banner>

        {renderCreateError(createMutation.error)}
        {renderPublishError(publishMutation.error)}

        <form onSubmit={onSubmit} noValidate>
          <FormLayout>
            <Field
              label="Program name"
              name="program-name"
              required
              value={name}
              onChange={(event) => setName(event.target.value)}
            />
            <Field
              label="Description"
              name="program-description"
              help="Optional."
              value={description}
              onChange={(event) => setDescription(event.target.value)}
            />
          </FormLayout>
          <Button
            type="submit"
            loading={createMutation.isPending}
            disabled={name.trim().length === 0}
          >
            Create program
          </Button>
        </form>

        <h2 id="programs-list-heading">Your programs</h2>
        {programsQuery.isLoading ? (
          <Spinner label="Loading programs…" />
        ) : programsQuery.isError ? (
          <StateBlock
            variant="error"
            message="We could not load your programs."
            action={<Button onClick={() => programsQuery.refetch()}>Try again</Button>}
          />
        ) : programs.length === 0 ? (
          <StateBlock
            variant="empty"
            message="No programs yet. Create your first program above."
          />
        ) : (
          <ul aria-labelledby="programs-list-heading">
            {programs.map((program) => (
              <li key={program.id}>
                <span>{program.name}</span>{' '}
                <span className="ds-badge" data-status={program.status}>
                  {STATUS_LABEL[program.status]}
                </span>
                {program.status === 'draft' ? (
                  <Button
                    variant="secondary"
                    loading={
                      publishMutation.isPending &&
                      publishMutation.variables === program.id
                    }
                    onClick={() => publishMutation.mutate(program.id)}
                  >
                    Publish
                  </Button>
                ) : null}
              </li>
            ))}
          </ul>
        )}
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

function renderPublishError(error: unknown) {
  if (!error) return null
  if (error instanceof PublishProgramError) {
    if (error.code === 'FORBIDDEN') {
      return <Banner variant="error">You do not have permission to publish this program.</Banner>
    }
    if (error.code === 'NOT_FOUND') {
      return <Banner variant="error">That program no longer exists.</Banner>
    }
    if (error.code === 'UNAUTHENTICATED') {
      return <Banner variant="error">Your session expired. Please sign in again.</Banner>
    }
  }
  return <Banner variant="error">We could not publish the program. Please try again.</Banner>
}
