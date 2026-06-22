import { useMutation } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { resendVerification, NativeAuthError } from '../api/auth'

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
    <section aria-labelledby="verify-heading">
      <h1 id="verify-heading">Verify your email</h1>
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
    </section>
  )
}
