import { useMutation } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { resendVerification, NativeAuthError } from '../api/auth'
import { Card, CardContent, CardHeader } from '../components/ui/card'

/**
 * Interstitial for an unverified native account (FR-007). The no-org gate renders this
 * before onboarding when the session reports email_verified === false. Startup Gate
 * accounts are auto-verified and never see it.
 */
export function VerifyEmailNotice() {
  const mutation = useMutation({ mutationFn: () => resendVerification() })

  const rateLimited =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'RATE_LIMITED'

  return (
    <main className="grid min-h-dvh place-items-center bg-background px-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <h1 id="verify-heading" className="leading-none font-semibold">Verify your email</h1>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <p>We've sent a verification link to your email. Click it to finish setting up your account.</p>
          {mutation.isSuccess ? (
            <Banner variant="info">We've sent another verification email.</Banner>
          ) : null}
          {rateLimited ? (
            <Banner variant="error">Too many attempts. Please try again shortly.</Banner>
          ) : null}
          <Button onClick={() => mutation.mutate()} loading={mutation.isPending}>
            Resend verification email
          </Button>
        </CardContent>
      </Card>
    </main>
  )
}
