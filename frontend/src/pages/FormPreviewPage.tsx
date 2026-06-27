import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { FormRenderer } from '../components/FormRenderer'
import { getFormVersion } from '../api/forms'

export function FormPreviewPage({ versionId }: { versionId: string }) {
  const versionQuery = useQuery({ queryKey: ['form-version', versionId], queryFn: () => getFormVersion(versionId), retry: false })
  const [answers, setAnswers] = useState<Record<string, unknown>>({})
  const [rtl, setRtl] = useState(false)
  const v = versionQuery.data

  return (
    <AppShell
      rail={<nav aria-label="Sections" className="grid gap-1 text-sm"><Link href="/programs">Programs</Link></nav>}
      pageHeader={
        <div className="flex items-center justify-between">
          <h1 id="preview-heading" className="text-2xl font-semibold">Form preview{v ? ` — v${v.version}` : ''}</h1>
          <Button variant="secondary" onClick={() => setRtl((r) => !r)}>{rtl ? 'Left-to-right' : 'Right-to-left (RTL)'}</Button>
        </div>
      }
    >
      <section aria-labelledby="preview-heading" className="grid max-w-2xl gap-6">
        {versionQuery.isLoading ? (
          <Spinner label="Loading form…" />
        ) : versionQuery.isError || !v ? (
          <StateBlock variant="error" message="Could not load this form version." />
        ) : (
          <div dir={rtl ? 'rtl' : 'ltr'} className="rounded-lg border border-border p-6">
            <FormRenderer fields={v.fields} answers={answers} onChange={(id, val) => setAnswers((a) => ({ ...a, [id]: val }))} />
          </div>
        )}
      </section>
    </AppShell>
  )
}
