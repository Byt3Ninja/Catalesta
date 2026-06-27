import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { cloneProgram, getProgram, publishProgram, updateProgram } from '../api/programs'
import { ProgramCohortsSection } from './ProgramCohortsSection'
import {
  CloneProgramError,
  GetProgramError,
  PublishProgramError,
  UpdateProgramError,
  type Program,
} from '../schemas/programs'

/** Human-readable program status (text, never colour-alone). */
const PROGRAM_STATUS_LABEL: Record<Program['status'], string> = {
  draft: 'Draft',
  published: 'Published',
  archived: 'Archived',
  closed: 'Closed',
}

/**
 * Program detail (Story 1.2 / FE-1). Shows one program and hosts its lifecycle
 * actions: inline Edit (name/description), Clone (→ new draft), and Publish (draft
 * only). Editing mutates the live program (audited) — it does NOT create a version;
 * publishing is what records an immutable version. A console surface → AppShell.
 */
export function ProgramDetailPage({ programId }: { programId: string }) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const programQuery = useQuery({
    queryKey: ['program', programId],
    queryFn: () => getProgram(programId),
    retry: false,
  })

  const [editing, setEditing] = useState(false)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [cloning, setCloning] = useState(false)
  const [cloneName, setCloneName] = useState('')

  const invalidate = () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: ['program', programId] }),
      queryClient.invalidateQueries({ queryKey: ['programs'] }),
    ])

  const updateMutation = useMutation({
    mutationFn: () =>
      updateProgram(programId, { name: name.trim(), description: description.trim() || null }),
    onSuccess: async () => {
      setEditing(false)
      await invalidate()
    },
  })

  const publishMutation = useMutation({
    mutationFn: () => publishProgram(programId),
    onSuccess: () => invalidate(),
  })

  const cloneMutation = useMutation({
    mutationFn: () => cloneProgram(programId, cloneName.trim()),
    onSuccess: (clone) => {
      void queryClient.invalidateQueries({ queryKey: ['programs'] })
      navigate(`/programs/${clone.id}`)
    },
  })

  const program = programQuery.data

  const beginEdit = (p: Program) => {
    setName(p.name)
    setDescription(p.description ?? '')
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
      <section aria-labelledby="program-heading">
        <p>
          <Link href="/programs">← Programs</Link>
        </p>

        {programQuery.isLoading ? (
          <Spinner label="Loading program…" />
        ) : programQuery.isError ? (
          renderLoadError(programQuery.error, () => programQuery.refetch())
        ) : program ? (
          <>
            <h1 id="program-heading">
              <bdi>{program.name}</bdi>
            </h1>
            <p>
              <span
                data-status={program.status}
                className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
              >
                {PROGRAM_STATUS_LABEL[program.status] ?? program.status}
              </span>{' '}
              <span className="text-sm text-muted-foreground">{program.slug}</span>
            </p>

            {renderMutationError(updateMutation.error)}
            {renderMutationError(publishMutation.error)}
            {renderMutationError(cloneMutation.error)}

            {editing ? (
              <form
                noValidate
                onSubmit={(event) => {
                  event.preventDefault()
                  if (name.trim().length > 0) updateMutation.mutate()
                }}
              >
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
                <Button type="submit" loading={updateMutation.isPending} disabled={name.trim().length === 0}>
                  Save
                </Button>{' '}
                <Button variant="secondary" onClick={() => setEditing(false)}>
                  Cancel
                </Button>
              </form>
            ) : (
              <>
                <div className="grid max-w-2xl gap-6">
                  {program.description ? (
                    <p>
                      <bdi>{program.description}</bdi>
                    </p>
                  ) : (
                    <p className="text-sm text-muted-foreground">No description.</p>
                  )}
                  <p className="text-sm text-muted-foreground">
                    Created {program.created_at} · Updated {program.updated_at}
                  </p>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" onClick={() => beginEdit(program)}>
                      Edit
                    </Button>
                    <Button
                      variant="secondary"
                      onClick={() => {
                        setCloneName(`${program.name} (copy)`)
                        setCloning(true)
                      }}
                    >
                      Clone
                    </Button>
                    {program.status === 'draft' ? (
                      <Button loading={publishMutation.isPending} onClick={() => publishMutation.mutate()}>
                        Publish
                      </Button>
                    ) : null}
                    <Link href={`/programs/${programId}/config`}>Configure program</Link>
                  </div>
                </div>
              </>
            )}

            {program.status === 'draft' ? (
              <Banner variant="info">
                Publishing records an immutable version of this program. Editing afterward changes the
                live program (and is audited) — it does not create a new version.
              </Banner>
            ) : null}

            {cloning ? (
              <form
                noValidate
                aria-label="Clone program"
                onSubmit={(event) => {
                  event.preventDefault()
                  if (cloneName.trim().length > 0) cloneMutation.mutate()
                }}
              >
                <FormLayout>
                  <Field
                    label="New program name"
                    name="clone-name"
                    required
                    value={cloneName}
                    onChange={(event) => setCloneName(event.target.value)}
                  />
                </FormLayout>
                <Button type="submit" loading={cloneMutation.isPending} disabled={cloneName.trim().length === 0}>
                  Create copy
                </Button>{' '}
                <Button variant="secondary" onClick={() => setCloning(false)}>
                  Cancel
                </Button>
              </form>
            ) : null}
            <ProgramCohortsSection programId={programId} />
          </>
        ) : null}
      </section>
    </AppShell>
  )
}

function renderLoadError(error: unknown, retry: () => void) {
  if (error instanceof GetProgramError && error.code === 'NOT_FOUND') {
    return (
      <StateBlock
        variant="error"
        message="That program no longer exists."
        action={<Link href="/programs">Back to Programs</Link>}
      />
    )
  }
  return (
    <StateBlock
      variant="error"
      message="We could not load this program."
      action={<Button onClick={retry}>Try again</Button>}
    />
  )
}

function renderMutationError(error: unknown) {
  if (
    !(
      error instanceof UpdateProgramError ||
      error instanceof CloneProgramError ||
      error instanceof PublishProgramError
    )
  ) {
    return error ? <Banner variant="error">Something went wrong. Please try again.</Banner> : null
  }
  switch (error.code) {
    case 'FORBIDDEN':
      return <Banner variant="error">You do not have permission to perform that action.</Banner>
    case 'NOT_FOUND':
      return <Banner variant="error">That program no longer exists.</Banner>
    case 'UNAUTHENTICATED':
      return <Banner variant="error">Your session expired. Please sign in again.</Banner>
    case 'VALIDATION':
      return <Banner variant="error">{error.message}</Banner>
    default:
      return <Banner variant="error">Something went wrong. Please try again.</Banner>
  }
}
