import { useState } from 'react'
import { AppShell } from '../components/AppShell'
import { RoleSidebar } from '../components/RoleSidebar'
import { Button } from '../components/Button'
import { Link } from '../components/Link'

const CHANNELS = [
  { key: 'email', label: 'Email' },
  { key: 'in_app', label: 'In-app' },
] as const
const FREQUENCIES = ['immediate', 'daily', 'weekly'] as const

/** Notification preferences — presentational only (mock persistence, slice 1b). */
export function NotificationPreferencesPage() {
  const [channels, setChannels] = useState<Record<string, boolean>>({ email: true, in_app: true })
  const [frequency, setFrequency] = useState<string>('immediate')
  const [saved, setSaved] = useState(false)

  return (
    <AppShell rail={<RoleSidebar />}>
      <section aria-labelledby="prefs-heading" className="grid max-w-md gap-6">
        <div>
          <h1 id="prefs-heading" className="text-2xl font-semibold">Notification preferences</h1>
          <p className="text-muted-foreground"><Link href="/notifications">Back to notifications</Link></p>
        </div>

        <fieldset className="grid gap-2">
          <legend className="text-sm font-medium">Channels</legend>
          {CHANNELS.map((c) => (
            <label key={c.key} className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={channels[c.key]}
                onChange={(e) => { setChannels((prev) => ({ ...prev, [c.key]: e.target.checked })); setSaved(false) }}
              />
              {c.label}
            </label>
          ))}
        </fieldset>

        <label className="grid gap-1">
          <span className="text-sm font-medium">Frequency</span>
          <select
            className="rounded-md border border-input bg-background px-2 py-1"
            value={frequency}
            onChange={(e) => { setFrequency(e.target.value); setSaved(false) }}
          >
            {FREQUENCIES.map((f) => <option key={f} value={f}>{f[0].toUpperCase() + f.slice(1)}</option>)}
          </select>
        </label>

        <div className="flex items-center gap-3">
          <Button onClick={() => setSaved(true)}>Save preferences</Button>
          {saved ? <p role="status" className="text-sm text-muted-foreground">Preferences saved.</p> : null}
        </div>
      </section>
    </AppShell>
  )
}
