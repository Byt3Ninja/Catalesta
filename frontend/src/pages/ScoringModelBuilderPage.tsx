import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { ScoringModelCanvas } from '../components/ScoringModelCanvas'
import { getScoringModel, getScoringModelVersion, saveScoringModelDraft, publishScoringModel, forkScoringModelDraft } from '../api/assessments'
import type { ScoringCriterion } from '../schemas/assessments'

let critSeq = 0
function newCriterion(): ScoringCriterion {
  critSeq += 1
  return {
    criterion_id: `crit_${critSeq}`,
    label: 'New criterion',
    max_points: 10,
    descriptors: null,
  }
}

export function ScoringModelBuilderPage({ modelId }: { modelId: string }) {
  const queryClient = useQueryClient()
  const modelQuery = useQuery({ queryKey: ['scoring-model', modelId], queryFn: () => getScoringModel(modelId), retry: false })
  const draftId = modelQuery.data?.current_draft_version_id ?? null
  const draftQuery = useQuery({ queryKey: ['scoring-model-version', draftId], queryFn: () => getScoringModelVersion(draftId!), enabled: !!draftId, retry: false })

  const [criteria, setCriteria] = useState<ScoringCriterion[]>([])
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [seededId, setSeededId] = useState<string | null>(null)
  // dirtyRef: true only after a user edit — never set during seeding, never reset.
  // Autosave checks this so it never fires on initial load or re-seed.
  const dirtyRef = useRef(false)
  const [justPublished, setJustPublished] = useState(false)

  const publishMutation = useMutation({
    mutationFn: () => publishScoringModel(modelId),
    onSuccess: () => {
      dirtyRef.current = false
      setJustPublished(true)
      void queryClient.invalidateQueries({ queryKey: ['scoring-model', modelId] })
    },
  })

  const forkMutation = useMutation({
    mutationFn: () => forkScoringModelDraft(modelId, modelQuery.data!.published_version_ids.at(-1)!),
    onSuccess: (v) => {
      dirtyRef.current = false
      setJustPublished(false)
      setCriteria(v.criteria)
      void queryClient.invalidateQueries({ queryKey: ['scoring-model', modelId] })
    },
  })

  // Render-time reset keyed on version id (React "adjust state when source changes" pattern).
  if (draftQuery.data && draftQuery.data.version_id !== seededId) {
    setSeededId(draftQuery.data.version_id)
    setCriteria(draftQuery.data.criteria)
  }

  // All edits flow through here so autosave can dirty-track them.
  function updateCriteria(next: ScoringCriterion[]) { dirtyRef.current = true; setCriteria(next) }

  // debounced autosave — only after the draft is seeded and a user edit has occurred.
  useEffect(() => {
    if (!draftId || seededId === null || !dirtyRef.current) return
    const t = setTimeout(() => { void saveScoringModelDraft(modelId, criteria).catch(() => {}) }, 400)
    return () => clearTimeout(t)
  }, [criteria, modelId, draftId, seededId])

  function addCriterion() { const c = newCriterion(); updateCriteria([...criteria, c]); setSelectedId(c.criterion_id) }
  function move(idx: number, dir: -1 | 1) {
    const j = idx + dir
    if (j < 0 || j >= criteria.length) return
    const next = [...criteria]
    ;[next[idx], next[j]] = [next[j], next[idx]]
    updateCriteria(next)
  }
  function remove(id: string) { updateCriteria(criteria.filter((c) => c.criterion_id !== id)); if (selectedId === id) setSelectedId(null) }

  const readOnly = !draftId || justPublished
  const selected = criteria.find((c) => c.criterion_id === selectedId)

  return (
    <AppShell rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><Link href="/programs">Programs</Link></nav>}>
      <div className="grid gap-6">
        {modelQuery.isLoading ? (
          <Spinner label="Loading scoring model…" />
        ) : modelQuery.isError ? (
          <StateBlock variant="error" message="Could not load this scoring model." />
        ) : (
          <>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <h1 id="builder-heading" className="text-2xl font-semibold">
                Scoring model builder{modelQuery.data ? ` — ${modelQuery.data.name}` : ''}
              </h1>
              <div className="flex items-center gap-3">
                <span
                  data-status={draftId && !justPublished ? 'draft' : 'published'}
                  className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                >
                  {draftId && !justPublished ? `Draft v${draftQuery.data?.version ?? ''}` : 'Published (read-only)'}
                </span>
                {draftId && !justPublished && (
                  <Button loading={publishMutation.isPending} disabled={criteria.length === 0} onClick={() => publishMutation.mutate()}>
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
              <div aria-label="Criterion palette" className="grid h-fit gap-2 rounded-lg border border-border p-3">
                <h2 className="text-sm font-medium text-muted-foreground">Add criterion</h2>
                <Button variant="secondary" disabled={readOnly} onClick={addCriterion}>
                  Add criterion
                </Button>
              </div>
              {/* canvas — data-version-id reflects the seeded draft version (tests wait on it) */}
              <div className="rounded-lg border border-border p-3" data-version-id={seededId ?? ''}>
                <ScoringModelCanvas
                  criteria={criteria}
                  selectedId={selectedId}
                  readOnly={readOnly}
                  onSelect={setSelectedId}
                  onMove={move}
                  onRemove={remove}
                />
              </div>
              {/* inspector — minimal read-only summary; Task 4 replaces this */}
              <div aria-label="Criterion settings" className="rounded-lg border border-border p-3">
                {selected ? (
                  <>
                    <h2 className="text-lg font-semibold">{selected.label}</h2>
                    <p className="text-sm text-muted-foreground">max {selected.max_points} pts</p>
                  </>
                ) : (
                  <p className="text-sm text-muted-foreground">Select a criterion to view its settings.</p>
                )}
              </div>
            </section>
          </>
        )}
      </div>
    </AppShell>
  )
}
