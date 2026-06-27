import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ChevronDown } from 'lucide-react'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from './ui/dropdown-menu'
import { Button } from './ui/button'
import { useActiveRole, setActiveRole } from '../app/active-role'
import { listMyRoles } from '../api/roles'
import { listPrograms } from '../api/programs'
import { listCohorts } from '../api/cohorts'
import type { RoleKey } from '../schemas/roles'

/**
 * Role / program / cohort context picker (org shown as a static label).
 * Role switches the active-role store (re-renders the shell). Program and cohort
 * are scope pickers fetched lazily — only when their menu opens (`enabled`) — so
 * mounting the shell fires no extra request beyond the roles query the shell has
 * always made. ponytail: program/cohort selection is local UI state with no
 * downstream consumer yet; wire it to an active-scope store when a surface needs it.
 */
export function ContextSelector() {
  const activeRole = useActiveRole()
  const rolesQuery = useQuery({ queryKey: ['me-roles'], queryFn: listMyRoles, retry: false })
  const roles = rolesQuery.data ?? []
  const activeLabel = roles.find((r) => r.key === activeRole)?.label ?? 'Program Manager'

  const [programOpen, setProgramOpen] = useState(false)
  const [cohortOpen, setCohortOpen] = useState(false)
  const [programId, setProgramId] = useState<string | null>(null)
  const [cohortId, setCohortId] = useState<string | null>(null)

  const programsQuery = useQuery({ queryKey: ['programs'], queryFn: listPrograms, retry: false, enabled: programOpen })
  const cohortsQuery = useQuery({ queryKey: ['cohorts'], queryFn: listCohorts, retry: false, enabled: cohortOpen })
  const programs = programsQuery.data ?? []
  const cohorts = cohortsQuery.data ?? []
  const programLabel = programs.find((p) => p.id === programId)?.name ?? 'All programs'
  const cohortLabel = cohorts.find((c) => c.id === cohortId)?.name ?? 'All cohorts'

  return (
    <div className="flex flex-wrap items-center gap-2 text-sm" aria-label="Active context">
      <span className="text-muted-foreground">Acme Incubator</span>

      {roles.length > 1 ? (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" size="sm" className="gap-1">
              {activeLabel} <ChevronDown className="size-3" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {roles.map((r) => (
              <DropdownMenuItem key={r.key} onClick={() => setActiveRole(r.key as RoleKey)}>
                {r.label}
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      ) : (
        <span className="font-medium text-foreground">{activeLabel}</span>
      )}

      <DropdownMenu open={programOpen} onOpenChange={setProgramOpen}>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" size="sm" className="gap-1">
            {programLabel} <ChevronDown className="size-3" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onClick={() => setProgramId(null)}>All programs</DropdownMenuItem>
          {programs.map((p) => (
            <DropdownMenuItem key={p.id} onClick={() => setProgramId(p.id)}>
              {p.name}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>

      <DropdownMenu open={cohortOpen} onOpenChange={setCohortOpen}>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" size="sm" className="gap-1">
            {cohortLabel} <ChevronDown className="size-3" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onClick={() => setCohortId(null)}>All cohorts</DropdownMenuItem>
          {cohorts.map((c) => (
            <DropdownMenuItem key={c.id} onClick={() => setCohortId(c.id)}>
              {c.name}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  )
}
