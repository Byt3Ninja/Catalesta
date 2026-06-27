import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from './Link'
import { search } from '../api/search'
import { SEARCH_CATEGORY_LABEL } from '../schemas/search'

/**
 * Header global search: a debounced type-ahead whose results surface is a
 * categorized dropdown. Fetches only for a non-empty query (no idle network),
 * so it is safe to mount in the shared AppShell header.
 */
export function GlobalSearch() {
  const [input, setInput] = useState('')
  const [debounced, setDebounced] = useState('')

  // 250ms debounce via a single timer, no lib.
  useEffect(() => {
    const t = setTimeout(() => setDebounced(input.trim()), 250)
    return () => clearTimeout(t)
  }, [input])

  const query = useQuery({
    queryKey: ['search', debounced],
    queryFn: () => search(debounced),
    enabled: debounced.length > 0,
    retry: false,
  })

  const groups = query.data ?? []
  const open = debounced.length > 0

  return (
    <div className="relative hidden sm:block">
      <input
        type="search"
        aria-label="Search"
        placeholder="Search…"
        value={input}
        onChange={(e) => setInput(e.target.value)}
        className="h-8 w-48 rounded-md border border-input bg-background px-2 text-sm"
      />
      {open ? (
        <div className="absolute end-0 z-50 mt-1 w-72 rounded-md border border-border bg-popover p-2 shadow-md" role="region" aria-label="Search results">
          {query.isLoading ? (
            <p className="px-2 py-1 text-sm text-muted-foreground">Searching…</p>
          ) : groups.length === 0 ? (
            <p className="px-2 py-1 text-sm text-muted-foreground">No matches.</p>
          ) : (
            groups.map((g) => (
              <div key={g.category} className="py-1">
                <p className="px-2 text-xs font-medium text-muted-foreground">{SEARCH_CATEGORY_LABEL[g.category]}</p>
                {g.items.map((item) => (
                  <Link key={item.id} href={item.href} className="block rounded px-2 py-1 text-sm hover:bg-accent">
                    {item.label}
                    {item.sublabel ? <span className="text-muted-foreground"> — {item.sublabel}</span> : null}
                  </Link>
                ))}
              </div>
            ))
          )}
        </div>
      ) : null}
    </div>
  )
}
