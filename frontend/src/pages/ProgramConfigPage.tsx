import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { listForms } from '../api/forms'
import { listStagePipelines, createStagePipeline } from '../api/stages'
import { listScoringModels, createScoringModel } from '../api/assessments'

function PublishedBadge({ count, hasDraft }: { count: number; hasDraft: boolean }) {
  return (
    <span className="flex items-center gap-2 text-xs text-muted-foreground">
      <span>{count} published</span>
      {hasDraft && (
        <span data-status="draft" className="rounded-full bg-secondary px-2 py-0.5 font-medium text-secondary-foreground">Draft</span>
      )}
    </span>
  )
}

export function ProgramConfigPage({ programId }: { programId: string }) {
  const [newName, setNewName] = useState('')
  const [scoringModelName, setScoringModelName] = useState('')

  const formsQuery = useQuery({ queryKey: ['forms'], queryFn: listForms, retry: false })
  const pipelinesQuery = useQuery({ queryKey: ['stage-pipelines', programId], queryFn: () => listStagePipelines(programId), retry: false })
  const scoringModelsQuery = useQuery({ queryKey: ['scoring-models', programId], queryFn: () => listScoringModels(programId), retry: false })

  const createPipeline = useMutation({
    mutationFn: () => createStagePipeline(programId, newName.trim()),
    onSuccess: (pipeline) => {
      window.location.assign(`/programs/${programId}/stages/${pipeline.pipeline_id}/edit`)
    },
  })

  const createModel = useMutation({
    mutationFn: () => createScoringModel(programId, scoringModelName.trim()),
    onSuccess: (model) => {
      window.location.assign(`/programs/${programId}/scoring/${model.model_id}/edit`)
    },
  })

  const forms = formsQuery.data ?? []
  const pipelines = pipelinesQuery.data ?? []
  const scoringModels = scoringModelsQuery.data ?? []

  return (
    <AppShell
      rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><Link href="/programs">Programs</Link></nav>}
      pageHeader={<h1 id="config-heading" className="text-2xl font-semibold">Program configuration</h1>}
    >
      <section aria-labelledby="config-heading" className="grid gap-6 lg:grid-cols-2">
        {/* Forms */}
        <div className="grid h-fit gap-3 rounded-lg border border-border bg-card p-4">
          <h2 className="text-lg font-medium">Forms</h2>
          {formsQuery.isLoading ? (
            <Spinner label="Loading forms…" />
          ) : formsQuery.isError ? (
            <StateBlock variant="error" message="Could not load forms." />
          ) : forms.length === 0 ? (
            <StateBlock variant="empty" message="No forms yet." />
          ) : (
            <ul className="grid gap-2">
              {forms.map((f) => (
                <li key={f.id} className="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2">
                  <span className="grid gap-0.5">
                    <span className="text-sm font-medium"><bdi>{f.name}</bdi></span>
                    <PublishedBadge count={f.published_version_ids.length} hasDraft={f.current_draft_version_id !== null} />
                  </span>
                  <Link href={`/forms/${f.id}/edit`}>Open builder</Link>
                </li>
              ))}
            </ul>
          )}
        </div>

        {/* Stages */}
        <div className="grid h-fit gap-3 rounded-lg border border-border bg-card p-4">
          <h2 className="text-lg font-medium">Stages</h2>
          <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(e) => { e.preventDefault(); if (newName.trim()) createPipeline.mutate() }}
          >
            <Field label="New pipeline name" name="new-pipeline-name" value={newName} onChange={(e) => setNewName(e.target.value)} />
            <Button type="submit" loading={createPipeline.isPending} disabled={!newName.trim()}>New pipeline</Button>
          </form>
          {createPipeline.isError && <StateBlock variant="error" message="Could not create the pipeline. Try again." />}
          {pipelinesQuery.isLoading ? (
            <Spinner label="Loading pipelines…" />
          ) : pipelinesQuery.isError ? (
            <StateBlock variant="error" message="Could not load pipelines." />
          ) : pipelines.length === 0 ? (
            <StateBlock variant="empty" message="No pipelines yet." />
          ) : (
            <ul className="grid gap-2">
              {pipelines.map((p) => (
                <li key={p.pipeline_id} className="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2">
                  <span className="grid gap-0.5">
                    <span className="text-sm font-medium"><bdi>{p.name}</bdi></span>
                    <PublishedBadge count={p.published_version_ids.length} hasDraft={p.current_draft_version_id !== null} />
                  </span>
                  <Link href={`/programs/${programId}/stages/${p.pipeline_id}/edit`}>Open builder</Link>
                </li>
              ))}
            </ul>
          )}
        </div>
        {/* Scoring */}
        <div className="grid h-fit gap-3 rounded-lg border border-border bg-card p-4">
          <h2 className="text-lg font-medium">Scoring</h2>
          <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(e) => { e.preventDefault(); if (scoringModelName.trim()) createModel.mutate() }}
          >
            <Field label="New scoring model name" name="new-scoring-model-name" value={scoringModelName} onChange={(e) => setScoringModelName(e.target.value)} />
            <Button type="submit" loading={createModel.isPending} disabled={!scoringModelName.trim()}>New scoring model</Button>
          </form>
          {createModel.isError && <StateBlock variant="error" message="Could not create the scoring model. Try again." />}
          {scoringModelsQuery.isLoading ? (
            <Spinner label="Loading scoring models…" />
          ) : scoringModelsQuery.isError ? (
            <StateBlock variant="error" message="Could not load scoring models." />
          ) : scoringModels.length === 0 ? (
            <StateBlock variant="empty" message="No scoring models yet." />
          ) : (
            <ul className="grid gap-2">
              {scoringModels.map((m) => (
                <li key={m.model_id} className="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2">
                  <span className="grid gap-0.5">
                    <span className="text-sm font-medium"><bdi>{m.name}</bdi></span>
                    <PublishedBadge count={m.published_version_ids.length} hasDraft={m.current_draft_version_id !== null} />
                  </span>
                  <Link href={`/programs/${programId}/scoring/${m.model_id}/edit`}>Open builder</Link>
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>
    </AppShell>
  )
}
