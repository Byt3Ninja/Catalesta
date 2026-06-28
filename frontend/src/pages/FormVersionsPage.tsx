import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { StateBlock } from '../components/StateBlock'
import { VersionHistoryList } from '../components/VersionHistoryList'
import { VersionCompare } from '../components/VersionCompare'
import { listFormVersions, getFormVersion } from '../api/forms'
import type { FormVersion } from '../schemas/forms'

function fieldsToLines(fields: FormVersion['fields']): string[] {
  return fields.map((f, i) => `${i + 1}. ${f.label} (${f.type})`)
}

export function FormVersionsPage({ formId }: { formId: string }) {
  const [selected, setSelected] = useState<string[]>([])

  const versionsQuery = useQuery({
    queryKey: ['form-versions', formId],
    queryFn: () => listFormVersions(formId),
    retry: false,
  })

  // Fetch both selected versions when exactly two are chosen
  const leftId = selected[0] ?? null
  const rightId = selected[1] ?? null

  const leftQuery = useQuery({
    queryKey: ['form-version', leftId],
    queryFn: () => getFormVersion(leftId!),
    enabled: selected.length === 2 && !!leftId,
    retry: false,
  })

  const rightQuery = useQuery({
    queryKey: ['form-version', rightId],
    queryFn: () => getFormVersion(rightId!),
    enabled: selected.length === 2 && !!rightId,
    retry: false,
  })

  function handleSelect(id: string) {
    setSelected((prev) => {
      if (prev.includes(id)) {
        return prev.filter((s) => s !== id)
      }
      if (prev.length >= 2) {
        // Replace oldest selection (first in array) with the new one
        return [prev[1], id]
      }
      return [...prev, id]
    })
  }

  const versions = versionsQuery.data ?? []
  const versionItems = versions.map((v) => ({
    id: v.id,
    version: v.version,
    status: v.status,
    published_at: v.published_at,
  }))

  const showCompare =
    selected.length === 2 &&
    leftQuery.data &&
    rightQuery.data

  return (
    <AppShell>
      <div className="grid gap-6">
        <h1 className="text-xl font-semibold">Version history</h1>

        {versionsQuery.isLoading && <p>Loading versions…</p>}
        {versionsQuery.isError && (
          <StateBlock
            title="Could not load versions"
            description="Please try again."
          />
        )}

        {!versionsQuery.isLoading && !versionsQuery.isError && (
          <VersionHistoryList
            versions={versionItems}
            selectedIds={selected.length === 2 ? [selected[0], selected[1]] : null}
            onSelect={handleSelect}
          />
        )}

        {selected.length === 2 && (leftQuery.isLoading || rightQuery.isLoading) && (
          <p>Loading comparison…</p>
        )}

        {showCompare && (
          <VersionCompare
            left={{
              label: `Version ${leftQuery.data!.version}`,
              lines: fieldsToLines(leftQuery.data!.fields),
            }}
            right={{
              label: `Version ${rightQuery.data!.version}`,
              lines: fieldsToLines(rightQuery.data!.fields),
            }}
          />
        )}
      </div>
    </AppShell>
  )
}
