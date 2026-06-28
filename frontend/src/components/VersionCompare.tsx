interface Side { label: string; lines: string[] }

/** Content-agnostic two-column diff. Lines present only on the right are 'added',
 *  only on the left are 'removed', present on both are 'unchanged'. */
export function VersionCompare({ left, right }: { left: Side; right: Side }) {
  const leftSet = new Set(left.lines)
  const rightSet = new Set(right.lines)
  function cls(diff: string) { return diff === 'added' ? 'bg-accent text-accent-foreground' : diff === 'removed' ? 'text-muted-foreground line-through' : '' }
  return (
    <div className="grid grid-cols-2 gap-4 text-sm">
      <div><h3 className="mb-2 font-medium">{left.label}</h3>
        <ul className="grid gap-1">{left.lines.map((l, i) => { const d = rightSet.has(l) ? 'unchanged' : 'removed'; return <li key={i} data-diff={d} className={`rounded px-2 py-1 ${cls(d)}`}>{l}</li> })}</ul>
      </div>
      <div><h3 className="mb-2 font-medium">{right.label}</h3>
        <ul className="grid gap-1">{right.lines.map((l, i) => { const d = leftSet.has(l) ? 'unchanged' : 'added'; return <li key={i} data-diff={d} className={`rounded px-2 py-1 ${cls(d)}`}>{l}</li> })}</ul>
      </div>
    </div>
  )
}
