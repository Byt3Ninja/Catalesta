import type { Meta, StoryObj } from '@storybook/react-vite'
import { ActionCard } from './ActionCard'
import type { ActionItem } from '../schemas/actionCenter'

const meta = {
  title: 'Components/ActionCard',
  component: ActionCard,
} satisfies Meta<typeof ActionCard>

export default meta
type Story = StoryObj<typeof meta>

const BASE: ActionItem = {
  id: 'demo-1',
  section: 'required_actions',
  what: 'Review 4 delayed applications',
  why: 'Past the screening SLA',
  deadline: 'Today',
  who: 'You',
  href: '/preview/applicants',
  blocker: null,
}

/** Full card: what, why, deadline, owner, link — no blocker. */
export const RequiredAction: Story = {
  args: { item: BASE },
}

/** Blocked card: shows "Blocked by" field and no link. */
export const Blocked: Story = {
  args: {
    item: {
      ...BASE,
      id: 'demo-2',
      section: 'blocked_items',
      what: 'Approve stage transition',
      why: 'Cohort cannot advance',
      deadline: null,
      href: null,
      blocker: 'Missing evaluator coverage',
    },
  },
}

/** Minimal card: only what + why; no deadline, owner, link, or blocker. */
export const Minimal: Story = {
  args: {
    item: {
      ...BASE,
      id: 'demo-3',
      section: 'progress',
      what: 'Startup profile 60% complete',
      why: 'Add traction metrics',
      deadline: null,
      who: null,
      href: null,
      blocker: null,
    },
  },
}

/** RTL text (Arabic content). */
export const RTL: Story = {
  args: {
    item: {
      ...BASE,
      id: 'demo-4',
      what: 'مراجعة 4 طلبات متأخرة',
      why: 'تجاوزت مستوى الخدمة المتفق عليه',
      deadline: 'اليوم',
      who: 'أنت',
    },
  },
}
