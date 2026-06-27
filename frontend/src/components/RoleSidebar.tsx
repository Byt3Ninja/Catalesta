import { Link } from './Link'
import { useActiveRole } from '../app/active-role'
import { ROLE_NAV } from '../app/role-nav'

/** Role-scoped sidebar nav. Re-renders when the active role changes. */
export function RoleSidebar() {
  const role = useActiveRole()
  return (
    <nav aria-label="Sections" className="grid gap-1 text-sm">
      {ROLE_NAV[role].map((item) => (
        <Link key={item.href + item.label} href={item.href} className="px-2 py-1">
          {item.label}
        </Link>
      ))}
    </nav>
  )
}
