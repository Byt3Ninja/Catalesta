import { render, screen, waitFor } from '@testing-library/react'
import { afterEach, expect, test, vi } from 'vitest'
import { App } from '../app/App'

afterEach(() => {
  vi.restoreAllMocks()
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
