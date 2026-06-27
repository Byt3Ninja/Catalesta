import type { Meta, StoryObj } from '@storybook/react-vite'
import { CohortSetupWizard } from './CohortSetupWizard'

const meta = {
  title: 'Pages/CohortSetupWizard',
  component: CohortSetupWizard,
  args: { programId: 'prog_1' },
} satisfies Meta<typeof CohortSetupWizard>

export default meta
type Story = StoryObj<typeof meta>

/** Step 1 — create the cohort. Later steps advance on interaction. */
export const Default: Story = {}
