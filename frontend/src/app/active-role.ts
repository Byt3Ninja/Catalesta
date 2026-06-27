import { useSyncExternalStore } from 'react'
import type { RoleKey } from '../schemas/roles'

let activeRole: RoleKey = 'program_manager'
const listeners = new Set<() => void>()

export function getActiveRole(): RoleKey {
  return activeRole
}

export function setActiveRole(key: RoleKey): void {
  if (key === activeRole) return
  activeRole = key
  listeners.forEach((l) => l())
}

export function subscribe(cb: () => void): () => void {
  listeners.add(cb)
  return () => listeners.delete(cb)
}

/** Re-renders the caller whenever the active role changes. */
export function useActiveRole(): RoleKey {
  return useSyncExternalStore(subscribe, getActiveRole, getActiveRole)
}
