import { useMemo, useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { resetPassword, NativeAuthError } from '../api/auth'

/**
 * Choose a new password from a reset link (FR-007). The token+email arrive in the URL
 * (from the emailed link); they are submitted with the new password and never stored.
 */
export function ResetPasswordPage() {
  const { token, email } = useMemo(() => {
    const params = new URLSearchParams(window.location.search)
    return { token: params.get('token'), email: params.get('email') }
  }, [])

  const [password, setPassword] = useState('')

  const mutation = useMutation({
    mutationFn: () => resetPassword({ token: token ?? '', email: email ?? '', password }),
  })

  if (!token || !email) {
    return (
      <section aria-labelledby="reset-heading">
        <h1 id="reset-heading">Reset your password</h1>
        <Banner variant="error">
          This reset link is invalid or incomplete. <Link href="/forgot-password">Request a new one</Link>.
        </Banner>
      </section>
    )
  }

  if (mutation.isSuccess) {
    return (
      <section aria-labelledby="reset-heading">
        <h1 id="reset-heading">Password reset</h1>
        <p>Your password has been reset. You can now sign in.</p>
        <p>
          <Link href="/login">Go to sign in</Link>
        </p>
      </section>
    )
  }

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (password.length < 8) return
    mutation.mutate()
  }

  const rateLimited =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'RATE_LIMITED'
  const invalidToken =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'INVALID_RESET_TOKEN'
  const bannerError = mutation.error
    ? rateLimited
      ? 'Too many attempts. Please try again shortly.'
      : invalidToken
        ? mutation.error.message || 'This password reset token is invalid or has expired.'
        : 'We could not reset your password. Please try again.'
    : undefined

  return (
    <section aria-labelledby="reset-heading">
      <h1 id="reset-heading">Choose a new password</h1>
      {bannerError ? (
        <Banner variant="error">
          {bannerError}
          {invalidToken ? (
            <>
              {' '}
              <Link href="/forgot-password">Request a new link</Link>.
            </>
          ) : null}
        </Banner>
      ) : null}
      <form onSubmit={onSubmit} noValidate>
        <FormLayout>
          <Field
            label="New password"
            type="password"
            name="password"
            autoComplete="new-password"
            required
            value={password}
            help="At least 8 characters."
            onChange={(e) => setPassword(e.target.value)}
          />
        </FormLayout>
        <Button type="submit" loading={mutation.isPending} disabled={password.length < 8}>
          Reset password
        </Button>
      </form>
    </section>
  )
}
