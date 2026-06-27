import type { Meta, StoryObj } from '@storybook/react-vite'
import { RoleSidebar } from './RoleSidebar'
import { setActiveRole } from '../app/active-role'

const meta = {
  title: 'Components/RoleSidebar',
  component: RoleSidebar,
} satisfies Meta<typeof RoleSidebar>

export default meta
type Story = StoryObj<typeof meta>

/** Program Manager — 10-item nav including Programs and operator sections. */
export const ProgramManager: Story = {
  render: () => { setActiveRole('program_manager'); return <RoleSidebar /> },
}

/** Founder — 9 items: My Application, Sessions, My Startup, etc. */
export const Founder: Story = {
  render: () => { setActiveRole('founder'); return <RoleSidebar /> },
}

/** Mentor — shorter nav: My Mentees, Sessions, Availability, Messages. */
export const Mentor: Story = {
  render: () => { setActiveRole('mentor'); return <RoleSidebar /> },
}

/** Org Admin — Members, Roles, Settings. */
export const OrgAdmin: Story = {
  render: () => { setActiveRole('org_admin'); return <RoleSidebar /> },
}
