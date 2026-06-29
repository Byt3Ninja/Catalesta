import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { StagePipelineCanvas } from '../components/StagePipelineCanvas'
import { STAGE_TYPE_LABEL } from '../components/stageTypeLabels'
import { getStagePipeline, getStagePipelineVersion, saveStagePipelineDraft, publishStagePipeline, forkStagePipelineDraft } from '../api/stages'
import { stageTypeSchema, type Stage, type StageType } from '../schemas/stages'

let stageSeq = 0
function newStage(type: StageType, order: number): Stage {
  stageSeq += 1
  return {
    stage_id: `stage_${type}_${stageSeq}`,
    name: STAGE_TYPE_LABEL[type] ?? type,
    type,
    entry_rule: null,
    exit_rule: null,
    next_stage_ids: [],
    depends_on_stage_ids: [],
    parallel_group: null,
    order,
  }
}

/** Keep each stage's `order` aligned with its array position so preview and the
 *  routing validator see a consistent ordering. */
function stampOrder(list: Stage[]): Stage[] {
  return list.map((s, i) => ({ ...s, order: i }))
}

export function StagePipelineBuilderPage({ pipelineId }: { pipelineId: string }) {
  const queryClient = useQueryClient()
  const pipelineQuery = useQuery({ queryKey: ['stage-pipeline', pipelineId], queryFn: () => getStagePipeline(pipelineId), retry: false })
  const draftId = pipelineQuery.data?.current_draft_version_id ?? null
  const draftQuery = useQuery({ queryKey: ['stage-pipeline-version', draftId], queryFn: () => getStagePipelineVersion(draftId!), enabled: !!draftId, retry: false })

  const [stages, setStages] = useState<Stage[]>([])
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [seededId, setSeededId] = useState<string | null>(null)
  // dirtyRef: true only after a user edit — never set during seeding, never reset.
  // Autosave checks this so it never fires on initial load or re-seed.
  const dirtyRef = useRef(false)
  const [justPublished, setJustPublished] = useState(false)

  const publishMutation = useMutation({
    mutationFn: () => publishStagePipeline(pipelineId),
    onSuccess: () => {
      dirtyRef.current = false
      setJustPublished(true)
      void queryClient.invalidateQueries({ queryKey: ['stage-pipeline', pipelineId] })
    },
  })

  const forkMutation = useMutation({
    mutationFn: () => forkStagePipelineDraft(pipelineId, pipelineQuery.data!.published_version_ids.at(-1)!),
    onSuccess: (v) => {
      dirtyRef.current = false
      setJustPublished(false)
      setStages(v.stages)
      void queryClient.invalidateQueries({ queryKey: ['stage-pipeline', pipelineId] })
    },
  })

  // Render-time reset keyed on version id (React "adjust state when source changes" pattern).
  if (draftQuery.data && draftQuery.data.version_id !== seededId) {
    setSeededId(draftQuery.data.version_id)
    setStages(draftQuery.data.stages)
  }

  // All edits flow through here so autosave can dirty-track them (inspector/routing edits in 5-6 too).
  function updateStages(next: Stage[]) { dirtyRef.current = true; setStages(stampOrder(next)) }

  // debounced autosave — only after the draft is seeded and a user edit has occurred.
  useEffect(() => {
    if (!draftId || seededId === null || !dirtyRef.current) return
    const t = setTimeout(() => { void saveStagePipelineDraft(pipelineId, stages).catch(() => {}) }, 400)
    return () => clearTimeout(t)
  }, [stages, pipelineId, draftId, seededId])

  function addStage(type: StageType) { const s = newStage(type, stages.length); updateStages([...stages, s]); setSelectedId(s.stage_id) }
  function move(idx: number, dir: -1 | 1) {
    const j = idx + dir
    if (j < 0 || j >= stages.length) return
    const next = [...stages]
    ;[next[idx], next[j]] = [next[j], next[idx]]
    updateStages(next)
  }
  function remove(id: string) { updateStages(stages.filter((s) => s.stage_id !== id)); if (selectedId === id) setSelectedId(null) }

  const readOnly = !draftId || justPublished
  const selected = stages.find((s) => s.stage_id === selectedId)

  return (
    <AppShell rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><Link href="/programs">Programs</Link></nav>}>
      <div className="grid gap-6">
        {pipelineQuery.isLoading ? (
          <Spinner label="Loading pipeline…" />
        ) : pipelineQuery.isError ? (
          <StateBlock variant="error" message="Could not load this pipeline." />
        ) : (
          <>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <h1 id="builder-heading" className="text-2xl font-semibold">
                Stage builder{pipelineQuery.data ? ` — ${pipelineQuery.data.name}` : ''}
              </h1>
              <div className="flex items-center gap-3">
                <span
                  data-status={draftId && !justPublished ? 'draft' : 'published'}
                  className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                >
                  {draftId && !justPublished ? `Draft v${draftQuery.data?.version ?? ''}` : 'Published (read-only)'}
                </span>
                {draftId && !justPublished && (
                  <Button loading={publishMutation.isPending} disabled={stages.length === 0} onClick={() => publishMutation.mutate()}>
                    Publish
                  </Button>
                )}
                {(!draftId || justPublished) && (
                  <Button variant="secondary" loading={forkMutation.isPending} onClick={() => forkMutation.mutate()}>
                    Edit (new draft)
                  </Button>
                )}
              </div>
            </div>
            {publishMutation.isError && <StateBlock variant="error" message="Could not publish. Try again." />}
            {forkMutation.isError && <StateBlock variant="error" message="Could not create new draft. Try again." />}
            {!draftId && !justPublished && <StateBlock variant="empty" message="Published — Edit to fork a new draft" />}
            <section aria-labelledby="builder-heading" className="grid gap-4 lg:grid-cols-[200px_1fr_320px]">
              {/* palette */}
              <div aria-label="Stage palette" className="grid h-fit gap-2 rounded-lg border border-border p-3">
                <h2 className="text-sm font-medium text-muted-foreground">Add stage</h2>
                {stageTypeSchema.options.map((t) => (
                  <Button key={t} variant="secondary" disabled={readOnly} onClick={() => addStage(t)}>
                    Add {STAGE_TYPE_LABEL[t] ?? t}
                  </Button>
                ))}
              </div>
              {/* canvas — data-version-id reflects the seeded draft version (tests wait on it) */}
              <div className="rounded-lg border border-border p-3" data-version-id={seededId ?? ''}>
                <StagePipelineCanvas
                  stages={stages}
                  selectedId={selectedId}
                  readOnly={readOnly}
                  onSelect={setSelectedId}
                  onMove={move}
                  onRemove={remove}
                />
              </div>
              {/* inspector frame — Task 5 swaps in <StageInspector>; until then a read-only summary */}
              <div aria-label="Stage settings" className="rounded-lg border border-border p-3">
                {selected ? (
                  <div className="grid gap-1">
                    <h2 className="font-medium"><bdi>{selected.name}</bdi></h2>
                    <p className="text-xs text-muted-foreground">{STAGE_TYPE_LABEL[selected.type] ?? selected.type}</p>
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">Select a stage to edit its settings.</p>
                )}
              </div>
            </section>
          </>
        )}
      </div>
    </AppShell>
  )
}
