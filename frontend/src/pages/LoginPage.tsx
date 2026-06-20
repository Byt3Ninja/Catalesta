import { useState } from 'react'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { beginLogin } from '../api/session'

/**
 * Sign-in entry (Story 1.1, AC-3). Initiates the Startup Gate OIDC handshake and
 * redirects the browser to the IdP. No console surface renders without a session.
 */
export function LoginPage() {
  const [error, setError] = useState(false)
  const [pending, setPending] = useState(false)

  const onSignIn = () => {
    setError(false)
    setPending(true)
    beginLogin().catch(() => {
      setError(true)
      setPending(false)
    })
  }

  return (
    <section aria-labelledby="login-heading">
      <h1 id="login-heading">Sign in</h1>
      <p>Sign in with Startup Gate to reach your operator console.</p>
      {error ? (
        <Banner variant="error">We could not start sign-in. Please try again.</Banner>
      ) : null}
      <Button onClick={onSignIn} loading={pending}>
        Sign in with Startup Gate
      </Button>
    </section>
  )
}
