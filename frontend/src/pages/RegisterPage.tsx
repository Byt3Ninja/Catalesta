import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { register, NativeAuthError } from '../api/auth'
import { consumePostLoginRedirect } from '../api/postLoginRedirect'
import { Card, CardContent, CardHeader } from '../components/ui/card'

/**
 * Native registration (FR-007). Creates an account, issues an (unverified) session,
 * then lands via the no-org gate — which shows the verify-email notice until the user
 * confirms their address.
 */
export function RegisterPage() {
  const queryClient = useQueryClient()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [displayName, setDisplayName] = useState('')

  const mutation = useMutation({
    mutationFn: () => register({ email, password, displayName: displayName || undefined }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['session'] })
      window.location.assign(consumePostLoginRedirect())
    },
  })

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (!email.trim() || !password) return
    mutation.mutate()
  }

  const error = mutation.error
  const emailError =
    error instanceof NativeAuthError && error.code === 'EMAIL_TAKEN'
      ? error.message || 'That email is already registered.'
      : undefined
  const bannerError =
    error && !emailError
      ? error instanceof NativeAuthError && error.code === 'RATE_LIMITED'
        ? 'Too many attempts. Please try again shortly.'
        : 'We could not create your account. Please try again.'
      : undefined

  return (
    <main className="grid min-h-dvh place-items-center bg-background px-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <h1 id="register-heading" className="leading-none font-semibold">Create your account</h1>
          <p className="text-sm text-muted-foreground">Register with your email and a password.</p>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {bannerError ? <Banner variant="error">{bannerError}</Banner> : null}
          <form onSubmit={onSubmit} noValidate>
            <FormLayout>
              <Field
                label="Email"
                type="email"
                name="email"
                autoComplete="email"
                required
                value={email}
                error={emailError}
                onChange={(e) => setEmail(e.target.value)}
              />
              <Field
                label="Password"
                type="password"
                name="password"
                autoComplete="new-password"
                required
                value={password}
                help="At least 8 characters."
                onChange={(e) => setPassword(e.target.value)}
              />
              <Field
                label="Display name (optional)"
                name="display_name"
                value={displayName}
                onChange={(e) => setDisplayName(e.target.value)}
              />
            </FormLayout>
            <Button type="submit" loading={mutation.isPending} disabled={!email.trim() || !password}>
              Create account
            </Button>
          </form>
          <p>
            Already have an account? <Link href="/login">Sign in</Link>
          </p>
        </CardContent>
      </Card>
    </main>
  )
}
