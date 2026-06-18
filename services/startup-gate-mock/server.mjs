// Startup Gate mock — PLACEHOLDER for the Phase 0 bootstrap.
//
// The full mock OIDC + profile/consent/role/achievement provider is specified
// in docs/10-startup-gate-mock.md and is implemented in Phase 1 (Identity &
// Tenancy). This placeholder only proves the service builds, boots, and is
// reachable on the network so the rest of the stack can wire against it.
//
// Zero runtime dependencies (Node built-in http) on purpose.

import { createServer } from 'node:http'

const PORT = Number(process.env.PORT ?? 8080)
const ISSUER = process.env.OIDC_ISSUER ?? `http://localhost:${PORT}`

const json = (res, status, body) => {
  res.writeHead(status, { 'Content-Type': 'application/json' })
  res.end(JSON.stringify(body))
}

const server = createServer((req, res) => {
  if (req.url === '/health') {
    return json(res, 200, { status: 'ok', service: 'startup-gate-mock', stub: true })
  }

  // Minimal OIDC discovery stub so dependents can detect the issuer.
  // Endpoints below are placeholders to be implemented in Phase 1 (docs/10).
  if (req.url === '/.well-known/openid-configuration') {
    return json(res, 200, {
      issuer: ISSUER,
      authorization_endpoint: `${ISSUER}/oauth/authorize`,
      token_endpoint: `${ISSUER}/oauth/token`,
      userinfo_endpoint: `${ISSUER}/oauth/userinfo`,
      jwks_uri: `${ISSUER}/.well-known/jwks.json`,
      _note: 'placeholder — full mock provider implemented in Phase 1',
    })
  }

  return json(res, 501, {
    error: { code: 'NOT_IMPLEMENTED', message: 'Mock endpoint implemented in Phase 1.' },
  })
})

server.listen(PORT, () => {
  console.log(`startup-gate-mock (placeholder) listening on ${PORT}`)
})
