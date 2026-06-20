import type { Meta, StoryObj } from '@storybook/react-vite'
import { AppShell } from './AppShell'

const meta = { title: 'Primitives/AppShell', component: AppShell, args: { children: null } } satisfies Meta<typeof AppShell>
export default meta
type Story = StoryObj<typeof meta>

export const WithRail: Story = {
  render: () => (
    <AppShell rail={<nav aria-label="Sections">Context rail</nav>}>
      <h1>Work area</h1>
      <p>Two-zone operator console; mirrors automatically under RTL.</p>
    </AppShell>
  ),
}
