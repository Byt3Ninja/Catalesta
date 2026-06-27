import type { Meta, StoryObj, Decorator } from '@storybook/react-vite'
import { RoleSidebar } from './RoleSidebar'
import { setActiveRole } from '../app/active-role'
import type { RoleKey } from '../schemas/roles'

const meta = {
  title: 'Components/RoleSidebar',
  component: RoleSidebar,
} satisfies Meta<typeof RoleSidebar>

export default meta
type Story = StoryObj<typeof meta>

// Set the active role from a decorator (runs before the story renders), not from
// render() — a render-time mutation of the module singleton leaks across stories.
// Each story sets its own role, so story order doesn't matter.
const withRole =
  (role: RoleKey): Decorator =>
  (Story) => {
    setActiveRole(role)
    return <Story />
  }

/** Program Manager — 10-item nav including Programs and operator sections. */
export const ProgramManager: Story = {
  decorators: [withRole('program_manager')],
  render: () => <RoleSidebar />,
}

/** Founder — 9 items: My Application, Sessions, My Startup, etc. */
export const Founder: Story = {
  decorators: [withRole('founder')],
  render: () => <RoleSidebar />,
}

/** Mentor — shorter nav: My Mentees, Sessions, Availability, Messages. */
export const Mentor: Story = {
  decorators: [withRole('mentor')],
  render: () => <RoleSidebar />,
}

/** Org Admin — Members, Roles, Settings. */
export const OrgAdmin: Story = {
  decorators: [withRole('org_admin')],
  render: () => <RoleSidebar />,
}
