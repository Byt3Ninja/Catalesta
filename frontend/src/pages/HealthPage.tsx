import { useQuery } from '@tanstack/react-query'
import { fetchHealth } from '../api/health'

export function HealthPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['health'],
    queryFn: fetchHealth,
  })

  return (
    <section>
      <h1>Catalesta</h1>
      <p>Program Management Platform</p>
      <h2>API status</h2>
      {isLoading && <p role="status">Checking…</p>}
      {isError && <p role="alert">API unreachable</p>}
      {data && (
        <ul aria-label="health-checks">
          <li>Overall: {data.status}</li>
          <li>Database: {data.checks.database.status}</li>
          <li>Redis: {data.checks.redis.status}</li>
          <li>Object storage: {data.checks.object_storage.status}</li>
        </ul>
      )}
    </section>
  )
}
