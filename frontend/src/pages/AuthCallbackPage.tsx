import { useEffect, useRef } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { completeLogin } from '../api/session'
import { consumePostLoginRedirect } from '../api/postLoginRedirect'
import { Card, CardContent, CardHeader } from '../components/ui/card'

/**
 * OIDC redirect target (Story 1.1, AC-3). Reads state+code from the query string,
 * completes the handshake (sets the Sanctum session cookie), then sends the user
 * onward — the no-org gate in App decides login/onboarding/Home from there.
 */
export function AuthCallbackPage() {
  const queryClient = useQueryClient()
  const started = useRef(false)

  const mutation = useMutation({
    mutationFn: ({ state, code }: { state: string; code: string }) =>
      completeLogin(state, code),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['session'] })
      window.location.assign(consumePostLoginRedirect())
    },
  })

  useEffect(() => {
    if (started.current) return
    started.current = true
    const params = new URLSearchParams(window.location.search)
    const state = params.get('state')
    const code = params.get('code')
    if (state && code) {
      mutation.mutate({ state, code })
    }
    // mutation identity is stable for the component lifetime.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const params = new URLSearchParams(window.location.search)
  const hasParams = params.get('state') && params.get('code')

  if (!hasParams || mutation.isError) {
    return (
      <main className="grid min-h-dvh place-items-center bg-background px-4">
        <Card className="w-full max-w-sm">
          <CardHeader>
            <h1 id="callback-heading" className="leading-none font-semibold">Sign in</h1>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <Banner variant="error">
              We could not complete sign-in. <Link href="/login">Try again</Link>
            </Banner>
            <Button onClick={() => window.location.assign('/login')}>Back to sign in</Button>
          </CardContent>
        </Card>
      </main>
    )
  }

  return (
    <main className="grid min-h-dvh place-items-center bg-background px-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <h1 id="callback-heading" className="leading-none font-semibold">Signing you in…</h1>
        </CardHeader>
        <CardContent>
          <Spinner label="Completing sign-in…" />
        </CardContent>
      </Card>
    </main>
  )
}
