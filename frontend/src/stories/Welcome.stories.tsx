import type { Meta, StoryObj } from '@storybook/react-vite'

/**
 * Seed story proving the Storybook + a11y toolchain is wired up. Replace with real
 * design-system component stories as `src/components/` is built out.
 */
function Welcome({ title }: { title: string }) {
  return (
    <section>
      <h1>{title}</h1>
      <p>Storybook is configured for the Catalesta frontend.</p>
    </section>
  )
}

const meta = {
  title: 'Example/Welcome',
  component: Welcome,
  args: { title: 'Catalesta' },
} satisfies Meta<typeof Welcome>

export default meta

type Story = StoryObj<typeof meta>

export const Default: Story = {}
