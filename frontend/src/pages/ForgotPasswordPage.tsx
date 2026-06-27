import { useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { forgotPassword, NativeAuthError } from '../api/auth'
import { Card, CardContent, CardHeader } from '../components/ui/card'

/**
 * Request a password-reset link (FR-007). The endpoint always 200s, so on success we
 * show a neutral confirmation that never reveals whether the email is registered.
 */
export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')

  const mutation = useMutation({
    mutationFn: () => forgotPassword(email),
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (!email.trim()) return
    mutation.mutate()
  }

  if (mutation.isSuccess) {
    return (
      <main className="grid min-h-dvh place-items-center bg-background px-4">
        <Card className="w-full max-w-sm">
          <CardHeader>
            <h1 id="forgot-heading" className="leading-none font-semibold">Check your email</h1>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <p>If that email is registered, we've sent a link to reset your password.</p>
            <p>
              <Link href="/login">Back to sign in</Link>
            </p>
          </CardContent>
        </Card>
      </main>
    )
  }

  const rateLimited =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'RATE_LIMITED'

  return (
    <main className="grid min-h-dvh place-items-center bg-background px-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <h1 id="forgot-heading" className="leading-none font-semibold">Reset your password</h1>
          <p className="text-sm text-muted-foreground">Enter your email and we'll send a reset link.</p>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {rateLimited ? (
            <Banner variant="error">Too many attempts. Please try again shortly.</Banner>
          ) : null}
          <form onSubmit={onSubmit} noValidate>
            <FormLayout>
              <Field
                label="Email"
                type="email"
                name="email"
                autoComplete="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </FormLayout>
            <Button type="submit" loading={mutation.isPending} disabled={!email.trim()}>
              Send reset link
            </Button>
          </form>
          <p>
            <Link href="/login">Back to sign in</Link>
          </p>
        </CardContent>
      </Card>
    </main>
  )
}
