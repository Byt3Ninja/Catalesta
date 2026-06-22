import { useQueryClient } from '@tanstack/react-query'
import { Button } from '../components/Button'

/**
 * Landing page after the backend verifies an email (FR-007). The signed verify link
 * hits the backend, which marks the email verified and redirects here — so this page
 * is purely informational. Continue refreshes the session (now verified) and proceeds.
 */
export function EmailVerifiedPage() {
  const queryClient = useQueryClient()

  const onContinue = () => {
    // Fire-and-forget: mark the session stale, then hard-navigate. The refetch need
    // not complete here — the gate refetches the (now verified) session on next load.
    void queryClient.invalidateQueries({ queryKey: ['session'] })
    window.location.assign('/')
  }

  return (
    <section aria-labelledby="verified-heading">
      <h1 id="verified-heading">Email verified</h1>
      <p>Your email is verified. You can now continue to your workspace.</p>
      <Button onClick={onContinue}>Continue</Button>
    </section>
  )
}
