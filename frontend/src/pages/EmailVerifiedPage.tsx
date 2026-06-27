import { useQueryClient } from '@tanstack/react-query'
import { Button } from '../components/Button'
import { Card, CardContent, CardHeader } from '../components/ui/card'

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
    <main className="grid min-h-dvh place-items-center bg-background px-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <h1 id="verified-heading" className="leading-none font-semibold">Email verified</h1>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <p>Your email is verified. You can now continue to your workspace.</p>
          <Button onClick={onContinue}>Continue</Button>
        </CardContent>
      </Card>
    </main>
  )
}
