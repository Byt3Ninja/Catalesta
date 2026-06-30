import { useMemo, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { Link } from '../components/Link'
import { ProgramStatusBadge } from '../components/ProgramStatusBadge'
import { ProgramTypeBadge } from '../components/ProgramTypeBadge'
import { createProgram, listPrograms } from '../api/programs'
import { listCohorts } from '../api/cohorts'
import { groupSummariesByProgram } from '../lib/programSummary'
import { CreateProgramError, PROGRAM_TYPES, PROGRAM_TYPE_LABEL, type ProgramType } from '../schemas/programs'
import type { Organization } from '../schemas/organizations'

const COHORT_STATUS_LABEL: Record<'draft' | 'open' | 'closed' | 'completed', string> = {
  draft: 'Draft', open: 'Open', closed: 'Closed', completed: 'Completed',
}
const STATUS_TABS = ['all', 'draft', 'published', 'archived', 'closed'] as const
type StatusTab = (typeof STATUS_TABS)[number]
const TAB_LABEL: Record<StatusTab, string> = {
  all: 'All', draft: 'Draft', published: 'Published', archived: 'Archived', closed: 'Closed',
}
const PAGE_SIZE = 10

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

export function ProgramsPage({ organization }: { organization: Organization }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [type, setType] = useState<ProgramType | ''>('')
  const [tab, setTab] = useState<StatusTab>('all')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const programsQuery = useQuery({ queryKey: ['programs'], queryFn: listPrograms, retry: false })
  const cohortsQuery = useQuery({ queryKey: ['cohorts'], queryFn: listCohorts, retry: false })

  const createMutation = useMutation({
    mutationFn: () => createProgram(name.trim(), {
      description: description.trim() || undefined,
      type: type || undefined,
    }),
    onSuccess: () => {
      setName(''); setDescription(''); setType('')
      return queryClient.invalidateQueries({ queryKey: ['programs'] })
    },
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (name.trim().length === 0) return
    createMutation.mutate()
  }

  const programs = useMemo(() => programsQuery.data ?? [], [programsQuery.data])
  const summaries = useMemo(
    () => groupSummariesByProgram(cohortsQuery.data ?? []),
    [cohortsQuery.data],
  )

  const cohortsUnavailable = cohortsQuery.isLoading || cohortsQuery.isError

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    return programs
      .filter((p) => (tab === 'all' ? true : p.status === tab))
      .filter((p) => (q ? p.name.toLowerCase().includes(q) : true))
  }, [programs, tab, search])

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const currentPage = Math.min(page, pageCount)
  const pageRows = filtered.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE)

  return (
    <AppShell
      rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><a href="/programs" className="font-medium">Programs</a></nav>}
      pageHeader={
        <div className="flex items-center justify-between">
          <div>
            <h1 id="programs-heading" className="text-2xl font-semibold"><bdi>{organization.name}</bdi> — Programs</h1>
            <p className="mt-0.5 text-sm text-muted-foreground">{programs.length} programs</p>
          </div>
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
            <div className="grid gap-1">
              <label htmlFor="program-type" className="text-sm font-medium">Program type</label>
              <select
                id="program-type"
                name="program-type"
                value={type}
                onChange={(e) => setType(e.target.value as ProgramType | '')}
                className="h-9 rounded-md border border-border bg-background px-3 text-sm"
              >
                <option value="">No type</option>
                {PROGRAM_TYPES.map((t) => <option key={t} value={t}>{PROGRAM_TYPE_LABEL[t]}</option>)}
              </select>
            </div>
          </FormLayout>
          <div>
            <Button type="submit" loading={createMutation.isPending} disabled={name.trim().length === 0}>Create program</Button>
          </div>
        </form>

        {/* Status filter tabs */}
        <div role="tablist" aria-label="Filter by status" className="flex flex-wrap gap-1 border-b border-border">
          {STATUS_TABS.map((t) => (
            <button
              key={t}
              role="tab"
              aria-selected={tab === t}
              onClick={() => { setTab(t); setPage(1) }}
              className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${
                tab === t ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
              }`}
            >
              {TAB_LABEL[t]}
            </button>
          ))}
        </div>

        {/* Search */}
        <div className="max-w-xs">
          <label htmlFor="programs-search" className="sr-only">Search programs</label>
          <input
            id="programs-search"
            type="search"
            placeholder="Search programs…"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
            className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
          />
        </div>

        <div className="grid gap-3">
          <h2 id="programs-list-heading" className="sr-only">Your programs</h2>
          {programsQuery.isLoading ? (
            <Spinner label="Loading programs…" />
          ) : programsQuery.isError ? (
            <StateBlock variant="error" message="We could not load your programs." action={<Button onClick={() => programsQuery.refetch()}>Try again</Button>} />
          ) : programs.length === 0 ? (
            <StateBlock variant="empty" message="No programs yet. Create your first program above." />
          ) : filtered.length === 0 ? (
            <StateBlock variant="empty" message="No programs match your filters." />
          ) : (
            <>
              <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[760px] text-sm" aria-labelledby="programs-list-heading">
                  <thead>
                    <tr className="border-b border-border bg-secondary/40 text-left text-xs uppercase tracking-wider text-muted-foreground">
                      <th scope="col" className="px-4 py-3 font-medium">Program</th>
                      <th scope="col" className="px-4 py-3 font-medium">Cohorts</th>
                      <th scope="col" className="px-4 py-3 font-medium">Submissions</th>
                      <th scope="col" className="px-4 py-3 font-medium">Capacity</th>
                      <th scope="col" className="px-4 py-3 font-medium">Status</th>
                      <th scope="col" className="px-4 py-3 font-medium">Active cohort</th>
                      <th scope="col" className="px-4 py-3 font-medium">Dates</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pageRows.map((program) => {
                      const s = summaries[program.id]
                      return (
                        <tr key={program.id} className="border-b border-border last:border-0 hover:bg-secondary/20">
                          <td className="px-4 py-3">
                            <span className="flex flex-wrap items-center gap-2">
                              <Link href={`/programs/${program.id}`}><bdi>{program.name}</bdi></Link>
                              <ProgramTypeBadge type={program.type} />
                            </span>
                          </td>
                          <td className="px-4 py-3 text-muted-foreground">{cohortsUnavailable ? '—' : s?.cohortCount ?? 0}</td>
                          <td className="px-4 py-3">{cohortsUnavailable ? '—' : s?.submissions ?? 0}</td>
                          <td className="px-4 py-3 text-muted-foreground">{cohortsUnavailable ? '—' : s?.capacity != null ? `— / ${s.capacity}` : '—'}</td>
                          <td className="px-4 py-3"><ProgramStatusBadge status={program.status} /></td>
                          <td className="px-4 py-3 text-muted-foreground">
                            {cohortsUnavailable ? '—' : s?.activeCohortStatus ? `Cohort: ${COHORT_STATUS_LABEL[s.activeCohortStatus]}` : '—'}
                          </td>
                          <td className="px-4 py-3 text-xs text-muted-foreground">
                            {cohortsUnavailable ? '—' : s?.dateRange ? `${fmtDate(s.dateRange.start)} → ${fmtDate(s.dateRange.end)}` : '—'}
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              <div className="flex items-center justify-between text-sm text-muted-foreground">
                <span>Showing {(currentPage - 1) * PAGE_SIZE + 1}–{Math.min(currentPage * PAGE_SIZE, filtered.length)} of {filtered.length}</span>
                <div className="flex items-center gap-2">
                  <Button variant="secondary" disabled={currentPage <= 1} onClick={() => setPage(currentPage - 1)}>Previous</Button>
                  <span>Page {currentPage} of {pageCount}</span>
                  <Button variant="secondary" disabled={currentPage >= pageCount} onClick={() => setPage(currentPage + 1)}>Next</Button>
                </div>
              </div>
            </>
          )}
        </div>
      </section>
    </AppShell>
  )
}

function renderCreateError(error: unknown) {
  if (!error) return null
  if (error instanceof CreateProgramError) {
    if (error.code === 'VALIDATION') return <Banner variant="error">{error.message}</Banner>
    if (error.code === 'UNAUTHENTICATED') return <Banner variant="error">Your session expired. Please sign in again.</Banner>
  }
  return <Banner variant="error">We could not create the program. Please try again.</Banner>
}
