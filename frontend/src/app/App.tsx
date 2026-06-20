import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { HealthPage } from '../pages/HealthPage'
import { ApplyPage } from '../pages/ApplyPage'

const queryClient = new QueryClient()

/** No router is installed — match the public apply route off the pathname. */
const APPLY_ROUTE = /^\/apply\/([^/]+)\/?$/

function resolveRoute() {
  const match = APPLY_ROUTE.exec(window.location.pathname)
  if (match) {
    return <ApplyPage cohortId={decodeURIComponent(match[1])} />
  }
  return <HealthPage />
}

export function App() {
  return <QueryClientProvider client={queryClient}>{resolveRoute()}</QueryClientProvider>
}
