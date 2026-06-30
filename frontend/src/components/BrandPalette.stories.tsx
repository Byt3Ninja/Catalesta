import type { Meta, StoryObj } from '@storybook/react-vite'

type Swatch = { name: string; cls: string; hex: string; use: string; aa: string }

const SWATCHES: Swatch[] = [
  { name: '--brand-strong', cls: 'bg-brand-strong', hex: '#0d7a74', use: 'Primary fills, links, text-primary, focus ring', aa: 'AA — white text ≈5.2:1' },
  { name: '--brand', cls: 'bg-brand', hex: '#1bbcb4', use: 'Decorative tints, dots, progress (behind dark text only)', aa: 'Decorative-only — fails text/ring floors' },
  { name: 'navy ink', cls: 'bg-foreground', hex: '#0d1b2a', use: 'Headings / body ink (light theme)', aa: 'AA — ≈16:1 on white' },
  { name: '--brand-orange-strong', cls: 'bg-brand-orange-strong', hex: '#c2410c', use: 'Orange CTA / urgent fills (when a surface needs one)', aa: 'AA — white text ≈5.2:1' },
  { name: '--brand-orange', cls: 'bg-brand-orange', hex: '#f26b3a', use: 'Decorative orange tints / dots', aa: 'Decorative-only — fails text floor' },
]

function Palette() {
  return (
    <div className="grid gap-3 p-4 text-foreground" style={{ maxWidth: 560 }}>
      <h2 className="text-lg font-semibold">Catalesta brand palette</h2>
      {SWATCHES.map((s) => (
        <div key={s.name} className="flex items-center gap-4 rounded-md border border-border p-3">
          <span className={`${s.cls} inline-block h-10 w-10 rounded-md`} aria-hidden="true" />
          <span className="grid gap-0.5 text-sm">
            <span className="font-medium">{s.name} <span className="text-muted-foreground">{s.hex}</span></span>
            <span className="text-muted-foreground">{s.use}</span>
            <span className="text-xs text-muted-foreground">{s.aa}</span>
          </span>
        </div>
      ))}
    </div>
  )
}

const meta = {
  title: 'Design System/Brand palette',
  component: Palette,
} satisfies Meta<typeof Palette>
export default meta
type Story = StoryObj<typeof meta>

export const Swatches: Story = {}
