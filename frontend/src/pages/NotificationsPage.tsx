import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { StateBlock } from '../components/StateBlock'
import { Spinner } from '../components/Loading'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { listNotifications, markNotificationRead, markAllNotificationsRead } from '../api/notifications'
import { NOTIFICATION_TYPES, NOTIFICATION_TYPE_LABEL, type NotificationType } from '../schemas/notifications'

type Filter = 'all' | NotificationType

/** Notifications center: list, type filter, mark single/all read, unread count. */
export function NotificationsPage() {
  const queryClient = useQueryClient()
  const [filter, setFilter] = useState<Filter>('all')
  const [pendingId, setPendingId] = useState<string | null>(null)
  const query = useQuery({ queryKey: ['notifications'], queryFn: listNotifications, retry: false })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['notifications'] })
  const markOne = useMutation({
    mutationFn: markNotificationRead,
    onMutate: (id) => setPendingId(id),
    onSettled: () => setPendingId(null),
    onSuccess: invalidate,
  })
  const markAll = useMutation({ mutationFn: markAllNotificationsRead, onSuccess: invalidate })

  const items = query.data ?? []
  const unread = items.filter((n) => n.read_at === null).length
  const shown = filter === 'all' ? items : items.filter((n) => n.type === filter)

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="notif-heading" className="grid gap-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 id="notif-heading" className="text-2xl font-semibold">Notifications</h1>
            {!query.isLoading && <p className="text-muted-foreground">{unread} unread</p>}
          </div>
          <div className="flex items-center gap-2">
            <Link href="/notifications/preferences" className="text-sm">Preferences</Link>
            <Button onClick={() => markAll.mutate()} disabled={unread === 0 || markAll.isPending}>Mark all read</Button>
          </div>
        </div>

        <div className="flex flex-wrap gap-2" role="group" aria-label="Filter by type">
          {(['all', ...NOTIFICATION_TYPES] as Filter[]).map((f) => (
            <Button key={f} variant={filter === f ? 'primary' : 'secondary'} onClick={() => setFilter(f)} aria-pressed={filter === f}>
              {f === 'all' ? 'All' : NOTIFICATION_TYPE_LABEL[f]}
            </Button>
          ))}
        </div>

        {query.isLoading ? (
          <Spinner label="Loading notifications…" />
        ) : query.isError ? (
          <StateBlock variant="error" message="We could not load your notifications." action={<Button onClick={() => query.refetch()}>Try again</Button>} />
        ) : shown.length === 0 ? (
          <StateBlock variant="empty" message={items.length === 0 ? 'No notifications yet.' : 'No notifications of this type.'} />
        ) : (
          <ul className="grid gap-2">
            {shown.map((n) => (
              <li key={n.id} className="rounded-md border border-border p-3" data-read={n.read_at !== null}>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-medium">
                      {n.read_at === null ? <span aria-label="Unread" className="me-2 inline-block size-2 rounded-full bg-primary align-middle" /> : null}
                      {n.title}
                    </p>
                    <p className="text-sm text-muted-foreground">{n.body}</p>
                    <p className="mt-1 text-xs text-muted-foreground">{NOTIFICATION_TYPE_LABEL[n.type]} · {n.created_at.slice(0, 10)}</p>
                    {n.href ? <Link href={n.href} className="text-sm">Open</Link> : null}
                  </div>
                  {n.read_at === null ? (
                    <Button variant="secondary" onClick={() => markOne.mutate(n.id)} disabled={pendingId === n.id}>Mark read</Button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    </AppShell>
  )
}
