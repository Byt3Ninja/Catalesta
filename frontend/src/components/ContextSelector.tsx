import { useQuery } from '@tanstack/react-query'
import { ChevronDown } from 'lucide-react'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from './ui/dropdown-menu'
import { Button } from './ui/button'
import { useActiveRole, setActiveRole } from '../app/active-role'
import { listMyRoles } from '../api/roles'
import type { RoleKey } from '../schemas/roles'

/** Role / org context. Role switches the active-role store (re-renders shell). */
export function ContextSelector() {
  const activeRole = useActiveRole()
  const rolesQuery = useQuery({ queryKey: ['me-roles'], queryFn: listMyRoles, retry: false })
  const roles = rolesQuery.data ?? []
  const activeLabel = roles.find((r) => r.key === activeRole)?.label ?? 'Program Manager'

  return (
    <div className="flex items-center gap-2 text-sm" aria-label="Active context">
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
    </div>
  )
}
