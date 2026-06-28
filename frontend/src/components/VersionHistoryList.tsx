interface VersionItem { id: string; version: number; status: 'draft' | 'published'; published_at: string | null }

export function VersionHistoryList({ versions, selectedIds, onSelect }: { versions: VersionItem[]; selectedIds: [string, string] | null; onSelect: (id: string) => void }) {
  return (
    <ul aria-label="Version history" className="grid gap-2">
      {versions.map((v) => {
        const checked = selectedIds?.includes(v.id) ?? false
        return (
          <li key={v.id} className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
            <label className="flex items-center gap-2">
              <input type="checkbox" checked={checked} onChange={() => onSelect(v.id)} />
              <span className="font-medium">Version {v.version}</span>
              <span data-status={v.status} className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground">{v.status === 'published' ? 'Published' : 'Draft'}</span>
            </label>
            <span className="text-xs text-muted-foreground">{v.published_at ? v.published_at.slice(0, 10) : '—'}</span>
          </li>
        )
      })}
    </ul>
  )
}
