import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { beginLogin } from '../api/session'
import { passwordLogin, NativeAuthError } from '../api/auth'
import { consumePostLoginRedirect } from '../api/postLoginRedirect'

/**
 * Sign-in (FR-007). Native email/password is primary; Startup Gate SSO is offered
 * below. Login failures are deliberately collapsed into one generic message — the
 * UI never reveals whether an email exists (enumeration guard).
 */
export function LoginPage() {
  const queryClient = useQueryClient()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [ssoError, setSsoError] = useState(false)
  const [ssoPending, setSsoPending] = useState(false)

  const mutation = useMutation({
    mutationFn: () => passwordLogin({ email, password }),
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

  const onSso = () => {
    setSsoError(false)
    setSsoPending(true)
    beginLogin().catch(() => {
      setSsoError(true)
      setSsoPending(false)
    })
  }

  const rateLimited =
    mutation.error instanceof NativeAuthError && mutation.error.code === 'RATE_LIMITED'
  const loginError = mutation.error
    ? rateLimited
      ? 'Too many attempts. Please try again shortly.'
      : "These details don't match our records."
    : undefined

  return (
    <section aria-labelledby="login-heading">
      <h1 id="login-heading">Sign in</h1>
      {loginError ? <Banner variant="error">{loginError}</Banner> : null}
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
          <Field
            label="Password"
            type="password"
            name="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
        </FormLayout>
        <Button type="submit" loading={mutation.isPending} disabled={!email.trim() || !password}>
          Sign in
        </Button>
      </form>
      <p>
        <Link href="/forgot-password">Forgot password?</Link> ·{' '}
        <Link href="/register">Create an account</Link>
      </p>

      <hr />
      <p>Or sign in with Startup Gate.</p>
      {ssoError ? (
        <Banner variant="error">We could not start sign-in. Please try again.</Banner>
      ) : null}
      <Button variant="secondary" onClick={onSso} loading={ssoPending}>
        Sign in with Startup Gate
      </Button>
    </section>
  )
}
