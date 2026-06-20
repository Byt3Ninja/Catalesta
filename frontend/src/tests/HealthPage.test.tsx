import { render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { App } from '../app/App'

// Story 1.1 moved the no-org gate to the root route; HealthPage now lives at /health.
beforeEach(() => {
  window.history.pushState({}, '', '/health')
})

afterEach(() => {
  vi.restoreAllMocks()
  window.history.pushState({}, '', '/')
})

test('renders API health checks returned by the backend', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(
    new Response(
      JSON.stringify({
        status: 'ok',
        service: 'program-platform-api',
        checks: {
          database: { status: 'ok' },
          redis: { status: 'ok' },
          object_storage: { status: 'ok' },
        },
      }),
      { status: 200, headers: { 'Content-Type': 'application/json' } },
    ),
  )

  render(<App />)

  expect(screen.getByRole('heading', { name: 'Catalesta' })).toBeInTheDocument()

  await waitFor(() => {
    expect(screen.getByLabelText('health-checks')).toBeInTheDocument()
  })
  expect(screen.getByText('Database: ok')).toBeInTheDocument()
  expect(screen.getByText('Redis: ok')).toBeInTheDocument()
})
